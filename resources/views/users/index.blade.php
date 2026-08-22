<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold text-emerald-700">Administración</p><h2 class="mt-1 text-2xl font-bold text-slate-900">Gestión de usuarios</h2></div></x-slot>
    <div class="py-10"><div class="mx-auto grid max-w-7xl gap-6 px-4 lg:grid-cols-[1fr_1.5fr] sm:px-6 lg:px-8">
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-bold">Crear usuario</h3><p class="mt-1 text-sm text-slate-500">Las acciones pueden asignarse a cualquiera de estos usuarios.</p>
            @if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200"><p class="font-bold">No fue posible crear el usuario:</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('users.store') }}" class="mt-6 space-y-4">@csrf
                <div><x-input-label for="name" value="Nombre completo"/><x-text-input id="name" name="name" value="{{ old('name') }}" class="mt-1 block w-full" required/><x-input-error :messages="$errors->get('name')" class="mt-1"/></div>
                <div><x-input-label for="email" value="Correo"/><x-text-input id="email" name="email" value="{{ old('email') }}" type="email" class="mt-1 block w-full" required/><x-input-error :messages="$errors->get('email')" class="mt-1"/></div>
                <div><x-input-label for="area_id" value="Área"/><select id="area_id" name="area_id" class="mt-1 block w-full rounded-xl border-slate-300"><option value="">Sin área</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(old('area_id')==$area->id)>{{ $area->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('area_id')" class="mt-1"/></div>
                <div><x-input-label for="role" value="Rol"/><select id="role" name="role" class="mt-1 block w-full rounded-xl border-slate-300"><option value="collaborator" @selected(old('role')==='collaborator')>Colaborador</option><option value="coordinator" @selected(old('role')==='coordinator')>Coordinador</option><option value="quality" @selected(old('role')==='quality')>Gestión de Calidad</option><option value="administrator" @selected(old('role')==='administrator')>Administrador</option></select><x-input-error :messages="$errors->get('role')" class="mt-1"/></div>
                <div><x-input-label for="password" value="Contraseña temporal"/><x-text-input id="password" name="password" type="password" minlength="8" class="mt-1 block w-full" required/><p class="mt-1 text-xs text-slate-500">Mínimo 8 caracteres.</p><x-input-error :messages="$errors->get('password')" class="mt-1"/></div>
                <div><x-input-label for="password_confirmation" value="Confirmar contraseña"/><x-text-input id="password_confirmation" name="password_confirmation" type="password" minlength="8" class="mt-1 block w-full" required/></div>
                <x-primary-button>Crear usuario</x-primary-button>
            </form>
        </section>
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><h3 class="text-lg font-bold">Usuarios registrados</h3>
            <div class="mt-5 overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs uppercase text-slate-500"><tr><th class="pb-3">Usuario</th><th class="pb-3">Área</th><th class="pb-3">Rol</th><th class="pb-3">Estado</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($users as $user)<tr><td class="py-4"><p class="font-semibold text-slate-900">{{ $user->name }}</p><p class="text-slate-500">{{ $user->email }}</p></td><td>{{ $user->area?->name ?? '—' }}</td><td>{{ ['administrator'=>'Administrador','coordinator'=>'Coordinador','quality'=>'Calidad','collaborator'=>'Colaborador'][$user->role] ?? $user->role }}</td><td><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span></td></tr>@endforeach</tbody></table></div>{{ $users->links() }}
        </section>
    </div></div>
</x-app-layout>
