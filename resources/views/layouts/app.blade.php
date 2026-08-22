<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CUMPLE') }} · por Koqoi</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <div class="min-h-screen bg-slate-50">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-slate-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="min-w-0 overflow-x-hidden">
                {{ $slot }}
            </main>
            <footer class="border-t border-slate-200 bg-white"><nav aria-label="Información legal" class="mx-auto flex max-w-7xl flex-wrap justify-center gap-x-5 gap-y-2 px-4 py-5 text-xs font-semibold text-slate-500 sm:px-6 lg:px-8"><a href="{{ route('legal.privacy') }}" class="hover:text-emerald-700">Política de privacidad</a><a href="{{ route('legal.terms') }}" class="hover:text-emerald-700">Términos y condiciones</a><a href="{{ route('legal.data-processing') }}" class="hover:text-emerald-700">Tratamiento de datos</a><a href="mailto:info@koqoi.com" class="hover:text-emerald-700">Soporte y contacto</a></nav></footer>
        </div>
    </body>
</html>
