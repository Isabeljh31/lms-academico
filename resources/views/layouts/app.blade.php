@php
    $rol = auth()->user()->getRoleNames()->first();
    $esEstudiante = $rol === 'estudiante';
    $esAdmin = $rol === 'supervisor';

    // Sistema de color por rol (ver ARQUITECTURA.md):
    // - Estudiante y Profesor comparten una sola paleta azul "LearnUp".
    // - Supervisor conserva su propio acento (pendiente definir su tono final).
    //
    // Las clases dark:* van asociadas a variables cromáticas semánticas
    // (azul-fondo-oscuro, azul-superficie-oscura, etc. para Estudiante/Profesor;
    // stone-800 / stone-900 para Supervisor).
    $acentos = [
        'estudiante' => [
            'activo' => 'border-azul-oscuro bg-celeste/40 text-azul-oscuro dark:border-cielo dark:bg-azul-oscuro/40 dark:text-cielo',
        ],
        'profesor' => [
            'activo' => 'border-azul-oscuro bg-celeste/40 text-azul-oscuro dark:border-cielo dark:bg-azul-oscuro/40 dark:text-cielo',
        ],
        'supervisor' => [
            'activo' => 'border-terracota-oscuro bg-arena-claro text-terracota-oscuro dark:border-terracota-base dark:bg-stone-800 dark:text-terracota-claro',
        ],
    ];
    $acento = $acentos[$rol] ?? $acentos['estudiante'];

    // Cromática estructural: fondo, superficies, bordes y tipografías
    // según el rol activo.
    $cromaBody = $esAdmin ? 'bg-arena-claro text-stone-900 dark:bg-stone-900 dark:text-stone-100' : 'bg-celeste/20 text-texto dark:bg-azul-fondo-oscuro dark:text-slate-100';
    $cromaSuperficie = $esAdmin ? 'bg-white dark:bg-stone-800' : 'bg-white dark:bg-azul-superficie-oscura';
    $cromaBorde = $esAdmin ? 'border-arena-medio dark:border-stone-700' : 'border-celeste dark:border-azul-borde-oscuro';
    $cromaLogo = $esAdmin ? 'text-menta-oscuro dark:text-menta-base' : 'text-azul-oscuro dark:text-cielo';
    $cromaTitulo = $esAdmin ? 'text-stone-800 dark:text-stone-100' : 'text-texto dark:text-slate-100';
    $cromaNavInactivo = $esAdmin ? 'text-stone-600 dark:text-stone-300 hover:bg-arena-claro dark:hover:bg-stone-800' : 'text-slate-600 dark:text-slate-300 hover:bg-celeste/30 dark:hover:bg-azul-borde-oscuro';
    $cromaIconoMenu = $esAdmin ? 'text-stone-500 dark:text-stone-400' : 'text-slate-500 dark:text-slate-400';
    $cromaNombreUsuario = $esAdmin ? 'text-stone-700 dark:text-stone-200' : 'text-slate-700 dark:text-slate-200';
    $avatarFallbackColor = $esAdmin ? '7FB3AE' : '2F5A78';

    $menu = [
        ['ruta' => 'dashboard', 'etiqueta' => 'Panel', 'icono' => 'inicio', 'visible' => true],
        ['ruta' => 'cursos.index', 'etiqueta' => 'Cursos', 'icono' => 'libro', 'visible' => true],
        ['ruta' => 'mensajeria.selector', 'etiqueta' => 'Mensajería', 'icono' => 'usuarios', 'visible' => $rol !== 'supervisor'],
        ['ruta' => 'docente.por-calificar', 'etiqueta' => 'Por Calificar', 'icono' => 'tarea', 'visible' => $rol === 'profesor'],
        ['ruta' => 'admin.usuarios.index', 'etiqueta' => 'Usuarios', 'icono' => 'usuarios', 'visible' => $rol === 'supervisor'],
        ['ruta' => 'admin.profesores.index', 'etiqueta' => 'Profesores', 'icono' => 'birrete', 'visible' => $rol === 'supervisor'],
        ['ruta' => 'admin.comunicados.index', 'etiqueta' => 'Comunicados', 'icono' => 'megafono', 'visible' => $rol === 'supervisor'],
        ['ruta' => 'admin.metricas', 'etiqueta' => 'Dashboard institucional', 'icono' => 'grafico', 'visible' => $rol === 'supervisor'],
        ['ruta' => 'admin.analitica-riesgo', 'etiqueta' => 'Riesgo de abandono', 'icono' => 'alerta', 'visible' => $rol === 'supervisor'],
        ['ruta' => 'admin.integraciones', 'etiqueta' => 'Integración API', 'icono' => 'enlace', 'visible' => $rol === 'supervisor'],
        ['ruta' => 'admin.configuracion', 'etiqueta' => 'Configuración', 'icono' => 'engranaje', 'visible' => $rol === 'supervisor'],
        ['ruta' => 'admin.auditoria', 'etiqueta' => 'Auditoría', 'icono' => 'escudo', 'visible' => $rol === 'supervisor'],
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
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs {{ ! $esAdmin ? 'font-bold bg-celeste text-azul-oscuro dark:bg-azul-oscuro dark:text-white' : 'font-semibold '.$acento['activo'] }}">
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
                                    src="{{ auth()->user()->fotoUrl($avatarFallbackColor) }}"
                                    class="h-8 w-8 rounded-full object-cover"
                                    alt="{{ auth()->user()->name }}"
                                    x-data="{{ json_encode(['name' => auth()->user()->name]) }}"
                                    x-on:profile-updated.window="if ($event.detail.avatar_url) $el.src = $event.detail.avatar_url"
                                >
                                <span class="hidden sm:block text-sm font-medium {{ $cromaNombreUsuario }}" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                                <x-icono nombre="flecha-abajo" class="h-4 w-4 {{ $cromaIconoMenu }} transition-transform duration-200" x-bind:class="{ 'rotate-180': open }" />
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
