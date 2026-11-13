<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\ClassComment;
use App\Models\ClassPost;
use App\Models\GroupSubject;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Datos del Classroom (estilo Google) que comparten el docente y el portal:
 * avisos y material, comentarios de la clase y comentarios privados, y la rúbrica.
 */
class ClassroomFeed
{
    /** Avisos y material de la clase, con sus comentarios públicos. */
    public static function posts(GroupSubject $class, User $viewer, bool $canManage): array
    {
        $posts = ClassPost::with('author:id,name,paternal_surname')->where('group_subject_id', $class->id)
            ->orderByDesc('pinned')->latest()->get();
        $comments = static::commentsFor('post', $posts->pluck('id')->all(), $viewer, $canManage);

        return $posts->map(fn (ClassPost $p) => [
            'id' => $p->id, 'type' => $p->type, 'title' => $p->title, 'body' => $p->body, 'pinned' => $p->pinned,
            'author' => $p->author?->full_name, 'initials' => static::initials($p->author?->full_name),
            'at' => $p->created_at?->isoFormat('D [de] MMM, HH:mm'), 'ago' => $p->created_at?->diffForHumans(),
            'edited' => $p->updated_at && $p->created_at && $p->updated_at->gt($p->created_at->copy()->addMinute()),
            'files' => $p->fileLinks('archivos.material'),
            'links' => collect($p->links ?? [])->map(fn ($l) => static::link($l))->values()->all(),
            'comments' => $comments->get($p->id, collect())->values()->all(),
            'can_edit' => $canManage,
        ])->all();
    }

    /**
     * Comentarios agrupados por elemento. Públicos para todos; los privados solo los ve
     * quien gestiona la clase o el alumno dueño del hilo (y su tutor).
     */
    public static function commentsFor(string $type, array $ids, User $viewer, bool $canManage, bool $private = false, ?array $studentIds = null): Collection
    {
        if (! $ids) {
            return collect();
        }
        $q = ClassComment::with('author:id,name,paternal_surname,maternal_surname', 'author.student:id,user_id,photo_path')
            ->where('commentable_type', $type)->whereIn('commentable_id', $ids)->where('is_private', $private)->oldest();
        if ($private && ! $canManage) {
            $q->whereIn('student_id', $studentIds ?? []);
        }

        return $q->get()->map(fn (ClassComment $c) => static::comment($c, $viewer, $canManage))->groupBy($private ? 'thread' : 'item');
    }

    public static function comment(ClassComment $c, User $viewer, bool $canManage): array
    {
        $name = $c->author?->full_name ?? 'Usuario';

        return [
            'id' => $c->id, 'item' => $c->commentable_id, 'thread' => $c->commentable_id . ':' . $c->student_id,
            'body' => $c->body, 'author' => $name, 'initials' => static::initials($name), 'photo' => $c->author?->student?->photoUrl(),
            'role' => static::role($c->author), 'mine' => $c->user_id === $viewer->id,
            'ago' => $c->created_at?->diffForHumans(), 'at' => $c->created_at?->isoFormat('D MMM, HH:mm'),
            'can_delete' => $c->user_id === $viewer->id || $canManage,
        ];
    }

    /** Rúbrica normalizada: [{name, description, levels:[{label, points, description}]}] y su puntaje máximo. */
    public static function rubric(?array $rubric): array
    {
        return collect($rubric ?? [])->map(fn ($c) => [
            'name' => $c['name'] ?? '', 'description' => $c['description'] ?? '',
            'levels' => collect($c['levels'] ?? [])->map(fn ($l) => ['label' => $l['label'] ?? '', 'points' => (float) ($l['points'] ?? 0), 'description' => $l['description'] ?? ''])->values()->all(),
        ])->values()->all();
    }

    public static function rubricMax(?array $rubric): float
    {
        return (float) collect(static::rubric($rubric))->sum(fn ($c) => collect($c['levels'])->max('points') ?? 0);
    }

    /** Puntaje según los niveles elegidos [criterio => índice de nivel]. */
    public static function rubricScore(?array $rubric, array $scores): float
    {
        $r = static::rubric($rubric);
        $sum = 0;
        foreach ($scores as $ci => $li) {
            if ($li !== null && isset($r[$ci]['levels'][$li])) {
                $sum += $r[$ci]['levels'][$li]['points'];
            }
        }

        return $sum;
    }

    public static function role(?User $u): string
    {
        if (! $u) return '';
        if ($u->teacher) return 'Docente';
        if ($u->student) return 'Alumno';
        if ($u->guardian) return 'Tutor';
        return 'Escuela';
    }

    public static function initials(?string $name): string
    {
        return collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    }

    /** Enlace con datos para mostrarlo (YouTube con miniatura). */
    public static function link(array $l): array
    {
        $url = $l['url'] ?? '';
        $yt = null;
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', $url, $m)) {
            $yt = $m[1];
        }
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        return [
            'url' => $url, 'title' => ($l['title'] ?? '') ?: ($yt ? 'Video de YouTube' : preg_replace('/^www\./', '', $host)),
            'host' => preg_replace('/^www\./', '', $host), 'youtube' => $yt,
            'thumb' => $yt ? "https://img.youtube.com/vi/{$yt}/mqdefault.jpg" : null,
            'kind' => $yt ? 'video' : (str_contains($host, 'docs.google') || str_contains($host, 'drive.google') ? 'drive' : 'link'),
        ];
    }

    /** Enlaces de un elemento listos para mostrarse. */
    public static function links(?array $links): array
    {
        return collect($links ?? [])->map(fn ($l) => static::link($l))->values()->all();
    }

    /** Ids de alumnos que representa el usuario (él mismo o sus hijos). */
    public static function studentIdsOf(User $u): array
    {
        $ids = [];
        if ($u->student) $ids[] = $u->student->id;
        if ($u->guardian) $ids = array_merge($ids, $u->guardian->students()->pluck('students.id')->all());

        return $ids;
    }

    /** Clase a la que pertenece un elemento comentable. */
    public static function classOf(string $type, int $id): ?GroupSubject
    {
        return match ($type) {
            'assignment' => Assignment::find($id)?->groupSubject,
            'post' => ClassPost::find($id)?->groupSubject,
            default => null,
        };
    }
}
