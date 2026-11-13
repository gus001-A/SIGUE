@extends('pdf.layout')

@section('title', 'Constancia de estudios')

@section('content')
    <div style="text-align: right; margin: 18px 0 30px">{{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</div>

    <div style="text-align: center; font-size: 16px; font-weight: bold; color: #0b3f6e; letter-spacing: 2px; margin-bottom: 30px">
        CONSTANCIA DE ESTUDIOS
    </div>

    <p style="font-size: 12px; line-height: 2">A QUIEN CORRESPONDA:</p>

    <p style="font-size: 12px; line-height: 2; text-align: justify">
        Por medio de la presente se hace constar que <b>{{ $student->full_name }}</b>,
        con matrícula <b>{{ $student->enrollment_number }}</b>@if ($student->curp) y CURP <b>{{ $student->curp }}</b>@endif,
        se encuentra inscrito(a) en esta institución cursando el grupo <b>{{ $enrollment->group?->label }}</b>
        @if ($enrollment->group?->grade?->level) del nivel <b>{{ $enrollment->group->grade->level }}</b> @endif
        en el ciclo escolar <b>{{ $enrollment->schoolYear?->name }}</b>, turno <b>{{ $enrollment->group?->shift }}</b>.
    </p>

    <p style="font-size: 12px; line-height: 2; text-align: justify">
        Se extiende la presente a petición del interesado para los fines legales que a éste convengan.
    </p>

    <table style="width: 100%; margin-top: 90px">
        <tr>
            <td style="width: 25%"></td>
            <td style="text-align: center; border-top: 1px solid #334155; padding-top: 6px; font-size: 11px">
                DIRECCIÓN ESCOLAR<br><span class="muted">{{ $institution?->name }}</span>
            </td>
            <td style="width: 25%"></td>
        </tr>
    </table>

    <p class="muted" style="margin-top: 50px; font-size: 8.5px">
        Folio de verificación: {{ strtoupper(substr(sha1($student->id . '|' . $enrollment->id . '|' . now()->toDateString()), 0, 12)) }}
    </p>
@endsection
