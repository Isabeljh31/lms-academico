{{-- Pestañas del curso dentro de la banda del detalle: la misma barra de píldoras que las demás pantallas. La activa la fija EncabezadoCurso::$activa. --}}
<div class="border-t border-[#ECE6DA] bg-[#F7F4EE] px-5 py-3">
    @include('livewire.profesor.cursos._pestanas-pildora', ['curso' => $curso, 'activa' => $activa])
</div>
