<nav x-data="{ open: false }" class="border-b border-slate-200 bg-white">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <span class="inline-flex items-center gap-3 text-slate-900"><img src="{{ asset('img/cumple-symbol.png') }}" alt="" class="h-9 w-9 object-contain"><span><strong class="block text-sm tracking-[.16em]">CUMPLE</strong><small class="block text-[9px] font-semibold tracking-wide text-slate-400">POR KOQOI</small></span></span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Inicio') }}
                    </x-nav-link>
                    <x-nav-link :href="route('cases.index')" :active="request()->routeIs('cases.*')">
                        {{ __('Hallazgos y planes') }}
                    </x-nav-link>
                    <x-nav-link :href="route('minutes.index')" :active="request()->routeIs('minutes.*')">Actas</x-nav-link>
                    @if(Auth::user()->role === 'administrator')
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            {{ __('Usuarios') }}
                        </x-nav-link>
                        <x-nav-link :href="route('administration.catalogs')" :active="request()->routeIs('administration.*')">Configuración</x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <a href="{{ route('notifications.index') }}" class="relative mr-2 inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-emerald-50 hover:text-emerald-800" aria-label="Notificaciones">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 01-6 0h6z"/></svg>
                    @if(Auth::user()->unreadNotifications()->count())<span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-rose-600 px-1.5 py-0.5 text-center text-[10px] font-bold text-white">{{ Auth::user()->unreadNotifications()->count() }}</span>@endif
                </a>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-xl border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-slate-600 transition duration-150 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                            <div class="text-left"><span class="block">{{ Auth::user()->name }}</span><span class="block max-w-40 truncate text-[10px] font-semibold text-slate-400">{{ Auth::user()->organization?->name }}</span></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Mi perfil') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Cerrar sesión') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-xl p-2 text-slate-500 transition duration-150 hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Inicio') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('cases.index')" :active="request()->routeIs('cases.*')">Hallazgos y planes</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('minutes.index')" :active="request()->routeIs('minutes.*')">Actas</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">Notificaciones @if(Auth::user()->unreadNotifications()->count())<span class="ml-2 rounded-full bg-rose-600 px-2 py-0.5 text-xs font-bold text-white">{{ Auth::user()->unreadNotifications()->count() }}</span>@endif</x-responsive-nav-link>
            @if(Auth::user()->role === 'administrator')
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Usuarios</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('administration.catalogs')" :active="request()->routeIs('administration.*')">Configuración</x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-slate-200">
            <div class="px-4">
                <div class="font-medium text-base text-slate-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-slate-500">{{ Auth::user()->email }}</div>
                <div class="mt-1 text-xs font-semibold text-emerald-700">{{ Auth::user()->organization?->name }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Mi perfil') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Cerrar sesión') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
