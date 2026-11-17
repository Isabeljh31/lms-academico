{{--
    Detalle del curso para el Profesor (maqueta "CursoDetalle"). Es la vista de
    Cursos\Show para un usuario con solo el rol profesor: mismas acciones,
    wire:model, eventos y permisos que livewire/cursos/show.blade.php; cambia
    solo la presentación. El encabezado y la tarjeta de evaluaciones son
    componentes aparte, que no se vuelven a calcular en cada acción. Los datos
    agregados por semana salen de DetalleCursoProfesor (consultas fijas).
--}}
@use('App\Services\Reportes\DetalleCursoProfesor')
@php
    $editable = $this->puedeGestionar && ! $curso->estaFinalizado();
    $detalleCurso = app(DetalleCursoProfesor::class)->resumen($curso, $secciones['semanas']);
    $anunciosCurso = app(DetalleCursoProfesor::class)->anuncios($curso);
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $tarjeta = 'rounded-[20px] bg-white shadow-[0_1px_2px_rgba(9,59,59,0.06),0_6px_20px_rgba(9,59,59,0.05)]';
    $etiquetaEstado = 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-[9px] py-0.5 text-[11px] font-bold';
    $botonContorno = "inline-flex h-[38px] items-center justify-center gap-[7px] whitespace-nowrap rounded-[10px] border-[1.5px] border-[#c9ddd8] bg-white px-3.5 text-[13px] font-bold text-[var(--p700)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none {$enfoque}";
    $prioridades = ['urgente' => 'bg-[var(--err100)] text-[var(--err700)]', 'importante' => 'bg-[var(--warn200)] text-[var(--warn800)]'];
    $totalArchivos = $carpetas->sum(fn ($carpeta) => count($carpeta->recursos ?? []));
    $erroresAnuncio = $errors->hasAny(['nuevoAnuncioTitulo', 'nuevoAnuncioTexto', 'nuevoAnuncioPrioridad']);
    $proxima = $detalleCurso['proxima'];
    $modalidad = $curso->modalidad ? (\App\Services\Reportes\CursosProfesor::MODALIDADES[$curso->modalidad] ?? ucfirst($curso->modalidad)) : null;
@endphp

