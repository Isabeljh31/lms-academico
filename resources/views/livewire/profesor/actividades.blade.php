{{--
    Pantallas «Actividades» y «Evaluación» del curso (Profesor), mismo
    componente ($seccion):
    - Actividades: resumen de la nota final, la lista de tareas y quizzes
      (filtros por tipo y evaluación, búsqueda, estado y menú por fila) y el
      Banco de preguntas.
    - Evaluación: la nota final con sus evaluaciones y una tarjeta por cada una
      (actividades que cuentan, notas registradas y qué falta).
    La pestaña, los filtros y la búsqueda viven en el componente (y en la URL).
--}}
@php
    $estiloTipo = [
        'tarea' => ['etiqueta' => 'Tarea', 'icono' => 'check-circulo', 'insignia' => 'bg-azul-oscuro text-white'],
        'quiz' => ['etiqueta' => 'Quiz', 'icono' => 'quiz', 'insignia' => 'bg-celeste text-texto'],
    ];
    $claseEstado = fn (string $estado) => match (true) {
        str_starts_with($estado, 'Publicado') => 'bg-exito-base/15 text-exito-oscuro',
        str_starts_with($estado, 'Programado') => 'bg-amber-100 text-amber-800',
        default => 'bg-celeste text-slate-700',
    };
    // Asistente «¿Qué quieres crear?»: el tipo abre el formulario existente mostrando solo lo necesario (?tipo=).
    $neutra = 'bg-celeste text-slate-700';
    $opcionesCrear = [
        ['etiqueta' => 'Práctica calificada', 'icono' => 'tarea', 'descripcion' => 'Los alumnos suben archivos, solos o en grupo. Cuenta para una evaluación.', 'url' => route('cursos.tareas.crear', [$curso, 'desde' => 'actividades', 'tipo' => 'practica']), 'caja' => 'bg-azul-oscuro/10 text-azul-oscuro'],
        ['etiqueta' => 'Examen o quiz calificado', 'icono' => 'quiz', 'descripcion' => 'Preguntas que se califican solas (o una entrega). Cuenta para una evaluación.', 'url' => route('cursos.quizzes.crear', [$curso, 'desde' => 'actividades', 'tipo' => 'examen']), 'caja' => 'bg-cielo-claro text-imperial'],
        ['etiqueta' => 'Quiz de práctica', 'icono' => 'bombilla', 'descripcion' => 'Para repasar. No cuenta para la nota.', 'url' => route('cursos.quizzes.crear', [$curso, 'desde' => 'actividades', 'tipo' => 'practica']), 'caja' => $neutra],
        ['etiqueta' => 'Entrega sin nota', 'icono' => 'documento', 'descripcion' => 'Solo para que suban material (ppt, informes). No lleva nota.', 'url' => route('cursos.tareas.crear', [$curso, 'desde' => 'actividades', 'tipo' => 'entrega']), 'caja' => $neutra],
    ];
    // Un color por evaluación (en el orden del esquema), igual en la barra, la tarjeta y las insignias de la lista.
    $paletaEvaluaciones = [
        ['barra' => 'bg-azul-oscuro', 'borde' => 'border-t-azul-oscuro', 'relleno' => 'bg-azul-oscuro'],
        ['barra' => 'bg-cielo', 'borde' => 'border-t-cielo', 'relleno' => 'bg-cielo'],
        ['barra' => 'bg-azul-fondo', 'borde' => 'border-t-azul-fondo', 'relleno' => 'bg-azul-fondo'],
        ['barra' => 'bg-imperial', 'borde' => 'border-t-imperial', 'relleno' => 'bg-imperial'],
    ];
    $colorDeEvaluacion = fn (int $rubroId) => $paletaEvaluaciones[max(0, $rubros->search(fn ($rubro) => $rubro->id === $rubroId)) % count($paletaEvaluaciones)];
    // Estado de cada evaluación (App\Services\Evaluacion\AvanceEvaluaciones).
    $estadoEvaluacion = [
        'lista' => ['etiqueta' => 'Lista', 'pildora' => 'bg-exito-base/15 text-exito-oscuro', 'siguiente' => 'bg-exito-base/10 text-exito-oscuro'],
        'en_proceso' => ['etiqueta' => 'En proceso', 'pildora' => 'bg-celeste text-texto', 'siguiente' => 'bg-celeste/70 text-texto'],
        'falta_definir' => ['etiqueta' => 'Falta definir', 'pildora' => 'bg-amber-100 text-amber-800', 'siguiente' => 'bg-amber-50 text-amber-900'],
    ];
    $formatoPeso = fn (float $valor) => rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
    $pildoraDato = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold';
    $listas = collect($avances)->where('estado', 'lista')->count();
    $primeraSinDefinir = $rubros->first(fn ($rubro) => ($avances[$rubro->id]['estado'] ?? null) === 'falta_definir');
    $resumenListas = $listas.' de '.$rubros->count().' '.($rubros->count() === 1 ? 'evaluación lista' : 'evaluaciones listas').' · Sobre 20';
