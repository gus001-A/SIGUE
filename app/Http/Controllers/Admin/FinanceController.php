<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rules\Visible;
use App\Models\Charge;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Scholarship;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Services\Notifier;
use App\Support\Options;
use App\Support\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Administración financiera (MOD-ADM-001): adeudos, cooperaciones, pagos, recibos y becas. */
class FinanceController extends Controller
{
    /* ---------------- Adeudos / cargos ---------------- */

    public function charges(Request $request)
    {
        $f = $request->only(['search', 'status', 'type', 'group_id', 'overdue']);

        $q = Charge::with(['student:id,name,paternal_surname,maternal_surname,enrollment_number', 'scholarship:id,code'])
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['overdue'] ?? null, fn ($q) => $q->whereIn('status', ['pendiente', 'parcial'])->whereDate('due_on', '<', today()))
            ->when($f['group_id'] ?? null, fn ($q, $g) => $q->whereHas('student.enrollments', fn ($e) => $e->where('group_id', $g)->where('status', 'autorizada')))
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('concept', 'like', "%{$s}%")
                ->orWhereHas('student', fn ($st) => $st->where('name', 'like', "%{$s}%")->orWhere('paternal_surname', 'like', "%{$s}%")
                    ->orWhere('enrollment_number', 'like', "%{$s}%"))))
            ->orderByRaw("FIELD(status, 'pendiente', 'parcial', 'pagado', 'cancelado')")->orderBy('due_on');

        $open = Charge::whereIn('status', ['pendiente', 'parcial'])->get();

        return Inertia::render('Admin/Finanzas/Adeudos', [
            'items' => $q->get()->map(fn ($c) => [
                'id' => $c->id, 'student_id' => $c->student_id, 'student' => $c->student?->full_name,
                'enrollment_number' => $c->student?->enrollment_number, 'type' => $c->type, 'concept' => $c->concept,
                'amount' => $c->amount, 'discount' => $c->discount, 'total' => $c->total(), 'paid' => $c->paid(),
                'balance' => $c->balance(), 'due_on' => $c->due_on?->format('d/m/Y'), 'status' => $c->status,
                'overdue' => $c->isOverdue(), 'scholarship' => $c->scholarship?->code,
            ]),
            'filters' => $f,
            'stats' => [
                'por_cobrar' => round($open->sum(fn ($c) => $c->balance()), 2),
                'vencidos' => $open->filter->isOverdue()->count(),
                'monto_vencido' => round($open->filter->isOverdue()->sum(fn ($c) => $c->balance()), 2),
                'cobrado_mes' => round((float) Payment::where('status', 'aplicado')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), 2),
            ],
            'options' => $this->options(),
            'canManage' => $request->user()->hasPermission('finanzas.gestionar'),
        ]);
    }

    /** Cargo individual o masivo (por grupo o a todos los activos). */
    public function storeCharge(Request $request)
    {
        $data = $request->validate([
            'target' => 'required|in:student,group,all',
            'student_id' => ['required_if:target,student', 'nullable', new Visible(Student::class)],
            'group_id' => ['required_if:target,group', 'nullable', new Visible(Group::class)],
            'type' => ['required', Rule::in(array_keys(Charge::TYPES))],
            'concept' => 'required|string|max:150',
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'due_on' => 'nullable|date',
            'apply_scholarship' => 'boolean',
            'notify' => 'boolean',
        ]);

        $students = match ($data['target']) {
            'student' => Student::whereKey($data['student_id'])->get(),
            'group' => Group::findOrFail($data['group_id'])->students()->get(),
            default => Student::where('status', 'activo')->get(),
        };
        $year = SchoolYear::current();

        $n = 0;
        DB::transaction(function () use ($students, $data, $year, $request, &$n) {
            foreach ($students as $s) {
                $beca = ($data['apply_scholarship'] ?? false) && in_array($data['type'], ['colegiatura', 'inscripcion'], true)
                    ? Scholarship::where('student_id', $s->id)->get()->first(fn ($b) => $b->isActive()) : null;
                $charge = Charge::create([
                    'student_id' => $s->id, 'school_year_id' => $year?->id, 'scholarship_id' => $beca?->id,
                    'type' => $data['type'], 'concept' => $data['concept'], 'amount' => $data['amount'],
                    'discount' => $beca ? $beca->discountFor((float) $data['amount']) : 0,
                    'due_on' => $data['due_on'] ?? null, 'created_by' => $request->user()->id,
                ]);
                if ($data['notify'] ?? false) {
                    Notifier::student($s, 'adeudo', 'Nuevo adeudo: ' . $charge->concept,
                        'Importe $' . number_format($charge->total(), 2) . ($charge->due_on ? ' · vence ' . $charge->due_on->format('d/m/Y') : ''),
                        route('portal.pagos'), $charge, includeStudent: false);
                }
                $n++;
            }
        });

        return back()->with('success', "{$n} cargo(s) registrado(s).");
    }

    public function cancelCharge(Charge $charge)
    {
        abort_if($charge->payments()->exists(), 422, 'El cargo tiene pagos aplicados; cancela primero los pagos.');
        $charge->update(['status' => 'cancelado']);

        return back()->with('success', 'Cargo cancelado.');
    }

    /* ---------------- Pagos ---------------- */

    public function payments(Request $request)
    {
        $f = $request->only(['search', 'method', 'from', 'to', 'status']);

        $q = Payment::with(['student:id,name,paternal_surname,maternal_surname,enrollment_number', 'charge:id,concept', 'receiver:id,name,paternal_surname'])
            ->when($f['method'] ?? null, fn ($q, $v) => $q->where('method', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v))
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$s}%")->orWhere('reference', 'like', "%{$s}%")
                ->orWhereHas('student', fn ($st) => $st->where('name', 'like', "%{$s}%")->orWhere('paternal_surname', 'like', "%{$s}%")
                    ->orWhere('enrollment_number', 'like', "%{$s}%"))))
            ->latest('paid_at');

        $sumQ = (clone $q)->where('status', 'aplicado');

        return Inertia::render('Admin/Finanzas/Pagos', [
            'items' => $q->get()->map(fn ($p) => [
                'id' => $p->id, 'folio' => $p->folio, 'student' => $p->student?->full_name, 'student_id' => $p->student_id,
                'enrollment_number' => $p->student?->enrollment_number, 'concept' => $p->charge?->concept ?? 'Pago a cuenta',
                'amount' => $p->amount, 'method' => $p->method, 'reference' => $p->reference,
                'paid_at' => $p->paid_at?->format('d/m/Y H:i'), 'status' => $p->status, 'received_by' => $p->receiver?->full_name,
            ]),
            'filters' => $f,
            'total' => round((float) $sumQ->sum('amount'), 2),
            'options' => $this->options(),
            'canManage' => $request->user()->hasPermission('finanzas.gestionar'),
        ]);
    }

    /** Adeudos abiertos de un alumno (para el formulario de cobro). */
    public function studentCharges(Student $student)
    {
        return response()->json(Charge::where('student_id', $student->id)->whereIn('status', ['pendiente', 'parcial'])->orderBy('due_on')->get()
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->concept . ' · saldo $' . number_format($c->balance(), 2), 'balance' => $c->balance()]));
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', new Visible(Student::class)],
            'charge_id' => ['nullable', new Visible(Charge::class)],
            'amount' => 'required|numeric|min:0.01',
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => 'nullable|string|max:100',
            'paid_at' => 'nullable|date|before_or_equal:now',
            'notes' => 'nullable|string|max:500',
        ]);

        $charge = ! empty($data['charge_id']) ? Charge::where('student_id', $data['student_id'])->findOrFail($data['charge_id']) : null;
        if ($charge && $data['amount'] > $charge->balance() + 0.009) {
            return back()->with('error', 'El importe excede el saldo del adeudo ($' . number_format($charge->balance(), 2) . ').');
        }

        $payment = DB::transaction(function () use ($data, $charge, $request) {
            $p = Payment::create($data + [
                'folio' => Payment::nextFolio(),
                'paid_at' => $data['paid_at'] ?? now(),
                'received_by' => $request->user()->id,
            ]);
            $charge?->refreshStatus();
            return $p;
        });

        return back()->with('success', "Pago registrado con folio {$payment->folio}.")->with('receipt', route('finanzas.recibo', $payment));
    }

    public function cancelPayment(Request $request, Payment $payment)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $payment->update(['status' => 'cancelado', 'notes' => trim(($payment->notes ? $payment->notes . ' | ' : '') . 'CANCELADO: ' . $request->reason)]);
        $payment->charge?->refreshStatus();

        return back()->with('success', 'Pago cancelado.');
    }

    public function receipt(Payment $payment)
    {
        $payment->load(['student.currentEnrollment.group.grade', 'charge', 'receiver', 'institution']);

        return Pdf::loadView('pdf.recibo', ['payment' => $payment, 'institution' => $payment->institution])
            ->setPaper([0, 0, 612, 396])->download('recibo-' . $payment->folio . '.pdf');
    }

    /* ---------------- Becas ---------------- */

    public function scholarships(Request $request)
    {
        $items = Scholarship::with('student:id,name,paternal_surname,maternal_surname,enrollment_number')
            ->orderByDesc('id')->get()->map(fn ($s) => [
                ...$s->only(['id', 'code', 'name', 'type', 'percentage', 'amount', 'conditions', 'status', 'student_id']),
                'starts_on' => $s->starts_on?->toDateString(), 'ends_on' => $s->ends_on?->toDateString(),
                'student' => $s->student?->full_name, 'active' => $s->isActive(),
            ]);

        return Inertia::render('Admin/Finanzas/Becas', [
            'items' => $items,
            'options' => $this->options(),
        ]);
    }

    public function storeScholarship(Request $request)
    {
        Scholarship::create($this->scholarshipData($request));

        return back()->with('success', 'Beca registrada.');
    }

    public function updateScholarship(Request $request, Scholarship $scholarship)
    {
        $scholarship->update($this->scholarshipData($request, $scholarship));

        return back()->with('success', 'Beca actualizada.');
    }

    public function destroyScholarship(Scholarship $scholarship)
    {
        if (Charge::where('scholarship_id', $scholarship->id)->exists()) {
            $scholarship->update(['status' => 'suspendida']);
            return back()->with('warning', 'La beca ya se aplicó a cargos: se marcó como suspendida.');
        }
        $scholarship->delete();

        return back()->with('success', 'Beca eliminada.');
    }

    private function scholarshipData(Request $request, ?Scholarship $s = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('scholarships')->where('institution_id', Tenant::id())->whereNull('deleted_at')->ignore($s?->id)],
            'name' => 'required|string|max:150',
            'type' => ['required', Rule::in(array_keys(Scholarship::TYPES))],
            'student_id' => ['nullable', new Visible(Student::class)],
            'percentage' => 'nullable|required_without:amount|numeric|min:0.01|max:100',
            'amount' => 'nullable|required_without:percentage|numeric|min:0.01',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'conditions' => 'nullable|string|max:2000',
            'status' => ['required', Rule::in(array_keys(Scholarship::STATUSES))],
        ]);
    }

    private function options(): array
    {
        return [
            'types' => Options::from(Charge::TYPES),
            'statuses' => Options::from(Charge::STATUSES),
            'methods' => Options::from(Payment::METHODS),
            'groups' => Options::groups(),
            'scholarshipTypes' => Options::from(Scholarship::TYPES),
            'scholarshipStatuses' => Options::from(Scholarship::STATUSES),
            'students' => Student::whereIn('status', ['aspirante', 'activo'])->orderBy('paternal_surname')->get()
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->listName() . ' · ' . $s->enrollment_number]),
        ];
    }
}