<div class="mx-auto flex w-full max-w-[1320px] flex-col gap-4">
    <livewire:docente.cursos.encabezado-curso :curso="$curso" :key="'encabezado-curso-'.$curso->id" />

    <x-gestion.alertas />

    @if ($curso->estaFinalizado())
        <div role="alert" class="flex items-start gap-3 rounded-xl bg-[var(--warn100)] px-4 py-3 text-sm text-[var(--warn800)]">
            <x-icono nombre="archivo" class="h-5 w-5 shrink-0" />
            <p>Curso archivado (finalizado): se puede consultar, pero no admite cambios en su contenido ni en sus notas.</p>
        </div>
    @endif

    <div class="mt-1.5 grid grid-cols-[minmax(0,1fr)_372px] items-start gap-[22px]">
        <div class="flex min-w-0 flex-col gap-4">
            {{-- Prácticas de campo: carpeta propia encima de las semanas. Solo aparece si el Supervisor las activó en el curso. --}}
            @if ($secciones['practicas'])
                @include('livewire.docente.cursos._practicas')
            @endif

            @include('livewire.docente.cursos._semanas')
        </div>

        <aside class="sticky top-4 flex min-w-0 flex-col gap-[18px]" aria-label="Próxima clase, evaluaciones, anuncios e información del curso">
            @if ($proxima)
                @php $sesion = $proxima['sesion']; @endphp
                <section aria-labelledby="titulo-proxima-clase" class="rounded-[20px] bg-[var(--p800)] px-6 py-[22px] text-white">
                    <h2 id="titulo-proxima-clase" class="text-[11px] font-bold uppercase tracking-[0.16em] text-[var(--sec2)]">Próxima clase</h2>
                    <p class="mb-1 mt-2 text-[30px] font-extrabold leading-tight tracking-[-0.02em]">{{ DetalleCursoProfesor::fechaCorta($sesion->fecha) }} · {{ $sesion->fecha->format('H:i') }}</p>
                    <p class="text-[13px] text-[#d3e3df]">{{ collect([$sesion->numero_sesion ? 'Sesión '.$sesion->numero_sesion : $sesion->titulo, $proxima['aula'], $modalidad])->filter()->join(' · ') }}</p>
                    <div class="mt-4 flex flex-wrap gap-2.5">
                        <a href="{{ route('asistencia.index', $curso) }}" wire:navigate
                            class="inline-flex h-10 items-center gap-[7px] rounded-[11px] bg-white px-[15px] text-[13px] font-bold text-[var(--p900)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--lime)]">
                            Ver sesión <x-icono nombre="flecha-derecha" class="h-4 w-4" />
                        </a>
                        @if ($curso->usa_asistencia && ! $curso->estaFinalizado())
                            <a href="{{ route('asistencia.index', $curso) }}" wire:navigate
                                class="inline-flex h-10 items-center rounded-[11px] border-[1.5px] border-white/35 px-[15px] text-[13px] font-bold text-white transition-colors hover:bg-white/10 motion-reduce:transition-none focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--lime)]">
                                Tomar asistencia
                            </a>
                        @endif
                    </div>
                </section>
            @endif

            <livewire:docente.cursos.evaluaciones-curso :curso="$curso" :key="'evaluaciones-curso-'.$curso->id" />

            {{-- Anuncios del curso (RF-81): el formulario existente se despliega solo al pulsar "Nuevo anuncio". --}}
            <section aria-labelledby="titulo-anuncios" class="{{ $tarjeta }} px-[22px] py-5" x-data="{ nuevo: @js($erroresAnuncio) }">
                <h2 id="titulo-anuncios" class="text-[17px] font-extrabold text-[var(--p900)]">Anuncios del curso</h2>
                <p class="mt-[3px] text-[12.5px] text-[#5b706c]">Cada anuncio llega como notificación a los estudiantes matriculados.</p>

                <div class="mt-2">
                    @forelse ($anunciosCurso as $anuncio)
                        <article class="flex items-start gap-2.5 border-t border-[#edf3f1] py-3 first:border-t-0" wire:key="anuncio-doc-{{ $anuncio->id }}">
                            <div class="min-w-0 flex-1">
                                <h3 class="break-words text-[13.5px] font-bold text-[var(--p900)]">{{ $anuncio->titulo }}</h3>
                                <p class="mt-0.5 line-clamp-2 whitespace-pre-line break-words text-xs text-[#4b625e]">{{ $anuncio->contenido }}</p>
                                <p class="mt-0.5 text-xs text-[#5b706c]">Publicado el {{ ($anuncio->publicar_en ?? $anuncio->created_at)->format('d/m/Y') }}@if ($anuncio->autor) · {{ $anuncio->autor->name }}@endif</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                @if (isset($prioridades[$anuncio->prioridad]))
                                    <span class="{{ $etiquetaEstado }} {{ $prioridades[$anuncio->prioridad] }}">{{ ucfirst($anuncio->prioridad) }}</span>
                                @endif
                                <span class="{{ $etiquetaEstado }} {{ $anuncio->activo ? 'bg-[var(--ok100)] text-[var(--ok900)]' : 'bg-[#ECF1F0] text-[#4b625e]' }}">{{ $anuncio->activo ? 'Activo' : 'Vencido' }}</span>
                                @if ($this->puedeGestionar)
                                    <button type="button" wire:click="eliminarAnuncio({{ $anuncio->id }})" wire:confirm="¿Eliminar el anuncio {{ $anuncio->titulo }}?"
                                        class="-my-2 -mr-2 flex h-11 w-11 items-center justify-center rounded-lg text-[#5b706c] transition-colors hover:bg-[var(--err100)] hover:text-[var(--err700)] motion-reduce:transition-none {{ $enfoque }}"
                                        aria-label="Eliminar anuncio {{ $anuncio->titulo }}">
                                        <x-icono nombre="papelera" class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="flex items-center gap-3 rounded-xl border-[1.5px] border-dashed border-[#c9ddd8] p-3.5 text-sm text-[#4b625e]">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-[var(--mint)] text-[var(--p700)]"><x-icono nombre="megafono" class="h-5 w-5" /></span>
                            Aún no publicaste anuncios en este curso.
                        </p>
                    @endforelse
                </div>

                @if ($this->puedeGestionar && ! $curso->estaFinalizado())
                    <button type="button" x-show="! nuevo" x-on:click="nuevo = true; $nextTick(() => $refs.tituloAnuncio.focus())"
                        @if ($erroresAnuncio) style="display: none" @endif
                        aria-controls="formulario-anuncio" class="{{ $botonContorno }} mt-1.5 w-full">
                        <x-icono nombre="mas" class="h-4 w-4" /> Nuevo anuncio
                    </button>
                    <form id="formulario-anuncio" wire:submit="publicarAnuncio" x-show="nuevo"
                        @unless ($erroresAnuncio) style="display: none" @endunless
                        class="mt-3.5 space-y-3 border-t border-[#edf3f1] pt-3.5">
                        <x-gestion.campo etiqueta="Título" nombre="nuevoAnuncioTitulo" requerido>
                            <x-gestion.control id="nuevoAnuncioTitulo" x-ref="tituloAnuncio" wire:model="nuevoAnuncioTitulo" placeholder="Recordatorio de la práctica 1" />
                        </x-gestion.campo>
                        <x-gestion.campo etiqueta="Mensaje" nombre="nuevoAnuncioTexto" requerido>
                            <x-gestion.control tipo="textarea" id="nuevoAnuncioTexto" wire:model="nuevoAnuncioTexto" />
                        </x-gestion.campo>
                        <div>
                            <p class="mb-1 text-sm font-semibold text-[var(--ink)]" aria-hidden="true">Prioridad</p>
                            <x-docente.desplegable wire:model="nuevoAnuncioPrioridad" :opciones="['normal' => 'Normal', 'importante' => 'Importante', 'urgente' => 'Urgente']" etiqueta="Prioridad" placeholder="Normal" />
                            @error('nuevoAnuncioPrioridad') <p class="mt-1 text-xs text-[var(--err700)]">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" x-on:click="nuevo = false" class="{{ $botonContorno }}">Cancelar</button>
                            <button type="submit" class="inline-flex h-[38px] items-center gap-[7px] rounded-[10px] bg-[var(--p900)] px-4 text-[13px] font-bold text-white transition-colors hover:bg-[var(--p800)] motion-reduce:transition-none {{ $enfoque }}">
                                <x-icono nombre="megafono" class="h-4 w-4" /> Publicar
                            </button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- Información del curso: carpetas que administra el Supervisor (solo lectura), plegada por defecto. --}}
            <details class="{{ $tarjeta }} group/info">
                <summary class="flex min-h-11 cursor-pointer list-none items-center gap-3 rounded-[20px] px-[22px] py-4 [&::-webkit-details-marker]:hidden {{ $enfoque }}">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-[var(--mint)] text-[var(--p700)]"><x-icono nombre="documento" class="h-5 w-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[15px] font-extrabold text-[var(--p900)]">Información del curso</span>
                        <span class="text-[12.5px] text-[#5b706c]">Sílabo, actas y enlaces administrativos · {{ $totalArchivos === 1 ? '1 archivo' : $totalArchivos.' archivos' }}</span>
                    </span>
                    <x-icono nombre="flecha-abajo" class="h-5 w-5 shrink-0 text-[#4b625e] transition-transform group-open/info:rotate-180 motion-reduce:transition-none" />
                </summary>
                <div class="space-y-2 border-t border-[#edf3f1] px-[22px] py-3">
                    @forelse ($carpetas as $carpeta)
                        <div wire:key="carpeta-{{ $carpeta->id }}">
                            <h3 class="flex items-center gap-2 text-sm font-bold text-[var(--p900)]">
                                <x-icono nombre="archivo" class="h-4 w-4 shrink-0 text-[var(--p700)]" />
                                {{ $carpeta->nombre }}
                                <span class="text-xs font-normal text-[#5b706c]">({{ count($carpeta->recursos ?? []) }})</span>
                            </h3>
                            <ul class="mt-1 pl-6">
                                @forelse ($carpeta->recursos ?? [] as $i => $recurso)
                                    <li wire:key="recurso-{{ $carpeta->id }}-{{ $i }}">
                                        <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($recurso['url']) }}" target="_blank" rel="noopener"
                                            class="inline-flex min-h-11 max-w-full items-center gap-1.5 rounded-md text-sm text-[var(--p700)] hover:underline {{ $enfoque }}">
                                            <x-icono :nombre="\App\Services\Multimedia\SubidorArchivos::esEnlaceExterno($recurso['url']) ? 'enlace' : 'documento'" class="h-4 w-4 shrink-0" />
                                            <span class="break-words">{{ $recurso['nombre'] }}</span>
                                        </a>
                                    </li>
                                @empty
                                    <li class="py-1.5 text-xs text-[#5b706c]">Sin archivos ni enlaces todavía.</li>
                                @endforelse
                            </ul>
                        </div>
                    @empty
                        <p class="text-sm text-[#4b625e]">Aún no hay carpetas. El Supervisor publica aquí el sílabo y los documentos administrativos.</p>
                    @endforelse
                </div>
            </details>
        </aside>
    </div>

    {{-- Modales del contenido semanal: las mismas acciones de livewire/cursos/show.blade.php, con el desplegable propio del Profesor. --}}
    @if ($mostrarModalModulo)
        <x-gestion.modal titulo="Editar sección" cerrar="$set('mostrarModalModulo', false)">
            <x-gestion.campo etiqueta="Tema" nombre="moduloTitulo" ayuda="Opcional. Se muestra junto a la semana, por ejemplo Semana 3: La derivada.">
                <x-gestion.control id="moduloTitulo" wire:model="moduloTitulo" placeholder="Tema de la semana" />
            </x-gestion.campo>
            @if ($moduloSemana !== '')
                <div>
                    <p class="mb-1 text-sm font-semibold text-[var(--ink)]" aria-hidden="true">Semana <span class="text-[var(--err700)]">*</span></p>
                    <x-docente.desplegable wire:model="moduloSemana" :opciones="collect(range(1, $curso->totalSemanas()))->mapWithKeys(fn ($numero) => [$numero => 'Semana '.$numero])->all()" etiqueta="Semana" placeholder="Semana" variante="borde" />
                    <p class="mt-1 text-xs text-[#5b706c]">Mueve todo el contenido (lecciones, tareas y quizzes) a otra semana libre.</p>
                    @error('moduloSemana') <p class="mt-1 text-xs text-[var(--err700)]">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <p class="mb-1 text-sm font-semibold text-[var(--ink)]" aria-hidden="true">Estado <span class="text-[var(--err700)]">*</span></p>
                <x-docente.desplegable wire:model.live="moduloEstado" :opciones="['borrador' => 'Borrador', 'publicado' => 'Publicado', 'programado' => 'Programado']" etiqueta="Estado" placeholder="Estado" variante="borde" />
                <p class="mt-1 text-xs text-[#5b706c]">Borrador y Programado no son visibles para los estudiantes hasta su publicación.</p>
                @error('moduloEstado') <p class="mt-1 text-xs text-[var(--err700)]">{{ $message }}</p> @enderror
            </div>
            @if ($moduloEstado === 'programado')
                <x-gestion.campo etiqueta="Publicar automáticamente el" nombre="moduloPublicarEn" requerido>
                    <x-gestion.control id="moduloPublicarEn" type="datetime-local" wire:model="moduloPublicarEn" />
                </x-gestion.campo>
            @endif
            <div>
                <p class="mb-1 text-sm font-semibold text-[var(--ink)]" aria-hidden="true">Se considera completado al <span class="text-[var(--err700)]">*</span></p>
                <x-docente.desplegable wire:model="moduloCondicion" :opciones="\App\Models\Modulo::CONDICIONES" etiqueta="Se considera completado al" placeholder="Condición" variante="borde" />
                <p class="mt-1 text-xs text-[#5b706c]">Los estudiantes ven el avance de la sección según esta condición.</p>
                @error('moduloCondicion') <p class="mt-1 text-xs text-[var(--err700)]">{{ $message }}</p> @enderror
            </div>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalModulo', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton wire:click="guardarModulo">Guardar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarFormularioLeccion && $seccionLeccion)
        @include('livewire.docente.cursos._formulario-leccion')
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
