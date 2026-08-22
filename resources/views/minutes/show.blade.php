<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0"><p class="text-sm font-semibold text-emerald-700">{{ $minute->number }}</p><h2 class="mt-1 break-words text-2xl font-bold">{{ $minute->title }}</h2></div>
            @if($minute->generated_document_path)<a href="{{ route('minutes.download',$minute) }}" class="shrink-0 rounded-xl bg-emerald-700 px-4 py-2 text-center text-sm font-bold text-white">Descargar Word</a>@endif
        </div>
    </x-slot>
    <div class="py-8"><div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:p-6">
            <div class="grid gap-5 sm:grid-cols-3"><div><p class="text-xs font-bold uppercase text-slate-500">Fecha</p><p>{{ $minute->held_at->format('d/m/Y H:i') }}</p></div><div><p class="text-xs font-bold uppercase text-slate-500">Área</p><p>{{ $minute->area?->name }}</p></div><div><p class="text-xs font-bold uppercase text-slate-500">Lugar</p><p>{{ $minute->location ?: '—' }}</p></div><div class="sm:col-span-3"><p class="text-xs font-bold uppercase text-slate-500">Objetivo</p><p class="mt-1 whitespace-pre-line">{{ $minute->objective ?: '—' }}</p></div><div class="sm:col-span-3"><p class="text-xs font-bold uppercase text-slate-500">Desarrollo</p><p class="mt-1 whitespace-pre-line leading-7">{{ $minute->development ?: '—' }}</p></div></div>
        </section>
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:p-6">
            <h3 class="text-lg font-bold">Compromisos</h3>
            <div class="mt-4 space-y-2">@foreach($minute->tasks as $task)<div class="flex min-w-0 flex-col gap-2 rounded-xl border p-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><p class="break-words font-semibold">{{ $task->title }}</p><p class="text-sm text-slate-500">{{ $task->responsible_name }}</p>@if($task->expected_result)<p class="mt-1 text-xs text-amber-800"><span class="font-bold">Evidencia esperada:</span> {{ $task->expected_result }}</p>@endif</div><p class="shrink-0 text-sm font-bold">{{ $task->due_at?->format('d/m/Y') }}</p></div>@endforeach</div>
            <form x-data="{ type: 'internal' }" method="POST" action="{{ route('minutes.commitments.store',$minute) }}" class="mt-6 grid gap-4 sm:grid-cols-2">@csrf
                <div class="sm:col-span-2"><x-input-label for="title" value="Nuevo compromiso"/><x-text-input id="title" name="title" class="mt-1 block w-full" required/></div>
                <div class="sm:col-span-2"><x-input-label for="expected_result" value="Evidencia esperada (opcional)"/><textarea id="expected_result" name="expected_result" rows="2" class="mt-1 block w-full rounded-xl border-slate-300" placeholder="Documento, acta, informe, captura o soporte esperado"></textarea></div>
                <select name="assignee_type" x-model="type" class="w-full rounded-xl border-slate-300"><option value="internal">Usuarios CUMPLE</option><option value="external">Responsable externo</option></select>
                <input name="due_at" type="date" class="w-full rounded-xl border-slate-300" required>
                <div x-show="type==='internal'" class="sm:col-span-2"><x-input-label value="Responsables"/><select name="assignee_ids[]" multiple size="5" class="mt-1 block w-full rounded-xl border-slate-300">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">Puedes seleccionar varios responsables.</p></div>
                <div x-show="type==='external'" class="sm:col-span-2"><x-input-label value="Nombre del responsable externo"/><x-text-input name="external_assignee_name" class="mt-1 block w-full"/></div>
                <div class="sm:col-span-2 flex justify-end"><x-secondary-button type="submit">Agregar compromiso</x-secondary-button></div>
            </form>
        </section>
        <section class="rounded-2xl bg-emerald-950 p-5 text-white sm:p-6"><h3 class="text-lg font-bold">Documento institucional</h3><p class="mt-2 text-sm text-emerald-100">Se generará una copia Word usando la plantilla oficial. El borrador de CUMPLE no reemplaza el documento que debe enviarse a Calidad.</p><form method="POST" action="{{ route('minutes.generate',$minute) }}" class="mt-5">@csrf<x-primary-button>Generar Word institucional</x-primary-button></form></section>
    </div></div>
</x-app-layout>
