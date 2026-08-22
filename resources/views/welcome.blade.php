<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CUMPLE</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 font-sans text-white antialiased">
    <main class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(16,185,129,.2),_transparent_40%),radial-gradient(circle_at_bottom_left,_rgba(14,165,233,.16),_transparent_35%)]"></div>
        <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col justify-between px-6 py-8 lg:px-10">
            <header class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/10"><img src="{{ asset('img/clinica-isotipo.png') }}" alt="Clínica de Occidente" class="h-8 w-8 object-contain brightness-0 invert"></div>
                    <div><div class="text-lg font-bold tracking-[.2em]">CUMPLE</div><div class="text-xs text-slate-400">Gestión asistencial</div></div>
                </div>
                <a href="{{ route('login') }}" class="rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold transition hover:border-emerald-300 hover:text-emerald-300">Ingresar</a>
            </header>
            <section class="grid items-center gap-14 py-16 lg:grid-cols-[1.15fr_.85fr]">
                <div>
                    <div class="mb-6 inline-flex rounded-full border border-emerald-300/25 bg-emerald-300/10 px-4 py-2 text-xs font-semibold uppercase tracking-[.2em] text-emerald-300">Koqoi · Control institucional</div>
                    <h1 class="max-w-4xl text-5xl font-extrabold leading-[1.04] tracking-tight sm:text-6xl lg:text-7xl">De cada compromiso,<br><span class="text-emerald-300">una evidencia.</span></h1>
                    <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-300">Control Unificado de Metas, Pendientes, Logros y Evidencias. Organiza actas, responsables y fechas límite en un solo lugar.</p>
                    <div class="mt-9 flex flex-wrap gap-3 text-sm text-slate-300">
                        @foreach (['UCI', 'Hospitalización', 'Urgencias', 'Cirugía'] as $area)
                            <span class="rounded-lg bg-white/5 px-4 py-2 ring-1 ring-white/10">{{ $area }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/[.06] p-6 shadow-2xl backdrop-blur">
                    <div class="mb-6 flex items-center justify-between"><span class="font-semibold">Ciclo de cumplimiento</span><span class="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_16px_rgba(52,211,153,.8)]"></span></div>
                    @foreach ([['01','Reunión y decisión'],['02','Responsable y fecha'],['03','Evidencia y revisión'],['04','Cierre verificable']] as [$number, $label])
                        <div class="mb-3 flex items-center gap-4 rounded-2xl bg-slate-900/70 p-4 ring-1 ring-white/5">
                            <span class="text-sm font-bold text-emerald-300">{{ $number }}</span><span class="text-slate-200">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
            <footer class="flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-6 text-xs text-slate-500"><span>© {{ date('Y') }} Koqoi · Información institucional protegida</span><img src="{{ asset('img/clinica-logo-horizontal.png') }}" alt="Clínica de Occidente" class="h-8 w-auto max-w-44 object-contain brightness-0 invert opacity-60"></footer>
        </div>
    </main>
</body>
</html>
