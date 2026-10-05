<div class="py-8">
    <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Lecciones" icono="documento"
            descripcion="{{ $modulo->nombreCompleto() }} · {{ $modulo->curso->nombre }}"
            :volver="route('cursos.show', $modulo->curso_id)" volver-texto="Volver al curso">
            @if ($this->cursoEditable)
                <x-gestion.boton icono="mas" wire:click="abrirNueva">Nueva lección</x-gestion.boton>
            @endif
        </x-gestion.encabezado>

        <x-gestion.alertas />

        @if ($lecciones->isEmpty())
            <x-gestion.vacio icono="documento" titulo="Este módulo todavía no tiene lecciones"
                descripcion="Crea lecciones con descripción, video y material. Los estudiantes solo verán las publicadas.">
                @if ($this->cursoEditable)
                    <x-gestion.boton icono="mas" wire:click="abrirNueva">Crear la primera lección</x-gestion.boton>
                @endif
            </x-gestion.vacio>
        @else
            <x-gestion.tarjeta :relleno="false">
                <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                    @foreach ($lecciones as $leccion)
                        @php
                            $estilosEstado = [
                                'publicado' => 'bg-exito-base/15 text-exito-oscuro',
                                'programado' => 'bg-arena-claro text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo',
                                'borrador' => 'bg-arena-medio text-stone-700 dark:bg-stone-700 dark:text-stone-200',
                            ];
                        @endphp
                        <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center" wire:key="leccion-{{ $leccion->id }}">
                            @if ($this->cursoEditable)
                                <div class="flex shrink-0 gap-1 sm:flex-col">
                                    <button type="button" wire:click="moverArriba({{ $leccion->id }})" @disabled($loop->first)
                                        class="rounded p-1 text-stone-400 hover:bg-arena-claro hover:text-stone-700 disabled:opacity-30 dark:hover:bg-stone-700" aria-label="Subir lección">
                                        <x-icono nombre="flecha-arriba" class="h-4 w-4" />
                                    </button>
                                    <button type="button" wire:click="moverAbajo({{ $leccion->id }})" @disabled($loop->last)
                                        class="rounded p-1 text-stone-400 hover:bg-arena-claro hover:text-stone-700 disabled:opacity-30 dark:hover:bg-stone-700" aria-label="Bajar lección">
                                        <x-icono nombre="flecha-abajo" class="h-4 w-4" />
                                    </button>
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-menta-fondo text-menta-oscuro dark:bg-stone-700 dark:text-menta-base" title="{{ $leccion->tipoRecurso()['etiqueta'] }}">
                                        <x-icono :nombre="$leccion->tipoRecurso()['icono']" class="h-4 w-4" />
                                    </span>
                                    <h3 class="font-medium text-stone-800 dark:text-stone-100">{{ $leccion->titulo }}</h3>
                                    <span class="text-xs text-stone-500 dark:text-stone-400">{{ $leccion->tipoRecurso()['etiqueta'] }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $estilosEstado[$leccion->estado] ?? '' }}">
                                        {{ $leccion->estado === 'programado' && $leccion->fecha_publicacion ? 'Programada para '.$leccion->fecha_publicacion->formatoFechaHora() : ucfirst($leccion->estado) }}
                                    </span>
                                </div>
                                @if ($leccion->descripcion)
                                    <p class="mt-0.5 line-clamp-2 text-sm text-stone-500 dark:text-stone-400">{{ $leccion->descripcion }}</p>
                                @endif
                                <div class="mt-1 flex flex-wrap gap-3 text-xs text-stone-500">
                                    @if ($leccion->video_url)
                                        <a href="{{ $leccion->video_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 hover:text-menta-oscuro"><x-icono nombre="video" class="h-3.5 w-3.5" /> Video</a>
                                    @endif
                                    @if ($leccion->material_url)
                                        <a href="{{ $leccion->material_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 hover:text-menta-oscuro"><x-icono nombre="material" class="h-3.5 w-3.5" /> {{ $leccion->material_nombre ?? 'Material' }}</a>
                                    @endif
                                    <span class="inline-flex items-center gap-1"><x-icono nombre="check-circulo" class="h-3.5 w-3.5" /> {{ $leccion->completadas_count }}/{{ $totalEstudiantes }} la completaron</span>
                                </div>
                            </div>
                            @if ($this->cursoEditable)
                                <div class="flex shrink-0 gap-1">
                                    <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="editar({{ $leccion->id }})">Editar</x-gestion.boton>
                                    <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar({{ $leccion->id }})"
                                        wire:confirm="¿Eliminar la lección {{ $leccion->titulo }}? Esta acción no se puede deshacer.">Eliminar</x-gestion.boton>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-gestion.tarjeta>
        @endif
    </div>

    @if ($mostrarFormulario)
        <x-gestion.modal :titulo="$leccionEditId ? 'Editar lección' : 'Nueva lección'" cerrar="$set('mostrarFormulario', false)" ancho="max-w-2xl">
            <x-gestion.campo etiqueta="Título" nombre="titulo" requerido>
                <x-gestion.control id="titulo" wire:model="titulo" placeholder="Introducción al tema" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Tipo de recurso" nombre="tipo_recurso" requerido ayuda="Define el ícono con que los estudiantes ven esta lección dentro de la semana.">
                <x-gestion.control tipo="select" id="tipo_recurso" wire:model="tipo_recurso">
                    @foreach (\App\Models\Leccion::TIPOS_RECURSO as $valor => $tipo)
                        <option value="{{ $valor }}">{{ $tipo['etiqueta'] }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Descripción" nombre="descripcion">
                <x-gestion.control tipo="textarea" id="descripcion" rows="4" wire:model="descripcion" />
            </x-gestion.campo>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="Enlace del video" nombre="video_url">
                    <x-gestion.control id="video_url" type="url" wire:model="video_url" placeholder="https://youtube.com/..." />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Enlace a material externo" nombre="material_url">
                    <x-gestion.control id="material_url" type="url" wire:model="material_url" placeholder="https://drive.google.com/..." />
                </x-gestion.campo>
            </div>
            <x-gestion.campo etiqueta="O sube un archivo" nombre="archivo" ayuda="PDF, Word, Excel, PowerPoint, ZIP o imagen. Máximo 10 MB. Reemplaza al enlace de material.">
                <input id="archivo" type="file" wire:model="archivo"
                    class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-lg file:border-0 file:bg-menta-oscuro file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:text-stone-300">
                <p wire:loading wire:target="archivo" class="text-xs text-stone-500">Cargando archivo...</p>
            </x-gestion.campo>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="Estado" nombre="estado" requerido>
                    <x-gestion.control tipo="select" id="estado" wire:model.live="estado">
                        <option value="borrador">Borrador (no visible)</option>
                        <option value="publicado">Publicado</option>
                        <option value="programado">Programado</option>
                    </x-gestion.control>
                </x-gestion.campo>
                @if ($estado === 'programado')
                    <x-gestion.campo etiqueta="Publicar automáticamente el" nombre="fecha_publicacion" requerido>
                        <x-gestion.control id="fecha_publicacion" type="datetime-local" wire:model="fecha_publicacion" />
                    </x-gestion.campo>
                @endif
            </div>
            <x-gestion.campo etiqueta="Se considera completada" nombre="condicion_completitud" requerido ayuda="Cuenta para el avance del estudiante y para completar el módulo.">
                <x-gestion.control tipo="select" id="condicion_completitud" wire:model="condicion_completitud">
                    @foreach (\App\Models\Leccion::CONDICIONES as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarFormulario', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton icono="check" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar,archivo">Guardar lección</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
