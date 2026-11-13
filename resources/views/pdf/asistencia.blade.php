@extends('pdf.layout', ['institution' => $class->group->institution])

@section('title', 'Asistencia')

@section('content')
    <h1>Asistencia · {{ $class->subject?->name }} · {{ $class->group?->label }}</h1>
    <h2>Docente: {{ $class->teacher?->full_name ?? '—' }} · Del {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</h2>

    @php $marks = ['presente' => '•', 'falta' => 'F', 'retardo' => 'R', 'justificada' => 'J']; @endphp

    <table class="grid">
        <thead>
            <tr>
                <th>Alumno</th>
                @foreach ($dates as $d)
                    <th class="center" style="font-size: 8px">{{ \Carbon\Carbon::parse($d)->format('d/m') }}</th>
                @endforeach
                <th class="center">F</th>
                <th class="center">R</th>
                <th class="center">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $s)
                @php
                    $r = ($rows[$s->id] ?? collect());
                    $byDate = $r->keyBy(fn ($a) => $a->date->toDateString());
                    $f = $r->where('status', 'falta')->count();
                @endphp
                <tr>
                    <td>{{ $s->listName() }}</td>
                    @foreach ($dates as $d)
                        @php $st = $byDate[\Carbon\Carbon::parse($d)->toDateString()]->status ?? null; @endphp
                        <td class="center" style="color: {{ $st === 'falta' ? '#dc2626' : ($st === 'retardo' ? '#d97706' : '#334155') }}">{{ $st ? $marks[$st] : '' }}</td>
                    @endforeach
                    <td class="center">{{ $f }}</td>
                    <td class="center">{{ $r->where('status', 'retardo')->count() }}</td>
                    <td class="center">{{ $r->count() ? round(($r->count() - $f) / $r->count() * 100) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted" style="margin-top: 8px">• asistencia · F falta · R retardo · J justificada</p>
@endsection
