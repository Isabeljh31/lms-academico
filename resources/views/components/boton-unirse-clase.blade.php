{{--
    Botón «Unirse a la clase» de la agenda del Estudiante (calendario, «Tus
    próximas clases»). Se incluye con @include y recibe $sesion con su curso
    ya cargado (sin consultas extra). La agenda solo lista clases de cursos con
    matrícula activa (ColoresCursos::consultaCursosDe), así que no se vuelve a
    consultar la matrícula aquí.

    - Clase no remota o ya terminada: no muestra nada.
    - Sin enlace válido (ni de la clase ni del curso): «El profesor aún no publicó el enlace».
    - Antes de la ventana (15 min antes del inicio): la hora desde la que estará disponible.
    - Ventana abierta: el botón, en otra pestaña y sin exponer la URL como texto.
    La URL se calcula al renderizar; nunca es una propiedad pública de Livewire.
--}}
@php
    $enlacesRemotos = app(\App\Services\Asistencia\EnlacesClaseRemota::class);
    $ventanaClase = $enlacesRemotos->esRemota($sesion) ? $enlacesRemotos->ventana($sesion) : null;
    $enlaceUnirse = $ventanaClase === 'abierta' ? $enlacesRemotos->enlaceParaUnirse($sesion) : null;
    $tieneEnlace = $ventanaClase !== null && $ventanaClase !== 'terminada' && ($enlaceUnirse !== null || $enlacesRemotos->resolver($sesion) !== null);
@endphp

@if ($ventanaClase !== null && $ventanaClase !== 'terminada')
    <span class="mt-1 block" data-unirse-clase="{{ $sesion->id }}">
        @if ($enlaceUnirse !== null)
            <a href="{{ $enlaceUnirse }}" target="_blank" rel="noopener noreferrer"
                aria-label="Unirse a la clase de {{ $sesion->curso->nombre }} (se abre en otra pestaña)"
                class="inline-flex items-center gap-1.5 rounded-md bg-azul-fondo px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-azul-oscuro focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-fondo focus-visible:ring-offset-2 motion-reduce:transition-none dark:focus-visible:ring-offset-azul-superficie-oscura">
                <x-icono nombre="enlace" class="h-3.5 w-3.5" /> Unirse a la clase
            </a>
        @elseif (! $tieneEnlace)
            <span class="text-xs text-slate-500 dark:text-slate-400">El profesor aún no publicó el enlace</span>
        @else
            <span class="text-xs text-slate-500 dark:text-slate-400">El enlace estará disponible 15 minutos antes ({{ $sesion->fecha->copy()->subMinutes(\App\Services\Asistencia\EnlacesClaseRemota::MINUTOS_ANTES)->format('H:i') }})</span>
        @endif
    </span>
@endif
