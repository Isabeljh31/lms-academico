<nav class="flex items-center gap-2">
    @auth
        <a href="{{ url('/dashboard') }}" wire:navigate
            class="inline-flex items-center px-4 py-2 bg-azul-oscuro rounded-md font-semibold text-sm text-white hover:bg-azul-oscuro/90 transition">
            Ir a mi panel
        </a>
    @else
        <a href="{{ route('login') }}" wire:navigate
            class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:text-azul-oscuro transition">
            Iniciar sesión
        </a>

        @if (Route::has('register'))
            <a href="{{ route('register') }}" wire:navigate
                class="inline-flex items-center px-4 py-2 bg-azul-oscuro rounded-md font-semibold text-sm text-white hover:bg-azul-oscuro/90 transition">
                Registrarme
            </a>
        @endif
    @endauth
</nav>
