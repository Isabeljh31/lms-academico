<x-gestion.modal titulo="Asignar curso"
    cerrar="$set('mostrarModalAsignar', false)"
    descripcion="{{ $asignarPorCurso
        ? 'Elige quién será el profesor titular de '.($cursoSeleccionado?->nombre ?? 'este curso').'.'
        : ($profesorSeleccionado?->name ?? 'El profesor elegido').' pasará a ser el profesor titular del curso elegido.' }}">
    @if ($asignarPorCurso)
        <x-gestion.campo etiqueta="Profesor" nombre="profesorParaAsignarId" requerido>
            <x-gestion.control tipo="select" id="profesorParaAsignarId" wire:model="profesorParaAsignarId">
                <option value="">Selecciona un profesor</option>
                @foreach ($profesoresDisponibles as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}{{ $p->especialidad ? " ({$p->especialidad})" : '' }}</option>
                @endforeach
            </x-gestion.control>
        </x-gestion.campo>
    @else
        <x-gestion.campo etiqueta="Curso" nombre="cursoAAsignarId" requerido ayuda="Los cursos archivados no se pueden reasignar.">
            <x-gestion.control tipo="select" id="cursoAAsignarId" wire:model="cursoAAsignarId">
                <option value="">Selecciona un curso</option>
                @foreach ($cursosDisponibles as $c)
                    <option value="{{ $c->id }}">{{ $c->nombre }} (actual: {{ $c->profesor?->name ?? 'sin asignar' }})</option>
                @endforeach
            </x-gestion.control>
        </x-gestion.campo>
    @endif
    <x-slot name="pie">
        <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalAsignar', false)">Cancelar</x-gestion.boton>
        <x-gestion.boton wire:click="confirmarAsignarCurso">Confirmar asignación</x-gestion.boton>
    </x-slot>
</x-gestion.modal>
