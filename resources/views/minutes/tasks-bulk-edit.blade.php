<x-app-layout>
    <x-slot name="header"><div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-emerald-700">{{ $minute->number }}</p><h2 class="mt-1 text-2xl font-bold text-slate-900">Edición masiva de tareas</h2><p class="mt-1 text-sm text-slate-500">{{ $minute->title }}</p></div><a href="{{ route('minutes.show',$minute) }}" class="text-sm font-bold text-slate-600 hover:text-emerald-700">← Volver al acta</a></div></x-slot>
    <div x-data="{ selected: [], allIds: @js($minute->tasks->reject(fn($task) => in_array($task->status,['in_review','completed']))->pluck('id')->values()), toggleAll() { this.selected = this.selected.length === this.allIds.length ? [] : [...this.allIds] } }" class="py-6 sm:py-8"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200">{{ $errors->first() }}</div>@endif

        @if($minute->tasks->isEmpty())
            <div class="rounded-2xl bg-white p-10 text-center text-slate-500 shadow-sm ring-1 ring-slate-200">Esta acta todavía no tiene tareas creadas.</div>
        @else
            <section class="sticky top-2 z-20 rounded-2xl bg-slate-950 p-4 text-white shadow-xl sm:p-5">
                <form id="bulk-form" method="POST" action="{{ route('minutes.tasks.bulk.apply',$minute) }}" class="grid gap-3 lg:grid-cols-4">@csrf @method('PATCH')
                    <div class="lg:col-span-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="font-bold">Acciones sobre la selección</h3><p class="text-xs text-slate-300"><span x-text="selected.length"></span> tareas seleccionadas</p></div><button type="button" @click="toggleAll" class="text-left text-xs font-bold text-emerald-300">Seleccionar o limpiar todas las editables</button></div>
                    <div><label class="text-xs font-bold text-slate-300">Cambiar área</label><select name="area_id" class="mt-1 block w-full rounded-xl border-slate-700 bg-slate-900 text-sm text-white"><option value="">Conservar</option>@foreach($areas as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach</select></div>
                    <div><label class="text-xs font-bold text-slate-300">Cambiar fecha</label><input name="due_at" type="date" class="mt-1 block w-full rounded-xl border-slate-700 bg-slate-900 text-sm text-white"></div>
                    <div><label class="text-xs font-bold text-slate-300">Reemplazar responsables</label><select name="assignee_ids[]" multiple size="2" class="mt-1 block w-full rounded-xl border-slate-700 bg-slate-900 text-xs text-white">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                    <div class="flex flex-col justify-end gap-2 sm:flex-row lg:flex-col"><button name="bulk_action" value="update" :disabled="selected.length===0" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold disabled:opacity-40">Aplicar cambios</button><button name="bulk_action" value="delete" :disabled="selected.length===0" onclick="return confirm('¿Eliminar todas las tareas seleccionadas? Esta acción no afecta el texto original del acta.')" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-bold disabled:opacity-40">Eliminar seleccionadas</button></div>
                </form>
            </section>

            <form method="POST" action="{{ route('minutes.tasks.bulk.update',$minute) }}" class="space-y-3">@csrf @method('PUT')
                @foreach($minute->tasks as $task)
                    @php($locked = in_array($task->status,['in_review','completed']))
                    <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 {{ $locked ? 'ring-slate-200 opacity-75' : 'ring-slate-200' }} sm:p-5">
                        <div class="mb-4 flex items-start gap-3"><input form="bulk-form" x-model.number="selected" name="task_ids[]" value="{{ $task->id }}" type="checkbox" @disabled($locked) class="mt-1 rounded text-emerald-700"><div class="min-w-0 flex-1"><div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs font-bold text-emerald-700">{{ $task->code }}</p><span class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ $locked ? ($task->status === 'completed' ? 'Cerrada' : 'En revisión') : 'Editable' }}</span></div>@if($locked)<p class="mt-2 text-xs text-slate-500">Esta tarea está protegida por el flujo de aprobación.</p>@endif</div></div>
                        @unless($locked)
                        <div class="grid gap-4 lg:grid-cols-12">
                            <div class="lg:col-span-7"><x-input-label value="Tarea"/><input name="tasks[{{ $task->id }}][title]" value="{{ $task->title }}" class="mt-1 block w-full rounded-xl border-slate-300" required></div>
                            <div class="lg:col-span-3"><x-input-label value="Área"/><select name="tasks[{{ $task->id }}][area_id]" class="mt-1 block w-full rounded-xl border-slate-300" required>@foreach($areas as $area)<option value="{{ $area->id }}" @selected($task->area_id===$area->id)>{{ $area->name }}</option>@endforeach</select></div>
                            <div class="lg:col-span-2"><x-input-label value="Fecha límite"/><input name="tasks[{{ $task->id }}][due_at]" type="date" value="{{ $task->due_at?->format('Y-m-d') }}" class="mt-1 block w-full rounded-xl border-slate-300" required></div>
                            <div class="lg:col-span-6"><x-input-label value="Evidencia esperada"/><textarea name="tasks[{{ $task->id }}][expected_result]" rows="3" class="mt-1 block w-full rounded-xl border-slate-300">{{ $task->expected_result }}</textarea></div>
                            <div x-data="{ type: '{{ $task->assignee_type }}' }" class="grid gap-4 lg:col-span-6 sm:grid-cols-2"><div><x-input-label value="Tipo"/><select name="tasks[{{ $task->id }}][assignee_type]" x-model="type" class="mt-1 block w-full rounded-xl border-slate-300"><option value="internal">Usuarios CUMPLE</option><option value="external">Responsable externo</option></select></div><div x-show="type==='external'"><x-input-label value="Nombre externo"/><input name="tasks[{{ $task->id }}][external_assignee_name]" value="{{ $task->external_assignee_name }}" class="mt-1 block w-full rounded-xl border-slate-300"></div><div x-show="type==='internal'" class="sm:col-span-2"><x-input-label value="Responsables"/><select name="tasks[{{ $task->id }}][assignee_ids][]" multiple size="3" class="mt-1 block w-full rounded-xl border-slate-300 text-sm">@foreach($users as $user)<option value="{{ $user->id }}" @selected($task->assignees->contains($user->id) || ($task->assignees->isEmpty() && $task->assigned_to===$user->id))>{{ $user->name }} · {{ $user->role_label }}</option>@endforeach</select></div></div>
                        </div>
                        @endunless
                    </article>
                @endforeach
                @if($minute->tasks->contains(fn($task) => !in_array($task->status,['in_review','completed'])))<div class="flex justify-end"><x-primary-button>Guardar todas las modificaciones</x-primary-button></div>@endif
            </form>
        @endif
    </div></div>
</x-app-layout>
