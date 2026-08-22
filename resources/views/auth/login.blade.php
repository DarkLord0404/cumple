<x-guest-layout>
    <div class="mb-4">
        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-700">Acceso seguro</span>
        <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Bienvenido a CUMPLE</h2>
        <p class="mt-1 text-sm leading-5 text-slate-500">Ingresa con tu correo y contraseña para continuar.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-3">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="nombre@correo.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="text-xs font-semibold text-emerald-700 hover:text-emerald-900" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                @endif
            </div>
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" placeholder="Tu contraseña" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <label for="remember_me" class="flex items-center gap-2 text-sm text-slate-600">
            <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500" name="remember">
            {{ __('Remember me') }}
        </label>
        <x-primary-button class="w-full py-3">{{ __('Log in') }}</x-primary-button>
    </form>
    <div class="mt-4 border-t border-slate-100 pt-3 text-center"><p class="text-xs text-slate-500 sm:text-sm">¿Tu organización aún no usa CUMPLE?</p><a href="{{ route('register') }}" class="mt-1 inline-flex text-sm font-bold text-emerald-700 hover:text-emerald-900">Crear una nueva organización →</a></div>
</x-guest-layout>
