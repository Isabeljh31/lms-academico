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
                                <span class="shrink-0 mt-0.5"><x-icono :nombre="$comunicado->prioridad === 'urgente' ? 'alerta' : 'megafono'" class="h-5 w-5" /></span>
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
        {{-- Profesor / Supervisor: ERS RF-88 y RF-90 --}}
        @if (auth()->user()->hasAnyRole(['profesor', 'supervisor']))
            <livewire:compartido.reportes.panel-gestion />
        @else
            <div class="py-8">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <x-gestion.vacio icono="info" :titulo="'Hola, '.auth()->user()->name"
                        descripcion="Tu cuenta aún no tiene un rol asignado. Contacta al Supervisor." />
                </div>
            </div>
        @endif
    @endif
</x-app-layout>
