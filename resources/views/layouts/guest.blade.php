<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CUMPLE') }} · por Koqoi</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-slate-900 antialiased">
    <main class="grid min-h-screen bg-slate-50 lg:grid-cols-[1.05fr_.95fr]">
        <section class="relative hidden overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_12%,_rgba(52,211,153,.22),_transparent_32%),radial-gradient(circle_at_90%_85%,_rgba(14,165,233,.18),_transparent_36%)]"></div>
            <img src="{{ asset('img/cumple-symbol.png') }}" alt="" class="absolute -bottom-28 -right-24 w-[34rem] opacity-[.07]">
            <a href="{{ url('/') }}" class="relative inline-flex items-center gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15"><img src="{{ asset('img/cumple-symbol.png') }}" alt="" class="h-10 w-10 object-contain"></span>
                <span><strong class="block text-xl tracking-[.2em]">CUMPLE</strong><small class="text-slate-400">por Koqoi</small></span>
            </a>
            <div class="relative max-w-xl">
                <p class="mb-5 text-sm font-bold uppercase tracking-[.24em] text-emerald-300">Control institucional</p>
                <h1 class="text-5xl font-extrabold leading-tight">Los compromisos claros se convierten en resultados.</h1>
                <p class="mt-6 max-w-lg text-lg leading-8 text-slate-300">Metas, pendientes, logros y evidencias de las áreas asistenciales en un solo lugar.</p>
            </div>
            <div class="relative flex items-center gap-4 border-t border-white/10 pt-7">
                <span class="text-sm font-bold tracking-[.16em] text-emerald-300">KOQOI</span>
                <span class="h-7 w-px bg-white/15"></span>
                <span class="text-xs text-slate-400">Tecnología para convertir compromisos en resultados</span>
            </div>
        </section>

        <section class="flex items-center justify-center px-5 py-10 sm:px-10">
            <div class="w-full max-w-md">
                <div class="mb-9 flex items-center justify-between lg:hidden">
                    <a href="{{ url('/') }}" class="flex items-center gap-3"><img src="{{ asset('img/cumple-symbol.png') }}" alt="" class="h-10 w-10 object-contain"><span><strong class="block tracking-[.18em]">CUMPLE</strong><small class="text-[9px] font-bold tracking-wide text-slate-400">POR KOQOI</small></span></a>
                </div>
                <div class="rounded-3xl bg-white p-7 shadow-xl shadow-slate-200/60 ring-1 ring-slate-200 sm:p-9">
                    {{ $slot }}
                </div>
                <p class="mt-6 text-center text-xs text-slate-500">CUMPLE · Control Unificado de Metas, Pendientes, Logros y Evidencias</p>
                <nav aria-label="Información legal" class="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs font-semibold text-slate-500"><a href="{{ route('legal.privacy') }}" class="hover:text-emerald-700">Privacidad</a><a href="{{ route('legal.terms') }}" class="hover:text-emerald-700">Términos</a><a href="{{ route('legal.data-processing') }}" class="hover:text-emerald-700">Datos personales</a></nav>
            </div>
        </section>
    </main>
</body>
</html>
