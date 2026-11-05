@php
    // Layout solo del Profesor (Panel, Cursos y detalle del curso, mockups
    // "Panel" y "Cursos"): sin barra lateral ni encabezado; la navegación va en
    // una barra inferior flotante. Las demás pantallas usan
    // layouts/app.blade.php, que no se modifica.
    $usuario = auth()->user();
    // Como layout de una página Livewire no pasa por PanelProfesorLayout: el contador se calcula aquí
    // (solo al cargar la página; las acciones de Livewire no vuelven a pintar el layout).
    $porCalificar ??= app(\App\Services\Reportes\PanelProfesor::class)->totalPorCalificar($usuario);
    $primerNombre = \Illuminate\Support\Str::of((string) $usuario->name)->trim()->before(' ')->value() ?: (string) $usuario->name;
    $enfoque = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cielo focus-visible:ring-offset-2';

    // corta: etiqueta bajo el icono (móvil y tablet); larga: barra completa (escritorio).
    // secundario: en móvil pasa al menú "Más".
    $enlaces = [
        ['ruta' => 'dashboard', 'corta' => 'Panel', 'larga' => 'Panel', 'icono' => 'inicio', 'secundario' => false],
        ['ruta' => 'cursos.index', 'activa' => ['cursos.index', 'cursos.show'], 'corta' => 'Cursos', 'larga' => 'Cursos', 'icono' => 'libro', 'secundario' => false],
        ['ruta' => 'docente.por-calificar', 'corta' => 'Calificar', 'larga' => 'Por calificar', 'icono' => 'check-circulo', 'secundario' => false, 'contador' => $porCalificar],
        ['ruta' => 'mensajeria.selector', 'corta' => 'Mensajes', 'larga' => 'Mensajería', 'icono' => 'usuarios', 'secundario' => true],
        ['ruta' => 'evento-calendario.index', 'corta' => 'Calendario', 'larga' => 'Calendario', 'icono' => 'calendario', 'secundario' => false],
        ['ruta' => 'reportes.dashboard', 'corta' => 'Reportes', 'larga' => 'Reportes', 'icono' => 'grafico', 'secundario' => true],
        ['ruta' => 'bot.resumen', 'corta' => 'Bot', 'larga' => 'Resumen del bot', 'icono' => 'chat', 'secundario' => true],
    ];

    $item = "relative flex min-h-11 min-w-11 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1.5 py-1 text-[11px] font-medium leading-tight transition md:flex-none md:px-2.5 xl:flex-row xl:gap-2 xl:px-3.5 xl:py-0 xl:text-sm motion-reduce:transition-none {$enfoque}";
    $itemInactivo = 'text-slate-600 hover:bg-fondo hover:text-azul-oscuro';
    $itemActivo = 'bg-celeste font-semibold text-azul-oscuro';
    $opcionMenu = "flex min-h-11 w-full items-center gap-2.5 rounded-lg px-3 text-left text-sm font-medium text-texto transition hover:bg-fondo motion-reduce:transition-none {$enfoque} focus-visible:ring-offset-0";
@endphp

