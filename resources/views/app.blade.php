<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="application-name" content="SIGUE">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#1d7fc9">
    <meta name="description" content="SIGUE · Sistema Integral de Gestión Universal Educativa">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'SIGUE') }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon.png') }}">
    <link rel="icon" type="image/png" sizes="180x180" href="{{ asset('images/favicon-180.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-180.png') }}">
    {{-- App web instalable (PWA) --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SIGUE">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}">
    {{-- El aviso "instalar app" del navegador se captura antes de cargar Vue (puede llegar muy pronto) --}}
    <script>
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            window.__installEvent = e;
            window.dispatchEvent(new Event('sigue:instalable'));
        });
    </script>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    @routes
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
