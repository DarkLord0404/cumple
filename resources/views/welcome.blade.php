<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CUMPLE · por Koqoi</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-dvh overflow-hidden bg-slate-950 font-sans text-white antialiased">
    <main class="relative isolate h-dvh overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(16,185,129,.2),_transparent_40%),radial-gradient(circle_at_bottom_left,_rgba(14,165,233,.16),_transparent_35%)]"></div>
        <div class="relative mx-auto flex h-full max-w-7xl flex-col px-5 py-5 sm:px-7 sm:py-6 lg:px-10">
            <header class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/10"><img src="{{ asset('img/cumple-symbol.png') }}" alt="" class="h-8 w-8 object-contain"></div>
                    <div><div class="text-lg font-bold tracking-[.2em]">CUMPLE</div><div class="text-xs font-semibold tracking-wide text-slate-400">POR KOQOI</div></div>
                </div>
                <a href="{{ route('login') }}" class="rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold transition hover:border-emerald-300 hover:text-emerald-300">Ingresar</a>
            </header>
            <section class="grid min-h-0 flex-1 items-center gap-5 py-4 sm:gap-7 lg:grid-cols-[1.15fr_.85fr] lg:gap-12 lg:py-6">
                <div>
                    <div class="mb-3 inline-flex rounded-full border border-emerald-300/25 bg-emerald-300/10 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[.2em] text-emerald-300 sm:mb-5 sm:px-4 sm:py-2 sm:text-xs">Koqoi · Control de compromisos</div>
                    <h1 class="max-w-4xl text-4xl font-extrabold leading-[1.04] tracking-tight sm:text-5xl lg:text-7xl">De cada compromiso,<br><span class="text-emerald-300">una evidencia.</span></h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:mt-5 sm:text-base sm:leading-7 lg:text-lg">Control Unificado de Metas, Pendientes, Logros y Evidencias. Organiza decisiones, responsables y fechas límite en un solo lugar.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[.06] p-4 shadow-2xl backdrop-blur sm:rounded-3xl sm:p-5 lg:p-6">
                    <div class="mb-3 flex items-center justify-between sm:mb-5"><span class="text-sm font-semibold sm:text-base">Ciclo de cumplimiento</span><span class="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_16px_rgba(52,211,153,.8)]"></span></div>
                    @foreach ([['01','Reunión y decisión'],['02','Responsable y fecha'],['03','Evidencia y revisión'],['04','Cierre verificable']] as [$number, $label])
                        <div class="mb-2 flex items-center gap-3 rounded-xl bg-slate-900/70 px-3 py-2 ring-1 ring-white/5 sm:mb-3 sm:gap-4 sm:rounded-2xl sm:p-3.5">
                            <span class="text-xs font-bold text-emerald-300 sm:text-sm">{{ $number }}</span><span class="text-sm text-slate-200 sm:text-base">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
            <footer class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-white/10 pt-3 text-[10px] text-slate-500 sm:pt-4 sm:text-xs"><span>© {{ date('Y') }} Koqoi</span><nav aria-label="Información legal" class="flex flex-wrap justify-end gap-x-4 gap-y-1 font-semibold text-slate-400"><a href="{{ route('legal.privacy') }}" class="hover:text-emerald-300">Privacidad</a><a href="{{ route('legal.terms') }}" class="hover:text-emerald-300">Términos</a><a href="{{ route('legal.data-processing') }}" class="hover:text-emerald-300">Datos personales</a><a href="mailto:info@koqoi.com" class="hover:text-emerald-300">Contacto</a></nav></footer>
        </div>
    </main>
</body>
</html>
