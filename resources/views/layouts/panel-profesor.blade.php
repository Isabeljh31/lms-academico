@php
    // Layout del Profesor (todas sus pantallas: layouts/app.blade.php lo usa para un profesor
    // "puro", ver PanelProfesorLayout::aplicaA). Diseño editorial: papel crema, barra superior
    // con el logo en serif, navegación en píldoras y el menú de usuario a la derecha.
    $usuario = auth()->user();
    // Como layout de una página Livewire no pasa por PanelProfesorLayout: el contador se calcula aquí
    // (solo al cargar la página; las acciones de Livewire no vuelven a pintar el layout).
    $porCalificar ??= app(\App\Services\Reportes\PanelProfesor::class)->totalPorCalificar($usuario);
    $primerNombre = \Illuminate\Support\Str::of((string) $usuario->name)->trim()->before(' ')->value() ?: (string) $usuario->name;
    $inicialesUsuario = collect(preg_split('/\s+/', trim((string) $usuario->name)) ?: [])
        ->filter()->take(2)->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)))->join('');
    // Enlace (firmado si el disco es Supabase) de la foto subida; null si no tiene foto.
    $fotoUsuario = \App\Services\Multimedia\SubidorArchivos::enlace($usuario->avatar_url);
    $enfoque = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cielo focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--mint)]';

    $enlaces = [
        ['ruta' => 'dashboard', 'etiqueta' => 'Inicio'],
        ['ruta' => 'cursos.index', 'activa' => ['cursos.*', 'tareas.*', 'quizzes.*', 'debates.*', 'asistencia.*', 'docente.asistencia.*'], 'etiqueta' => 'Mis cursos'],
        ['ruta' => 'docente.por-calificar', 'etiqueta' => 'Por calificar', 'contador' => $porCalificar],
        ['ruta' => 'evento-calendario.index', 'etiqueta' => 'Calendario'],
        ['ruta' => 'mensajeria.selector', 'activa' => ['mensajeria.*'], 'etiqueta' => 'Mensajería'],
        ['ruta' => 'reportes.dashboard', 'activa' => ['reportes.*'], 'etiqueta' => 'Reportes'],
        ['ruta' => 'bot.resumen', 'activa' => ['bot.*'], 'etiqueta' => 'Resumen del bot'],
    ];

    $pildora = "relative inline-flex min-h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3.5 text-[13.5px] font-medium transition-colors motion-reduce:transition-none {$enfoque}";
    $pildoraActiva = 'bg-white text-[var(--ink)] shadow-[0_1px_2px_rgba(29,28,25,0.12)]';
    $pildoraInactiva = 'text-[var(--ink)]/75 hover:bg-white/60 hover:text-[var(--ink)]';
    $opcionMenu = "flex min-h-11 w-full items-center gap-2.5 rounded-lg px-3 text-left text-sm font-medium text-[var(--ink)] transition hover:bg-[var(--mint)] motion-reduce:transition-none {$enfoque} focus-visible:ring-offset-0";
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? (request()->routeIs('dashboard') ? 'Panel — ' : '').config('app.name', 'LearnUp') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|instrument-serif:400,400i|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="tema-profesor min-h-screen overflow-x-clip bg-[var(--mint)] font-sans text-[var(--ink)] antialiased">
        <header class="sticky top-0 z-30 border-b border-[var(--sec2)] bg-[var(--mint)]/95 backdrop-blur supports-[backdrop-filter]:bg-[var(--mint)]/85">
            <div class="mx-auto flex h-[68px] w-full max-w-[1320px] items-center gap-4 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('dashboard') }}" wire:navigate class="shrink-0 rounded-md font-serif text-[28px] leading-none tracking-[-0.01em] text-[var(--ink)] {{ $enfoque }}">LearnUp</a>

                {{-- Navegación: en escritorio ancho va en la barra; por debajo, en una segunda fila desplazable. --}}
                <nav role="navigation" aria-label="Navegación principal" class="contents">
                    <div id="navegacion-profesor" class="order-last -mx-1 flex min-w-0 items-center gap-0.5 overflow-x-auto rounded-xl bg-[var(--sec2)]/70 p-1 [scrollbar-width:none] max-xl:fixed max-xl:inset-x-0 max-xl:top-[68px] max-xl:mx-0 max-xl:rounded-none max-xl:border-b max-xl:border-[var(--sec2)] max-xl:bg-[var(--mint)] max-xl:px-3 max-xl:py-1.5 xl:order-none xl:ml-4 [&::-webkit-scrollbar]:hidden">
                        @foreach ($enlaces as $enlace)
                            @php
                                $activo = request()->routeIs(...($enlace['activa'] ?? [$enlace['ruta']]));
                                $contador = $enlace['contador'] ?? 0;
                            @endphp
                            <a href="{{ route($enlace['ruta']) }}" wire:navigate wire:key="nav-{{ $enlace['ruta'] }}"
                                aria-label="{{ $enlace['etiqueta'] }}{{ $contador > 0 ? ', '.($contador === 1 ? '1 entrega' : $contador.' entregas').' por calificar' : '' }}"
                                @if ($activo) aria-current="page" @endif
                                class="{{ $pildora }} {{ $activo ? $pildoraActiva : $pildoraInactiva }}">
                                {{ $enlace['etiqueta'] }}
                                @if ($contador > 0)
                                    <span class="rounded-full bg-[var(--ink)] px-1.5 py-px font-mono text-[10.5px] font-medium leading-4 tabular-nums text-white" aria-hidden="true">{{ $contador > 99 ? '99+' : $contador }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </nav>

                <div class="ml-auto flex shrink-0 items-center gap-2.5">
                    {{-- Campana: el componente de notificaciones existente (con su único polling); solo se ajusta su botón. --}}
                    <div class="relative flex items-center [&>div>button]:flex [&>div>button]:h-10 [&>div>button]:w-10 [&>div>button]:items-center [&>div>button]:justify-center [&>div>button]:rounded-xl [&>div>button]:bg-[var(--sec2)]/70 [&>div>button]:!text-[var(--ink)] [&>div>button]:transition-colors [&>div>button:hover]:bg-[var(--sec2)] [&>div>button>svg]:h-[18px] [&>div>button>svg]:w-[18px] [&>div>div]:max-w-[calc(100vw-1rem)]">
                        <livewire:compartido.notificaciones.campanita />
                    </div>

                    <div class="relative" x-data="{ abierto: false }" x-on:keydown.escape.window="if (abierto) { abierto = false; $refs.botonUsuario.focus() }" x-on:click.outside="abierto = false" x-on:focusout="if (! $el.contains($event.relatedTarget)) abierto = false">
                        <button type="button" x-ref="botonUsuario" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto" aria-controls="menu-usuario"
                            aria-label="Menú de usuario de {{ $usuario->name }}"
                            class="flex items-center gap-2.5 rounded-xl py-1 pl-1 pr-1 text-left transition-colors hover:bg-[var(--sec2)]/60 motion-reduce:transition-none sm:pr-2.5 {{ $enfoque }}">
                            {{-- Foto de perfil si la tiene (iniciales si no); se refresca al guardar el perfil (evento profile-updated). --}}
                            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-[var(--p700)] font-serif text-[15px] text-white" aria-hidden="true"
                                x-data="{{ json_encode(['foto' => $fotoUsuario]) }}"
                                x-on:profile-updated.window="if ($event.detail.avatar_url && ! $event.detail.avatar_url.includes('ui-avatars.com')) foto = $event.detail.avatar_url">
                                <img x-show="foto" x-bind:src="foto" @if ($fotoUsuario) src="{{ $fotoUsuario }}" @else style="display: none" @endif alt="" class="h-full w-full object-cover">
                                <span x-show="! foto" @if ($fotoUsuario) style="display: none" @endif>{{ $inicialesUsuario }}</span>
                            </span>
                            <span class="hidden leading-tight sm:block" aria-hidden="true">
                                <span class="block text-[13px] font-semibold text-[var(--ink)]" x-data="{{ json_encode(['nombre' => $primerNombre]) }}" x-text="nombre"
                                    x-on:profile-updated.window="if ($event.detail.name) nombre = $event.detail.name.trim().split(/\s+/)[0]">{{ $primerNombre }}</span>
                                <span class="block text-[11.5px] text-[var(--muted)]">Profesor</span>
                            </span>
                        </button>
                        <div id="menu-usuario" x-show="abierto" x-cloak x-transition.opacity.duration.150ms
                            class="absolute right-0 top-full z-40 mt-2 w-60 rounded-xl border border-[var(--sec2)] bg-white p-1.5 shadow-xl shadow-black/10">
                            <div class="px-3 pb-2 pt-1.5">
                                <p class="break-words text-sm font-semibold text-[var(--ink)]">{{ $usuario->name }}</p>
                                <span class="rotulo mt-1 inline-flex items-center rounded-full bg-[var(--mint)] px-2.5 py-0.5 text-[var(--p700)]">Rol: Profesor</span>
                            </div>
                            <div class="mb-1 h-px bg-[var(--sec2)]" aria-hidden="true"></div>
                            <a href="{{ route('profile') }}" wire:navigate class="{{ $opcionMenu }}">
                                <x-icono nombre="persona" class="h-[18px] w-[18px] shrink-0 text-[var(--muted)]" />
                                Mi perfil
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="{{ $opcionMenu }}">
                                    <x-icono nombre="flecha-derecha" class="h-[18px] w-[18px] shrink-0 text-[var(--muted)]" />
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Contenido: hasta 1320 px, con relleno lateral que crece con la pantalla (y espacio para la fila de navegación por debajo de xl). --}}
        <main class="mx-auto w-full max-w-[1320px] px-4 pb-16 pt-[4.5rem] sm:px-6 lg:px-8 xl:pt-8">
            @isset($header)
                @if (trim((string) $header) !== '')
                    <h1 class="mb-6 text-[34px] text-[var(--ink)]">{{ $header }}</h1>
                @endif
            @endisset
            {{ $slot }}
        </main>

        <livewire:bot-sugerencias.widget />
    </body>
</html>
