<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-terracota-oscuro border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-terracota-oscuro/90 active:bg-terracota-oscuro focus:outline-none focus:ring-2 focus:ring-terracota-oscuro focus:ring-offset-2 dark:focus:ring-offset-stone-800 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
