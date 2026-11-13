@extends('pdf.layout')

@section('title', $title)

@section('content')
    <h1>{{ $title }}</h1>
    <h2>{{ $subtitle ?? '' }}</h2>

    <table class="grid">
        <thead>
            <tr>
                @foreach ($headings as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td class="{{ is_numeric($cell) ? 'num' : '' }}">
                            {{ is_float($cell) ? number_format($cell, 2) : $cell }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}" class="center muted">Sin registros para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($footer))
        <p class="strong" style="margin-top: 10px">{{ $footer }}</p>
    @endif
    <p class="muted" style="margin-top: 6px">{{ count($rows) }} registro(s).</p>
@endsection
