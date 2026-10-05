@php
    $estilos = [
        'presente' => ['label' => 'Presente', 'activo' => 'bg-exito-oscuro text-white', 'suave' => 'bg-exito-base/15 text-exito-oscuro'],
        'tardanza' => ['label' => 'Tardanza', 'activo' => 'bg-terracota-base text-white', 'suave' => 'bg-arena-claro text-terracota-oscuro'],
        'justificado' => ['label' => 'Justificado', 'activo' => 'bg-cafe-arcilla text-white', 'suave' => 'bg-arena-medio text-stone-700'],
        'ausente' => ['label' => 'Ausente', 'activo' => 'bg-red-600 text-white', 'suave' => 'bg-red-50 text-red-700'],
    ];
@endphp

<div class="py-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Asistencia" icono="calendario" :descripcion="$curso->nombre.' · '.$totalSesiones.' sesiones registradas'"
            :volver="route('cursos.show', $curso)" volver-texto="Volver al curso">
            @if ($this->puedeConfigurar && $curso->usa_asistencia)
                <x-gestion.boton variante="secundario" icono="ojo-tachado" wire:click="alternarUsoAsistencia"
                    wire:confirm="¿Omitir el control de asistencia en este curso? Los registros ya guardados se conservan y puedes volver a activarlo.">Omitir asistencia</x-gestion.boton>
            @endif
            @if ($this->editable && $vista === 'sesiones')
                <x-gestion.boton icono="mas" wire:click="$set('mostrarFormSesion', true)">Nueva sesión</x-gestion.boton>
            @endif
        </x-gestion.encabezado>

        <x-gestion.alertas />

        @unless ($curso->usa_asistencia)
            <x-gestion.vacio icono="calendario" titulo="Este curso no registra asistencia"
                descripcion="El control de asistencia está omitido en este curso. Los registros anteriores, si los hay, se conservan.">
                @if ($this->puedeConfigurar)
                    <x-gestion.boton icono="check" wire:click="alternarUsoAsistencia">Activar control de asistencia</x-gestion.boton>
                @endif
            </x-gestion.vacio>
        @else
            <div class="flex gap-1 border-b border-arena-medio dark:border-stone-700" role="tablist">
                @foreach (['sesiones' => 'Sesiones', 'resumen' => 'Resumen por estudiante'] as $clave => $etiqueta)
                    <button type="button" role="tab" wire:click="$set('vista', '{{ $clave }}')" aria-selected="{{ $vista === $clave ? 'true' : 'false' }}"
                        class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ $vista === $clave || ($clave === 'sesiones' && $vista === 'pase_lista') ? 'border-menta-oscuro text-menta-oscuro dark:border-menta-base dark:text-menta-base' : 'border-transparent text-stone-500 hover:text-stone-700' }}">
                        {{ $etiqueta }}
                    </button>
                @endforeach
            </div>

            @if ($vista === 'sesiones')
                @if ($mostrarFormSesion)
                    <x-gestion.tarjeta titulo="Nueva sesión de clase">
                        <form wire:submit="crearSesion" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-gestion.campo etiqueta="Título" nombre="sesionTitulo" requerido>
                                <x-gestion.control id="sesionTitulo" wire:model="sesionTitulo" placeholder="Clase 1: Introducción" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Fecha y hora" nombre="sesionFecha" requerido>
                                <x-gestion.control id="sesionFecha" type="datetime-local" wire:model="sesionFecha" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Duración (minutos)" nombre="sesionDuracion" requerido>
                                <x-gestion.control id="sesionDuracion" type="number" min="15" max="480" wire:model="sesionDuracion" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Descripción" nombre="sesionDescripcion">
                                <x-gestion.control id="sesionDescripcion" wire:model="sesionDescripcion" />
                            </x-gestion.campo>
                            <div class="flex gap-2 sm:col-span-2 sm:justify-end">
                                <x-gestion.boton variante="secundario" wire:click="$set('mostrarFormSesion', false)">Cancelar</x-gestion.boton>
                                <x-gestion.boton type="submit" icono="check">Crear sesión</x-gestion.boton>
                            </div>
                        </form>
                    </x-gestion.tarjeta>
                @endif

                @if ($sesiones->isEmpty())
                    <x-gestion.vacio icono="calendario" titulo="Aún no hay sesiones de clase" descripcion="Crea una sesión para pasar lista." />
                @else
                    <x-gestion.tarjeta :relleno="false">
                        <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($sesiones as $sesion)
                                <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center" wire:key="sesion-{{ $sesion->id }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-stone-800 dark:text-stone-100">#{{ $sesion->numero_sesion }} · {{ $sesion->titulo }}</p>
                                        <p class="text-xs text-stone-500">{{ $sesion->fecha->formatoFechaHora() }} · {{ $sesion->duracion_minutos }} min · {{ $sesion->asistencias_count }}/{{ $estudiantes->count() }} registrados</p>
                                    </div>
                                    <div class="flex gap-1">
                                        <x-gestion.boton tamano="sm" icono="lista" wire:click="abrirPaseLista({{ $sesion->id }})">{{ $this->editable ? 'Pasar lista' : 'Ver asistencia' }}</x-gestion.boton>
                                        @if ($this->editable && $sesion->asistencias_count === 0)
                                            <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminarSesion({{ $sesion->id }})"
                                                wire:confirm="¿Eliminar la sesión #{{ $sesion->numero_sesion }}?">Eliminar</x-gestion.boton>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-gestion.tarjeta>
                @endif
            @endif

            @if ($vista === 'pase_lista' && $sesionActiva)
                <x-gestion.tarjeta :titulo="'Sesión #'.$sesionActiva->numero_sesion.': '.$sesionActiva->titulo" :descripcion="$sesionActiva->fecha->formatoFechaHora()" :relleno="false">
                    <x-slot name="acciones">
                        <x-gestion.boton variante="secundario" tamano="sm" icono="flecha-izquierda" wire:click="$set('vista', 'sesiones')">Sesiones</x-gestion.boton>
                        @if ($this->editable)
                            <x-gestion.boton variante="secundario" tamano="sm" icono="check" wire:click="marcarTodos('presente')">Todos presentes</x-gestion.boton>
                        @endif
                    </x-slot>
                    @if ($estudiantes->isEmpty())
                        <div class="p-4"><x-gestion.vacio icono="usuarios" titulo="No hay estudiantes matriculados" /></div>
                    @else
                        <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($estudiantes as $estudiante)
                                <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="marca-{{ $estudiante->id }}">
                                    <span class="font-medium text-stone-800 dark:text-stone-100">{{ $estudiante->name }}</span>
                                    <div class="flex flex-wrap gap-1" role="radiogroup" aria-label="Asistencia de {{ $estudiante->name }}">
                                        @foreach ($estilos as $estado => $estilo)
                                            <label class="cursor-pointer">
                                                <input type="radio" class="peer sr-only" wire:model.live="marcas.{{ $estudiante->id }}" value="{{ $estado }}" @disabled(! $this->editable)>
                                                <span class="inline-block rounded-full border border-arena-medio px-3 py-1 text-xs font-semibold transition dark:border-stone-600
                                                    {{ ($marcas[$estudiante->id] ?? '') === $estado ? $estilo['activo'].' border-transparent' : 'text-stone-600 hover:bg-arena-claro dark:text-stone-300 dark:hover:bg-stone-700' }}">
                                                    {{ $estilo['label'] }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('marcas.'.$estudiante->id) <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </li>
                            @endforeach
                        </ul>
                        @if ($this->editable)
                            <div class="flex justify-end border-t border-arena-medio px-4 py-3 dark:border-stone-700">
                                <x-gestion.boton icono="check" wire:click="guardarAsistencia">Guardar asistencia</x-gestion.boton>
                            </div>
                        @endif
                    @endif
                </x-gestion.tarjeta>
            @endif

            @if ($vista === 'resumen')
                <x-gestion.tarjeta :relleno="false" titulo="Porcentaje acumulado" descripcion="Presente y Tardanza cuentan como asistencia; Justificado no cuenta a favor ni en contra. Solo sesiones ya realizadas.">
                    @if ($resumen->isEmpty())
                        <div class="p-4"><x-gestion.vacio icono="usuarios" titulo="No hay estudiantes matriculados" /></div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[600px] text-sm">
                                <thead class="bg-arena-claro/50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                                    <tr>
                                        <th class="px-4 py-3">Estudiante</th>
                                        @foreach ($estilos as $estilo) <th class="px-3 py-3 text-center">{{ $estilo['label'] }}</th> @endforeach
                                        <th class="px-3 py-3 text-center">Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                                    @foreach ($resumen as $fila)
                                        <tr wire:key="resumen-{{ $fila['estudiante']->id }}">
                                            <td class="px-4 py-2 font-medium text-stone-800 dark:text-stone-100">{{ $fila['estudiante']->name }}</td>
                                            @foreach ($estilos as $estado => $estilo)
                                                <td class="px-3 py-2 text-center text-stone-600 dark:text-stone-300">{{ $fila['conteo'][$estado] ?? 0 }}</td>
                                            @endforeach
                                            <td class="px-3 py-2 text-center">
                                                @if ($fila['porcentaje'] !== null)
                                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $fila['porcentaje'] >= 70 ? 'bg-exito-base/15 text-exito-oscuro' : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' }}">{{ number_format($fila['porcentaje'], 1) }} %</span>
                                                @else
                                                    <span class="text-stone-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-gestion.tarjeta>
            @endif
        @endunless
    </div>
</div>
