{{--
    Módulo del Supervisor «Sedes y salones» (Admin\SedesSalones\Index).
    Diseño del Supervisor: layouts.app y componentes x-gestion.*.
--}}
@php
    $th = 'px-4 py-2';
    $td = 'px-4 py-2 text-stone-700 dark:text-stone-200';
    $tdTenue = 'px-4 py-2 text-stone-500 dark:text-stone-400';
    $tenue = 'text-xs text-stone-500 dark:text-stone-400';
    $pildora = 'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold';
    $pildoraActiva = 'bg-exito-base/20 text-exito-oscuro dark:bg-exito-oscuro/30 dark:text-exito-base';
    $pildoraInactiva = 'bg-arena-medio text-stone-700 dark:bg-stone-700 dark:text-stone-200';
    $cabecera = 'bg-arena-claro/50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400';
    $cuerpo = 'divide-y divide-arena-medio/60 dark:divide-stone-700';
@endphp

<x-gestion.pagina>
    <x-gestion.encabezado titulo="Sedes, salones y facultades" icono="base-datos"
        descripcion="Catálogo de sedes, facultades, tipos de salón y salones. Cada curso presencial se asigna a una sede (y facultad) y sus clases usan estos salones." />

    <x-gestion.alertas />

    <div class="flex flex-wrap gap-2" role="group" aria-label="Secciones">
        @foreach ($this::PESTANAS as $clave => $etiqueta)
            <x-gestion.boton :variante="$pestana === $clave ? 'primario' : 'secundario'" tamano="sm" wire:click="verPestana('{{ $clave }}')"
                aria-pressed="{{ $pestana === $clave ? 'true' : 'false' }}">{{ $etiqueta }}</x-gestion.boton>
        @endforeach
    </div>

    @if ($pestana === 'salones')
        <x-gestion.tarjeta titulo="Salones" descripcion="Un salón con clases futuras no se borra: se desactiva." :relleno="false">
            <x-slot:acciones>
                <x-gestion.boton icono="mas" tamano="sm" wire:click="nuevo('salon')">Nuevo salón</x-gestion.boton>
            </x-slot:acciones>

            <div class="grid grid-cols-1 gap-4 border-b border-arena-medio p-4 sm:grid-cols-2 dark:border-stone-700">
                <x-gestion.desplegable id="filtro-sede" etiqueta="Sede" wire:model.live="filtroSede" :seleccionado="$filtroSede"
                    :opciones="['' => 'Todas las sedes'] + $opcionesSede" />
                <x-gestion.desplegable id="filtro-tipo" etiqueta="Tipo" wire:model.live="filtroTipo" :seleccionado="$filtroTipo"
                    :opciones="['' => 'Todos los tipos'] + $opcionesTipo" />
            </div>

            @if ($salones->isEmpty())
                <div class="p-4"><x-gestion.vacio icono="base-datos" titulo="No hay salones con estos filtros" /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="{{ $cabecera }}">
                            <tr>
                                <th class="{{ $th }}">Código</th>
                                <th class="{{ $th }}">Nombre</th>
                                <th class="{{ $th }}">Sede</th>
                                <th class="{{ $th }}">Tipo</th>
                                <th class="{{ $th }}">Capacidad</th>
                                <th class="{{ $th }}">Clases futuras</th>
                                <th class="{{ $th }}">Estado</th>
                                <th class="{{ $th }} text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="{{ $cuerpo }}">
                            @foreach ($salones as $salon)
                                <tr wire:key="salon-{{ $salon->id }}">
                                    <td class="{{ $td }} font-mono">{{ $salon->codigo }}</td>
                                    <td class="{{ $td }}">{{ $salon->nombre }}</td>
                                    <td class="{{ $tdTenue }}">{{ $salon->sede->nombre }}</td>
                                    <td class="{{ $tdTenue }}">{{ $salon->tipo->nombre }}</td>
                                    <td class="{{ $tdTenue }}">{{ $salon->capacidad }}</td>
                                    <td class="{{ $tdTenue }}">{{ $salon->clases_futuras }}</td>
                                    <td class="px-4 py-2"><span class="{{ $pildora }} {{ $salon->activo ? $pildoraActiva : $pildoraInactiva }}">{{ $salon->activo ? 'Activo' : 'Inactivo' }}</span></td>
                                    <td class="px-4 py-2">
                                        <div class="flex justify-end gap-1">
                                            <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="editar('salon', {{ $salon->id }})" aria-label="Editar {{ $salon->codigo }}">Editar</x-gestion.boton>
                                            <x-gestion.boton variante="fantasma" tamano="sm" wire:click="alternarActivo('salon', {{ $salon->id }})">{{ $salon->activo ? 'Desactivar' : 'Activar' }}</x-gestion.boton>
                                            <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar('salon', {{ $salon->id }})"
                                                wire:confirm="¿Borrar el salón {{ $salon->codigo }}? Si tiene clases futuras no se borrará." aria-label="Borrar {{ $salon->codigo }}">Borrar</x-gestion.boton>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3">{{ $salones->links() }}</div>
            @endif
        </x-gestion.tarjeta>
    @elseif ($pestana === 'sedes')
        <x-gestion.tarjeta titulo="Sedes" descripcion="Una sede con salones, facultades o cursos no se borra: se desactiva." :relleno="false">
            <x-slot:acciones>
                <x-gestion.boton icono="mas" tamano="sm" wire:click="nuevo('sede')">Nueva sede</x-gestion.boton>
            </x-slot:acciones>

            @if ($sedes->isEmpty())
                <div class="p-4"><x-gestion.vacio icono="base-datos" titulo="Aún no hay sedes" /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-sm">
                        <thead class="{{ $cabecera }}">
                            <tr>
                                <th class="{{ $th }}">Código</th>
                                <th class="{{ $th }}">Nombre</th>
                                <th class="{{ $th }}">Salones</th>
                                <th class="{{ $th }}">Facultades</th>
                                <th class="{{ $th }}">Cursos</th>
                                <th class="{{ $th }}">Estado</th>
                                <th class="{{ $th }} text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="{{ $cuerpo }}">
                            @foreach ($sedes as $sede)
                                <tr wire:key="sede-{{ $sede->id }}">
                                    <td class="{{ $td }} font-mono">{{ $sede->codigo }}</td>
                                    <td class="{{ $td }}">{{ $sede->nombre }}</td>
                                    <td class="{{ $tdTenue }}">{{ $sede->salones_count }}</td>
                                    <td class="{{ $tdTenue }}">{{ $sede->facultades_count }}</td>
                                    <td class="{{ $tdTenue }}">{{ $sede->cursos_count }}</td>
                                    <td class="px-4 py-2"><span class="{{ $pildora }} {{ $sede->activa ? $pildoraActiva : $pildoraInactiva }}">{{ $sede->activa ? 'Activa' : 'Inactiva' }}</span></td>
                                    <td class="px-4 py-2">
                                        <div class="flex justify-end gap-1">
                                            <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="editar('sede', {{ $sede->id }})">Editar</x-gestion.boton>
                                            <x-gestion.boton variante="fantasma" tamano="sm" wire:click="alternarActivo('sede', {{ $sede->id }})">{{ $sede->activa ? 'Desactivar' : 'Activar' }}</x-gestion.boton>
                                            <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar('sede', {{ $sede->id }})"
                                                wire:confirm="¿Borrar la sede {{ $sede->nombre }}?">Borrar</x-gestion.boton>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-gestion.tarjeta>
    @elseif ($pestana === 'facultades')
        <x-gestion.tarjeta titulo="Facultades" descripcion="Cada facultad pertenece a una sede. Una facultad con cursos no se borra: se desactiva." :relleno="false">
            <x-slot:acciones>
                <x-gestion.boton icono="mas" tamano="sm" wire:click="nuevo('facultad')">Nueva facultad</x-gestion.boton>
            </x-slot:acciones>

            <div class="border-b border-arena-medio p-4 dark:border-stone-700 sm:max-w-sm">
                <x-gestion.desplegable id="filtro-sede-facultad" etiqueta="Sede" wire:model.live="filtroSede" :seleccionado="$filtroSede"
                    :opciones="['' => 'Todas las sedes'] + $opcionesSede" />
            </div>

            @if ($facultades->isEmpty())
                <div class="p-4"><x-gestion.vacio icono="base-datos" titulo="Aún no hay facultades" descripcion="Registra las facultades de cada sede para asignarlas a los cursos." /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="{{ $cabecera }}">
                            <tr>
                                <th class="{{ $th }}">Código</th>
                                <th class="{{ $th }}">Nombre</th>
                                <th class="{{ $th }}">Sede</th>
                                <th class="{{ $th }}">Cursos</th>
                                <th class="{{ $th }}">Estado</th>
                                <th class="{{ $th }} text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="{{ $cuerpo }}">
                            @foreach ($facultades as $facultad)
                                <tr wire:key="facultad-{{ $facultad->id }}">
                                    <td class="{{ $td }} font-mono">{{ $facultad->codigo }}</td>
                                    <td class="{{ $td }}">{{ $facultad->nombre }}</td>
                                    <td class="{{ $tdTenue }}">{{ $facultad->sede->nombre }}</td>
                                    <td class="{{ $tdTenue }}">{{ $facultad->cursos_count }}</td>
                                    <td class="px-4 py-2"><span class="{{ $pildora }} {{ $facultad->activa ? $pildoraActiva : $pildoraInactiva }}">{{ $facultad->activa ? 'Activa' : 'Inactiva' }}</span></td>
                                    <td class="px-4 py-2">
                                        <div class="flex justify-end gap-1">
                                            <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="editar('facultad', {{ $facultad->id }})" aria-label="Editar {{ $facultad->nombre }}">Editar</x-gestion.boton>
                                            <x-gestion.boton variante="fantasma" tamano="sm" wire:click="alternarActivo('facultad', {{ $facultad->id }})">{{ $facultad->activa ? 'Desactivar' : 'Activar' }}</x-gestion.boton>
                                            <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar('facultad', {{ $facultad->id }})"
                                                wire:confirm="¿Borrar la facultad {{ $facultad->nombre }}?">Borrar</x-gestion.boton>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-gestion.tarjeta>
    @elseif ($pestana === 'tipos')
        <x-gestion.tarjeta titulo="Tipos de salón" descripcion="Una clase solo se reprograma sola a un salón de su mismo tipo." :relleno="false">
            <x-slot:acciones>
                <x-gestion.boton icono="mas" tamano="sm" wire:click="nuevo('tipo')">Nuevo tipo</x-gestion.boton>
            </x-slot:acciones>

            @if ($tipos->isEmpty())
                <div class="p-4"><x-gestion.vacio icono="base-datos" titulo="Aún no hay tipos de salón" /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[480px] text-sm">
                        <thead class="{{ $cabecera }}">
                            <tr>
                                <th class="{{ $th }}">Nombre</th>
                                <th class="{{ $th }}">Salones</th>
                                <th class="{{ $th }}">Estado</th>
                                <th class="{{ $th }} text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="{{ $cuerpo }}">
                            @foreach ($tipos as $tipo)
                                <tr wire:key="tipo-{{ $tipo->id }}">
                                    <td class="{{ $td }}">{{ $tipo->nombre }}</td>
                                    <td class="{{ $tdTenue }}">{{ $tipo->salones_count }}</td>
                                    <td class="px-4 py-2"><span class="{{ $pildora }} {{ $tipo->activo ? $pildoraActiva : $pildoraInactiva }}">{{ $tipo->activo ? 'Activo' : 'Inactivo' }}</span></td>
                                    <td class="px-4 py-2">
                                        <div class="flex justify-end gap-1">
                                            <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="editar('tipo', {{ $tipo->id }})">Editar</x-gestion.boton>
                                            <x-gestion.boton variante="fantasma" tamano="sm" wire:click="alternarActivo('tipo', {{ $tipo->id }})">{{ $tipo->activo ? 'Desactivar' : 'Activar' }}</x-gestion.boton>
                                            <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar('tipo', {{ $tipo->id }})"
                                                wire:confirm="¿Borrar el tipo {{ $tipo->nombre }}?">Borrar</x-gestion.boton>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-gestion.tarjeta>
    @else
        <x-gestion.tarjeta titulo="Asignar salón a las clases futuras de un curso"
            descripcion="Solo se asigna cuando la línea «Aula: X» de la clase coincide exactamente con el código de un salón activo. Las demás clases no se tocan.">
            <div class="max-w-md">
                <x-gestion.desplegable id="curso-asignar" etiqueta="Curso" wire:model.live="cursoAsignar" :seleccionado="$cursoAsignar"
                    :opciones="$opcionesCurso" placeholder="Elige un curso" />
            </div>
            @error('asignacion') <p class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $message }}</p> @enderror

            @if ($vistaPrevia)
                <div class="mt-5 space-y-4">
                    <p class="text-sm text-stone-700 dark:text-stone-200">
                        Sede actual del curso: <strong>{{ $cursoElegido->sede?->nombre ?? 'sin definir' }}</strong>.
                        Cambiarían <strong>{{ $vistaPrevia['asignables']->count() }}</strong> clases; {{ $vistaPrevia['sin_coincidencia']->count() }} no se tocan.
                    </p>

                    @if ($vistaPrevia['asignables']->isEmpty())
                        <x-gestion.vacio icono="info" titulo="Ninguna clase futura coincide exactamente con un salón"
                            descripcion="Revisa que el código del salón sea idéntico al texto «Aula: X» de las clases." />
                    @else
                        <div class="overflow-x-auto rounded-xl border border-arena-medio dark:border-stone-700">
                            <table class="w-full min-w-[560px] text-sm" aria-label="Clases que cambiarían">
                                <thead class="{{ $cabecera }}">
                                    <tr>
                                        <th class="{{ $th }}">Clase</th>
                                        <th class="{{ $th }}">Fecha</th>
                                        <th class="{{ $th }}">Aula escrita</th>
                                        <th class="{{ $th }}">Salón que se asigna</th>
                                    </tr>
                                </thead>
                                <tbody class="{{ $cuerpo }}">
                                    @foreach ($vistaPrevia['asignables'] as $fila)
                                        <tr wire:key="asignable-{{ $fila['sesion']->id }}">
                                            <td class="{{ $td }}">{{ $fila['sesion']->numero_sesion ? 'Sesión '.$fila['sesion']->numero_sesion : $fila['sesion']->titulo }}</td>
                                            <td class="{{ $tdTenue }}">{{ $fila['sesion']->fecha->format('d/m/Y H:i') }}</td>
                                            <td class="{{ $tdTenue }} font-mono">{{ $fila['aula'] }}</td>
                                            <td class="{{ $td }} font-mono">{{ $fila['salon']->codigo }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($vistaPrevia['sin_coincidencia']->isNotEmpty())
                        <details class="rounded-xl border border-arena-medio px-4 py-3 dark:border-stone-700">
                            <summary class="cursor-pointer text-sm font-medium text-stone-700 dark:text-stone-200">Clases que no se tocan ({{ $vistaPrevia['sin_coincidencia']->count() }})</summary>
                            <ul class="mt-2 space-y-1">
                                @foreach ($vistaPrevia['sin_coincidencia'] as $fila)
                                    <li wire:key="sin-{{ $fila['sesion']->id }}" class="{{ $tenue }}">
                                        {{ $fila['sesion']->fecha->format('d/m/Y H:i') }} · {{ $fila['aula'] ? 'Aula «'.$fila['aula'].'» sin salón con ese código' : 'Sin aula escrita' }}
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    @if ($vistaPrevia['asignables']->isNotEmpty())
                        <div class="flex justify-end">
                            <x-gestion.boton icono="check" wire:click="asignarSalones" wire:loading.attr="disabled" wire:target="asignarSalones"
                                wire:confirm="Se asignará salón a {{ $vistaPrevia['asignables']->count() }} clases futuras de este curso (se guarda un respaldo antes). ¿Continuar?">
                                Confirmar asignación
                            </x-gestion.boton>
                        </div>
                    @endif
                </div>
            @endif
        </x-gestion.tarjeta>
    @endif

    @if ($formulario)
        <x-gestion.modal :titulo="($editandoId ? 'Editar ' : 'Nuevo ').$this::ENTIDADES[$formulario]" cerrar="cerrarFormulario()" icono="base-datos">
            <form wire:submit="guardar" class="space-y-4">
                @if ($formulario === 'sede')
                    <x-gestion.campo etiqueta="Nombre" nombre="sedeNombre" requerido>
                        <x-gestion.control id="sedeNombre" wire:model="sedeNombre" maxlength="100" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Código corto" nombre="sedeCodigo" requerido ayuda="Hasta 10 caracteres, p. ej. LO.">
                        <x-gestion.control id="sedeCodigo" wire:model="sedeCodigo" maxlength="10" />
                    </x-gestion.campo>
                @elseif ($formulario === 'facultad')
                    <div>
                        <x-gestion.desplegable id="facultadSedeId" etiqueta="Sede" wire:model="facultadSedeId" :seleccionado="$facultadSedeId" :opciones="$opcionesSede" placeholder="Elige la sede" />
                        @error('facultadSedeId') <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <x-gestion.campo etiqueta="Nombre" nombre="facultadNombre" requerido>
                        <x-gestion.control id="facultadNombre" wire:model="facultadNombre" maxlength="120" placeholder="Facultad de Ingeniería" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Código corto" nombre="facultadCodigo" requerido ayuda="Único, hasta 15 caracteres, p. ej. FI-LO.">
                        <x-gestion.control id="facultadCodigo" wire:model="facultadCodigo" maxlength="15" />
                    </x-gestion.campo>
                @elseif ($formulario === 'tipo')
                    <x-gestion.campo etiqueta="Nombre" nombre="tipoNombre" requerido>
                        <x-gestion.control id="tipoNombre" wire:model="tipoNombre" maxlength="80" />
                    </x-gestion.campo>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-gestion.campo etiqueta="Código" nombre="salonCodigo" requerido ayuda="Único. Para asignar clases antiguas debe ser idéntico a su «Aula: X».">
                            <x-gestion.control id="salonCodigo" wire:model="salonCodigo" maxlength="20" placeholder="LO-2-04" />
                        </x-gestion.campo>
                        <x-gestion.campo etiqueta="Nombre" nombre="salonNombre" requerido>
                            <x-gestion.control id="salonNombre" wire:model="salonNombre" maxlength="100" />
                        </x-gestion.campo>
                        <div>
                            <x-gestion.desplegable id="salonSedeId" etiqueta="Sede" wire:model="salonSedeId" :seleccionado="$salonSedeId" :opciones="$opcionesSede" placeholder="Elige la sede" />
                            @error('salonSedeId') <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-gestion.desplegable id="salonTipoId" etiqueta="Tipo" wire:model="salonTipoId" :seleccionado="$salonTipoId" :opciones="$opcionesTipo" placeholder="Elige el tipo" />
                            @error('salonTipoId') <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $message }}</p> @enderror
                        </div>
                        <x-gestion.campo etiqueta="Capacidad" nombre="salonCapacidad" requerido>
                            <x-gestion.control id="salonCapacidad" type="number" min="1" max="1000" wire:model="salonCapacidad" />
                        </x-gestion.campo>
                    </div>
                    <x-gestion.campo etiqueta="Equipamiento" nombre="salonEquipamiento" ayuda="Opcional.">
                        <x-gestion.control tipo="textarea" id="salonEquipamiento" rows="2" wire:model="salonEquipamiento" maxlength="1000" />
                    </x-gestion.campo>
                @endif

                <div class="flex justify-end gap-2">
                    <x-gestion.boton variante="secundario" wire:click="cerrarFormulario">Cancelar</x-gestion.boton>
                    <x-gestion.boton type="submit" icono="check" wire:loading.attr="disabled">Guardar</x-gestion.boton>
                </div>
            </form>
        </x-gestion.modal>
    @endif
</x-gestion.pagina>
