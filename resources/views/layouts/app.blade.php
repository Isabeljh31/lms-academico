@php
    $rol = auth()->user()?->rolPrincipal() ?? 'estudiante';
    $esEstudiante = $rol === 'estudiante';

    $acento = match ($rol) {
        'administrador' => ['activo' => 'bg-cafe-arcilla/15 text-cafe-arcilla border-cafe-arcilla', 'punto' => 'bg-cafe-arcilla'],
        'profesor' => ['activo' => 'bg-terracota-fondo text-terracota-oscuro border-terracota-oscuro', 'punto' => 'bg-terracota-oscuro'],
        // Estudiante: paleta fría nueva — ver ARQUITECTURA.md § nueva paleta de color.
        default => ['activo' => 'bg-celeste/60 text-azul-oscuro border-azul-oscuro', 'punto' => 'bg-azul-oscuro'],
    };

    // Colores de "chrome" compartido (body, sidebar, header) condicionados al
    // rol: solo Estudiante usa la paleta fría por ahora; Profesor y
    // Administrador conservan exactamente los mismos tonos que ya tenían.
    $cromaBody = $esEstudiante ? 'bg-fondo dark:bg-azul-pagina-oscura' : 'bg-arena-claro dark:bg-stone-950';
    $cromaSuperficie = $esEstudiante ? 'bg-white dark:bg-azul-superficie-oscura' : 'bg-white dark:bg-stone-900';
    $cromaBorde = $esEstudiante ? 'border-celeste dark:border-azul-borde-oscuro' : 'border-arena-medio dark:border-stone-800';
    $cromaLogo = $esEstudiante ? 'text-azul-oscuro dark:text-azul-fondo' : 'text-menta-oscuro dark:text-menta-base';
    $cromaTitulo = $esEstudiante ? 'text-texto dark:text-slate-100' : 'text-stone-800 dark:text-stone-100';
    $cromaNavInactivo = $esEstudiante ? 'text-slate-600 dark:text-slate-300 hover:bg-celeste/30 dark:hover:bg-azul-borde-oscuro' : 'text-stone-600 dark:text-stone-300 hover:bg-arena-claro dark:hover:bg-stone-800';
    $cromaIconoMenu = $esEstudiante ? 'text-slate-500 dark:text-slate-400' : 'text-stone-500 dark:text-stone-400';
    $cromaNombreUsuario = $esEstudiante ? 'text-slate-700 dark:text-slate-200' : 'text-stone-700 dark:text-stone-200';
    $avatarFallbackColor = $esEstudiante ? '2F5A78' : '7FB3AE';

    $menu = [
        ['ruta' => 'dashboard', 'etiqueta' => 'Panel', 'icono' => 'inicio', 'visible' => true],
        ['ruta' => 'cursos.index', 'etiqueta' => 'Cursos', 'icono' => 'libro', 'visible' => true],
        ['ruta' => 'admin.usuarios.index', 'etiqueta' => 'Usuarios', 'icono' => 'usuarios', 'visible' => $rol === 'administrador'],
        ['ruta' => 'admin.metricas', 'etiqueta' => 'Métricas', 'icono' => 'grafico', 'visible' => $rol === 'administrador'],
        ['ruta' => 'admin.configuracion', 'etiqueta' => 'Configuración', 'icono' => 'engranaje', 'visible' => $rol === 'administrador'],
        ['ruta' => 'evento-calendario.index', 'etiqueta' => 'Calendario', 'icono' => 'calendario', 'visible' => true],
        ['ruta' => 'reportes.dashboard', 'etiqueta' => 'Reportes', 'icono' => 'grafico', 'visible' => true],
        ['ruta' => 'bot.propuestas', 'etiqueta' => 'Propuestas del bot', 'icono' => 'bombilla', 'visible' => $rol === 'estudiante'],
        ['ruta' => 'bot.resumen', 'etiqueta' => 'Resumen del bot', 'icono' => 'chat', 'visible' => $rol === 'profesor'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'LearnUp') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased {{ $cromaBody }}">
        <div x-data="{ sidebarAbierto: false }" class="lg:flex lg:h-screen lg:overflow-hidden">
            <div x-show="sidebarAbierto" x-cloak @click="sidebarAbierto = false"
                class="fixed inset-0 z-30 bg-stone-900/50 lg:hidden"></div>

            <aside
                :class="sidebarAbierto ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-40 w-64 transform {{ $cromaSuperficie }} border-r {{ $cromaBorde }} transition-transform duration-200 ease-in-out lg:static lg:translate-x-0 lg:flex lg:flex-col lg:shrink-0"
            >
                <div class="flex items-center gap-2 px-6 h-16 border-b {{ $cromaBorde }}">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                        <x-application-logo class="h-8 w-auto fill-current {{ $cromaLogo }}" />
                        <span class="font-semibold {{ $cromaTitulo }}">{{ config('app.name') }}</span>
                    </a>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                    @foreach ($menu as $item)
                        @continue(! $item['visible'])
                        @php $activo = request()->routeIs($item['ruta']); @endphp
                        <a href="{{ route($item['ruta']) }}" wire:navigate
                            class="flex items-center gap-3 rounded-lg border-l-4 px-3 py-2 text-sm font-medium transition
                                {{ $activo ? $acento['activo'] : 'border-transparent '.$cromaNavInactivo }}">
                            <x-icono :nombre="$item['icono']" class="h-5 w-5 shrink-0" />
                            {{ $item['etiqueta'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="px-3 py-4 border-t {{ $cromaBorde }}">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs {{ $esEstudiante ? 'font-bold bg-celeste text-azul-oscuro dark:bg-azul-oscuro dark:text-white' : 'font-semibold '.$acento['activo'] }}">
                        {{ ucfirst($rol) }}
                    </span>
                </div>
            </aside>

            <div class="flex-1 flex flex-col min-w-0 lg:overflow-y-auto">
                {{-- shrink-0: sin esto, en páginas cuyo contenido es más alto que
                     la pantalla, flexbox encoge esta barra junto con el resto en
                     vez de dejarla fija en 64px y hacer scroll solo en <main>. --}}
                <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-4 border-b {{ $cromaBorde }} {{ $cromaSuperficie }} px-4 sm:px-6">
                    <button @click="sidebarAbierto = true" class="lg:hidden {{ $cromaIconoMenu }}">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div class="flex-1">
                        @isset($header)
                            <div class="font-semibold {{ $cromaTitulo }}">{{ $header }}</div>
                        @endisset
                    </div>

                    <livewire:notificaciones.campanita />

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 focus:outline-none">
                                <img
                                    src="{{ auth()->user()->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode(auth()->user()->name).'&background='.$avatarFallbackColor.'&color=fff' }}"
                                    class="h-8 w-8 rounded-full object-cover"
                                    alt="{{ auth()->user()->name }}"
                                    x-data="{{ json_encode(['name' => auth()->user()->name]) }}"
                                    x-on:profile-updated.window="if ($event.detail.avatar_url) $el.src = $event.detail.avatar_url"
                                >
                                <span class="hidden sm:block text-sm font-medium {{ $cromaNombreUsuario }}" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile')" wire:navigate>
                                {{ __('Perfil') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link href="#" onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Cerrar sesión') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <livewire:bot-sugerencias.widget />
    </body>
</html>
