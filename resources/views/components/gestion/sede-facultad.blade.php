@props([
    // Propiedades Livewire enlazadas (p. ej. nuevoSedeId / nuevoFacultadId) y la sede elegida.
    'campoSede',
    'campoFacultad',
    'sedeId' => '',
])

{{-- Sede y facultad propias de una persona (la facultad, de la sede elegida). --}}
@php
    $sedes = \App\Models\Sede::where('activa', true)->orderBy('nombre')->pluck('nombre', 'id');
    $facultades = $sedeId === '' ? collect() : \App\Models\Facultad::where('sede_id', (int) $sedeId)->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id');
    $obligatoria = \App\Models\Facultad::where('activa', true)->exists();
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-gestion.campo etiqueta="Sede" :nombre="$campoSede" :requerido="$obligatoria">
        <x-gestion.control tipo="select" :id="$campoSede" wire:model.live="{{ $campoSede }}">
            <option value="">Elige la sede</option>
            @foreach ($sedes as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </x-gestion.control>
    </x-gestion.campo>
    <x-gestion.campo etiqueta="Facultad" :nombre="$campoFacultad" :requerido="$obligatoria"
        :ayuda="$sedeId !== '' && $facultades->isEmpty() ? 'Esta sede aún no tiene facultades.' : null">
        <x-gestion.control tipo="select" :id="$campoFacultad" wire:model="{{ $campoFacultad }}" :disabled="$sedeId === ''">
            <option value="">{{ $sedeId === '' ? 'Elige primero la sede' : 'Elige la facultad' }}</option>
            @foreach ($facultades as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </x-gestion.control>
    </x-gestion.campo>
</div>
