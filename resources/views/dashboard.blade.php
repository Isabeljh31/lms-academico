<x-app-layout>
    {{-- El estudiante muestra su propio <h1> "Panel" dentro del contenido
         (igual que Cursos, Tareas, etc.), así que la barra superior va
         vacía para él; Profesor/Administrador conservan el título en la
         barra superior, sin cambios. --}}
    <x-slot name="header">@unless (auth()->user()->hasRole('estudiante')) Panel @endunless</x-slot>

    @if (auth()->user()->hasRole('estudiante'))
        {{-- Rediseño visual del rol estudiante (ver ARQUITECTURA.md). El
             panel de profesor/administrador que sigue abajo no se tocó. --}}
        <div class="py-8">
            {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                @php
                    $comunicadosEstudiante = \App\Models\Comunicado::activos()
                        ->paraRol('estudiante')
                        ->latest()
                        ->take(2)
                        ->get();
                @endphp

                @if ($comunicadosEstudiante->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($comunicadosEstudiante as $comunicado)
                            <div class="rounded-xl border p-4 shadow-sm flex items-start gap-3.5 {{ $comunicado->prioridad === 'urgente' ? 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-900 text-red-900 dark:text-red-200' : 'bg-sky-50 dark:bg-sky-950/30 border-sky-200 dark:border-sky-900 text-sky-900 dark:text-sky-200' }}" wire:key="banner-com-est-{{ $comunicado->id }}">
                                <span class="text-xl shrink-0 mt-0.5">{{ $comunicado->prioridad === 'urgente' ? '🚨' : '📢' }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $comunicado->prioridad === 'urgente' ? 'bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-100' : 'bg-sky-200 text-sky-800 dark:bg-sky-800 dark:text-sky-100' }}">
                                            Comunicado Oficial {{ $comunicado->prioridad === 'urgente' ? 'Urgente' : '' }}
                                        </span>
                                        <span class="text-xs opacity-75">{{ $comunicado->created_at->diffForHumans() }}</span>
                                    </div>
                                    <h4 class="font-bold text-sm mt-1">{{ $comunicado->titulo }}</h4>
                                    <p class="text-xs opacity-90 mt-0.5 whitespace-pre-line">{{ $comunicado->contenido }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <livewire:estudiante.panel-inicio />
            </div>
        </div>
    @else
        {{-- Vista de profesor/administrador --}}
        @php
            $rolActual = auth()->user()->rolPrincipal() ?? 'profesor';
            $comunicadosActivos = \App\Models\Comunicado::activos()
                ->paraRol($rolActual)
                ->latest()
                ->take(3)
                ->get();
        @endphp

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                {{-- Banner de Comunicados Institucionales del sistema --}}
                @if ($comunicadosActivos->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($comunicadosActivos as $comunicado)
                            <div class="rounded-xl border p-4 shadow-sm flex items-start gap-3.5 {{ $comunicado->prioridad === 'urgente' ? 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-900 text-red-900 dark:text-red-200' : 'bg-sky-50 dark:bg-sky-950/30 border-sky-200 dark:border-sky-900 text-sky-900 dark:text-sky-200' }}" wire:key="banner-com-{{ $comunicado->id }}">
                                <span class="text-xl shrink-0 mt-0.5">{{ $comunicado->prioridad === 'urgente' ? '🚨' : '📢' }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $comunicado->prioridad === 'urgente' ? 'bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-100' : 'bg-sky-200 text-sky-800 dark:bg-sky-800 dark:text-sky-100' }}">
                                            Comunicado Institucional {{ $comunicado->prioridad === 'urgente' ? 'Urgente' : '' }}
                                        </span>
                                        <span class="text-xs opacity-75">{{ $comunicado->created_at->diffForHumans() }}</span>
                                    </div>
                                    <h4 class="font-bold text-sm mt-1">{{ $comunicado->titulo }}</h4>
                                    <p class="text-xs opacity-90 mt-0.5 whitespace-pre-line">{{ $comunicado->contenido }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="bg-white dark:bg-stone-800 overflow-hidden shadow-sm rounded-xl border border-arena-medio dark:border-stone-700">
                    <div class="p-6 text-stone-700 dark:text-stone-200">
                        <p class="text-lg">Hola, <span class="font-semibold">{{ auth()->user()->name }}</span> 👋</p>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                            Rol: <x-badge-estado :estado="auth()->user()->rolPrincipal() ?? 'estudiante'" />
                        </p>
                    </div>
                </div>

                {{-- Para el profesor: resumen de cursos asignados y pendientes de calificar --}}
                @if (auth()->user()->hasRole('profesor'))
                    @php
                        $misCursos = auth()->user()->cursosComoProfesor()->with(['tareas.entregas', 'inscripciones'])->get();
                        $totalEntregasPendientes = $misCursos->flatMap->tareas->flatMap->entregas->where('estado', '!=', 'calificado')->count();
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                            <h3 class="text-xs font-bold uppercase text-stone-500 mb-2">Mis Cursos Asignados ({{ $misCursos->count() }})</h3>
                            <div class="space-y-2">
                                @forelse ($misCursos as $curso)
                                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-stone-50 dark:bg-stone-700/50">
                                        <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="text-sm font-semibold text-menta-oscuro dark:text-menta-base hover:underline truncate">
                                            {{ $curso->nombre }}
                                        </a>
                                        <span class="text-xs text-stone-500">{{ $curso->inscripciones->count() }} alumnos</span>
                                    </div>
                                @empty
                                    <p class="text-xs text-stone-400">No tienes cursos asignados aún.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm flex flex-col justify-between">
                            <div>
                                <h3 class="text-xs font-bold uppercase text-stone-500 mb-2">Bandeja de Calificaciones Docente</h3>
                                <div class="flex items-center gap-3 mt-3">
                                    <span class="text-3xl font-black {{ $totalEntregasPendientes > 0 ? 'text-terracota-oscuro' : 'text-exito-oscuro' }}">
                                        {{ $totalEntregasPendientes }}
                                    </span>
                                    <span class="text-xs text-stone-500">
                                        {{ $totalEntregasPendientes === 1 ? 'entrega pendiente de calificación' : 'entregas pendientes de calificación' }}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-arena-medio/40">
                                <a href="{{ route('cursos.index') }}" wire:navigate class="text-xs font-semibold text-menta-oscuro dark:text-menta-base hover:underline">
                                    Ir a mis cursos para calificar &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    @endif
</x-app-layout>
