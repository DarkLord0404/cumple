<x-app-layout>
    <x-slot name="header"><div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-emerald-700">Configuración</p><h2 class="mt-1 text-2xl font-bold text-slate-900">Integración con Kairo</h2><p class="mt-1 text-sm text-slate-500">Controla quién puede consultar las actas importadas.</p></div><a href="{{ route('administration.catalogs') }}" class="text-sm font-bold text-slate-600 hover:text-emerald-700">← Volver a configuración</a></div></x-slot>
    <div class="py-6 sm:py-8"><div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200">{{ $errors->first() }}</div>@endif
        <form x-data="{ visibility: '{{ old('kairo_minute_visibility', $organization->kairo_minute_visibility) }}' }" method="POST" action="{{ route('kairo.update') }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-7">@csrf @method('PATCH')
            <div><h3 class="text-lg font-bold text-slate-900">Quién puede ver las actas de Kairo</h3><p class="mt-1 text-sm leading-6 text-slate-500">Los administradores siempre conservan acceso. Esta regla no afecta actas creadas directamente en CUMPLE.</p></div>
            <div class="mt-6 grid gap-3 md:grid-cols-3">
                @foreach(['administrators'=>['Solo administradores','Máxima privacidad; ideal para revisar antes de compartir.'],'selected'=>['Usuarios seleccionados','Permite formar un equipo revisor específico.'],'everyone'=>['Todos los usuarios','Hace visibles los borradores a toda la organización.']] as $value=>$option)
                    <label class="cursor-pointer rounded-xl border p-4" :class="visibility === '{{ $value }}' ? 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-500' : 'border-slate-200'"><input type="radio" name="kairo_minute_visibility" value="{{ $value }}" x-model="visibility" class="text-emerald-700"><span class="ml-2 font-bold text-slate-900">{{ $option[0] }}</span><span class="mt-2 block text-xs leading-5 text-slate-500">{{ $option[1] }}</span></label>
                @endforeach
            </div>
            <div x-show="visibility === 'selected'" x-cloak class="mt-6"><x-input-label value="Usuarios autorizados"/><div class="mt-2 grid max-h-72 gap-2 overflow-y-auto rounded-xl border border-slate-200 p-3 sm:grid-cols-2">@foreach($users as $user)<label class="flex items-center gap-3 rounded-lg p-2 hover:bg-slate-50"><input type="checkbox" name="viewer_ids[]" value="{{ $user->id }}" @checked(collect(old('viewer_ids', $viewerIds))->contains($user->id)) class="rounded text-emerald-700"><span><strong class="block text-sm">{{ $user->name }}</strong><small class="text-slate-500">{{ $user->role_label }}</small></span></label>@endforeach</div></div>
            <div class="mt-6 flex justify-end"><x-primary-button>Guardar visibilidad</x-primary-button></div>
        </form>
    </div></div>
</x-app-layout>
