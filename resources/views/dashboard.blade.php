{{-- Panel del Profesor: layout propio con barra inferior (PanelProfesorLayout). Estudiante y
     Supervisor siguen por la rama de siempre, sin cambios. --}}
@if (auth()->user()->hasRole('profesor') && ! auth()->user()->hasAnyRole(['supervisor', 'estudiante']))
<x-panel-profesor-layout>
    <livewire:profesor.panel />
</x-panel-profesor-layout>
@else
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
                    $comunicadosLeidos = \App\Models\Comunicado::idsLeidosPor(auth()->user());
                @endphp

                @if ($comunicadosEstudiante->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($comunicadosEstudiante as $comunicado)
                            @php $leido = $comunicadosLeidos->contains($comunicado->id); @endphp
                            <a href="{{ route('comunicados.show', $comunicado) }}" wire:navigate
                                class="block rounded-xl border p-4 shadow-sm flex items-start gap-3.5 {{ $leido ? 'opacity-60' : '' }} {{ $comunicado->prioridad === 'urgente' ? 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-900 text-red-900 dark:text-red-200' : 'bg-sky-50 dark:bg-sky-950/30 border-sky-200 dark:border-sky-900 text-sky-900 dark:text-sky-200' }}" wire:key="banner-com-est-{{ $comunicado->id }}">
                                <span class="shrink-0 mt-0.5"><x-icono :nombre="$comunicado->prioridad === 'urgente' ? 'alerta' : 'megafono'" class="h-5 w-5" /></span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $comunicado->prioridad === 'urgente' ? 'bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-100' : 'bg-sky-200 text-sky-800 dark:bg-sky-800 dark:text-sky-100' }}">
                                            Comunicado Oficial {{ $comunicado->prioridad === 'urgente' ? 'Urgente' : '' }}
                                        </span>
                                        <span class="text-xs opacity-75">{{ $comunicado->created_at->diffForHumans() }}</span>
                                        @unless ($leido)
                                            <span class="h-1.5 w-1.5 rounded-full bg-current shrink-0"></span>
                                        @endunless
                                    </div>
                                    <h4 class="font-bold text-sm mt-1">{{ $comunicado->titulo }}</h4>
                                    <p class="text-xs opacity-90 mt-0.5 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($comunicado->contenido, 140) }}</p>
                                    <span class="text-xs font-semibold underline mt-1 inline-block">Leer más</span>
                                </div>
                            </a>
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
@endif
