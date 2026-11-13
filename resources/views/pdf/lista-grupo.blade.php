@extends('pdf.layout', ['institution' => $group->institution])

@section('title', 'Lista ' . $group->label)

@section('content')
    <h1>Lista de alumnos · Grupo {{ $group->label }}</h1>
    <h2>Ciclo {{ $group->schoolYear?->name }} · Turno {{ $group->shift }} @if ($group->tutor) · Tutor(a): {{ $group->tutor->full_name }} @endif</h2>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 24px">#</th>
                <th style="width: 70px">Matrícula</th>
                <th>Alumno</th>
                @for ($i = 0; $i < 10; $i++)
                    <th style="width: 22px"></th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $i => $s)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $s->enrollment_number }}</td>
                    <td>{{ $s->listName() }}</td>
                    @for ($j = 0; $j < 10; $j++)
                        <td style="border-left: 1px solid #eef1f6"></td>
                    @endfor
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted" style="margin-top: 8px">{{ $students->count() }} alumnos inscritos.</p>
@endsection
