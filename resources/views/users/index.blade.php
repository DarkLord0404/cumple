<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold text-emerald-700">Administración</p><h2 class="mt-1 text-2xl font-bold text-slate-900">Gestión de usuarios</h2></div></x-slot>
    <div class="py-8"><div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200"><p class="font-bold">No fue posible guardar los cambios:</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="grid items-start gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 xl:sticky xl:top-6">
                <h3 class="text-lg font-bold text-slate-900">Crear usuario</h3><p class="mt-1 text-sm text-slate-500">Registra a quien recibirá acciones y pendientes.</p>
                <form method="POST" action="{{ route('users.store') }}" class="mt-5 space-y-4">@csrf
                    <div><x-input-label for="name" value="Nombre completo"/><x-text-input id="name" name="name" value="{{ old('name') }}" class="mt-1 block w-full" required/></div>
                    <div><x-input-label for="email" value="Correo electrónico"/><x-text-input id="email" name="email" value="{{ old('email') }}" type="email" class="mt-1 block w-full" required/></div>
                    <div><x-input-label for="area_id" value="Área o servicio"/><select id="area_id" name="area_id" class="mt-1 block w-full rounded-xl border-slate-300"><option value="">Sin área asignada</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(old('area_id')==$area->id)>{{ $area->name }}</option>@endforeach</select></div>
                    <div><x-input-label for="role" value="Rol en CUMPLE"/><select id="role" name="role" class="mt-1 block w-full rounded-xl border-slate-300"><option value="collaborator">Colaborador</option><option value="coordinator" @selected(old('role')==='coordinator')>Coordinador</option><option value="quality" @selected(old('role')==='quality')>Gestión de Calidad</option><option value="administrator" @selected(old('role')==='administrator')>Administrador</option></select></div>
                    <div><x-input-label for="password" value="Contraseña temporal"/><x-text-input id="password" name="password" type="password" minlength="8" class="mt-1 block w-full" required/><p class="mt-1 text-xs text-slate-500">Debe tener mínimo 8 caracteres.</p></div>
                    <div><x-input-label for="password_confirmation" value="Confirmar contraseña"/><x-text-input id="password_confirmation" name="password_confirmation" type="password" minlength="8" class="mt-1 block w-full" required/></div>
                    <x-primary-button>Crear usuario</x-primary-button>
                </form>
            </section>
            <section class="min-w-0 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200" x-data="{ editing: null }">
                <h3 class="text-lg font-bold text-slate-900">Usuarios registrados</h3><p class="mt-1 text-sm text-slate-500">Edita los datos o restablece la contraseña desde cada tarjeta.</p>
                <div class="mt-5 space-y-3">@foreach($users as $user)
                    <article class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="break-words font-bold text-slate-900">{{ $user->name }}</p><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span></div><p class="mt-1 break-all text-sm text-slate-500">{{ $user->email }}</p><p class="mt-2 text-xs font-medium text-slate-600">{{ $user->area?->name ?? 'Sin área' }} · {{ ['administrator'=>'Administrador','coordinator'=>'Coordinador','quality'=>'Calidad','collaborator'=>'Colaborador'][$user->role] ?? $user->role }}</p></div>
                            <button type="button" @click="editing = editing === {{ $user->id }} ? null : {{ $user->id }}" class="shrink-0 rounded-xl bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-100" x-text="editing === {{ $user->id }} ? 'Cerrar' : 'Administrar'"></button>
                        </div>
                        <div x-show="editing === {{ $user->id }}" x-collapse x-cloak class="border-t border-slate-200 bg-slate-50 p-4"><div class="grid gap-6 lg:grid-cols-2">
                            <form method="POST" action="{{ route('users.update',$user) }}" class="min-w-0 space-y-3">@csrf @method('PATCH')<h4 class="font-bold text-slate-800">Datos y permisos</h4>
                                <div><x-input-label value="Nombre completo"/><input name="name" value="{{ $user->name }}" class="mt-1 block w-full rounded-xl border-slate-300" required></div><div><x-input-label value="Correo electrónico"/><input name="email" type="email" value="{{ $user->email }}" class="mt-1 block w-full rounded-xl border-slate-300" required></div>
                                <div class="grid gap-3 sm:grid-cols-2"><div><x-input-label value="Área"/><select name="area_id" class="mt-1 block w-full rounded-xl border-slate-300"><option value="">Sin área</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected($user->area_id==$area->id)>{{ $area->name }}</option>@endforeach</select></div><div><x-input-label value="Rol"/><select name="role" class="mt-1 block w-full rounded-xl border-slate-300">@foreach(['collaborator'=>'Colaborador','coordinator'=>'Coordinador','quality'=>'Calidad','administrator'=>'Administrador'] as $value=>$label)<option value="{{ $value }}" @selected($user->role===$value)>{{ $label }}</option>@endforeach</select></div></div>
                                <div><x-input-label value="Estado de la cuenta"/><select name="is_active" class="mt-1 block w-full rounded-xl border-slate-300"><option value="1" @selected($user->is_active)>Activa</option><option value="0" @selected(!$user->is_active)>Inactiva</option></select></div><x-primary-button>Guardar cambios</x-primary-button>
                            </form>
                            <form method="POST" action="{{ route('users.password.update',$user) }}" class="min-w-0 space-y-3 lg:border-l lg:border-slate-200 lg:pl-6">@csrf @method('PATCH')<div><h4 class="font-bold text-slate-800">Restablecer contraseña</h4><p class="mt-1 text-xs text-slate-500">La contraseña anterior dejará de funcionar inmediatamente.</p></div><div><x-input-label value="Nueva contraseña"/><input name="password" type="password" minlength="8" class="mt-1 block w-full rounded-xl border-slate-300" required></div><div><x-input-label value="Confirmar nueva contraseña"/><input name="password_confirmation" type="password" minlength="8" class="mt-1 block w-full rounded-xl border-slate-300" required></div><x-secondary-button type="submit">Actualizar contraseña</x-secondary-button></form>
                        </div></div>
                    </article>
                @endforeach</div><div class="mt-5">{{ $users->links() }}</div>
            </section>
        </div>
    </div></div>
</x-app-layout>
