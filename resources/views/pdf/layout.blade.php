@php
    $inst = $institution ?? \App\Support\Tenant::institution();
    $instLogo = $inst?->logo_path && is_file(storage_path('app/' . $inst->logo_path)) ? storage_path('app/' . $inst->logo_path) : null;
    $sigueLogo = public_path('images/logo-sigue-sm.png');
    $toData = fn ($path) => 'data:' . mime_content_type($path) . ';base64,' . base64_encode(file_get_contents($path));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'SIGUE')</title>
    <style>
        @page { margin: 28px 34px 46px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10.5px; color: #1e293b; }
        .head { width: 100%; border-bottom: 3px solid #1d7fc9; padding-bottom: 8px; margin-bottom: 14px; }
        .head td { vertical-align: middle; }
        .inst { font-size: 14px; font-weight: bold; color: #0b3f6e; }
        .muted { color: #64748b; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #0b3f6e; }
        h2 { font-size: 11px; margin: 0 0 12px; color: #475569; font-weight: normal; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #eaf4fc; color: #0b3f6e; text-align: left; font-size: 9.5px; padding: 5px 6px; border-bottom: 1px solid #b6d6ef; }
        table.grid td { padding: 4px 6px; border-bottom: 1px solid #eef1f6; font-size: 9.5px; }
        table.grid tr:nth-child(even) td { background: #fafbfd; }
        .num { text-align: right; }
        .center { text-align: center; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #94a3b8; text-align: center; }
        .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; }
        .strong { font-weight: bold; }
    </style>
    @yield('styles')
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 70px">
                @if ($instLogo)
                    <img src="{{ $toData($instLogo) }}" style="height: 48px">
                @endif
            </td>
            <td>
                <div class="inst">{{ $inst?->name }}</div>
                <div class="muted">
                    @if ($inst?->cct) CCT {{ $inst->cct }} · @endif {{ $inst?->address }}
                </div>
            </td>
            <td style="text-align: right; width: 110px">
                <img src="{{ $toData($sigueLogo) }}" style="height: 38px">
            </td>
        </tr>
    </table>

    @yield('content')

    <div class="footer">
        Generado con SIGUE · Sistema Integral de Gestión Universal Educativa · {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
