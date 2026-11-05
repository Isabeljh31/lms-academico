{{--
    Detalle del curso para el Profesor (mockup "CursoDetalle"). Es la vista de
    Cursos\Show para un usuario con solo el rol profesor: mismas acciones,
    wire:model, eventos y permisos que livewire/cursos/show.blade.php; cambia
    solo la presentación. El encabezado y la tarjeta de evaluaciones son
    componentes aparte, que no se vuelven a calcular en cada acción.
--}}
@php
    $editable = $this->puedeGestionar && ! $curso->estaFinalizado();
    $enfoque = 'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-cielo focus-visible:ring-offset-2';
    $tarjeta = 'rounded-xl border border-celeste bg-white shadow-sm';
    $pastilla = 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold';
    $botonSecundario = "inline-flex min-h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-azul-oscuro transition hover:border-azul-oscuro hover:bg-celeste motion-reduce:transition-none {$enfoque}";
    $prioridades = ['urgente' => 'bg-red-100 text-red-800', 'importante' => 'bg-amber-100 text-amber-800'];
    $totalArchivos = $carpetas->sum(fn ($carpeta) => count($carpeta->recursos ?? []));
@endphp

<div class="mx-auto flex w-full max-w-[1280px] flex-col gap-4">
    <livewire:docente.cursos.encabezado-curso :curso="$curso" :key="'encabezado-curso-'.$curso->id" />

    <x-gestion.alertas />

    @if ($curso->estaFinalizado())
        <div role="alert" class="flex items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-icono nombre="archivo" class="h-5 w-5 shrink-0" />
            <p>Curso archivado (finalizado): se puede consultar, pero no admite cambios en su contenido ni en sus notas.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)] lg:items-start">
        <div class="flex min-w-0 flex-col gap-4">
            {{-- Prácticas de campo: carpeta propia encima de las semanas. Solo aparece si el Supervisor las activó en el curso. --}}
            @if ($secciones['practicas'])
                @include('livewire.docente.cursos._practicas')
            @endif

            <section aria-labelledby="titulo-contenido-semanas" class="{{ $tarjeta }} overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-3 p-4">
                    <div class="min-w-0 flex-1 basis-64">
                        <h2 id="titulo-contenido-semanas" class="text-lg font-semibold text-texto">Contenido por semanas</h2>
                        <p class="mt-1 text-[13px] text-slate-500">
                            El curso dura {{ $curso->totalSemanas() }} semanas{{ $curso->semanas_duracion ? '' : ' (duración sugerida: el Supervisor aún no la definió)' }}.
                            Las semanas en borrador o programadas no son visibles para los estudiantes.
                        </p>
                    </div>
                </div>

                @include('livewire.docente.cursos._semanas')
            </section>
        </div>

        <aside class="flex min-w-0 flex-col gap-4" aria-label="Evaluaciones, anuncios e información del curso">
            <livewire:docente.cursos.evaluaciones-curso :curso="$curso" :key="'evaluaciones-curso-'.$curso->id" />

            {{-- Anuncios del curso (RF-81): el formulario existente se despliega solo al pulsar "Nuevo anuncio". --}}
            <section aria-labelledby="titulo-anuncios" class="{{ $tarjeta }} p-4"
                x-data="{ nuevo: @js($errors->hasAny(['nuevoAnuncioTitulo', 'nuevoAnuncioTexto', 'nuevoAnuncioPrioridad'])) }">
                <h2 id="titulo-anuncios" class="text-lg font-semibold text-texto">Anuncios del curso</h2>
                <p class="mt-1 text-[13px] text-slate-500">Cada anuncio llega como notificación a los estudiantes matriculados.</p>

                <div class="mt-3.5 flex flex-col gap-3">
                    @forelse ($this->anuncios as $anuncio)
                        <article class="rounded-[10px] border border-celeste bg-fondo/60 p-3" wire:key="anuncio-doc-{{ $anuncio->id }}">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="min-w-0 break-words text-sm font-semibold text-texto">{{ $anuncio->titulo }}</h3>
                                <div class="flex shrink-0 items-center gap-1">
                                    @if (isset($prioridades[$anuncio->prioridad]))
                                        <span class="{{ $pastilla }} {{ $prioridades[$anuncio->prioridad] }}">{{ ucfirst($anuncio->prioridad) }}</span>
                                    @endif
                                    @if ($this->puedeGestionar)
                                        <button type="button" wire:click="eliminarAnuncio({{ $anuncio->id }})" wire:confirm="¿Eliminar el anuncio {{ $anuncio->titulo }}?"
                                            class="-my-2 -mr-2 flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 transition hover:bg-red-50 hover:text-red-700 motion-reduce:transition-none {{ $enfoque }}"
                                            aria-label="Eliminar anuncio {{ $anuncio->titulo }}">
                                            <x-icono nombre="papelera" class="h-4 w-4" />
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <p class="mt-1 whitespace-pre-line break-words text-[13px] text-slate-600">{{ $anuncio->contenido }}</p>
                            <span class="mt-2 block text-xs text-slate-500">{{ $anuncio->created_at->diffForHumans() }} · {{ $anuncio->autor?->name }}</span>
                        </article>
                    @empty
                        <p class="flex items-center gap-3 rounded-[10px] border border-dashed border-slate-200 p-3.5 text-sm text-slate-600">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-celeste text-azul-oscuro"><x-icono nombre="megafono" class="h-5 w-5" /></span>
                            Aún no publicaste anuncios en este curso.
                        </p>
                    @endforelse
                </div>

                @if ($this->puedeGestionar && ! $curso->estaFinalizado())
                    <button type="button" x-show="! nuevo" x-on:click="nuevo = true; $nextTick(() => $refs.tituloAnuncio.focus())"
                        @if ($errors->hasAny(['nuevoAnuncioTitulo', 'nuevoAnuncioTexto', 'nuevoAnuncioPrioridad'])) style="display: none" @endif
                        aria-controls="formulario-anuncio" class="{{ $botonSecundario }} mt-3.5 w-full text-texto">
                        <x-icono nombre="mas" class="h-4 w-4" /> Nuevo anuncio
                    </button>
                    <form id="formulario-anuncio" wire:submit="publicarAnuncio" x-show="nuevo"
                        @unless ($errors->hasAny(['nuevoAnuncioTitulo', 'nuevoAnuncioTexto', 'nuevoAnuncioPrioridad'])) style="display: none" @endunless
                        class="mt-3.5 space-y-3 border-t border-celeste pt-3.5">
                        <x-gestion.campo etiqueta="Título" nombre="nuevoAnuncioTitulo" requerido>
                            <x-gestion.control id="nuevoAnuncioTitulo" x-ref="tituloAnuncio" wire:model="nuevoAnuncioTitulo" placeholder="Recordatorio de la práctica 1" />
                        </x-gestion.campo>
                        <x-gestion.campo etiqueta="Mensaje" nombre="nuevoAnuncioTexto" requerido>
                            <x-gestion.control tipo="textarea" id="nuevoAnuncioTexto" wire:model="nuevoAnuncioTexto" />
                        </x-gestion.campo>
                        <x-gestion.campo etiqueta="Prioridad" nombre="nuevoAnuncioPrioridad">
                            <x-gestion.control tipo="select" id="nuevoAnuncioPrioridad" wire:model="nuevoAnuncioPrioridad">
                                <option value="normal">Normal</option>
                                <option value="importante">Importante</option>
                                <option value="urgente">Urgente</option>
                            </x-gestion.control>
                        </x-gestion.campo>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" x-on:click="nuevo = false" class="{{ $botonSecundario }} text-texto">Cancelar</button>
                            <x-gestion.boton type="submit" icono="megafono">Publicar</x-gestion.boton>
                        </div>
                    </form>
                @endif
            </section>

            {{-- Información del curso: carpetas que administra el Supervisor (solo lectura), plegada por defecto. --}}
            <details class="{{ $tarjeta }} group/info">
                <summary class="flex min-h-11 cursor-pointer list-none items-center gap-3 rounded-xl px-4 py-3 [&::-webkit-details-marker]:hidden {{ $enfoque }} focus-visible:ring-offset-0">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-celeste text-azul-oscuro"><x-icono nombre="documento" class="h-5 w-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-base font-semibold text-texto">Información del curso</span>
                        <span class="text-[13px] text-slate-500">Sílabo, actas y enlaces administrativos · {{ $totalArchivos === 1 ? '1 archivo' : $totalArchivos.' archivos' }}</span>
                    </span>
                    <x-icono nombre="flecha-abajo" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/info:rotate-180 motion-reduce:transition-none" />
                </summary>
                <div class="space-y-2 border-t border-celeste px-4 py-3">
                    @forelse ($carpetas as $carpeta)
                        <div wire:key="carpeta-{{ $carpeta->id }}">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-texto">
                                <x-icono nombre="archivo" class="h-4 w-4 shrink-0 text-slate-500" />
                                {{ $carpeta->nombre }}
                                <span class="text-xs font-normal text-slate-500">({{ count($carpeta->recursos ?? []) }})</span>
                            </h3>
                            <ul class="mt-1 pl-6">
                                @forelse ($carpeta->recursos ?? [] as $i => $recurso)
                                    <li wire:key="recurso-{{ $carpeta->id }}-{{ $i }}">
                                        <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($recurso['url']) }}" target="_blank" rel="noopener"
                                            class="inline-flex min-h-11 max-w-full items-center gap-1.5 rounded-md text-sm text-azul-oscuro hover:underline {{ $enfoque }}">
                                            <x-icono :nombre="\App\Services\Multimedia\SubidorArchivos::esEnlaceExterno($recurso['url']) ? 'enlace' : 'documento'" class="h-4 w-4 shrink-0" />
                                            <span class="break-words">{{ $recurso['nombre'] }}</span>
                                        </a>
                                    </li>
                                @empty
                                    <li class="py-1.5 text-xs text-slate-500">Sin archivos ni enlaces todavía.</li>
                                @endforelse
                            </ul>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">Aún no hay carpetas. El Supervisor publica aquí el sílabo y los documentos administrativos.</p>
                    @endforelse
                </div>
            </details>
        </aside>
    </div>

    {{-- Modales del contenido semanal: los mismos de livewire/cursos/show.blade.php. --}}
    @if ($mostrarModalModulo)
        <x-gestion.modal titulo="Editar sección" cerrar="$set('mostrarModalModulo', false)">
            <x-gestion.campo etiqueta="Tema" nombre="moduloTitulo" ayuda="Opcional. Se muestra junto a la semana, por ejemplo Semana 3: La derivada.">
                <x-gestion.control id="moduloTitulo" wire:model="moduloTitulo" placeholder="Tema de la semana" />
            </x-gestion.campo>
            @if ($moduloSemana !== '')
                <x-gestion.campo etiqueta="Semana" nombre="moduloSemana" requerido ayuda="Mueve todo el contenido (lecciones, tareas y quizzes) a otra semana libre.">
                    <x-gestion.control tipo="select" id="moduloSemana" wire:model="moduloSemana">
                        @foreach (range(1, $curso->totalSemanas()) as $numeroSemana)
                            <option value="{{ $numeroSemana }}">Semana {{ $numeroSemana }}</option>
                        @endforeach
                    </x-gestion.control>
                </x-gestion.campo>
            @endif
            <x-gestion.campo etiqueta="Estado" nombre="moduloEstado" requerido ayuda="Borrador y Programado no son visibles para los estudiantes hasta su publicación.">
                <x-gestion.control tipo="select" id="moduloEstado" wire:model.live="moduloEstado">
                    <option value="borrador">Borrador</option>
                    <option value="publicado">Publicado</option>
                    <option value="programado">Programado</option>
                </x-gestion.control>
            </x-gestion.campo>
            @if ($moduloEstado === 'programado')
                <x-gestion.campo etiqueta="Publicar automáticamente el" nombre="moduloPublicarEn" requerido>
                    <x-gestion.control id="moduloPublicarEn" type="datetime-local" wire:model="moduloPublicarEn" />
                </x-gestion.campo>
            @endif
            <x-gestion.campo etiqueta="Se considera completado al" nombre="moduloCondicion" requerido ayuda="Los estudiantes ven el avance de la sección según esta condición.">
                <x-gestion.control tipo="select" id="moduloCondicion" wire:model="moduloCondicion">
                    @foreach (\App\Models\Modulo::CONDICIONES as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalModulo', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton wire:click="guardarModulo">Guardar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarFormularioLeccion && $seccionLeccion)
        @include('livewire.cursos._formulario-leccion')
    @endif

    @if ($mostrarConfirmacion)
        <x-gestion.modal :titulo="$confirmacionTitulo" cerrar="cerrarConfirmacion()" icono="alerta">
            <p class="text-sm text-texto">{{ $confirmacionMensaje }}</p>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="cerrarConfirmacion">Cancelar</x-gestion.boton>
                <x-gestion.boton variante="peligro" icono="papelera" wire:click="ejecutarConfirmacion"
                    wire:loading.attr="disabled" wire:target="ejecutarConfirmacion">{{ $confirmacionTextoBoton }}</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
