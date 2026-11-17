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
    $inicialesUsuario = collect(preg_split('/\s+/', trim((string) $usuario->name)) ?: [])
        ->filter()->take(2)->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)))->join('');
    $enfoque = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cielo focus-visible:ring-offset-2';
    // Foco visible sobre la barra verde oscuro: contorno de 3 px en lima (solo sobre fondo oscuro).
    $enfoqueBarra = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--lime)]';

    // corta: etiqueta del activo en pantallas angostas; larga: barra completa (escritorio ancho).
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

    // Barra verde (--p900): texto --claro; el activo es una píldora blanca. Por debajo de 1400 px
    // solo el activo muestra su etiqueta (las demás quedan para lectores de pantalla).
    $item = "relative flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl px-3 py-[11px] text-sm font-semibold leading-none transition-colors motion-reduce:transition-none min-[1400px]:px-3.5 {$enfoqueBarra}";
    $itemInactivo = 'text-[var(--claro)] hover:bg-white/[.08] hover:text-white';
    $itemActivo = 'bg-white text-[var(--p900)] shadow-[0_2px_8px_rgba(0,0,0,0.25)]';
    $opcionMenu = "flex min-h-11 w-full items-center gap-2.5 rounded-lg px-3 text-left text-sm font-medium text-texto transition hover:bg-[var(--mint)] motion-reduce:transition-none {$enfoque} focus-visible:ring-offset-0";
@endphp

