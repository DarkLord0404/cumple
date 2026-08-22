@props(['title', 'description'])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    <title>{{ $title }} · CUMPLE por Koqoi</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-3"><img src="{{ asset('img/cumple-symbol.png') }}" alt="" class="h-10 w-10"><span><strong class="block tracking-[.16em] text-slate-900">CUMPLE</strong><small class="font-semibold tracking-wide text-slate-400">POR KOQOI</small></span></a>
            <a href="{{ route('login') }}" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Ingresar</a>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-5 py-10 sm:px-8 sm:py-14">
        <div class="mb-8"><a href="{{ url('/') }}" class="text-sm font-bold text-emerald-700">← Volver a CUMPLE</a><h1 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">{{ $title }}</h1><p class="mt-3 text-sm text-slate-500">Última actualización: 22 de agosto de 2026</p></div>
        <article class="legal-copy rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-10">{{ $slot }}</article>
    </main>
    <footer class="border-t border-slate-200 bg-white"><nav aria-label="Información legal" class="mx-auto flex max-w-5xl flex-wrap gap-x-5 gap-y-2 px-5 py-6 text-sm font-semibold text-slate-600 sm:px-8"><a href="{{ route('legal.privacy') }}" class="hover:text-emerald-700">Privacidad</a><a href="{{ route('legal.terms') }}" class="hover:text-emerald-700">Términos y condiciones</a><a href="{{ route('legal.data-processing') }}" class="hover:text-emerald-700">Tratamiento de datos</a><a href="mailto:info@koqoi.com" class="hover:text-emerald-700">Contacto</a></nav></footer>
</body>
</html>