@endphp
{{-- relative: contiene los textos sr-only (position: absolute) para que no estiren la página fuera de la columna con scroll. --}}
<div class="relative py-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-gestion.volver :href="route('cursos.show', $curso)" :texto="$curso->nombre" class="-mb-3" />

        @if ($seccion === 'evaluacion')
            <x-gestion.encabezado titulo="Evaluación" icono="grafico"
                descripcion="Cómo se calcula la nota final del curso. Los pesos los define el supervisor." />
        @else
            <x-gestion.encabezado titulo="Actividades" icono="check-circulo"
                descripcion="Tareas y quizzes del curso en un solo lugar, y el banco de preguntas para armar tus quizzes.">
                @if ($editable)
                    {{-- Asistente sin ida al servidor (Alpine): Esc o clic fuera cierra y el foco vuelve al botón; flechas recorren las tarjetas. --}}
                    <div x-data="{ abierto: false, cerrar() { this.abierto = false; this.$nextTick(() => this.$refs.boton.focus()) } }"
                        x-on:keydown.escape.window="if (abierto) cerrar()">
                        <x-gestion.boton icono="mas" x-ref="boton" aria-haspopup="dialog" aria-controls="asistente-crear" aria-expanded="false" x-bind:aria-expanded="abierto.toString()"
                            x-on:click="abierto = true; $nextTick(() => $focus.within($refs.tarjetas).first())">
                            Crear actividad
                        </x-gestion.boton>
                        <div x-show="abierto" style="display: none" x-on:click.self="cerrar()"
                            x-transition.opacity.duration.150ms
                            class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/50 p-0 sm:items-center sm:p-4">
                            <div id="asistente-crear" role="dialog" aria-modal="true" aria-labelledby="titulo-asistente-crear" x-trap.noscroll="abierto"
                                class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
                                <div class="flex items-start justify-between gap-4 border-b border-celeste px-5 py-4">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-celeste text-azul-oscuro">
                                            <x-icono nombre="mas" class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <h2 id="titulo-asistente-crear" class="text-2xl text-texto">¿Qué quieres crear?</h2>
                                            <p class="mt-1 text-xs text-slate-500">Elige el tipo: el formulario mostrará solo lo necesario.</p>
                                        </div>
                                    </div>
                                    <button type="button" x-on:click="cerrar()" aria-label="Cerrar"
                                        class="shrink-0 rounded-lg p-1 text-slate-400 hover:bg-celeste hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">
                                        <x-icono nombre="cerrar" class="h-5 w-5" />
                                    </button>
                                </div>
                                <ul x-ref="tarjetas" class="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-2"
                                    x-on:keydown.arrow-down.prevent="$focus.within($refs.tarjetas).wrap().next()"
                                    x-on:keydown.arrow-right.prevent="$focus.within($refs.tarjetas).wrap().next()"
                                    x-on:keydown.arrow-up.prevent="$focus.within($refs.tarjetas).wrap().previous()"
                                    x-on:keydown.arrow-left.prevent="$focus.within($refs.tarjetas).wrap().previous()">
                                    @foreach ($opcionesCrear as $opcion)
                                        <li>
                                            <a href="{{ $opcion['url'] }}" wire:navigate
                                                class="flex h-full items-start gap-3 rounded-xl border border-celeste bg-white p-4 transition-colors hover:border-azul-oscuro hover:bg-fondo focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro motion-reduce:transition-none">
                                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $opcion['caja'] }}">
                                                    <x-icono :nombre="$opcion['icono']" stroke-width="2" class="h-6 w-6" />
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block font-semibold text-texto">{{ $opcion['etiqueta'] }}</span>
                                                    <span class="mt-0.5 block text-sm text-slate-600">{{ $opcion['descripcion'] }}</span>
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="border-t border-celeste px-5 py-3 text-xs text-slate-500">
                                    ¿Prefieres el formulario completo?
                                    <a href="{{ route('cursos.tareas.crear', [$curso, 'desde' => 'actividades']) }}" wire:navigate class="font-semibold text-azul-oscuro hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">Tarea</a>
                                    ·
                                    <a href="{{ route('cursos.quizzes.crear', [$curso, 'desde' => 'actividades']) }}" wire:navigate class="font-semibold text-azul-oscuro hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">Quiz</a>
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </x-gestion.encabezado>
        @endif

        @include('livewire.profesor.cursos._pestanas-pildora', ['activa' => $seccion])

        <x-gestion.alertas />

        @if (! $editable)
            <div role="alert" class="flex items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <x-icono nombre="archivo" class="h-5 w-5 shrink-0" />
                <p>Curso archivado (finalizado): se puede consultar, pero no admite cambios.</p>
            </div>
        @endif

        @if ($seccion === 'evaluacion' && $rubros->isEmpty())
            <x-gestion.tarjeta titulo="Nota final del curso">
                <p class="text-sm text-slate-600">El Supervisor todavía no definió las evaluaciones del curso. Cuando lo haga, aquí podrás asignar las actividades que cuentan para cada una.</p>
            </x-gestion.tarjeta>
        @elseif ($seccion === 'evaluacion')
            {{-- ======================= EVALUACIÓN ======================= --}}
            <section aria-labelledby="titulo-nota-final" class="rounded-2xl border border-celeste bg-white p-5 sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="titulo-nota-final" class="flex items-center gap-1.5 text-[28px] text-texto">
                        Nota final del curso
                        <x-gestion.ayuda texto="El Supervisor fijó las evaluaciones y sus pesos. Tú decides qué actividades cuentan y cuánto pesa cada una dentro de la evaluación. Tus alumnos solo ven su nota." />
                    </h2>
                    <p class="text-sm text-slate-500">{{ $resumenListas }}</p>
                </div>
                {{-- Barra proporcional a los pesos, con el nombre, peso, semana y estado de cada evaluación debajo. --}}
                <ol class="mt-4 flex flex-col gap-4 sm:flex-row sm:gap-1">
                    @foreach ($rubros as $rubro)
                        @php $avance = $avances[$rubro->id]; $color = $colorDeEvaluacion($rubro->id); @endphp
                        <li wire:key="segmento-{{ $rubro->id }}" style="flex: {{ (float) $rubro->peso }} 1 0%" class="min-w-0">
                            <span class="block h-3.5 rounded-full {{ $avance['estado'] === 'falta_definir' ? 'bg-celeste' : $color['barra'] }}" aria-hidden="true"></span>
                            <p class="mt-2.5 truncate text-sm font-semibold text-texto">{{ $rubro->nombre }}</p>
                            <p class="text-sm text-slate-500">{{ (float) $rubro->peso }} % · {{ $rubro->semana ? 'Semana '.$rubro->semana : 'Sin semana' }}</p>
                            <span class="mt-1.5 {{ $pildoraDato }} {{ $estadoEvaluacion[$avance['estado']]['pildora'] }}">{{ $estadoEvaluacion[$avance['estado']]['etiqueta'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <ul class="grid gap-5 md:grid-cols-2">
                @foreach ($rubros as $rubro)
                    @php
                        $color = $colorDeEvaluacion($rubro->id);
                        $avance = $avances[$rubro->id];
                        $estado = $estadoEvaluacion[$avance['estado']];
                        $faltaDefinir = $avance['estado'] === 'falta_definir';
                        // Las prácticas de campo se muestran agrupadas en una línea (su tramo), no una por una.
                        $idsPracticas = $rubro->tareas->whereNotNull('practica')->modelKeys();
                        $vinculadas = $rubro->actividadesVinculadas()->reject(fn (array $vinculada) => $vinculada['tipo'] === 'tarea' && in_array($vinculada['id'], $idsPracticas, true))->values();
                        $cantidadActividades = $vinculadas->count() + count($idsPracticas);
                        $registradas = max(0, $avance['total'] - $avance['faltan']);
                        $porcentajeNotas = $avance['total'] > 0 ? (int) round($registradas / $avance['total'] * 100) : 0;
                        $siguiente = match (true) {
                            $faltaDefinir && $vinculadas->isNotEmpty() => 'Ajusta los pesos de sus actividades para que sumen 100 %.',
                            $faltaDefinir => 'Asigna una práctica o un examen para que esta evaluación tenga nota. Sin eso, no aparecerá en la nota final.',
                            $avance['estado'] === 'lista' => 'Todos los alumnos tienen nota.',
                            $avance['practicas'] > 0 => 'Prepara '.($avance['practicas'] === 1 ? 'la práctica de campo que falta' : "las {$avance['practicas']} prácticas de campo que faltan").' del tramo: hasta entonces esta evaluación no tiene nota.',
                            $avance['total'] === 0 => 'Cuando haya alumnos matriculados podrás registrar sus notas.',
                            $rubro->esAutomatico() => "Faltan {$avance['faltan']} ".($avance['faltan'] === 1 ? 'nota' : 'notas').'. Califica las entregas pendientes; quien no entregue una tarea vencida queda con 0 en su parte.',
                            default => "Faltan {$avance['faltan']} ".($avance['faltan'] === 1 ? 'nota' : 'notas').'. Regístralas en el libro de calificaciones.',
                        };
                    @endphp
                    <li wire:key="evaluacion-{{ $rubro->id }}"
                        class="flex flex-col gap-4 rounded-2xl border border-t-4 border-celeste bg-white p-5 {{ $faltaDefinir ? 'border-t-celeste' : $color['borde'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-2">
                                <h3 class="font-serif text-[28px] leading-tight text-texto">{{ $rubro->nombre }}</h3>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="{{ $pildoraDato }} {{ $estado['pildora'] }}">{{ $estado['etiqueta'] }}</span>
                                    @if ($rubro->modo_calculo)
                                        <span class="{{ $pildoraDato }} bg-fondo text-slate-700 ring-1 ring-inset ring-celeste">{{ $rubro->modo_calculo->etiqueta() }}</span>
                                    @endif
                                    <span class="{{ $pildoraDato }} bg-fondo text-slate-700 ring-1 ring-inset ring-celeste">{{ $rubro->semana ? 'Semana '.$rubro->semana : 'Sin semana' }}</span>
                                </div>
                            </div>
                            <p class="shrink-0 text-right">
                                <span class="block text-[40px] font-bold leading-none tabular-nums text-texto">{{ (float) $rubro->peso }} %</span>
                                <span class="text-sm text-slate-500">de la nota final</span>
                            </p>
                        </div>

                        @if (! $faltaDefinir && $avance['total'] > 0)
                            <div>
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span class="font-semibold text-texto">{{ $registradas }} de {{ $avance['total'] }} notas registradas</span>
                                    <span class="text-slate-500">{{ $porcentajeNotas }} %</span>
                                </div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-celeste" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $porcentajeNotas }}" aria-label="Notas registradas de {{ $rubro->nombre }}">
                                    <div class="h-full rounded-full {{ $color['relleno'] }}" style="width: {{ $porcentajeNotas }}%"></div>
                                </div>
                            </div>
                        @endif

                        <div class="border-t border-celeste pt-4">
                            <p class="rotulo mb-2.5 text-slate-500">Actividades que cuentan y su peso</p>
                            @if ($rubro->tienePracticas())
                                <div class="mb-2.5 space-y-1">
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="shrink-0 rounded-md bg-exito-base/15 px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-exito-oscuro">Campo</span>
                                        <span class="min-w-0 flex-1 truncate text-texto">Prácticas de campo {{ $rubro->practica_desde }}{{ $rubro->practica_hasta !== $rubro->practica_desde ? '–'.$rubro->practica_hasta : '' }}</span>
                                        <span class="shrink-0 font-semibold tabular-nums text-texto">{{ $formatoPeso((float) $rubro->peso_practicas) }} %</span>
                                    </div>
                                    <p class="text-xs text-slate-500">
                                        Cada práctica vale {{ $formatoPeso((float) $rubro->peso_practicas / $rubro->cantidadPracticas()) }} % · {{ count($idsPracticas) }} de {{ $rubro->cantidadPracticas() }} con entrega preparada
                                    </p>
                                </div>
                            @endif
                            @if ($vinculadas->isNotEmpty())
                                <ul class="space-y-2.5" aria-label="Actividades de {{ $rubro->nombre }}">
                                    @foreach ($vinculadas as $vinculada)
                                        <li wire:key="vinculada-{{ $rubro->id }}-{{ $vinculada['tipo'] }}-{{ $vinculada['id'] }}" class="flex items-center gap-2.5 text-[15px]">
                                            <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $estiloTipo[$vinculada['tipo']]['insignia'] }}">{{ $estiloTipo[$vinculada['tipo']]['etiqueta'] }}</span>
                                            <span class="min-w-0 flex-1 truncate text-texto">{{ $vinculada['titulo'] }}</span>
                                            <span class="shrink-0 font-semibold tabular-nums text-texto">{{ $formatoPeso($vinculada['peso']) }} %</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif (! $rubro->tienePracticas())
                                <p class="rounded-xl border border-dashed border-celeste px-4 py-3.5 text-center text-sm text-slate-600">
                                    {{ $faltaDefinir ? 'Aún no hay actividades que cuenten para esta evaluación.' : 'Su nota la escribes tú en el libro de calificaciones.' }}
                                </p>
                            @endif
                        </div>

                        <p class="flex items-start gap-2 rounded-xl px-4 py-3 text-sm {{ $estado['siguiente'] }}">
                            <x-icono nombre="flecha-derecha" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span><span class="font-semibold">Siguiente:</span> {{ $siguiente }}</span>
                        </p>

                        <div class="mt-auto flex flex-wrap gap-2">
                            @if ($editable)
                                <x-gestion.boton :variante="$faltaDefinir ? 'primario' : 'secundario'" :icono="$faltaDefinir ? 'mas' : 'lapiz'"
                                    wire:click="configurarCalculo({{ $rubro->id }})" wire:loading.attr="disabled" wire:target="configurarCalculo({{ $rubro->id }})"
                                    aria-label="{{ $faltaDefinir ? 'Asignar actividades a' : 'Cambiar reparto de' }} {{ $rubro->nombre }}">
                                    {{ $faltaDefinir ? 'Asignar actividades' : 'Cambiar reparto' }}
                                </x-gestion.boton>
                            @endif
                            <x-gestion.boton variante="secundario" :href="route('cursos.libro-calificaciones', $curso)">
                                {{ $editable && ! $rubro->esAutomatico() ? 'Registrar notas' : 'Ver notas' }}
                            </x-gestion.boton>
                            @if ($cantidadActividades > 0)
                                <x-gestion.boton variante="fantasma" :href="route('cursos.actividades', [$curso, 'evaluacion' => $rubro->id])">Ver actividades ({{ $cantidadActividades }})</x-gestion.boton>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($curso->tienePracticasCampo())
                <livewire:compartido.cursos.practicas-evaluaciones :curso="$curso" :editable="$editable" :key="'practicas-evaluaciones-'.$curso->id" />
            @endif
        @else
            {{-- ======================= ACTIVIDADES ======================= --}}
            @if ($rubros->isNotEmpty())
            <section aria-labelledby="titulo-nota-final" class="flex flex-col gap-4 rounded-2xl border border-celeste bg-white px-5 py-4 lg:flex-row lg:items-center">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="titulo-nota-final" class="font-sans text-[15px] font-semibold text-texto">Nota final del curso</h2>
                        <p class="text-sm text-slate-500">{{ $resumenListas }}</p>
                    </div>
                    <div class="mt-2 flex h-2.5 gap-1" aria-hidden="true">
                        @foreach ($rubros as $rubro)
                            <span wire:key="barra-{{ $rubro->id }}" style="flex: {{ (float) $rubro->peso }} 1 0%"
                                class="rounded-full {{ $avances[$rubro->id]['estado'] === 'falta_definir' ? 'bg-celeste' : $colorDeEvaluacion($rubro->id)['barra'] }}"></span>
                        @endforeach
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($primeraSinDefinir)
                        <span class="{{ $pildoraDato }} bg-amber-100 py-1 text-sm text-amber-800">{{ $primeraSinDefinir->nombre }} sin actividades</span>
                    @endif
                    <x-gestion.boton variante="secundario" :href="route('cursos.evaluacion', $curso)">Ver evaluación <x-icono nombre="flecha-derecha" class="h-4 w-4" /></x-gestion.boton>
                </div>
            </section>
            @endif

            <div x-data="{
                    pestanas: @js(array_keys(\App\Livewire\Profesor\Actividades::PESTANAS)),
                    get activa() { return this.$wire.pestana },
                    elegir(clave, enfocar = false) {
                        this.$wire.pestana = clave;
                        if (enfocar) this.$nextTick(() => document.getElementById('pestana-' + clave)?.focus());
                    },
                    mover(paso) {
                        const i = this.pestanas.indexOf(this.activa);
                        this.elegir(this.pestanas[(i + paso + this.pestanas.length) % this.pestanas.length], true);
                    },
                }">
                <div role="tablist" aria-label="Apartados de Actividades" class="flex gap-6 border-b border-celeste"
                    x-on:keydown.arrow-right.prevent="mover(1)" x-on:keydown.arrow-left.prevent="mover(-1)"
                    x-on:keydown.home.prevent="elegir(pestanas[0], true)" x-on:keydown.end.prevent="elegir(pestanas[pestanas.length - 1], true)">
                    @foreach (\App\Livewire\Profesor\Actividades::PESTANAS as $clave => $etiqueta)
                        @php $esActiva = $pestana === $clave; @endphp
                        <button type="button" role="tab" id="pestana-{{ $clave }}" aria-controls="panel-{{ $clave }}"
                            aria-selected="{{ $esActiva ? 'true' : 'false' }}" x-bind:aria-selected="(activa === '{{ $clave }}').toString()"
                            tabindex="{{ $esActiva ? '0' : '-1' }}" x-bind:tabindex="activa === '{{ $clave }}' ? 0 : -1"
                            x-on:click="elegir('{{ $clave }}')"
                            @class(['-mb-px inline-flex items-center gap-2 border-b-2 py-2.5 text-[15px] transition-colors motion-reduce:transition-none focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro',
                                'border-texto font-semibold text-texto' => $esActiva,
                                'border-transparent text-slate-600 hover:text-texto' => ! $esActiva])
                            x-bind:class="{
                                'border-texto font-semibold text-texto': activa === '{{ $clave }}',
                                'border-transparent font-normal text-slate-600 hover:text-texto': activa !== '{{ $clave }}',
                            }">
                            <x-icono :nombre="$clave === 'banco' ? 'base-datos' : 'lista'" class="h-[18px] w-[18px]" />
                            {{ $etiqueta }}
                        </button>
                    @endforeach
                </div>

                {{-- Pestaña Actividades --}}
                <div id="panel-actividades" role="tabpanel" aria-labelledby="pestana-actividades" tabindex="0"
                    x-show="activa === 'actividades'" @unless ($pestana === 'actividades') style="display: none" @endunless
                    class="space-y-4 pt-5 focus:outline-none">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div role="group" aria-label="Filtrar actividades" class="flex flex-wrap gap-2">
                            @foreach (\App\Livewire\Profesor\Actividades::FILTROS as $clave => $etiqueta)
                                @php $cantidad = match ($clave) { 'tareas' => $totales['tareas'], 'quizzes' => $totales['quizzes'], 'sin_evaluacion' => $totales['sin_evaluacion'], default => $totales['tareas'] + $totales['quizzes'] }; @endphp
                                <button type="button" wire:click="filtrar('{{ $clave }}')" aria-pressed="{{ $tipo === $clave ? 'true' : 'false' }}"
                                    @class(['inline-flex min-h-10 items-center gap-1.5 rounded-full border px-4 text-sm font-semibold transition-colors motion-reduce:transition-none focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro',
                                        'border-texto bg-texto text-white' => $tipo === $clave,
                                        'border-celeste bg-white text-slate-700 hover:border-texto/40' => $tipo !== $clave])>
                                    {{ $etiqueta }} <span class="font-normal opacity-70">{{ $cantidad }}</span>
                                </button>
                            @endforeach
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label class="flex min-h-11 items-center gap-2 rounded-lg border border-celeste bg-white pl-3 text-sm focus-within:ring-2 focus-within:ring-azul-oscuro sm:w-64">
                                <span class="shrink-0 text-slate-500">Evaluación</span>
                                <select wire:model.live="filtroEvaluacion" class="min-w-0 flex-1 border-0 bg-transparent py-2 pl-1 pr-8 text-sm text-texto focus:ring-0">
                                    <option value="">Todas</option>
                                    @foreach ($rubros as $rubro)
                                        <option value="{{ $rubro->id }}">{{ $rubro->nombre }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="sm:w-72">
                                <label for="busqueda-actividades" class="sr-only">Buscar actividades</label>
                                <x-gestion.control id="busqueda-actividades" type="search" icono="lupa" wire:model.live.debounce.300ms="busqueda" placeholder="Buscar por título" class="min-h-11" />
                            </div>
                        </div>
                    </div>

                    @if ($actividades->isEmpty())
                        <x-gestion.vacio icono="tarea"
                            :titulo="trim($busqueda) !== '' || $tipo !== 'todas' || $filtroEvaluacion !== '' ? 'Ninguna actividad coincide con los filtros' : 'Todavía no hay actividades en este curso'"
                            :descripcion="trim($busqueda) !== '' || $tipo !== 'todas' || $filtroEvaluacion !== '' ? 'Prueba con otro tipo, evaluación o texto de búsqueda.' : 'Crea una tarea o un quiz con el botón «Crear actividad».'" />
                    @else
                        <ul class="divide-y divide-celeste overflow-hidden rounded-2xl border border-celeste bg-white">
                            @foreach ($actividades as $fila)
                                @php
                                    $estilo = $estiloTipo[$fila['tipo']];
                                    $fecha = $fila['fecha'];
                                    $esQuiz = $fila['tipo'] === 'quiz';
                                    $prefijoFecha = $esQuiz ? ($fecha?->isPast() ? 'Cerró' : 'Cierra') : 'Vence';
                                    // Días de calendario hasta el cierre (0 = hoy); null si ya cerró.
                                    $diasRestantes = $fecha?->isFuture() ? (int) now()->startOfDay()->diffInDays($fecha->copy()->startOfDay()) : null;
                                @endphp
                                <li wire:key="actividad-{{ $fila['tipo'] }}-{{ $fila['id'] }}" class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center lg:gap-6">
                                    <div class="flex min-w-0 flex-1 items-start gap-3.5">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-fondo text-texto" aria-hidden="true">
                                            <x-icono :nombre="$estilo['icono']" class="h-6 w-6" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-md px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $estilo['insignia'] }}">{{ $estilo['etiqueta'] }}</span>
                                                <a href="{{ $fila['urlVer'] }}" wire:navigate class="font-semibold text-texto hover:underline">{{ $fila['titulo'] }}</a>
                                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $claseEstado($fila['estado']) }}">{{ $fila['estado'] }}</span>
                                            </div>
                                            <p class="mt-1 text-sm text-slate-500">
                                                {{ collect([
                                                    $fecha ? $prefijoFecha.' '.$fecha->formatoFechaHora() : 'Sin fecha límite',
                                                    $esQuiz ? $fila['dato'] : null,
                                                    $fila['semana'],
                                                ])->filter()->join(' · ') }}
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Estado: lo que falta calificar, cuánto falta para el cierre o al día. --}}
                                    <div class="shrink-0 pl-[62px] lg:w-44 lg:pl-0">
                                        @if ($fila['porCalificar'] > 0)
                                            <a href="{{ $fila['urlCalificar'] }}" wire:navigate
                                                class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-celeste bg-white px-4 text-sm font-semibold text-texto hover:border-texto/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">
                                                <span class="text-terracota-oscuro">{{ $fila['porCalificar'] }}</span> por calificar
                                            </a>
                                        @elseif ($diasRestantes !== null)
                                            <span class="text-sm text-slate-500">{{ $esQuiz ? 'Cierra' : 'Vence' }} {{ match ($diasRestantes) { 0 => 'hoy', 1 => 'mañana', default => "en {$diasRestantes} días" } }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-exito-oscuro"><x-icono nombre="check-circulo" class="h-4 w-4" /> Al día</span>
                                        @endif
                                    </div>

                                    {{-- Evaluación a la que aporta (o asignarla). --}}
                                    <div class="flex shrink-0 items-center gap-1.5 pl-[62px] lg:w-64 lg:pl-0">
                                        @if ($fila['evaluacion'])
                                            <span class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border border-celeste bg-white px-3.5 py-1.5 text-sm font-semibold text-texto"
                                                title="Aporta {{ $formatoPeso($fila['evaluacion']['aporte']) }} % de la nota final">
                                                <span class="h-2.5 w-2.5 rounded-sm {{ $colorDeEvaluacion($fila['evaluacion']['id'])['relleno'] }}" aria-hidden="true"></span>
                                                {{ $fila['evaluacion']['nombre'] }} · {{ $fila['evaluacion']['examen'] ? 'Examen' : $formatoPeso($fila['evaluacion']['peso']).' %' }}
                                            </span>
                                        @elseif ($editable && $rubros->isNotEmpty())
                                            <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false" x-on:keydown.escape.stop="abierto = false">
                                                <button type="button" x-on:click="abierto = ! abierto" aria-haspopup="menu" x-bind:aria-expanded="abierto.toString()" aria-expanded="false"
                                                    aria-label="{{ $fila['titulo'] }} no cuenta para la nota. Asignarla a una evaluación"
                                                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border border-dashed border-slate-300 px-3.5 py-1.5 text-sm font-semibold text-slate-600 hover:border-texto hover:text-texto focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">
                                                    No cuenta para la nota · Asignar <x-icono nombre="flecha-abajo" class="h-3.5 w-3.5" />
                                                </button>
                                                <div x-show="abierto" style="display: none" role="menu" aria-label="Asignar {{ $fila['titulo'] }} a una evaluación"
                                                    class="absolute right-0 z-30 mt-1 w-60 rounded-xl border border-celeste bg-white p-1.5 shadow-lg">
                                                    @foreach ($rubros as $rubro)
                                                        <button type="button" role="menuitem" wire:key="asignar-{{ $fila['tipo'] }}-{{ $fila['id'] }}-{{ $rubro->id }}"
                                                            wire:click="configurarCalculo({{ $rubro->id }}, '{{ $fila['tipo'] }}-{{ $fila['id'] }}')" x-on:click="abierto = false"
                                                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-texto hover:bg-fondo focus:bg-fondo focus:outline-none">
                                                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm {{ $colorDeEvaluacion($rubro->id)['relleno'] }}" aria-hidden="true"></span>
                                                            <span class="flex-1">{{ $rubro->nombre }}</span>
                                                            <span class="text-xs text-slate-500">{{ (float) $rubro->peso }} %</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            <span class="whitespace-nowrap rounded-full border border-dashed border-slate-300 px-3.5 py-1.5 text-sm font-semibold text-slate-500">No cuenta para la nota</span>
                                        @endif
                                    </div>

                                    {{-- Menú ⋯: ver, editar y eliminar. --}}
                                    <div class="relative shrink-0 self-end lg:self-auto" x-data="{ abierto: false }" x-on:click.outside="abierto = false" x-on:keydown.escape.stop="abierto = false; $refs.botonMenu.focus()">
                                        <button type="button" x-ref="botonMenu" x-on:click="abierto = ! abierto" aria-haspopup="menu" aria-expanded="false" x-bind:aria-expanded="abierto.toString()"
                                            aria-label="Opciones de {{ $fila['titulo'] }}"
                                            class="flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-fondo hover:text-texto focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">
                                            <x-icono nombre="puntos" class="h-5 w-5" />
                                        </button>
                                        <div x-show="abierto" style="display: none" role="menu"
                                            class="absolute right-0 z-30 mt-1 w-56 rounded-xl border border-celeste bg-white p-1.5 shadow-lg">
                                            <a href="{{ $fila['urlVer'] }}" wire:navigate role="menuitem" class="flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm text-texto hover:bg-fondo focus:bg-fondo focus:outline-none">
                                                <x-icono nombre="ojo" class="h-4 w-4" /> {{ $esQuiz ? 'Ver quiz' : 'Ver entregas' }}
                                            </a>
                                            @if ($editable)
                                                <a href="{{ $fila['urlEditar'] }}" wire:navigate role="menuitem" class="flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm text-texto hover:bg-fondo focus:bg-fondo focus:outline-none">
                                                    <x-icono nombre="lapiz" class="h-4 w-4" /> Editar
                                                </a>
                                                @if ($fila['puedeEliminarse'])
                                                    <button type="button" role="menuitem" wire:click="confirmarEliminar('{{ $fila['tipo'] }}', {{ $fila['id'] }})" x-on:click="abierto = false"
                                                        class="flex min-h-10 w-full items-center gap-2 rounded-lg px-3 text-left text-sm text-red-700 hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                                                        <x-icono nombre="papelera" class="h-4 w-4" /> Eliminar
                                                    </button>
                                                @else
                                                    <p class="flex items-start gap-2 px-3 py-2 text-xs text-slate-500">
                                                        <x-icono nombre="papelera" class="h-4 w-4 shrink-0" /> {{ $fila['motivoNoEliminar'] }}
                                                    </p>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <div>{{ $actividades->links() }}</div>
                    @endif
                </div>

                {{-- Pestaña Banco de preguntas (componente propio: su estado no se pierde al cambiar de pestaña). --}}
                <div id="panel-banco" role="tabpanel" aria-labelledby="pestana-banco" tabindex="0"
                    x-show="activa === 'banco'" @unless ($pestana === 'banco') style="display: none" @endunless
                    class="pt-5 focus:outline-none">
                    <livewire:profesor.banco-preguntas :curso="$curso" :incrustado="true" :key="'banco-'.$curso->id" />
                </div>
            </div>
        @endif
    </div>

    @if ($mostrarConfirmacion)
        <x-gestion.modal titulo="Eliminar actividad" icono="papelera" cerrar="cerrarConfirmacion()">
            <p class="text-sm text-slate-700 dark:text-slate-300">
                Se eliminará {{ $eliminarTipo === 'tarea' ? 'la tarea' : 'el quiz' }} <span class="font-semibold">"{{ $eliminarTitulo }}"</span>.
                Los estudiantes dejarán de verlo. Esta acción no se puede deshacer.
            </p>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="cerrarConfirmacion">Cancelar</x-gestion.boton>
                <x-gestion.boton variante="peligro" icono="papelera" wire:click="eliminar">Eliminar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarCalculo)
        @php
            $rubroConfigurado = $rubros->firstWhere('id', $rubroCalculoId);
            $disponibles = $tareasDisponibles->map(fn ($tarea) => ['tipo' => 'tarea', 'modelo' => $tarea, 'titulo' => $tarea->titulo])
                ->concat($quizzesDisponibles->map(fn ($quiz) => ['tipo' => 'quiz', 'modelo' => $quiz, 'titulo' => $quiz->tema]));
            $enOtraEvaluacion = fn ($modelo) => $modelo->rubrosEvaluacion->first(fn ($rubro) => $rubro->id !== $rubroCalculoId);
            // Parte de la evaluación reservada a prácticas de campo (se reparte en su propio panel).
            $conPracticas = (bool) $rubroConfigurado?->tienePracticas();
            $pesoPracticas = $conPracticas ? (float) $rubroConfigurado->peso_practicas : 0.0;
            $puedeGuardar = match ($modoCalculo) {
                'promedio' => $totalCalculo['estado'] === 'ok',
                'examen' => $examenCalculo !== null,
                default => true,
            };
            $estiloTotal = [
                'ok' => ['barra' => 'bg-exito-base', 'texto' => 'text-exito-oscuro dark:text-green-300', 'icono' => 'check-circulo'],
                'aviso' => ['barra' => 'bg-amber-500', 'texto' => 'text-amber-800 dark:text-amber-300', 'icono' => 'alerta'],
                'error' => ['barra' => 'bg-red-500', 'texto' => 'text-red-700 dark:text-red-300', 'icono' => 'alerta'],
            ];
        @endphp
        <x-gestion.modal :titulo="'Cálculo de '.($rubroConfigurado?->nombre ?? 'evaluación')" cerrar="$set('mostrarCalculo', false)" ancho="max-w-2xl" icono="engranaje"
            :descripcion="(float) $rubroConfigurado?->peso.' % de la nota final · '.($rubroConfigurado?->semana ? 'Semana '.$rubroConfigurado->semana : 'Sin semana').' · Sobre 20'">
            <form wire:submit="guardarCalculo" class="space-y-5">
                <fieldset class="space-y-2.5">
                    <legend class="mb-2.5 flex items-center gap-2 text-sm font-semibold text-texto dark:text-slate-100">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-celeste text-[11px] font-extrabold text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo" aria-hidden="true">1</span>
                        ¿Cómo se obtiene la nota?
                    </legend>
                    <div class="grid gap-2.5 sm:grid-cols-3">
                        @foreach (\App\Enums\ModoCalculo::cases() as $modo)
                            @php $bloqueado = $conPracticas && $modo !== \App\Enums\ModoCalculo::Promedio; @endphp
                            <label wire:key="modo-{{ $modo->value }}" @class(['relative flex flex-col gap-0.5 rounded-xl border p-3 transition-colors motion-reduce:transition-none focus-within:ring-2 focus-within:ring-azul-oscuro',
                                'cursor-pointer' => ! $bloqueado,
                                'cursor-not-allowed opacity-50' => $bloqueado,
                                'border-azul-oscuro bg-azul-oscuro/5 ring-1 ring-azul-oscuro dark:border-azul-fondo dark:bg-azul-fondo/10 dark:ring-azul-fondo' => $modoCalculo === $modo->value,
                                'border-celeste hover:border-azul-oscuro/50 dark:border-azul-borde-oscuro' => $modoCalculo !== $modo->value && ! $bloqueado,
                                'border-celeste dark:border-azul-borde-oscuro' => $bloqueado])>
                                <input type="radio" class="sr-only" wire:model.live="modoCalculo" value="{{ $modo->value }}" @disabled($bloqueado)>
                                <span class="text-sm font-semibold text-texto dark:text-slate-100">{{ $modo->etiqueta() }}</span>
                                <span class="text-xs leading-snug text-slate-500 dark:text-slate-400">{{ $modo->descripcion() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if ($conPracticas)
                        <p class="text-xs text-slate-500 dark:text-slate-400">Incluye prácticas de campo, así que solo se calcula como promedio.</p>
                    @endif
                    @error('modoCalculo') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                </fieldset>

                @if ($modoCalculo === 'manual')
                    <div class="space-y-1 rounded-xl bg-fondo px-4 py-3 text-sm text-slate-600 dark:bg-azul-pagina-oscura dark:text-slate-300">
                        <p>Registrarás la nota de cada estudiante (0 a 20) en el libro de calificaciones.</p>
                        <p>Las actividades vinculadas se liberan al guardar.</p>
                    </div>
                @elseif ($modoCalculo === 'promedio')
                    <fieldset class="space-y-2.5">
                        <legend class="mb-2.5 flex items-center gap-2 text-sm font-semibold text-texto dark:text-slate-100">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-celeste text-[11px] font-extrabold text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo" aria-hidden="true">2</span>
                            Elige las actividades y su peso
                            <x-gestion.ayuda texto="Se usa la nota de la entrega calificada y el mejor intento de cada quiz. La evaluación queda pendiente hasta que todas tengan nota." />
                        </legend>
                        @if ($disponibles->isEmpty())
                            <p class="rounded-lg border border-dashed border-celeste px-3 py-2.5 text-sm text-slate-600 dark:border-azul-borde-oscuro dark:text-slate-300">Todavía no hay tareas ni quizzes en el curso. Créalos con «Crear actividad».</p>
                        @else
                            <ul class="max-h-72 divide-y divide-celeste overflow-y-auto rounded-xl border border-celeste dark:divide-azul-borde-oscuro dark:border-azul-borde-oscuro">
                                @if ($conPracticas)
                                    <li wire:key="calculo-practicas" class="flex items-center gap-3 bg-fondo px-3 py-2.5 dark:bg-azul-pagina-oscura">
                                        <x-icono nombre="info" class="h-4 w-4 shrink-0 text-slate-400" />
                                        <div class="min-w-0 flex-1">
                                            <span class="flex items-center gap-1.5 text-sm font-medium text-texto dark:text-slate-100">
                                                <span class="shrink-0 rounded-md bg-exito-base/15 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-exito-oscuro">Campo</span>
                                                <span class="truncate">Prácticas de campo {{ $rubroConfigurado->practica_desde }}{{ $rubroConfigurado->practica_hasta !== $rubroConfigurado->practica_desde ? '–'.$rubroConfigurado->practica_hasta : '' }}</span>
                                            </span>
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">Reservado en el panel de prácticas. Las demás actividades reparten el resto.</span>
                                        </div>
                                        <span class="shrink-0 text-sm font-semibold tabular-nums text-texto dark:text-slate-100">{{ $formatoPeso($pesoPracticas) }} %</span>
                                    </li>
                                @endif
                                @foreach ($disponibles as $disponible)
                                    @php
                                        $clave = $disponible['tipo'].'-'.$disponible['modelo']->id;
                                        $otra = $enOtraEvaluacion($disponible['modelo']);
                                        $marcada = array_key_exists($clave, $pesosCalculo);
                                    @endphp
                                    <li wire:key="calculo-{{ $clave }}" @class(['flex items-center gap-3 px-3 py-2.5', 'opacity-60' => $otra])>
                                        <input type="checkbox" id="calculo-{{ $clave }}" value="{{ $disponible['modelo']->id }}" @disabled($otra)
                                            wire:model.live="{{ $disponible['tipo'] === 'tarea' ? 'tareasCalculo' : 'quizzesCalculo' }}"
                                            class="h-4 w-4 rounded border-slate-300 text-azul-oscuro focus:ring-azul-oscuro">
                                        <label for="calculo-{{ $clave }}" class="min-w-0 flex-1 cursor-pointer">
                                            <span class="flex items-center gap-1.5 text-sm font-medium text-texto dark:text-slate-100">
                                                <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $estiloTipo[$disponible['tipo']]['insignia'] }}">{{ $estiloTipo[$disponible['tipo']]['etiqueta'] }}</span>
                                                <span class="truncate">{{ $disponible['titulo'] }}</span>
                                            </span>
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                {{ $disponible['modelo']->modulo?->nombreCompleto() ?? 'Sin semana' }}
                                                @if ($marcada && ! str_starts_with($disponible['modelo']->etiquetaEstadoPublicacion(), 'Publicado')) · Aún no está publicada @endif
                                            </span>
                                        </label>
                                        @if ($otra)
                                            <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">Ya cuenta para {{ $otra->nombre }}</span>
                                        @elseif ($marcada)
                                            <div class="shrink-0 text-right">
                                                <span @class(['inline-flex items-center overflow-hidden rounded-lg border bg-white dark:bg-azul-superficie-oscura', 'border-red-500' => $errors->has("pesosCalculo.$clave"), 'border-celeste dark:border-azul-borde-oscuro' => ! $errors->has("pesosCalculo.$clave")])>
                                                    <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="pesosCalculo.{{ $clave }}" aria-label="Peso de {{ $disponible['titulo'] }} en porcentaje"
                                                        class="w-16 border-0 bg-transparent py-1.5 pl-2 pr-1 text-right text-sm font-semibold tabular-nums text-texto focus:ring-0 dark:text-slate-100">
                                                    <span class="pr-2 text-sm text-slate-500" aria-hidden="true">%</span>
                                                </span>
                                                @error("pesosCalculo.$clave") <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @php $estilo = $estiloTotal[$totalCalculo['estado']]; @endphp
                        <div class="space-y-2 rounded-xl bg-fondo p-3 dark:bg-azul-pagina-oscura">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600 dark:text-slate-300">
                                @if ($conPracticas)
                                    <span>Actividades <span class="text-base font-bold tabular-nums text-texto dark:text-slate-100">{{ $formatoPeso($totalCalculo['total']) }} %</span> + prácticas {{ $formatoPeso($pesoPracticas) }} % = <span class="font-semibold tabular-nums text-texto dark:text-slate-100">{{ $formatoPeso($totalCalculo['total'] + $pesoPracticas) }} %</span> de 100 %</span>
                                @else
                                    <span>Total <span class="text-base font-bold tabular-nums text-texto dark:text-slate-100">{{ $formatoPeso($totalCalculo['total']) }} %</span> de 100 %</span>
                                @endif
                                <x-gestion.boton variante="fantasma" tamano="sm" wire:click="repartirPesos" :disabled="$pesosCalculo === []">Repartir en partes iguales</x-gestion.boton>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-celeste dark:bg-azul-borde-oscuro" aria-hidden="true">
                                <div class="h-full rounded-full transition-[width] motion-reduce:transition-none {{ $estilo['barra'] }}" style="width: {{ min(100, $totalCalculo['total'] + $pesoPracticas) }}%"></div>
                            </div>
                            <p role="status" class="flex items-center gap-1.5 text-sm font-medium {{ $estilo['texto'] }}">
                                <x-icono :nombre="$estilo['icono']" class="h-4 w-4 shrink-0" /> {{ $totalCalculo['mensaje'] }}
                            </p>
                        </div>
                    </fieldset>
                @else
                    <fieldset class="space-y-2.5">
                        <legend class="mb-2.5 flex items-center gap-2 text-sm font-semibold text-texto dark:text-slate-100">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-celeste text-[11px] font-extrabold text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo" aria-hidden="true">2</span>
                            Elige el examen
                            <x-gestion.ayuda texto="Un quiz o una tarea que vale el 100 % de esta evaluación. En un quiz se usa el mejor intento." />
                        </legend>
                        @if ($disponibles->isNotEmpty())
                            <ul class="max-h-64 divide-y divide-celeste overflow-y-auto rounded-xl border border-celeste dark:divide-azul-borde-oscuro dark:border-azul-borde-oscuro">
                                @foreach ($disponibles->sortBy(fn ($disponible) => $disponible['tipo'] === 'quiz' ? 0 : 1) as $disponible)
                                    @php
                                        $clave = $disponible['tipo'].'-'.$disponible['modelo']->id;
                                        $otra = $enOtraEvaluacion($disponible['modelo']);
                                    @endphp
                                    <li wire:key="examen-{{ $clave }}" @class(['flex items-center gap-3 px-3 py-2.5', 'opacity-60' => $otra])>
                                        <input type="radio" id="examen-{{ $clave }}" value="{{ $clave }}" wire:model.live="examenCalculo" @disabled($otra)
                                            class="h-4 w-4 border-slate-300 text-azul-oscuro focus:ring-azul-oscuro">
                                        <label for="examen-{{ $clave }}" class="min-w-0 flex-1 cursor-pointer">
                                            <span class="flex items-center gap-1.5 text-sm font-medium text-texto dark:text-slate-100">
                                                <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $estiloTipo[$disponible['tipo']]['insignia'] }}">{{ $estiloTipo[$disponible['tipo']]['etiqueta'] }}</span>
                                                <span class="truncate">{{ $disponible['titulo'] }}</span>
                                            </span>
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $disponible['modelo']->modulo?->nombreCompleto() ?? 'Sin semana' }} · {{ $disponible['modelo']->etiquetaEstadoPublicacion() }}</span>
                                        </label>
                                        <span class="shrink-0 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $otra ? 'Ya cuenta para '.$otra->nombre : ($examenCalculo === $clave ? '100 %' : '') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-dashed border-celeste px-3 py-2.5 text-sm text-slate-600 dark:border-azul-borde-oscuro dark:text-slate-300">
                            <span>¿Aún no lo creas?</span>
                            <x-gestion.boton variante="secundario" tamano="sm" icono="mas" :href="route('cursos.quizzes.crear', [$curso, 'desde' => 'actividades'])">Crear quiz de examen</x-gestion.boton>
                        </div>
                        @error('examenCalculo') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                @foreach ($errors->get('actividadesCalculo') as $mensaje)
                    <p role="alert" class="text-sm text-red-600">{{ $mensaje }}</p>
                @endforeach
                @foreach (['tareasCalculo', 'tareasCalculo.*', 'quizzesCalculo', 'quizzesCalculo.*'] as $campoCalculo)
                    @error($campoCalculo) <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                @endforeach

                <div class="flex flex-col-reverse gap-3 border-t border-celeste pt-4 dark:border-azul-borde-oscuro sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Si ya hay notas, se recalculan al guardar. El cambio queda en la auditoría.</p>
                    <div class="flex justify-end gap-2">
                        <x-gestion.boton variante="secundario" wire:click="$set('mostrarCalculo', false)">Cancelar</x-gestion.boton>
                        <x-gestion.boton type="submit" icono="check" :disabled="! $puedeGuardar" wire:loading.attr="disabled" wire:target="guardarCalculo">Guardar cálculo</x-gestion.boton>
                    </div>
                </div>
            </form>
        </x-gestion.modal>
    @endif
</div>
