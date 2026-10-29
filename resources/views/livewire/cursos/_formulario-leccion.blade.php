{{--
    Formulario de un elemento de contenido (modal), con los campos de su sección.
    Esc o "Cancelar" lo cierran y limpian todo su estado.
--}}
@php
    $esForo = $seccionLeccion === \App\Models\Leccion::SECCION_HEREDADA;
    $formulario = $this->formularioLeccion;
    $opcionesCompletitud = $this->opcionesCompletitud;
    $nombreSeccion = $esForo ? 'Foro de preguntas' : \App\Models\Leccion::SECCIONES[$seccionLeccion]['etiqueta'];
    $nombreSemana = $semanaLeccion === \App\Livewire\Cursos\Show::CLAVE_PRACTICAS ? 'Prácticas de campo' : 'Semana '.$semanaLeccion;
    $claseError = 'flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400';
    $claseSegmento = 'cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-celeste/60 has-[:checked]:bg-azul-oscuro has-[:checked]:text-white has-[:checked]:shadow-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-azul-oscuro has-[:focus-visible]:ring-offset-1 dark:text-slate-300 dark:has-[:checked]:bg-azul-fondo';
@endphp
<x-gestion.modal :titulo="($leccionEditId ? 'Editar' : 'Agregar').' — '.$nombreSeccion" cerrar="cerrarFormularioLeccion()" ancho="max-w-2xl"
    :icono="$esForo ? 'chat' : \App\Models\Leccion::SECCIONES[$seccionLeccion]['icono']"
    :descripcion="$nombreSemana.($esForo ? '' : ' · '.\App\Models\Leccion::SECCIONES[$seccionLeccion]['descripcion'])">

    @error('seccionLeccion')
        <p class="{{ $claseError }}"><x-icono nombre="alerta" class="h-3.5 w-3.5" /> {{ $message }}</p>
    @enderror
    @if ($esForo)
        <p class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            <x-icono nombre="info" class="h-4 w-4 shrink-0" />
            Este formato ya no se ofrece para contenido nuevo. La lección se conserva y se puede seguir editando.
        </p>
    @endif
    @if ($conservaOculto)
        <p class="flex items-start gap-2 rounded-lg bg-celeste px-3 py-2 text-xs text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200">
            <x-icono nombre="info" class="h-4 w-4 shrink-0" />
            Este elemento conserva un video o un material del formulario anterior, que los estudiantes siguen viendo.
        </p>
    @endif
    @if ($seccionLeccion === 'actividades')
        <p class="flex items-start gap-2 rounded-lg bg-celeste px-3 py-2 text-xs text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200">
            <x-icono nombre="info" class="h-4 w-4 shrink-0" />
            Si tiene nota, créala en Tareas o Quizzes.
        </p>
    @endif

    <x-gestion.campo etiqueta="Título" nombre="titulo" requerido>
        <x-gestion.control id="titulo" wire:model="titulo" maxlength="255" placeholder="{{ $seccionLeccion === 'grabacion' ? 'Clase del lunes' : 'Introducción al tema' }}" />
    </x-gestion.campo>

    @if ($formulario['texto'])
        <x-gestion.campo :etiqueta="$formulario['texto']" nombre="descripcion" :requerido="$formulario['textoRequerido']"
            :ayuda="$seccionLeccion === 'mensaje_inicio' ? 'Una bienvenida o una pregunta de reflexión para empezar la semana.' : null">
            <x-gestion.control tipo="textarea" id="descripcion" :rows="$seccionLeccion === 'recursos' ? 2 : 4" wire:model="descripcion" maxlength="10000" />
        </x-gestion.campo>
    @endif

    @if ($formulario['video'])
        <x-gestion.campo :etiqueta="$formulario['video']" nombre="video_url" :requerido="$formulario['videoRequerido']"
            ayuda="Los videos se agregan por enlace: Zoom, Teams, YouTube o Google Drive.">
            <x-gestion.control id="video_url" type="url" wire:model.blur="video_url" maxlength="500" placeholder="https://..." />
        </x-gestion.campo>
    @endif

    @if ($formulario['fuentes'] !== [])
        {{-- Archivo o enlace: selector segmentado con radios nativos (flechas para moverse). --}}
        <fieldset class="space-y-2">
            <legend class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                Material @if (! in_array('ninguno', $formulario['fuentes'], true)) <span class="text-red-600" aria-hidden="true">*</span> @endif
            </legend>
            <div class="inline-flex flex-wrap rounded-lg border border-celeste bg-white p-1 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura">
                @foreach ($formulario['fuentes'] as $valor)
                    <label wire:key="fuente-{{ $valor }}" class="{{ $claseSegmento }}">
                        <input type="radio" name="leccion_fuente" value="{{ $valor }}" wire:model.live="fuente" @checked($fuente === $valor)
                            class="sr-only" x-data x-on:keydown.enter.prevent="$el.click()">
                        {{ \App\Livewire\Cursos\Show::FUENTES_LECCION[$valor] }}
                    </label>
                @endforeach
            </div>
            @error('fuente')
                <p class="{{ $claseError }}"><x-icono nombre="alerta" class="h-3.5 w-3.5" /> {{ $message }}</p>
            @enderror

            @if ($fuente === 'archivo')
                <x-gestion.campo etiqueta="Archivo" nombre="archivo"
                    :ayuda="$esForo ? 'PDF, Word, Excel, PowerPoint, ZIP o imagen. Máximo 10 MB.' : 'PDF, Word, Excel, PPT/PPTX o imagen. Máximo 10 MB.'">
                    @if ($archivoActual)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-celeste px-3 py-2 text-sm dark:border-azul-borde-oscuro">
                            <span class="inline-flex min-w-0 items-center gap-2 {{ $quitarArchivo ? 'text-slate-400 line-through' : 'text-slate-700 dark:text-slate-200' }}">
                                <x-icono nombre="material" class="h-4 w-4 shrink-0" />
                                <span class="truncate">Archivo actual: {{ $archivoActual }}</span>
                            </span>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                <input type="checkbox" wire:model.live="quitarArchivo" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                                Quitar archivo
                            </label>
                        </div>
                    @endif
                    @php
                        $aceptar = collect($esForo ? \App\Services\Multimedia\SubidorArchivos::extensionesPermitidas() : \App\Models\Leccion::EXTENSIONES_ARCHIVO)
                            ->map(fn ($extension) => '.'.$extension)->join(',');
                    @endphp
                    <input id="archivo" type="file" wire:model="archivo" accept="{{ $aceptar }}"
                        class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-lg file:border-0 file:bg-azul-oscuro file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:text-stone-300">
                    <p wire:loading wire:target="archivo" class="text-xs text-stone-500">Cargando archivo...</p>
                </x-gestion.campo>
            @elseif ($fuente === 'enlace')
                <x-gestion.campo etiqueta="Enlace" nombre="material_url" requerido ayuda="Página web o documento en línea (Drive, OneDrive, etc.).">
                    <x-gestion.control id="material_url" type="url" wire:model.blur="material_url" maxlength="500" placeholder="https://..." />
                </x-gestion.campo>
            @endif
        </fieldset>
    @endif

    {{-- Estado: selector segmentado con radios nativos. --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
        <fieldset class="space-y-1">
            <legend class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                Estado <span class="text-red-600" aria-hidden="true">*</span>
            </legend>
            <div class="inline-flex rounded-lg border border-celeste bg-white p-1 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura">
                @foreach ($this->estadosLeccion as $valor => $etiquetaEstado)
                    <label wire:key="estado-leccion-{{ $valor }}" class="{{ $claseSegmento }}">
                        <input type="radio" name="leccion_estado" value="{{ $valor }}" wire:model.live="estado" @checked($estado === $valor)
                            class="sr-only" x-data x-on:keydown.enter.prevent="$el.click()">
                        {{ $etiquetaEstado }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ match ($estado) { 'publicado' => 'Visible para los estudiantes.', 'programado' => 'Se publica sola en la fecha indicada.', default => 'Los estudiantes todavía no lo ven.' } }}
            </p>
            @error('estado')
                <p class="{{ $claseError }}"><x-icono nombre="alerta" class="h-3.5 w-3.5" /> {{ $message }}</p>
            @enderror
        </fieldset>
        @if ($estado === 'programado')
            <x-gestion.campo etiqueta="Publicar automáticamente el" nombre="fecha_publicacion" requerido class="flex-1">
                <x-gestion.control id="fecha_publicacion" type="datetime-local" wire:model="fecha_publicacion" />
            </x-gestion.campo>
        @endif
    </div>

    {{-- ERS RF-67: zona colapsada con la condición de completitud; el valor elegido siempre se ve en el resumen. --}}
    <div class="rounded-xl border border-celeste dark:border-azul-borde-oscuro"
        x-data="{ abierta: @js($errors->has('condicion_completitud')) }">
        <button type="button" x-on:click="abierta = ! abierta" x-bind:aria-expanded="abierta.toString()" aria-controls="avance-estudiante"
            class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-2.5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Avance del estudiante</span>
                <span id="resumen-completitud" class="block text-xs text-slate-500 dark:text-slate-400">Se considera completado: {{ mb_strtolower(\App\Models\Leccion::CONDICIONES[$condicion_completitud] ?? \App\Models\Leccion::CONDICIONES['marcar']) }}</span>
            </span>
            <x-icono nombre="flecha-abajo" class="h-4 w-4 shrink-0 text-slate-400 transition-transform" x-bind:class="abierta ? 'rotate-180' : ''" />
        </button>
        <fieldset id="avance-estudiante" x-show="abierta" @unless ($errors->has('condicion_completitud')) style="display: none" @endunless
            class="space-y-2 border-t border-celeste px-4 pb-3 pt-2 dark:border-azul-borde-oscuro">
            <legend class="sr-only">Se considera completado cuando</legend>
            @foreach (\App\Models\Leccion::CONDICIONES as $valor => $textoCondicion)
                @php $disponible = array_key_exists($valor, $opcionesCompletitud); @endphp
                <label wire:key="condicion-{{ $valor }}"
                    class="flex items-start gap-2 text-sm {{ $disponible ? 'cursor-pointer text-slate-700 dark:text-slate-200' : 'cursor-not-allowed text-slate-400 dark:text-slate-500' }}">
                    <input type="radio" name="leccion_condicion_completitud" value="{{ $valor }}" wire:model.live="condicion_completitud"
                        @checked($condicion_completitud === $valor) @disabled(! $disponible)
                        class="mt-0.5 border-celeste text-azul-oscuro focus:ring-azul-oscuro disabled:opacity-50 dark:border-azul-borde-oscuro">
                    <span>{{ $textoCondicion }}</span>
                </label>
            @endforeach
            @unless (array_key_exists('abrir_recurso', $opcionesCompletitud))
                <p class="text-xs text-slate-500 dark:text-slate-400">La segunda opción se habilita cuando hay un archivo o un enlace.</p>
            @endunless
            @error('condicion_completitud')
                <p class="{{ $claseError }}"><x-icono nombre="alerta" class="h-3.5 w-3.5" /> {{ $message }}</p>
            @enderror
        </fieldset>
    </div>

    <x-slot name="pie">
        <x-gestion.boton variante="secundario" wire:click="cerrarFormularioLeccion">Cancelar</x-gestion.boton>
        <x-gestion.boton icono="check" wire:click="guardarLeccion" wire:loading.attr="disabled" wire:target="guardarLeccion,archivo">Guardar</x-gestion.boton>
    </x-slot>
</x-gestion.modal>
