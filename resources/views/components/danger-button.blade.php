<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-xl border border-transparent bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 active:bg-rose-800']) }}>
    {{ $slot }}
</button>
