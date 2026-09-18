@php
    // Compartido entre roles; Estudiante usa la paleta fría nueva y
    // Profesor/Administrador conservan la anterior — ver ARQUITECTURA.md §
    // nueva paleta de color. Sin sesión (login/registro/recuperar
    // contraseña) se usa la paleta fría por defecto — ver text-input.blade.php.
    $paletaFria = ! auth()->check() || auth()->user()->hasRole('estudiante');
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => $paletaFria
    ? 'inline-flex items-center px-4 py-2 bg-azul-oscuro dark:bg-azul-fondo border border-transparent rounded-md font-semibold text-xs text-white dark:text-slate-900 uppercase tracking-widest hover:bg-azul-oscuro/90 dark:hover:bg-azul-fondo/90 focus:bg-azul-oscuro/90 active:bg-azul-oscuro focus:outline-none focus:ring-2 focus:ring-azul-fondo focus:ring-offset-2 dark:focus:ring-offset-azul-superficie-oscura transition ease-in-out duration-150'
    : 'inline-flex items-center px-4 py-2 bg-menta-oscuro dark:bg-menta-base border border-transparent rounded-md font-semibold text-xs text-white dark:text-stone-900 uppercase tracking-widest hover:bg-menta-oscuro/90 dark:hover:bg-menta-base/90 focus:bg-menta-oscuro/90 active:bg-menta-oscuro focus:outline-none focus:ring-2 focus:ring-menta-base focus:ring-offset-2 dark:focus:ring-offset-stone-800 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
