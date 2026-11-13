<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupSubject;
use App\Models\Message;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Mensajería interna: docentes ↔ padres, solicitudes de reunión y comunicados. */
class MessageController extends Controller
{
    /**
     * Mensajería en tiempo real (estilo chat): la página carga la bandeja y el chat
     * se mantiene al día con sondeo incremental (solo trae lo nuevo).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Mensajes/Index', [
            'me' => $user->id,
            'open' => $request->integer('conversacion') ?: null,
            'recipients' => $this->recipients($user),
            'students' => $this->contextStudents($user),
            'kinds' => collect(Message::KINDS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
        ]);
    }

    /** Bandeja: conversaciones donde participa el usuario, con último mensaje y no leídos. */
    public function conversations(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->query('buscar', ''));

        $threads = Message::with(['sender:id,name,paternal_surname,maternal_surname', 'recipient:id,name,paternal_surname,maternal_surname', 'student:id,name,paternal_surname'])
            ->whereNull('parent_id')
            ->where(fn ($w) => $w->where('sender_id', $user->id)->orWhere('recipient_id', $user->id))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('subject', 'like', "%{$search}%")
                ->orWhereHas('sender', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('paternal_surname', 'like', "%{$search}%"))
                ->orWhereHas('recipient', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('paternal_surname', 'like', "%{$search}%"))))
            ->latest('updated_at')->limit(80)->get();

        $ids = $threads->pluck('id');
        $last = Message::whereIn('id', Message::selectRaw('MAX(id)')->where(fn ($w) => $w->whereIn('id', $ids)->orWhereIn('parent_id', $ids))
            ->groupByRaw('COALESCE(parent_id, id)'))->get()->keyBy(fn ($m) => $m->parent_id ?? $m->id);
        $unread = Message::selectRaw('COALESCE(parent_id, id) as t, COUNT(*) as n')
            ->where(fn ($w) => $w->whereIn('id', $ids)->orWhereIn('parent_id', $ids))
            ->where('recipient_id', $user->id)->whereNull('read_at')->groupByRaw('COALESCE(parent_id, id)')->pluck('n', 't');

        return response()->json([
            'conversations' => $threads->map(function ($t) use ($user, $last, $unread) {
                $other = $t->sender_id === $user->id ? $t->recipient : $t->sender;
                $lm = $last[$t->id] ?? $t;
                return [
                    'id' => $t->id, 'subject' => $t->subject, 'kind' => $t->kind, 'kind_label' => Message::KINDS[$t->kind] ?? 'Mensaje',
                    'other' => $other?->full_name, 'other_initials' => $this->initials($other?->full_name),
                    'student' => $t->student?->full_name,
                    'last' => mb_strimwidth($lm->body, 0, 90, '…'), 'last_mine' => $lm->sender_id === $user->id,
                    'last_at' => $lm->created_at?->toIso8601String(), 'unread' => (int) ($unread[$t->id] ?? 0),
                ];
            })->values(),
            'unread' => Message::where('recipient_id', $user->id)->whereNull('read_at')->count(),
        ]);
    }

    /**
     * Mensajes de una conversación. Con ?desde=ID solo devuelve los nuevos (sondeo).
     * Con ?leer=1 marca como leídos los recibidos.
     */
    public function feed(Request $request, Message $message)
    {
        $user = $request->user();
        $thread = $message->parent_id ? $message->parent : $message;
        $this->authorizeThread($thread, $user->id);
        $since = $request->integer('desde');

        if ($request->boolean('leer', true)) {
            Message::where(fn ($w) => $w->whereKey($thread->id)->orWhere('parent_id', $thread->id))
                ->where('recipient_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
        }

        $items = Message::with('sender:id,name,paternal_surname')
            ->where(fn ($w) => $w->whereKey($thread->id)->orWhere('parent_id', $thread->id))
            ->when($since, fn ($q) => $q->where('id', '>', $since))
            ->orderBy('id')->get();

        $otherId = $thread->sender_id === $user->id ? $thread->recipient_id : $thread->sender_id;
        // Hasta qué mensaje mío ya leyó la otra persona (palomitas azules)
        $seenUpTo = Message::where(fn ($w) => $w->whereKey($thread->id)->orWhere('parent_id', $thread->id))
            ->where('sender_id', $user->id)->whereNotNull('read_at')->max('id');
        $typing = cache()->get("msg-typing:{$thread->id}:{$otherId}");

        $other = $thread->sender_id === $user->id ? $thread->recipient : $thread->sender;

        return response()->json([
            'thread' => [
                'id' => $thread->id, 'subject' => $thread->subject, 'kind' => $thread->kind, 'kind_label' => Message::KINDS[$thread->kind] ?? 'Mensaje',
                'meeting_at' => $thread->meeting_at?->format('d/m/Y H:i'), 'student' => $thread->student?->full_name,
                'other' => $other?->full_name, 'other_initials' => $this->initials($other?->full_name),
            ],
            'items' => $items->map(fn ($m) => $this->bubble($m, $user->id))->values(),
            'seen_up_to' => $seenUpTo,
            'typing' => $typing && now()->diffInSeconds($typing, true) < 6 ? ($other?->name ?? 'La otra persona') : null,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $allowed = collect($this->recipients($user))->pluck('value')->all();

        $data = $request->validate([
            'recipient_id' => ['required', Rule::in($allowed)],
            'student_id' => 'nullable|integer',
            'kind' => ['required', Rule::in(array_keys(Message::KINDS))],
            'subject' => 'required|string|max:150',
            'body' => 'required|string|max:5000',
            'meeting_at' => 'nullable|date|after:now',
        ]);

        $m = Message::create($data + ['sender_id' => $user->id, 'institution_id' => Tenant::id()]);

        Notifier::send($m->recipient_id, $m->kind === 'reunion' ? 'reunion' : 'mensaje',
            ($m->kind === 'reunion' ? 'Solicitud de reunión: ' : 'Nuevo mensaje: ') . $m->subject,
            'De ' . $user->full_name, route('mensajes.index', ['conversacion' => $m->id]), $m);

        return $request->wantsJson()
            ? response()->json(['id' => $m->id])
            : redirect()->route('mensajes.index', ['conversacion' => $m->id])->with('success', 'Mensaje enviado.');
    }

    /** Enlaces antiguos (notificaciones) abren la conversación en el chat. */
    public function show(Message $message)
    {
        return redirect()->route('mensajes.index', ['conversacion' => $message->parent_id ?? $message->id]);
    }

    public function reply(Request $request, Message $message)
    {
        $user = $request->user();
        $thread = $message->parent_id ? $message->parent : $message;
        $this->authorizeThread($thread, $user->id);

        $data = $request->validate(['body' => 'required|string|max:5000']);
        $to = $thread->sender_id === $user->id ? $thread->recipient_id : $thread->sender_id;

        $m = Message::create([
            'institution_id' => $thread->institution_id, 'sender_id' => $user->id, 'recipient_id' => $to,
            'parent_id' => $thread->id, 'kind' => $thread->kind, 'subject' => 'RE: ' . $thread->subject,
            'body' => $data['body'], 'student_id' => $thread->student_id,
        ]);
        $thread->touch();
        cache()->forget("msg-typing:{$thread->id}:{$user->id}");

        Notifier::send($to, 'mensaje', 'Respuesta: ' . $thread->subject, 'De ' . $user->full_name, route('mensajes.index', ['conversacion' => $thread->id]), $thread);

        return $request->wantsJson()
            ? response()->json(['item' => $this->bubble($m->load('sender'), $user->id)])
            : back()->with('success', 'Respuesta enviada.');
    }

    /** "Está escribiendo…": se guarda unos segundos en caché. */
    public function typing(Request $request, Message $message)
    {
        $thread = $message->parent_id ? $message->parent : $message;
        $this->authorizeThread($thread, $request->user()->id);
        cache()->put("msg-typing:{$thread->id}:{$request->user()->id}", now(), 8);

        return response()->noContent();
    }

    private function bubble(Message $m, int $me): array
    {
        return [
            'id' => $m->id, 'body' => $m->body, 'mine' => $m->sender_id === $me, 'from' => $m->sender?->full_name,
            'at' => $m->created_at?->toIso8601String(), 'read' => (bool) $m->read_at,
        ];
    }

    private function initials(?string $name): string
    {
        $p = preg_split('/\s+/', trim((string) $name)) ?: [];
        return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1) . mb_substr($p[1] ?? '', 0, 1));
    }

    private function authorizeThread(Message $thread, int $userId): void
    {
        abort_unless($thread->sender_id === $userId || $thread->recipient_id === $userId, 403);
    }

    /**
     * Destinatarios permitidos según el perfil:
     * - personal administrativo: cualquier usuario de la institución;
     * - docentes: personal administrativo y tutores/alumnos de sus grupos;
     * - tutores y alumnos: docentes de sus clases y personal administrativo.
     */
    private function recipients(User $user): array
    {
        $staff = User::where('institution_id', Tenant::id())
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', [Role::DIRECTOR, Role::ADMIN]))->pluck('id');

        if ($user->hasPermission('dashboard.ver')) {
            $ids = User::where('institution_id', Tenant::id())->where('is_active', true)->pluck('id');
            // Alumnos, tutores y docentes son de un solo plantel: no se ofrecen los de otros planteles.
            if ($campus = Tenant::campusId()) {
                $other = fn ($model) => $model::withoutGlobalScopes()->where('institution_id', Tenant::id())
                    ->where('campus_id', '!=', $campus)->whereNotNull('user_id')->pluck('user_id');
                $ids = $ids->diff($other(Student::class)->merge($other(\App\Models\Guardian::class))->merge($other(\App\Models\Teacher::class)));
            }
        } else {
            $ids = collect($staff);
            $studentIds = $this->contextStudentIds($user);

            if ($user->hasPermission('docencia.usar') && $user->teacher) {
                $groupIds = GroupSubject::where('teacher_id', $user->teacher->id)->pluck('group_id');
                $sids = Student::whereHas('enrollments', fn ($q) => $q->whereIn('group_id', $groupIds)->where('status', 'autorizada'))->get();
                $ids = $ids->merge($sids->pluck('user_id'))->merge($sids->flatMap->guardianUserIds());
            }
            if ($studentIds) {
                $groupIds = \App\Models\Enrollment::whereIn('student_id', $studentIds)->where('status', 'autorizada')->pluck('group_id');
                $teacherUsers = GroupSubject::whereIn('group_id', $groupIds)->with('teacher:id,user_id')->get()->pluck('teacher.user_id');
                $tutorTeachers = Group::whereIn('id', $groupIds)->with('tutor:id,user_id')->get()->pluck('tutor.user_id');
                $ids = $ids->merge($teacherUsers)->merge($tutorTeachers);
            }
        }

        return User::with('roles')->whereIn('id', $ids->filter()->unique())->where('id', '!=', $user->id)
            ->orderBy('name')->get()
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->full_name . ' · ' . ($u->roles->first()?->name ?? '')])
            ->values()->all();
    }

    private function contextStudentIds(User $user): array
    {
        $ids = [];
        if ($user->student) {
            $ids[] = $user->student->id;
        }
        if ($user->guardian) {
            $ids = array_merge($ids, $user->guardian->students()->pluck('students.id')->all());
        }
        return $ids;
    }

    private function contextStudents(User $user): array
    {
        $ids = $this->contextStudentIds($user);
        if (! $ids) {
            return [];
        }
        return Student::whereIn('id', $ids)->get()->map(fn ($s) => ['value' => $s->id, 'label' => $s->full_name])->all();
    }
}
