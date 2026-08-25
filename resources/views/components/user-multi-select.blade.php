@props(['users', 'selected' => [], 'name' => 'assignee_ids', 'label' => 'Responsables'])

@php
    $people = $users->map(fn ($user) => ['id' => (int) $user->id, 'name' => $user->name, 'detail' => $user->role_label])->values();
    $selectedIds = collect($selected)->map(fn ($id) => (int) $id)->unique()->values();
@endphp

<div x-data="{ candidate: '', selected: @js($selectedIds), people: @js($people), add() { const id=Number(this.candidate); if(id && !this.selected.includes(id)) this.selected.push(id); this.candidate=''; }, person(id) { return this.people.find(person => person.id === id); } }" {{ $attributes }}>
    <x-input-label :value="$label"/>
    <div class="mt-1 flex flex-col gap-2 sm:flex-row">
        <select x-model="candidate" class="block min-w-0 flex-1 rounded-xl border-slate-300 text-sm"><option value="">Selecciona un usuario…</option><template x-for="person in people" :key="person.id"><option :value="person.id" :disabled="selected.includes(person.id)" x-text="person.name + (person.detail ? ' · '+person.detail : '')"></option></template></select>
        <button type="button" @click="add" :disabled="!candidate" class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-40">Agregar</button>
    </div>
    <div class="mt-3 flex flex-wrap gap-2"><template x-for="id in selected" :key="id"><span class="inline-flex max-w-full items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 ring-1 ring-emerald-200"><input type="hidden" name="{{ $name }}[]" :value="id"><span class="truncate" x-text="person(id)?.name"></span><button type="button" @click="selected=selected.filter(item=>item!==id)" class="text-emerald-600 hover:text-rose-700" aria-label="Quitar responsable">×</button></span></template><p x-show="selected.length===0" class="text-xs font-semibold text-amber-700">Aún no has agregado responsables.</p></div>
</div>
