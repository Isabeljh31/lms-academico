@props([
    'curso',
    // Clave de la pestaña actual (contenido, actividades, evaluacion, matricula, grupos, debates, asistencia, libro, mensajeria).
    'activa',
])

{{-- Barra de pestañas del curso para el Profesor (las pantallas compartidas no la muestran a Supervisor ni Estudiante). --}}
@if (\App\View\Components\PanelProfesorLayout::aplicaA(auth()->user()))
    @include('livewire.profesor.cursos._pestanas-pildora', ['curso' => $curso, 'activa' => $activa])
@endif
