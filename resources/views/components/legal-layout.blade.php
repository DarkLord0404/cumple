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
    <section class="relative overflow-hidden bg-slate-950 text-white"><div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_15%,_rgba(16,185,129,.2),_transparent_35%)]"></div><div class="relative mx-auto max-w-5xl px-5 py-10 sm:px-8 sm:py-14"><a href="{{ url('/') }}" class="text-sm font-bold text-emerald-300">← Volver a CUMPLE</a><p class="mt-7 text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Centro de confianza</p><h1 class="mt-3 max-w-3xl text-3xl font-extrabold tracking-tight sm:text-5xl">{{ $title }}</h1><p class="mt-4 text-sm text-slate-400">Información clara sobre el uso de CUMPLE y la protección de tus datos · Actualizada el 22 de agosto de 2026</p></div></section>
    <main class="mx-auto grid max-w-5xl items-start gap-6 px-5 py-8 sm:px-8 sm:py-12 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside class="rounded-2xl bg-emerald-950 p-5 text-white shadow-sm lg:sticky lg:top-6"><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-300">Documentos legales</p><nav class="mt-4 space-y-2 text-sm"><a href="{{ route('legal.privacy') }}" class="block rounded-xl px-3 py-2.5 {{ request()->routeIs('legal.privacy') ? 'bg-white text-emerald-950' : 'text-emerald-50 hover:bg-white/10' }}">Política de privacidad</a><a href="{{ route('legal.terms') }}" class="block rounded-xl px-3 py-2.5 {{ request()->routeIs('legal.terms') ? 'bg-white text-emerald-950' : 'text-emerald-50 hover:bg-white/10' }}">Términos y condiciones</a><a href="{{ route('legal.data-processing') }}" class="block rounded-xl px-3 py-2.5 {{ request()->routeIs('legal.data-processing') ? 'bg-white text-emerald-950' : 'text-emerald-50 hover:bg-white/10' }}">Tratamiento de datos</a></nav><div class="mt-5 border-t border-white/10 pt-5"><p class="text-xs leading-5 text-emerald-100/70">¿Tienes una solicitud sobre tus datos?</p><a href="mailto:info@koqoi.com" class="mt-2 block text-sm font-bold text-emerald-300">info@koqoi.com</a></div></aside>
        <article class="legal-copy rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-10">{{ $slot }}</article>
    </main>
    <footer class="border-t border-slate-200 bg-white"><nav aria-label="Información legal" class="mx-auto flex max-w-5xl flex-wrap gap-x-5 gap-y-2 px-5 py-6 text-sm font-semibold text-slate-600 sm:px-8"><a href="{{ route('legal.privacy') }}" class="hover:text-emerald-700">Privacidad</a><a href="{{ route('legal.terms') }}" class="hover:text-emerald-700">Términos y condiciones</a><a href="{{ route('legal.data-processing') }}" class="hover:text-emerald-700">Tratamiento de datos</a><a href="mailto:info@koqoi.com" class="hover:text-emerald-700">Contacto</a></nav></footer>
</body>
</html>
