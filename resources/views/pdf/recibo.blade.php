@extends('pdf.layout')

@section('title', 'Recibo ' . $payment->folio)

@section('content')
    <table style="width: 100%">
        <tr>
            <td><h1>RECIBO DE PAGO</h1></td>
            <td style="text-align: right">
                <div style="font-size: 14px; font-weight: bold; color: {{ $payment->status === 'cancelado' ? '#dc2626' : '#0b3f6e' }}">
                    {{ $payment->folio }}{{ $payment->status === 'cancelado' ? ' · CANCELADO' : '' }}
                </div>
                <div class="muted">{{ $payment->paid_at?->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="box" style="margin-top: 10px">
        <table style="width: 100%">
            <tr>
                <td style="width: 60%">
                    <span class="muted">Alumno</span><br>
                    <b>{{ $payment->student?->full_name }}</b><br>
                    Matrícula {{ $payment->student?->enrollment_number }}
                    @if ($payment->student?->currentEnrollment?->group) · Grupo {{ $payment->student->currentEnrollment->group->label }} @endif
                </td>
                <td>
                    <span class="muted">Forma de pago</span><br>
                    <b>{{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }}</b>
                    @if ($payment->reference)<br>Ref. {{ $payment->reference }}@endif
                </td>
            </tr>
        </table>
    </div>

    <table class="grid" style="margin-top: 14px">
        <thead><tr><th>Concepto</th><th class="num">Importe</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $payment->charge?->concept ?? 'PAGO A CUENTA' }}</td>
                <td class="num">${{ number_format($payment->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 10px">
        <tr>
            <td class="muted">
                Recibió: {{ $payment->receiver?->full_name ?? '—' }}
                @if ($payment->charge)
                    <br>Saldo restante del concepto: ${{ number_format($payment->charge->balance(), 2) }}
                @endif
            </td>
            <td style="text-align: right; font-size: 15px; font-weight: bold; color: #0b3f6e">
                TOTAL ${{ number_format($payment->amount, 2) }} MXN
            </td>
        </tr>
    </table>
@endsection
