{{--
    Lista de requisitos de la política de contraseñas (AppServiceProvider →
    Password::defaults()) que se marca en verde a medida que se cumplen.
    Debe estar dentro de un elemento con x-data="{ clave: '' }" cuyo campo de
    contraseña actualice "clave" (x-on:input="clave = $event.target.value").
    Solo orienta al usuario: la validación real se hace en el servidor.
--}}
<ul {{ $attributes->merge(['class' => 'mt-2 space-y-1 text-xs']) }} aria-live="polite">
    @foreach ([
        ['regla' => 'clave.length >= 8', 'texto' => 'Al menos 8 caracteres'],
        ['regla' => '/\p{Lu}/u.test(clave)', 'texto' => 'Una letra mayúscula'],
        ['regla' => '/\p{Ll}/u.test(clave)', 'texto' => 'Una letra minúscula'],
        ['regla' => '/\p{N}/u.test(clave)', 'texto' => 'Un número'],
        ['regla' => '/[\p{P}\p{S}\p{Z}]/u.test(clave)', 'texto' => 'Un carácter especial, como - . @ # ! $ %'],
    ] as $requisito)
        <li class="flex items-center gap-1.5"
            x-bind:class="{{ $requisito['regla'] }} ? 'text-green-700 dark:text-green-400' : 'text-stone-500 dark:text-stone-400'">
            <span aria-hidden="true" class="inline-flex h-3.5 w-3.5 items-center justify-center">
                <x-icono nombre="check" class="h-3.5 w-3.5" x-show="{{ $requisito['regla'] }}" x-cloak />
                <span class="h-2 w-2 rounded-full border border-current" x-show="! ({{ $requisito['regla'] }})"></span>
            </span>
            <span>{{ $requisito['texto'] }}</span>
            <span class="sr-only" x-text="{{ $requisito['regla'] }} ? '(cumplido)' : '(pendiente)'"></span>
        </li>
    @endforeach
</ul>
