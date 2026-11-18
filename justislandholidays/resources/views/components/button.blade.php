<button {{ $attributes->merge(['type' => 'submit', 'class' => 'min-h-11 rounded bg-slate-900 px-5 font-medium text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