<!DOCTYPE html>
{{-- scroll-padding: al navegar con el teclado, lo enfocado no queda tapado por la barra fija. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pb-[calc(7.5rem+env(safe-area-inset-bottom))] lg:scroll-pb-36">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Panel — '.config('app.name', 'LearnUp') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- Fondo de página con el token "fondo" (#F5F7FA) para que las tarjetas blancas resalten. --}}
    <body class="min-h-screen bg-fondo font-sans text-texto antialiased">
        {{-- Contenido: hasta 1440 px, con relleno lateral que crece con la pantalla. --}}
        <main class="mx-auto w-full max-w-[1440px] px-4 pb-[calc(7.5rem+env(safe-area-inset-bottom))] pt-8 sm:px-6 sm:pt-12 lg:px-8 lg:pb-36 xl:px-12 2xl:px-16">
            {{ $slot }}
        </main>

        {{-- Barra de navegación inferior flotante, centrada en el mismo contenedor (ancho y relleno) que el contenido. --}}
        <div class="pointer-events-none fixed inset-x-0 bottom-[calc(0.5rem+env(safe-area-inset-bottom))] z-40 sm:bottom-5">
        <div class="mx-auto flex w-full max-w-[1440px] justify-center px-2 sm:px-6 lg:px-8 xl:px-12 2xl:px-16">
        <nav aria-label="Navegación principal"
            class="pointer-events-auto flex w-full min-w-0 max-w-full items-center gap-1 rounded-2xl border border-celeste bg-white p-1.5 shadow-xl shadow-texto/10 md:w-auto xl:p-2">

            <a href="{{ route('dashboard') }}" wire:navigate aria-label="{{ config('app.name', 'LearnUp') }}: inicio"
                class="mr-1.5 hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-azul-oscuro text-white md:flex {{ $enfoque }}">
                <x-icono nombre="libro" class="h-[22px] w-[22px]" />
            </a>

            @foreach ($enlaces as $enlace)
                @php $activo = request()->routeIs(...($enlace['activa'] ?? [$enlace['ruta']])); @endphp
                <a href="{{ route($enlace['ruta']) }}" wire:navigate wire:key="dock-{{ $enlace['ruta'] }}"
                    @if ($activo) aria-current="page" @endif
                    class="{{ $item }} {{ $activo ? $itemActivo : $itemInactivo }} {{ $enlace['secundario'] ? 'hidden md:flex' : '' }}">
                    <x-icono :nombre="$enlace['icono']" class="h-5 w-5 shrink-0" />
                    <span class="xl:hidden">{{ $enlace['corta'] }}</span>
                    <span class="hidden xl:inline">{{ $enlace['larga'] }}</span>
                    @if (($enlace['contador'] ?? 0) > 0)
                        <span class="absolute right-0.5 top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-azul-oscuro px-1 text-[11px] font-bold leading-none text-white ring-2 ring-white xl:static xl:ring-0">
                            {{ $enlace['contador'] > 99 ? '99+' : $enlace['contador'] }}<span class="sr-only"> entregas por calificar</span>
                        </span>
                    @endif
                </a>
            @endforeach

            <span class="mx-1.5 hidden h-7 w-px shrink-0 bg-celeste md:block" aria-hidden="true"></span>

            {{-- Campana: el componente de notificaciones existente (con su único polling).
                 Solo se ajusta desde aquí su área táctil y que el panel se abra hacia arriba. --}}
            <div class="relative flex flex-1 items-center justify-center md:flex-none [&>div>button:hover]:bg-fondo [&>div>button]:flex [&>div>button]:h-11 [&>div>button]:w-11 [&>div>button]:items-center [&>div>button]:justify-center [&>div>button]:rounded-xl [&>div>div]:bottom-full [&>div>div]:mb-3 [&>div>div]:mt-0 [&>div>div]:max-w-[calc(100vw-1rem)] [&>div>button]:focus-visible:outline-none [&>div>button]:focus-visible:ring-2 [&>div>button]:focus-visible:ring-cielo">

                <livewire:notificaciones.campanita />
            </div>

            {{-- Móvil: lo que no cabe va al menú "Más" (incluye perfil y cierre de sesión) --}}
            <div class="relative flex flex-1 md:hidden" x-data="{ abierto: false }" x-on:keydown.escape.window="if (abierto) { abierto = false; $refs.botonMas.focus() }" x-on:click.outside="abierto = false" x-on:focusout="if (! $el.contains($event.relatedTarget)) abierto = false">
                <button type="button" x-ref="botonMas" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto" aria-controls="menu-mas"
                    class="{{ $item }} {{ $itemInactivo }} w-full">
                    <x-icono nombre="puntos" class="h-5 w-5 shrink-0" />
                    <span>Más</span>
                </button>
                <div id="menu-mas" x-show="abierto" x-cloak x-transition.opacity.duration.150ms
                    class="absolute bottom-full right-0 mb-3 w-60 rounded-xl border border-celeste bg-white p-1.5 shadow-xl shadow-texto/15">
                    @foreach ($enlaces as $enlace)
                        @continue(! $enlace['secundario'])
                        <a href="{{ route($enlace['ruta']) }}" wire:navigate class="{{ $opcionMenu }}">
                            <x-icono :nombre="$enlace['icono']" class="h-[18px] w-[18px] shrink-0 text-slate-500" />
                            {{ $enlace['larga'] }}
                        </a>
                    @endforeach
                    <div class="my-1 h-px bg-celeste" aria-hidden="true"></div>
                    <div class="px-3 pb-1.5 pt-1">
                        <p class="break-words text-sm font-semibold text-texto">{{ $usuario->name }}</p>
                        <span class="mt-1 inline-flex items-center rounded-full bg-lavanda-fondo/40 px-2.5 py-0.5 text-xs font-bold text-lavanda-oscuro">Rol: Profesor</span>
                    </div>
                    <a href="{{ route('profile') }}" wire:navigate class="{{ $opcionMenu }}">
                        <x-icono nombre="persona" class="h-[18px] w-[18px] shrink-0 text-slate-500" />
                        Mi perfil
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="{{ $opcionMenu }}">
                            <x-icono nombre="flecha-derecha" class="h-[18px] w-[18px] shrink-0 text-slate-500" />
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>

            {{-- Tablet y escritorio: menú de usuario --}}
            <div class="relative hidden md:block" x-data="{ abierto: false }" x-on:keydown.escape.window="if (abierto) { abierto = false; $refs.botonUsuario.focus() }" x-on:click.outside="abierto = false" x-on:focusout="if (! $el.contains($event.relatedTarget)) abierto = false">
                <button type="button" x-ref="botonUsuario" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto" aria-controls="menu-usuario"
                    aria-label="Menú de usuario de {{ $usuario->name }}"
                    class="flex min-h-11 items-center gap-2 rounded-full bg-celeste py-1 pl-1.5 pr-2.5 transition hover:bg-celeste/70 motion-reduce:transition-none {{ $enfoque }}">
                    <img src="{{ $usuario->fotoUrl('2F5A78') }}" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover">
                    <span class="max-w-[7rem] truncate text-[13px] font-bold text-azul-oscuro lg:max-w-[9rem]" aria-hidden="true">{{ $primerNombre }}</span>
                    <x-icono nombre="flecha-abajo" class="h-4 w-4 text-azul-oscuro transition-transform motion-reduce:transition-none" x-bind:class="abierto && 'rotate-180'" />
                </button>
                <div id="menu-usuario" x-show="abierto" x-cloak x-transition.opacity.duration.150ms
                    class="absolute bottom-full right-0 mb-3 w-60 rounded-xl border border-celeste bg-white p-1.5 shadow-xl shadow-texto/15">
                    <div class="px-3 pb-2 pt-1.5">
                        <p class="break-words text-sm font-semibold text-texto">{{ $usuario->name }}</p>
                        <span class="mt-1 inline-flex items-center rounded-full bg-lavanda-fondo/40 px-2.5 py-0.5 text-xs font-bold text-lavanda-oscuro">Rol: Profesor</span>
                    </div>
                    <div class="mb-1 h-px bg-celeste" aria-hidden="true"></div>
                    <a href="{{ route('profile') }}" wire:navigate class="{{ $opcionMenu }}">
                        <x-icono nombre="persona" class="h-[18px] w-[18px] shrink-0 text-slate-500" />
                        Mi perfil
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="{{ $opcionMenu }}">
                            <x-icono nombre="flecha-derecha" class="h-[18px] w-[18px] shrink-0 text-slate-500" />
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </nav>
        </div>
        </div>

        {{-- Asistente (el mismo widget del layout general), subido para no tapar la barra inferior. --}}
        <div class="[&>div]:!bottom-[calc(5.5rem+env(safe-area-inset-bottom))]">
            <livewire:bot-sugerencias.widget />
        </div>
    </body>
</html>
