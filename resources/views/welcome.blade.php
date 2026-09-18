<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'LearnUp') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans bg-fondo text-texto">
        {{-- Encabezado --}}
        <header class="border-b border-celeste bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <a href="/" wire:navigate class="flex items-center gap-2">
                    <x-application-logo class="h-8 w-auto fill-current text-azul-oscuro" />
                    <span class="font-semibold text-lg">{{ config('app.name', 'LearnUp') }}</span>
                </a>

                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </div>
        </header>

        {{-- Hero --}}
        <section class="relative overflow-hidden bg-gradient-to-br from-imperial via-azul-fondo to-cielo text-white">
            <svg class="absolute -right-16 -top-16 h-80 w-80 text-white/10 pointer-events-none" fill="none" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="99" stroke="currentColor" stroke-width="1.5" />
                <circle cx="100" cy="100" r="70" stroke="currentColor" stroke-width="1.5" />
                <circle cx="100" cy="100" r="41" stroke="currentColor" stroke-width="1.5" />
            </svg>

            <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 text-center">
                <p class="text-white/70 text-sm font-semibold tracking-wide uppercase">Plataforma académica</p>
                <h1 class="mt-3 text-4xl sm:text-5xl font-bold tracking-tight max-w-2xl mx-auto">
                    Aprende, enseña y organiza todo en un solo lugar
                </h1>
                <p class="mt-4 text-white/80 max-w-xl mx-auto">
                    Cursos, tareas, quizzes, calendario y un asistente con IA
                    <br>
                        Todo lo que necesita tu institución para dar seguimiento al progreso académico.
                </p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" wire:navigate
                            class="inline-flex items-center gap-1.5 bg-white text-azul-oscuro font-semibold rounded-lg px-5 py-2.5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition">
                            Comenzar gratis <x-icono nombre="flecha-derecha" class="h-4 w-4" />
                        </a>
                    @endif
                    <a href="{{ route('login') }}" wire:navigate
                        class="inline-flex items-center gap-1.5 bg-white/15 hover:bg-white/25 font-semibold rounded-lg px-5 py-2.5 transition">
                        Iniciar sesión
                    </a>
                </div>
            </div>
        </section>

        {{-- Características --}}
        <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
            <div class="text-center max-w-xl mx-auto mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold">Todo lo que necesitas para aprender mejor</h2>
                <p class="mt-3 text-slate-500">Una sola plataforma para estudiantes, profesores y administradores.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ([
                    ['icono' => 'libro', 'titulo' => 'Cursos y contenidos', 'texto' => 'Unidades, materiales y todo el contenido de cada curso organizado en un solo lugar.'],
                    ['icono' => 'tarea', 'titulo' => 'Tareas y calificaciones', 'texto' => 'Entrega trabajos, recibe retroalimentación y consulta tus notas al instante.'],
                    ['icono' => 'calendario', 'titulo' => 'Calendario integrado', 'texto' => 'Fechas límite, recordatorios y eventos propios, combinados en un solo calendario.'],
                    ['icono' => 'chat', 'titulo' => 'Asistente con IA', 'texto' => 'Un bot que responde tus dudas y te avisa si te estás atrasando en un curso.'],
                ] as $caracteristica)
                    <div class="bg-white rounded-xl border border-celeste p-6">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-azul-oscuro/10 text-azul-oscuro">
                            <x-icono :nombre="$caracteristica['icono']" class="h-5 w-5" />
                        </span>
                        <h3 class="mt-4 font-semibold">{{ $caracteristica['titulo'] }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ $caracteristica['texto'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Pie de página --}}
        <footer class="border-t border-celeste">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-center text-sm text-slate-400">
                &copy; {{ now()->year }} {{ config('app.name', 'LearnUp') }}. Todos los derechos reservados.
            </div>
        </footer>
    </body>
</html>
