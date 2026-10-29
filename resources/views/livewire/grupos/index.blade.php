@php
    $soloLectura = ! $this->editable;
    $esAdminVista = auth()->user()->hasRole('supervisor');
    $contador = function (int $cantidad) use ($maximo): array {
        if ($maximo === null) {
            return ['texto' => $cantidad === 1 ? '1 integrante' : "{$cantidad} integrantes", 'clase' => 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200'];
        }

        return match (true) {
            $cantidad > $maximo => ['texto' => "{$cantidad} de {$maximo} · Excede", 'clase' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'],
            $cantidad === $maximo => ['texto' => "{$cantidad} de {$maximo} · Completo", 'clase' => 'bg-exito-base/15 text-exito-oscuro dark:text-green-300'],
            default => ['texto' => "{$cantidad} de {$maximo} · Faltan ".($maximo - $cantidad), 'clase' => 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200'],
        };
    };
@endphp

{{-- relative (solo profesor; la vista se comparte con Supervisor): contiene las etiquetas sr-only (position: absolute)
     de cada grupo; sin esto se posicionaban respecto de <body> y estiraban la página. --}}
<div @class(['py-8', 'relative' => auth()->user()->hasRole('profesor')])>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Grupos" icono="usuarios" :descripcion="$curso->nombre.', organiza a los estudiantes matriculados para los trabajos grupales.'" />

        <x-gestion.alertas />

        @unless ($soloLectura)
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-gestion.tarjeta titulo="Nuevo grupo">
                    <form wire:submit="crearGrupo" class="flex gap-2">
                        <div class="min-w-0 flex-1">
                            <label for="nuevoGrupoNombre" class="sr-only">Nombre del grupo</label>
                            <x-gestion.control id="nuevoGrupoNombre" wire:model="nuevoGrupoNombre" placeholder="Nombre del grupo" />
                            @error('nuevoGrupoNombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <x-gestion.boton type="submit" icono="mas">Crear</x-gestion.boton>
                    </form>
                </x-gestion.tarjeta>

                <x-gestion.tarjeta titulo="Generar automáticamente" descripcion="Reparte solo a quienes aún no tienen grupo.">
                    <form wire:submit="generarAutomatico" class="flex gap-2">
                        <div class="min-w-0 flex-1">
                            <label for="tamanoGrupoAuto" class="sr-only">Estudiantes por grupo</label>
                            <x-gestion.control id="tamanoGrupoAuto" type="number" min="2" :max="$maximo ?? 20" wire:model="tamanoGrupoAuto" />
                            @error('tamanoGrupoAuto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <x-gestion.boton type="submit" variante="secundario" icono="refrescar">Repartir</x-gestion.boton>
                    </form>
                </x-gestion.tarjeta>

                <x-gestion.tarjeta titulo="Máximo por grupo" descripcion="Vacío = sin límite. Impide agregar más integrantes.">
                    <form wire:submit="guardarTamanoMaximo" class="flex gap-2">
                        <div class="min-w-0 flex-1">
                            <label for="tamanoMaximo" class="sr-only">Máximo de integrantes por grupo</label>
                            <x-gestion.control id="tamanoMaximo" type="number" min="2" max="20" wire:model="tamanoMaximo" placeholder="Sin límite" />
                            @error('tamanoMaximo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <x-gestion.boton type="submit" variante="secundario" icono="check">Guardar</x-gestion.boton>
                    </form>
                </x-gestion.tarjeta>
            </div>
        @endunless

        @if ($grupos->isEmpty() && $sinGrupo->isEmpty())
            <x-gestion.vacio icono="usuarios" titulo="Todavía no hay estudiantes matriculados"
                descripcion="Cuando haya estudiantes en el curso podrás organizarlos en grupos." />
        @else
            <div @unless ($soloLectura) x-data="window.tableroGrupos()" x-on:keydown.window="teclaGlobal($event)" x-on:grupos-actualizados.window="$nextTick(() => sincronizarSinGrupo())" @endunless
                class="grid items-start gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]">
                @unless ($soloLectura)
                    <p class="sr-only" aria-live="polite" x-text="anuncio"></p>
                @endunless

                {{-- Panel «Sin grupo»: origen de los estudiantes y destino para quitarlos de un grupo. --}}
                <section data-tarjeta-zona aria-labelledby="titulo-sin-grupo"
                    @unless ($soloLectura)
                        x-bind:class="destino === 'sin-grupo' ? 'border-azul-oscuro bg-azul-oscuro/5 ring-2 ring-azul-oscuro/30 dark:border-azul-fondo' : (arrastrando ? 'border-dashed border-azul-oscuro/40' : '')"
                    @endunless
                    class="rounded-xl border border-celeste bg-celeste/40 p-4 transition-colors motion-reduce:transition-none dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura lg:sticky lg:top-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 id="titulo-sin-grupo" class="flex items-center gap-1.5 font-semibold text-texto dark:text-slate-100">
                            Sin grupo <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo">{{ $sinGrupo->count() }}</span>
                        </h2>
                        @unless ($soloLectura)
                            <x-gestion.ayuda texto="Marca varios alumnos y pulsa Mover seleccionados. También puedes arrastrarlos juntos. Con teclado: Espacio selecciona, Enter levanta, flechas eligen el grupo y Enter suelta." />
                        @endunless
                    </div>

                    @unless ($soloLectura)
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Marca alumnos para moverlos juntos o arrástralos a un grupo.</p>
                        @if ($sinGrupo->isNotEmpty())
                            <div class="mt-3">
                                <label for="buscar-sin-grupo" class="sr-only">Buscar estudiante sin grupo</label>
                                <x-gestion.control id="buscar-sin-grupo" type="search" icono="lupa" x-model="busqueda" placeholder="Buscar estudiante" />
                            </div>
                        @endif
                    @endunless

                    <ul @unless ($soloLectura) data-zona="sin-grupo" data-grupo="" data-nombre="Sin grupo" data-cuenta="{{ $sinGrupo->count() }}" data-maximo="" x-init="montar($el)" @endunless
                        aria-label="Estudiantes sin grupo"
                        class="mt-3 flex max-h-72 min-h-[4rem] flex-col gap-1.5 overflow-y-auto overscroll-contain px-1 py-1 sm:max-h-80">
                        @foreach ($sinGrupo as $estudiante)
                            <x-gestion.chip-estudiante :estudiante="$estudiante" :editable="! $soloLectura" :buscable="true" />
                        @endforeach
                    </ul>
                    @if ($sinGrupo->isEmpty())
                        <p class="text-center text-sm text-slate-500 dark:text-slate-400" @unless ($soloLectura) x-show="destino !== 'sin-grupo'" @endunless>Todos los estudiantes tienen grupo.</p>
                    @endif
                    @unless ($soloLectura)
                        <p x-show="destino === 'sin-grupo'" style="display: none" class="mt-2 text-center text-xs font-semibold text-azul-oscuro dark:text-azul-fondo">Suelta aquí para quitar del grupo</p>
                        @if ($sinGrupo->isNotEmpty())
                            <p x-show="busqueda.trim() !== '' && sinGrupoVisibles().length === 0" style="display: none"
                                class="text-center text-sm text-slate-500">Nadie coincide con «<span x-text="busqueda"></span>».</p>
                        @endif
                        <div class="mt-3 space-y-3 border-t border-celeste pt-3 dark:border-azul-borde-oscuro">
                            @if ($sinGrupo->isNotEmpty())
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <span class="text-slate-500 dark:text-slate-400" x-text="sinGrupoVisibles().length + ' de ' + alumnosSinGrupo.length + ' sin grupo'"></span>
                                    <button type="button" x-on:click="seleccionarVisibles()" x-bind:disabled="sinGrupoVisibles().length === 0"
                                        class="rounded font-semibold text-azul-oscuro hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro disabled:opacity-50 dark:text-azul-fondo">Seleccionar resultados</button>
                                </div>
                            @endif
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span role="status" class="font-semibold text-texto dark:text-slate-100" x-text="seleccionados.length + ' seleccionados en el curso'"></span>
                                <button type="button" x-on:click="limpiarSeleccion()" x-bind:disabled="seleccionados.length === 0"
                                    class="rounded text-slate-500 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro disabled:opacity-50 dark:text-slate-400">Limpiar</button>
                            </div>
                            <x-gestion.boton class="w-full" icono="usuarios" x-on:click="abrirSeleccion()" x-bind:disabled="seleccionados.length === 0 || cantidadGrupos === 0">Mover seleccionados</x-gestion.boton>
                            @if ($grupos->isEmpty())
                                <p class="text-xs text-slate-500 dark:text-slate-400">Crea un grupo para asignar los alumnos seleccionados.</p>
                            @endif
                        </div>
                    @endunless
                </section>

                {{-- Tablero de grupos. --}}
                @if ($grupos->isEmpty())
                    <x-gestion.vacio icono="usuarios" titulo="Todavía no hay grupos en este curso"
                        descripcion="Crea un grupo o genera varios automáticamente con los estudiantes sin grupo." />
                @else
                    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Grupos del curso">
                        @foreach ($grupos as $grupo)
                            @php
                                $cantidad = $grupo->estudiantes->count();
                                $conteo = $contador($cantidad);
                                $zona = 'grupo-'.$grupo->id;
                            @endphp
                            <li wire:key="grupo-{{ $grupo->id }}" data-tarjeta-zona
                                @unless ($soloLectura)
                                    x-bind:class="destino === '{{ $zona }}' ? (destinoBloqueado ? 'border-red-400 bg-red-50 dark:bg-red-950/30' : 'border-azul-oscuro bg-azul-oscuro/5 ring-2 ring-azul-oscuro/30 dark:border-azul-fondo') : (arrastrando ? 'border-dashed border-azul-oscuro/40' : '')"
                                @endunless
                                class="flex flex-col rounded-xl border border-celeste bg-white p-4 shadow-sm transition-colors motion-reduce:transition-none dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:shadow-none">
                                @if ($grupoRenombrarId === $grupo->id)
                                    <form wire:submit="renombrar" class="flex gap-2">
                                        <label for="renombrar-{{ $grupo->id }}" class="sr-only">Nuevo nombre</label>
                                        <div class="min-w-0 flex-1">
                                            <x-gestion.control id="renombrar-{{ $grupo->id }}" wire:model="nombreRenombrar" />
                                        </div>
                                        <x-gestion.boton type="submit" tamano="sm" icono="check" aria-label="Guardar nombre" />
                                        <x-gestion.boton variante="fantasma" tamano="sm" icono="cerrar" wire:click="cancelarRenombrar" aria-label="Cancelar" />
                                    </form>
                                    @error('nombreRenombrar') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <h2 class="truncate font-semibold text-texto dark:text-slate-100">{{ $grupo->nombre }}</h2>
                                            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $conteo['clase'] }}">{{ $conteo['texto'] }}</span>
                                        </div>
                                        @unless ($soloLectura)
                                            <div class="flex shrink-0">
                                                <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="empezarRenombrar({{ $grupo->id }})" aria-label="Renombrar {{ $grupo->nombre }}" title="Renombrar" />
                                                <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminarGrupo({{ $grupo->id }})" aria-label="Eliminar {{ $grupo->nombre }}" title="Eliminar"
                                                    wire:confirm="{{ $cantidad > 0 ? '¿Eliminar '.$grupo->nombre.'? '.($cantidad === 1 ? 'Su integrante volverá' : 'Sus '.$cantidad.' integrantes volverán').' a Sin grupo. Podrás deshacerlo.' : '¿Eliminar '.$grupo->nombre.'?' }}" />
                                            </div>
                                        @endunless
                                    </div>
                                @endif

                                <ul @unless ($soloLectura) data-zona="{{ $zona }}" data-grupo="{{ $grupo->id }}" data-nombre="{{ $grupo->nombre }}" data-cuenta="{{ $cantidad }}" data-maximo="{{ $maximo }}" x-init="montar($el)" @endunless
                                    aria-label="Integrantes de {{ $grupo->nombre }}"
                                    class="mt-3 flex max-h-80 min-h-[3.5rem] flex-1 flex-col gap-1.5 overflow-y-auto overscroll-contain px-1 py-1">
                                    @foreach ($grupo->estudiantes as $estudiante)
                                        <x-gestion.chip-estudiante :estudiante="$estudiante" :editable="! $soloLectura" :recien="in_array($estudiante->id, $recienMovidos, true)" />
                                    @endforeach
                                </ul>
                                @if ($cantidad === 0)
                                    <p class="pointer-events-none -mt-10 mb-3 rounded-lg border border-dashed border-celeste px-3 py-2 text-center text-xs text-slate-500 dark:border-azul-borde-oscuro dark:text-slate-400"
                                        @unless ($soloLectura) x-show="destino !== '{{ $zona }}'" @endunless>
                                        {{ $soloLectura ? 'Todavía no tiene integrantes.' : 'Arrastra estudiantes aquí' }}
                                    </p>
                                @endif
                                @unless ($soloLectura)
                                    <p x-show="destino === '{{ $zona }}'" style="display: none" class="mt-2 text-center text-xs font-semibold"
                                        x-bind:class="destinoBloqueado ? 'text-red-700 dark:text-red-300' : 'text-azul-oscuro dark:text-azul-fondo'"
                                        x-text="destinoBloqueado ? @js('Grupo completo: admite '.$maximo.' integrantes') : 'Suelta aquí para agregar'"></p>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Menú «Mover a…» (alternativa al arrastre para teclado, lectores de pantalla y móvil). --}}
                @unless ($soloLectura)
                    <div x-show="menuPara !== null" style="display: none" x-on:keydown.escape.window="menuPara = null"
                        class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/50 sm:items-center sm:p-4" x-on:click.self="menuPara = null">
                        <div role="dialog" aria-modal="true" aria-labelledby="titulo-mover-a" x-trap.noscroll="menuPara !== null"
                            class="w-full max-w-sm rounded-t-2xl bg-white p-4 shadow-2xl dark:bg-azul-superficie-oscura sm:rounded-2xl">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <h2 id="titulo-mover-a" class="text-lg font-bold text-texto dark:text-slate-100">Mover a…</h2>
                                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="seleccionados.length > 1 ? seleccionados.length + ' estudiantes seleccionados' : 'Elige el destino'"></p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">El grupo debe tener espacio para todos los seleccionados.</p>
                                </div>
                                <button type="button" x-on:click="menuPara = null" aria-label="Cerrar" class="rounded-lg p-1 text-slate-400 hover:bg-celeste hover:text-slate-600 dark:hover:bg-azul-borde-oscuro">
                                    <x-icono nombre="cerrar" class="h-5 w-5" />
                                </button>
                            </div>
                            <ul class="max-h-[60vh] space-y-1 overflow-y-auto">
                                <li>
                                    <button type="button" x-on:click="moverA(null)"
                                        class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-texto hover:bg-celeste focus:bg-celeste focus:outline-none dark:text-slate-100 dark:hover:bg-azul-borde-oscuro dark:focus:bg-azul-borde-oscuro">
                                        Sin grupo <span class="text-xs font-normal text-slate-500">Quitar del grupo</span>
                                    </button>
                                </li>
                                @foreach ($grupos as $grupo)
                                    <li wire:key="mover-a-{{ $grupo->id }}">
                                        <button type="button" x-on:click="moverA({{ $grupo->id }})"
                                            x-bind:disabled="! cabe(zonas().find((zona) => zona.dataset.zona === 'grupo-{{ $grupo->id }}'))"
                                            class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-texto hover:bg-celeste focus:bg-celeste focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:text-slate-100 dark:hover:bg-azul-borde-oscuro dark:focus:bg-azul-borde-oscuro">
                                            <span class="truncate">{{ $grupo->nombre }}</span>
                                            <span class="shrink-0 text-xs font-normal text-slate-500">{{ $contador($grupo->estudiantes->count())['texto'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endunless
            </div>
        @endif
    </div>

    {{-- Confirmación breve del último cambio, con «Deshacer». --}}
    @if ($aviso)
        <div wire:key="aviso-{{ $avisoNumero }}" x-data="{ visible: true }" x-init="setTimeout(() => visible = false, 8000)" x-show="visible"
            x-transition.opacity.duration.200ms class="pointer-events-none fixed inset-x-0 bottom-4 z-40 flex justify-center px-4">
            <div role="status" @class(['pointer-events-auto flex max-w-xl items-center gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg',
                'border-exito-base/40 bg-[#EEF6F0] text-exito-oscuro dark:bg-[#17291D] dark:text-green-300' => $avisoTipo === 'exito',
                'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200' => $avisoTipo === 'error'])>
                <x-icono :nombre="$avisoTipo === 'exito' ? 'check-circulo' : 'alerta'" class="h-5 w-5 shrink-0" />
                <p class="flex-1">{{ $aviso }}</p>
                @if ($avisoTipo === 'exito' && $deshacer && ! $soloLectura)
                    <button type="button" wire:click="deshacerUltimo" class="shrink-0 rounded-md px-2 py-1 font-semibold underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-exito-oscuro">Deshacer</button>
                @endif
                <button type="button" x-on:click="visible = false" aria-label="Cerrar aviso" class="shrink-0 rounded-md p-0.5 opacity-70 hover:opacity-100">
                    <x-icono nombre="cerrar" class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif
</div>
