@php
    // Compartido entre roles; Estudiante usa la paleta fría nueva y
    // Profesor/Administrador conservan la anterior — ver ARQUITECTURA.md §
    // nueva paleta de color.
    $paletaFria = auth()->check() && auth()->user()->hasRole('estudiante');
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => $paletaFria
    ? 'inline-flex items-center px-4 py-2 bg-white dark:bg-azul-superficie-oscura border border-celeste dark:border-azul-borde-oscuro rounded-md font-semibold text-xs text-azul-oscuro dark:text-azul-fondo uppercase tracking-widest shadow-sm hover:bg-celeste/20 dark:hover:bg-azul-borde-oscuro/40 focus:outline-none focus:ring-2 focus:ring-azul-fondo focus:ring-offset-2 dark:focus:ring-offset-azul-superficie-oscura disabled:opacity-25 transition ease-in-out duration-150'
    : 'inline-flex items-center px-4 py-2 bg-white dark:bg-stone-800 border border-stone-300 dark:border-stone-500 rounded-md font-semibold text-xs text-stone-700 dark:text-stone-300 uppercase tracking-widest shadow-sm hover:bg-arena-claro dark:hover:bg-stone-700 focus:outline-none focus:ring-2 focus:ring-menta-base focus:ring-offset-2 dark:focus:ring-offset-stone-800 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