<!DOCTYPE html>
{{-- scroll-padding: al navegar con el teclado, lo enfocado no queda tapado por la barra fija. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pb-[130px]">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Panel — '.config('app.name', 'LearnUp') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Paleta del Profesor: solo dentro de .tema-profesor (no es global). --}}
        <style>
            .tema-profesor {
                --p900: #093B3B;
                --p800: #2A5C5B;
                --p700: #136E61;
                --lime: #EAF141;
                --sec2: #CCDDBE;
                --mint: #E8F6F3;
                --ink: #1A202C;
                --claro: #CFE3DE;
                --ok900: #3B6506;
                --ok100: #F5FCD2;
                --warn800: #936312;
                --warn200: #FFEFB0;
                --warn100: #FFF8D7;
                --err700: #B71112;
                --err100: #FFE7D3;
                --inf700: #2A60B7;
                --inf100: #DCF3FF;
            }
        </style>
    </head>
    {{-- Fondo de página --mint para que las tarjetas blancas resalten; overflow-x-clip permite bandas a todo el ancho. --}}
    <body class="tema-profesor min-h-screen overflow-x-clip bg-[var(--mint)] font-sans text-[var(--ink)] antialiased">
        {{-- Contenido: hasta 1440 px, con relleno lateral que crece con la pantalla y 160 px abajo para la barra. --}}
        <main class="mx-auto w-full max-w-[1440px] px-4 pb-[calc(130px+env(safe-area-inset-bottom))] pt-7 sm:px-6 lg:px-8 xl:px-12 2xl:px-16">
            {{ $slot }}
        </main>

        {{-- Degradado detrás de la barra: el contenido se desvanece antes de llegar a ella. --}}
        <div class="pointer-events-none fixed inset-x-0 bottom-0 z-30 h-[130px] bg-[linear-gradient(to_top,var(--mint)_35%,rgba(232,246,243,0))]" aria-hidden="true"></div>

        {{-- Barra de navegación inferior flotante, centrada en el mismo contenedor (ancho y relleno) que el contenido. --}}
        <div class="pointer-events-none fixed inset-x-0 bottom-[calc(0.5rem+env(safe-area-inset-bottom))] z-40 sm:bottom-[22px]">
        <div class="mx-auto flex w-full max-w-[1440px] justify-center px-2 sm:px-6 lg:px-8 xl:px-12 2xl:px-16">
        <nav role="navigation" aria-label="Navegación principal"
            class="pointer-events-auto flex w-full min-w-0 max-w-full items-center gap-0.5 rounded-[22px] bg-[var(--p900)] p-2 shadow-[0_16px_40px_rgba(9,59,59,0.4),0_0_0_1px_rgba(255,255,255,0.06)] md:w-auto">

            {{-- Si no caben, los enlaces se desplazan en horizontal; el relleno deja ver el contorno de foco. --}}
            <div class="-m-1 flex min-w-0 flex-1 items-center gap-0.5 overflow-x-auto scroll-smooth p-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($enlaces as $enlace)
                    @php
                        $activo = request()->routeIs(...($enlace['activa'] ?? [$enlace['ruta']]));
                        $contador = $enlace['contador'] ?? 0;
                    @endphp
                    <a href="{{ route($enlace['ruta']) }}" wire:navigate wire:key="dock-{{ $enlace['ruta'] }}"
                        aria-label="{{ $enlace['larga'] }}{{ $contador > 0 ? ', '.($contador === 1 ? '1 entrega' : $contador.' entregas').' por calificar' : '' }}"
                        @if ($activo) aria-current="page" @endif
                        class="{{ $item }} {{ $activo ? $itemActivo : $itemInactivo }} {{ $enlace['secundario'] ? 'hidden md:flex' : '' }}">
                        <x-icono :nombre="$enlace['icono']" class="h-[18px] w-[18px] shrink-0" />
                        <span class="{{ $activo ? 'min-[1400px]:hidden' : 'sr-only min-[1400px]:hidden' }}">{{ $enlace['corta'] }}</span>
                        <span class="hidden min-[1400px]:inline">{{ $enlace['larga'] }}</span>
                        @if ($contador > 0)
                            <span class="absolute right-0 top-0 rounded-full px-2 py-0.5 text-[11.5px] font-extrabold leading-4 tabular-nums min-[1400px]:static {{ $activo ? 'bg-[var(--p900)] text-white' : 'bg-white text-[var(--p900)]' }}" aria-hidden="true">
                                {{ $contador > 99 ? '99+' : $contador }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>

            {{-- Campana: el componente de notificaciones existente (con su único polling).
                 Solo se ajusta desde aquí su área táctil, su color sobre la barra y que el panel se abra hacia arriba. --}}
            <div class="relative flex shrink-0 items-center justify-center [&>div>button:hover]:!text-white [&>div>button:hover]:bg-white/[.08] [&>div>button]:flex [&>div>button]:h-10 [&>div>button]:w-10 [&>div>button>svg]:h-[18px] [&>div>button>svg]:w-[18px] [&>div>button]:items-center [&>div>button]:justify-center [&>div>button]:rounded-xl [&>div>button]:!text-[var(--claro)] [&>div>button]:transition-colors [&>div>div]:bottom-full [&>div>div]:mb-3 [&>div>div]:mt-0 [&>div>div]:max-w-[calc(100vw-1rem)] [&>div>button]:focus-visible:outline [&>div>button]:focus-visible:outline-[3px] [&>div>button]:focus-visible:outline-offset-2 [&>div>button]:focus-visible:outline-[color:var(--lime)]">

                <livewire:notificaciones.campanita />
            </div>

            <span class="mx-1 h-[26px] w-px shrink-0 bg-white/[.18] md:mx-2" aria-hidden="true"></span>

            {{-- Móvil: lo que no cabe va al menú "Más" (incluye perfil y cierre de sesión) --}}
            <div class="relative flex shrink-0 md:hidden" x-data="{ abierto: false }" x-on:keydown.escape.window="if (abierto) { abierto = false; $refs.botonMas.focus() }" x-on:click.outside="abierto = false" x-on:focusout="if (! $el.contains($event.relatedTarget)) abierto = false">
                <button type="button" x-ref="botonMas" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto" aria-controls="menu-mas"
                    class="{{ $item }} {{ $itemInactivo }}">
                    <x-icono nombre="puntos" class="h-[18px] w-[18px] shrink-0" />
                    <span class="sr-only">Más</span>
                </button>
                <div id="menu-mas" x-show="abierto" x-cloak x-transition.opacity.duration.150ms
                    class="absolute bottom-full right-0 mb-3 w-60 rounded-xl border border-[var(--mint)] bg-white p-1.5 shadow-xl shadow-black/15">
                    @foreach ($enlaces as $enlace)
                        @continue(! $enlace['secundario'])
                        <a href="{{ route($enlace['ruta']) }}" wire:navigate class="{{ $opcionMenu }}">
                            <x-icono :nombre="$enlace['icono']" class="h-[18px] w-[18px] shrink-0 text-slate-500" />
                            {{ $enlace['larga'] }}
                        </a>
                    @endforeach
                    <div class="my-1 h-px bg-[var(--mint)]" aria-hidden="true"></div>
                    <div class="px-3 pb-1.5 pt-1">
                        <p class="break-words text-sm font-semibold text-texto">{{ $usuario->name }}</p>
                        <span class="mt-1 inline-flex items-center rounded-full bg-[var(--mint)] px-2.5 py-0.5 text-xs font-bold text-[var(--p700)]">Rol: Profesor</span>
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

            {{-- Tablet y escritorio: menú de usuario con avatar de iniciales --}}
            <div class="relative hidden md:block" x-data="{ abierto: false }" x-on:keydown.escape.window="if (abierto) { abierto = false; $refs.botonUsuario.focus() }" x-on:click.outside="abierto = false" x-on:focusout="if (! $el.contains($event.relatedTarget)) abierto = false">
                <button type="button" x-ref="botonUsuario" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto" aria-controls="menu-usuario"
                    aria-label="Menú de usuario de {{ $usuario->name }}"
                    class="flex items-center gap-2 whitespace-nowrap rounded-xl bg-white/[.08] py-[5px] pl-[5px] pr-3 font-semibold text-white transition-colors hover:bg-white/[.14] motion-reduce:transition-none {{ $enfoqueBarra }}">
                    <span class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-[9px] bg-[var(--p700)] text-[11px] font-extrabold" aria-hidden="true">{{ $inicialesUsuario }}</span>
                    <span class="text-sm" aria-hidden="true">{{ $primerNombre }}</span>
                    <x-icono nombre="flecha-abajo" class="h-[16px] w-[16px] text-[var(--claro)] transition-transform motion-reduce:transition-none" x-bind:class="abierto && 'rotate-180'" />
                </button>
                <div id="menu-usuario" x-show="abierto" x-cloak x-transition.opacity.duration.150ms
                    class="absolute bottom-full right-0 mb-3 w-60 rounded-xl border border-[var(--mint)] bg-white p-1.5 shadow-xl shadow-black/15">
                    <div class="px-3 pb-2 pt-1.5">
                        <p class="break-words text-sm font-semibold text-texto">{{ $usuario->name }}</p>
                        <span class="mt-1 inline-flex items-center rounded-full bg-[var(--mint)] px-2.5 py-0.5 text-xs font-bold text-[var(--p700)]">Rol: Profesor</span>
                    </div>
                    <div class="mb-1 h-px bg-[var(--mint)]" aria-hidden="true"></div>
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
