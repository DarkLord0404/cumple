<x-app-layout>
    <x-slot name="header">
        <div><p class="text-sm font-semibold text-emerald-700">Resumen de gestión</p><h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Buenos días, {{ auth()->user()->name }}</h2></div>
    </x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([['Pendientes', $metrics['pending'], 'text-amber-800 bg-amber-50 ring-amber-200'], ['En ejecución', $metrics['in_progress'], 'text-emerald-800 bg-emerald-50 ring-emerald-200'], ['Por revisar', $metrics['in_review'], 'text-teal-800 bg-teal-50 ring-teal-200'], ['Vencidas', $metrics['overdue'], 'text-rose-800 bg-rose-50 ring-rose-200']] as [$label, $value, $color])
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><div class="mb-4 inline-flex rounded-lg px-3 py-1 text-xs font-semibold ring-1 {{ $color }}">{{ $label }}</div><div class="text-4xl font-extrabold tracking-tight text-slate-900">{{ $value }}</div></div>
                @endforeach
            </div>
            <div class="grid gap-6 lg:grid-cols-[1.6fr_1fr]">
                <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><div class="flex items-center justify-between"><div><h3 class="font-bold text-slate-900">Pendientes próximos</h3><p class="mt-1 text-sm text-slate-500">Ordenados por la fecha límite más cercana.</p></div><a href="{{ route('cases.index') }}" class="text-sm font-bold text-emerald-700">Ver planes</a></div>
                    <div class="mt-5 divide-y divide-slate-100">@forelse($upcomingTasks as $task)<a href="{{ $task->improvementCase ? route('cases.show',$task->improvementCase) : '#' }}" class="flex items-center justify-between gap-4 py-4"><div class="min-w-0"><p class="truncate font-semibold text-slate-900">{{ $task->title }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $task->improvementCase?->code ?? 'Tarea independiente' }} · {{ $task->area->name }}</p></div><div class="shrink-0 text-right"><p class="text-sm font-bold {{ $task->due_at?->isPast() ? 'text-rose-700' : 'text-slate-700' }}">{{ $task->due_at?->format('d/m/Y') ?? 'Sin fecha' }}</p><p class="text-xs text-slate-500">{{ $task->progress }}% completado</p></div></a>@empty<div class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">No tienes acciones pendientes.</div>@endforelse</div>
                </section>
                <section class="rounded-2xl bg-emerald-900 p-6 text-white shadow-sm"><p class="text-sm font-semibold text-emerald-200">Control general</p><div class="mt-3 text-5xl font-extrabold">{{ $openCases }}</div><h3 class="mt-2 text-xl font-bold tracking-tight">planes abiertos</h3><p class="mt-4 text-sm leading-6 text-emerald-50/80">Un plan solamente puede cerrarse cuando todas sus acciones estén terminadas y la verificación confirme que fue eficaz.</p></section>
            </div>
        </div>
    </div>
</x-app-layout>
