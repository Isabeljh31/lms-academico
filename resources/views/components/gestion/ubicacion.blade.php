@props([
    // Valores del trait App\Livewire\Concerns\FiltraPorUbicacion del componente que la usa.
    'vista',
    'sede',
    'facultad',
    'opciones',
    'vistas',
    // «profesores» o «estudiantes», para los textos.
    'personas' => 'profesores',
    // false = aún no hay facultades registradas: se avisa y el listado se muestra completo.
    'activa' => true,
])

{{-- Orden por sede y facultad de los listados del Supervisor (Profesores, Estudiantes). --}}
<x-gestion.tarjeta>
    @if (! $activa)
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="flex items-start gap-2 text-sm text-stone-600 dark:text-stone-300">
                <x-icono nombre="info" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>Para ordenar los {{ $personas }} por sede y facultad, registra las facultades y asigna a cada curso su sede y facultad. Mientras tanto se muestran todos.</span>
            </p>
            <x-gestion.boton variante="secundario" tamano="sm" icono="base-datos" :href="route('admin.sedes-salones.index', ['ver' => 'facultades'])">Registrar facultades</x-gestion.boton>
        </div>
    @else
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
        @if ($vista === 'presencial')
            <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="1. Sede" nombre="sedeUbicacion">
                    <x-gestion.control tipo="select" id="sedeUbicacion" wire:model.live="sedeUbicacion">
                        <option value="">Elige la sede</option>
                        @foreach ($opciones['sedes'] as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </x-gestion.control>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="2. Facultad" nombre="facultadUbicacion"
                    :ayuda="$sede !== '' && $opciones['facultades'] === [] ? 'Esta sede aún no tiene facultades.' : null">
                    <x-gestion.control tipo="select" id="facultadUbicacion" wire:model.live="facultadUbicacion" :disabled="$sede === ''">
                        <option value="">{{ $sede === '' ? 'Elige primero la sede' : 'Elige la facultad' }}</option>
                        @foreach ($opciones['facultades'] as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </x-gestion.control>
                </x-gestion.campo>
            </div>
        @elseif ($vista === 'virtual')
            <div class="flex-1 sm:max-w-md">
                <x-gestion.campo etiqueta="Facultad" nombre="facultadUbicacion">
                    <x-gestion.control tipo="select" id="facultadUbicacion" wire:model.live="facultadUbicacion">
                        <option value="">Todas las facultades</option>
                        @foreach ($opciones['facultades'] as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </x-gestion.control>
                </x-gestion.campo>
            </div>
        @else
            <p class="flex-1 text-sm text-stone-600 dark:text-stone-300">{{ ucfirst($personas) }} que aún no tienen cursos{{ $personas === 'estudiantes' ? ' matriculados' : ' asignados' }}.</p>
        @endif

        <div class="flex flex-wrap gap-2" role="group" aria-label="Listado de {{ $personas }}">
            @foreach ($vistas as $clave => $etiqueta)
                <x-gestion.boton :variante="$vista === $clave ? 'primario' : 'secundario'" tamano="sm" wire:click="verVistaUbicacion('{{ $clave }}')"
                    aria-pressed="{{ $vista === $clave ? 'true' : 'false' }}">{{ $clave === 'presencial' ? 'Por sede y facultad' : 'Ver '.$personas.' '.mb_strtolower($etiqueta) }}</x-gestion.boton>
            @endforeach
        </div>
    </div>
    @endif
</x-gestion.tarjeta>
