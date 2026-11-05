@php
    // ERS RF-78/RF-79. Misma estructura para los tres roles. Estudiante y
    // Profesor comparten la paleta azul corporativa; Supervisor conserva
    // su acento cálido de gestión.
    $esEstudiante = $rol === 'estudiante';
    $esAdminVista = $rol === 'supervisor';
    $tarjeta = ! $esAdminVista
        ? 'bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none'
        : 'bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 shadow-sm dark:shadow-none';
    $titulo = ! $esAdminVista ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100';
    $suave = ! $esAdminVista ? 'text-slate-500 dark:text-slate-400' : 'text-stone-500 dark:text-stone-400';
    $acento = ! $esAdminVista ? 'text-azul-oscuro dark:text-azul-fondo' : 'text-menta-oscuro dark:text-menta-base';
    $acentoFondo = ! $esAdminVista ? 'bg-celeste/60 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste' : 'bg-menta-fondo text-menta-oscuro dark:bg-stone-700 dark:text-menta-base';
    $divisor = ! $esAdminVista ? 'divide-celeste dark:divide-azul-borde-oscuro' : 'divide-arena-medio/60 dark:divide-stone-700';
    $borde = ! $esAdminVista ? 'border-celeste dark:border-azul-borde-oscuro' : 'border-arena-medio dark:border-stone-700';
    $campo = ! $esAdminVista
        ? 'mt-1 block w-full rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo text-sm'
        : 'mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm';
    $variablesCalendario = ! $esAdminVista
        ? '[--fc-button-bg-color:#2541B2] [--fc-button-border-color:#2541B2] [--fc-button-hover-bg-color:#1768AC] [--fc-button-hover-border-color:#1768AC] [--fc-button-active-bg-color:#03256C] [--fc-button-active-border-color:#03256C] [--fc-border-color:#EBF0FB] [--fc-page-bg-color:transparent] [--fc-neutral-bg-color:#EBF0FB] [--fc-neutral-text-color:#374151] [--fc-today-bg-color:rgba(6,190,225,0.10)] [--fc-highlight-color:rgba(37,65,178,0.12)] [--fc-more-link-bg-color:#EBF0FB] [--fc-more-link-text-color:#2541B2] dark:[--fc-border-color:#1A3242] dark:[--fc-neutral-bg-color:#12232F] dark:[--fc-neutral-text-color:#d6d3d1] dark:[--fc-today-bg-color:rgba(6,190,225,0.12)] dark:[--fc-more-link-bg-color:#1A3242] dark:[--fc-more-link-text-color:#e7e5e4]'
        : '[--fc-button-bg-color:#3C7A75] [--fc-button-border-color:#3C7A75] [--fc-button-hover-bg-color:#2f615d] [--fc-button-hover-border-color:#2f615d] [--fc-button-active-bg-color:#2f615d] [--fc-button-active-border-color:#2f615d] [--fc-border-color:#E7D8C9] [--fc-page-bg-color:transparent] [--fc-neutral-bg-color:#EEE4E1] [--fc-neutral-text-color:#57534e] [--fc-today-bg-color:rgba(127,179,174,0.18)] [--fc-highlight-color:rgba(127,179,174,0.25)] [--fc-more-link-bg-color:#E7D8C9] [--fc-more-link-text-color:#44403c] dark:[--fc-border-color:#44403c] dark:[--fc-neutral-bg-color:#292524] dark:[--fc-neutral-text-color:#d6d3d1] dark:[--fc-today-bg-color:rgba(127,179,174,0.15)] dark:[--fc-more-link-bg-color:#44403c] dark:[--fc-more-link-text-color:#e7e5e4]';

    $tipos = $rol === 'supervisor'
        ? ['evaluacion' => 'Evaluaciones', 'hito' => 'Inicio y cierre de cursos', 'comunicado' => 'Comunicados programados', 'propio' => 'Mis eventos']
        : ['tarea' => 'Tareas', 'quiz' => 'Quizzes', 'evaluacion' => 'Evaluaciones', 'sesion' => 'Clases', 'propio' => 'Mis eventos'];
    $subtitulo = match ($rol) {
        'supervisor' => 'Panorama institucional: evaluaciones, inicio y cierre de los cursos publicados y comunicados programados.',
        'profesor' => 'Tus clases, las entregas por calificar y las evaluaciones de los cursos que dictas.',
        default => 'Tus entregas, quizzes, evaluaciones y clases, junto a tus bloques de estudio.',
    };
    $agenda = $this->agenda;
@endphp

<div class="py-8" x-data="{
        modalAbierto: false,
        detalleEvento: null,
        fp: null,
        init() {
            window.cargarFlatpickr().then(() => {
                this.fp = window.flatpickr(this.$refs.nuevaFecha, {
                    enableTime: true,
                    time_24hr: false,
                    dateFormat: 'Y-m-d\\TH:i',
                    altInput: true,
                    altFormat: 'j \\d\\e F \\d\\e Y, h:i K',
                    altInputClass: @js($campo),
                    allowInput: true,
                    locale: window.flatpickr.l10ns.es,
                });
            });

            $wire.on('evento-creado', () => { this.modalAbierto = false; });
        },
        abrirDesdeFecha(fechaStr) {
            this.modalAbierto = true;
            this.$nextTick(() => {
                if (! this.fp) return;
                const [y, m, d] = fechaStr.split('-').map(Number);
                const previa = this.fp.selectedDates[0];
                this.fp.setDate(new Date(y, m - 1, d, previa ? previa.getHours() : 9, previa ? previa.getMinutes() : 0), true);
                this.fp.open();
            });
        },
        ponerHora(hora, minuto) {
            if (! this.fp) return;
            const base = this.fp.selectedDates[0] ? new Date(this.fp.selectedDates[0]) : new Date();
            base.setHours(hora, minuto, 0, 0);
            this.fp.setDate(base, true);
        },
    }"
    x-effect="if (! modalAbierto) { fp?.clear() }"
    @abrir-modal-evento.window="$event.detail?.fecha ? abrirDesdeFecha($event.detail.fecha) : modalAbierto = true"
    @ver-evento.window="detalleEvento = $event.detail">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        {{-- Encabezado --}}
        @if ($esEstudiante)
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold {{ $titulo }}">Calendario</h1>
                    <p class="text-sm {{ $suave }}">{{ $subtitulo }}</p>
                </div>
                <button type="button" x-on:click="modalAbierto = true"
                    class="inline-flex items-center gap-2 rounded-lg bg-azul-oscuro px-4 py-2 text-sm font-semibold text-white transition hover:bg-azul-oscuro/90">
                    <x-icono nombre="mas" class="h-4 w-4" /> Nuevo evento
                </button>
            </div>
        @else
            <x-gestion.encabezado titulo="Calendario" icono="calendario" :descripcion="$subtitulo">
                <x-gestion.boton icono="mas" x-on:click="modalAbierto = true">Nuevo evento</x-gestion.boton>
            </x-gestion.encabezado>
        @endif

        {{-- Agenda orientada al rol --}}
        <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
            @if ($rol === 'estudiante')
                <section class="{{ $tarjeta }} p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $acentoFondo }}"><x-icono nombre="tarea" class="h-4 w-4" /></span>
                        Por entregar esta semana
                    </h2>
                    @if ($agenda['vencidas']->isNotEmpty())
                        <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
                            <x-icono nombre="alerta" class="-mt-0.5 inline h-4 w-4" /> {{ $agenda['vencidas']->count() }} tarea(s) vencida(s) sin entregar esta semana.
                        </p>
                    @endif
                    <ul class="mt-3 divide-y {{ $divisor }}">
                        @forelse ($agenda['porEntregar'] as $item)
                            <li class="py-2">
                                <a href="{{ route('cursos.tareas.show', [$item['tarea']->curso_id, $item['tarea']->id]) }}" wire:navigate class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium {{ $titulo }}">{{ $item['tarea']->titulo }}</span>
                                        <span class="flex items-center gap-1.5 text-xs {{ $suave }}">
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $this->coloresCursos[$item['tarea']->curso_id] ?? '#2541B2' }}"></span>
                                            {{ $item['tarea']->curso->nombre }}
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-right text-xs {{ $item['tarea']->fecha_entrega->diffInDays(now(), true) <= 2 ? 'font-semibold text-red-600 dark:text-red-400' : $suave }}">
                                        {{ $item['tarea']->fecha_entrega->translatedFormat('D d M') }}<br>{{ $item['tarea']->fecha_entrega->format('H:i') }}
                                    </span>
                                </a>
                            </li>
                        @empty
                            <li class="py-3 text-sm {{ $suave }}">No tienes entregas pendientes en los próximos 7 días.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="{{ $tarjeta }} p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $acentoFondo }}"><x-icono nombre="quiz" class="h-4 w-4" /></span>
                        Quizzes que cierran pronto
                    </h2>
                    <ul class="mt-3 divide-y {{ $divisor }}">
                        @forelse ($agenda['quizzes'] as $quiz)
                            <li class="py-2">
                                <a href="{{ route('cursos.quizzes.show', [$quiz->curso_id, $quiz->id]) }}" wire:navigate class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium {{ $titulo }}">{{ $quiz->tema }}</span>
                                        <span class="text-xs {{ $suave }}">{{ $quiz->curso->nombre }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs {{ $suave }}">Cierra {{ $quiz->fecha_limite->translatedFormat('D d M, H:i') }}</span>
                                </a>
                            </li>
                        @empty
                            <li class="py-3 text-sm {{ $suave }}">No tienes quizzes por rendir esta semana.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="{{ $tarjeta }} p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $acentoFondo }}"><x-icono nombre="birrete" class="h-4 w-4" /></span>
                        Tus próximas clases
                    </h2>
                    <ul class="mt-3 divide-y {{ $divisor }}">
                        @forelse ($agenda['sesiones'] as $sesion)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium {{ $titulo }}">{{ $sesion->curso->nombre }}</span>
                                    <span class="text-xs {{ $suave }}">{{ $sesion->titulo }}</span>
                                </span>
                                <span class="shrink-0 text-xs {{ $suave }}">{{ $sesion->fecha->translatedFormat('D d, H:i') }}</span>
                            </li>
                        @empty
                            <li class="py-3 text-sm {{ $suave }}">No hay clases programadas en los próximos 7 días.</li>
                        @endforelse
                    </ul>
                </section>
            @elseif ($rol === 'profesor')
                <section class="{{ $tarjeta }} p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $acentoFondo }}"><x-icono nombre="birrete" class="h-4 w-4" /></span>
                        Tus próximas clases
                    </h2>
                    <ul class="mt-3 divide-y {{ $divisor }}">
                        @forelse ($agenda['sesiones'] as $sesion)
                            <li class="py-2">
                                <a href="{{ route('asistencia.index', $sesion->curso_id) }}" wire:navigate class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5 truncate font-medium {{ $titulo }}">
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $this->coloresCursos[$sesion->curso_id] ?? '#3C7A75' }}"></span>
                                            {{ $this->secciones->nombreVisible($sesion->curso_id) }}
                                        </span>
                                        <span class="text-xs {{ $suave }}">{{ $sesion->titulo }}</span>
                                    </span>
                                    <span class="shrink-0 text-right text-xs {{ $sesion->fecha->isToday() ? 'font-semibold '.$acento : $suave }}">
                                        {{ $sesion->fecha->isToday() ? 'Hoy' : $sesion->fecha->translatedFormat('D d') }}<br>{{ $sesion->fecha->format('H:i') }}
                                    </span>
                                </a>
                            </li>
                        @empty
                            <li class="py-3 text-sm {{ $suave }}">No tienes sesiones registradas en los próximos 7 días.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="{{ $tarjeta }} p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"><x-icono nombre="reloj" class="h-4 w-4" /></span>
                        Por calificar, por semana de entrega
                    </h2>
                    <ul class="mt-3 space-y-3">
                        @forelse ($agenda['porCalificar'] as $semana)
                            <li>
                                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide {{ $suave }}">
                                    <span>Semana del {{ $semana['semana']->translatedFormat('d M') }}</span>
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">{{ $semana['total'] }}</span>
                                </div>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($semana['tareas'] as $item)
                                        <li>
                                            <a href="{{ route('cursos.tareas.show', [$item['tarea']->curso_id, $item['tarea']->id]) }}" wire:navigate
                                                class="flex items-center justify-between gap-2 rounded-md px-2 py-1 text-sm {{ $esAdminVista ? 'hover:bg-arena-claro dark:hover:bg-stone-700' : 'hover:bg-celeste dark:hover:bg-azul-borde-oscuro' }}">
                                                <span class="truncate {{ $titulo }}">{{ $item['tarea']->titulo }} <span class="text-xs {{ $suave }}">- {{ $this->secciones->referenciaCorta($item['tarea']->curso_id) }}</span></span>
                                                <span class="shrink-0 text-xs font-semibold text-amber-700 dark:text-amber-300">{{ $item['total'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @empty
                            <li class="py-3 text-sm {{ $suave }}">No tienes entregas pendientes de calificar.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="{{ $tarjeta }} p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $acentoFondo }}"><x-icono nombre="grafico" class="h-4 w-4" /></span>
                        Evaluaciones por registrar
                    </h2>
                    <ul class="mt-3 divide-y {{ $divisor }}">
                        @forelse ($agenda['evaluaciones'] as $item)
                            <li class="py-2">
                                <a href="{{ route('cursos.libro-calificaciones', $item['curso']) }}" wire:navigate class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium {{ $titulo }}">{{ $item['rubro']->nombre }} ({{ (float) $item['rubro']->peso }} %)</span>
                                        <span class="text-xs {{ $suave }}">{{ $this->secciones->nombreVisible($item['curso']->id) }} - semana {{ $item['rubro']->semana }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs {{ $item['fecha']->isCurrentWeek() ? 'font-semibold '.$acento : $suave }}">
                                        {{ $item['fecha']->isCurrentWeek() ? 'Esta semana' : 'Sem. del '.$item['fecha']->translatedFormat('d M') }}
                                    </span>
                                </a>
                            </li>
                        @empty
                            <li class="py-3 text-sm {{ $suave }}">No hay evaluaciones del esquema en estas semanas.</li>
                        @endforelse
                    </ul>
                </section>
            @else
                <section class="{{ $tarjeta }} p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $acentoFondo }}"><x-icono nombre="libro" class="h-4 w-4" /></span>
                        Cursos en marcha
                    </h2>
                    <ul class="mt-3 space-y-3">
                        @forelse ($agenda['cursos'] as $item)
                            @php $pct = $item['semana'] && $item['total'] ? (int) round($item['semana'] / $item['total'] * 100) : null; @endphp
                            <li>
                                <a href="{{ route('cursos.show', $item['curso']) }}" wire:navigate class="block text-sm">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="flex min-w-0 items-center gap-1.5 truncate font-medium {{ $titulo }}">
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $this->coloresCursos[$item['curso']->id] ?? '#3C7A75' }}"></span>
                                            {{ $item['curso']->nombre }}
                                        </span>
                                        <span class="shrink-0 text-xs {{ $suave }}">{{ $item['semana'] ? 'Semana '.$item['semana'].' de '.$item['total'] : 'Sin iniciar o cerrado' }}</span>
                                    </span>
                                    @if ($pct !== null)
                                        <span class="mt-1 block h-1.5 overflow-hidden rounded-full {{ $esAdminVista ? 'bg-arena-claro dark:bg-stone-700' : 'bg-celeste dark:bg-azul-borde-oscuro' }}">
                                            <span class="block h-full rounded-full {{ $esAdminVista ? 'bg-menta-oscuro dark:bg-menta-base' : 'bg-azul-oscuro dark:bg-azul-fondo' }}" style="width: {{ $pct }}%"></span>
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @empty
                            <li class="py-3 text-sm {{ $suave }}">No hay cursos publicados.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="{{ $tarjeta }} p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $acentoFondo }}"><x-icono nombre="grafico" class="h-4 w-4" /></span>
                        Evaluaciones y notas
                    </h2>
                    <ul class="mt-3 divide-y {{ $divisor }}">
                        @forelse ($agenda['evaluaciones'] as $item)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium {{ $titulo }}">{{ $item['rubro']->nombre }} ({{ (float) $item['rubro']->peso }} %)</span>
                                    <span class="text-xs {{ $suave }}">{{ $item['curso']->nombre }}</span>
                                </span>
                                <span class="shrink-0 text-xs {{ $item['fecha']->isCurrentWeek() ? 'font-semibold '.$acento : $suave }}">
                                    {{ $item['fecha']->isCurrentWeek() ? 'Esta semana' : 'Sem. del '.$item['fecha']->translatedFormat('d M') }}
                                </span>
                            </li>
                        @empty
                            <li class="py-3 text-sm {{ $suave }}">No hay evaluaciones en las próximas cuatro semanas.</li>
                        @endforelse
                    </ul>
                    @if ($agenda['notasPendientes']->isNotEmpty())
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide {{ $suave }}">Notas pendientes de registrar</p>
                        <ul class="mt-1 space-y-1">
                            @foreach ($agenda['notasPendientes'] as $item)
                                <li>
                                    <a href="{{ route('cursos.libro-calificaciones', $item['curso']) }}" wire:navigate
                                        class="flex items-center justify-between gap-2 rounded-md px-2 py-1 text-sm {{ $esAdminVista ? 'hover:bg-arena-claro dark:hover:bg-stone-700' : 'hover:bg-celeste dark:hover:bg-azul-borde-oscuro' }}">
                                        <span class="truncate {{ $titulo }}">{{ $item['rubro']->nombre }} <span class="text-xs {{ $suave }}">- {{ $item['curso']->codigo ?? $item['curso']->nombre }}</span></span>
                                        <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">{{ $item['faltan'] }} sin nota</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="{{ $tarjeta }} p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $acentoFondo }}"><x-icono nombre="megafono" class="h-4 w-4" /></span>
                        Comunicados programados
                    </h2>
                    <ul class="mt-3 divide-y {{ $divisor }}">
                        @forelse ($agenda['comunicados'] as $comunicado)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0 truncate font-medium {{ $titulo }}">{{ $comunicado->titulo }}</span>
                                <span class="shrink-0 text-xs {{ $suave }}">{{ $comunicado->publicar_en->translatedFormat('D d M, H:i') }}</span>
                            </li>
                        @empty
                            <li class="py-3 text-sm {{ $suave }}">No hay comunicados programados.</li>
                        @endforelse
                    </ul>
                    <a href="{{ route('admin.comunicados.index') }}" wire:navigate class="mt-2 inline-block text-xs font-semibold {{ $acento }} hover:underline">Gestionar comunicados</a>
                </section>
            @endif
        </div>

        {{-- Llegada desde otra pantalla (p. ej. "Ver en calendario" de una propuesta): fecha enfocada y regreso. --}}
        @if ($fechaInicial !== '')
            <div id="contexto-calendario" x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm {{ ! $esAdminVista ? 'border-azul-oscuro/20 bg-celeste/60 text-azul-oscuro dark:border-azul-borde-oscuro dark:bg-azul-oscuro/20 dark:text-celeste' : 'border-menta-base/40 bg-menta-fondo text-menta-oscuro dark:border-stone-700 dark:bg-stone-800 dark:text-menta-base' }}">
                <p class="flex items-center gap-2">
                    <x-icono nombre="calendario" class="h-4 w-4 shrink-0" />
                    <span>Mostrando el <strong class="font-semibold">{{ \Illuminate\Support\Carbon::parse($fechaInicial)->translatedFormat('l j \d\e F') }}</strong>{{ $eventoResaltado !== '' ? '; la actividad relacionada aparece resaltada.' : '.' }}</span>
                </p>
            </div>
        @endif

        {{-- Filtros + calendario. wire:ignore: FullCalendar controla este subárbol. --}}
        <div wire:ignore
            x-data="{
                calendar: null,
                eventos: @js($this->eventos),
                cursosSeleccionados: @js($this->cursos->pluck('id')->values()),
                tiposSeleccionados: @js(array_keys($tipos)),
                resaltado: @js($eventoResaltado),
                fechaInicial: @js($fechaInicial),
                {{-- Profesor: bloque "Actividades de la semana por curso" (null para los demás roles). --}}
                bloque: @js($this->semanaPorCurso),
                semanaInicio: '',
                semanaFin: '',
                rangoSemana: '',
                abiertos: {},
                expandidos: {},
                // Resultado del bloque semanal, calculado una vez por cambio de semana o de filtros (no en cada fila).
                vista: { grupos: [], primera: null, primerGrupo: null, conActividad: [] },
                // Muchos cursos: búsqueda y materias abiertas en el filtro, y materias abiertas en el bloque.
                busquedaCurso: '',
                materiasFiltro: {},
                materiasAbiertas: {},
                seleccionRecordada: false,
                init() {
                    // Muchos cursos: se recupera la selección guardada en este navegador (si se puede leer).
                    if (this.bloque?.muchos) {
                        try {
                            const guardada = JSON.parse(localStorage.getItem(this.bloque.claveSeleccion) ?? 'null');
                            if (Array.isArray(guardada)) {
                                const ids = this.bloque.cursos.map(c => c.id);
                                this.cursosSeleccionados = guardada.filter(id => ids.includes(id));
                                this.seleccionRecordada = true;
                            }
                        } catch (error) {}
                    }
                    // Sin fecha en la URL pero con evento: se abre en la fecha de ese evento.
                    const destino = this.fechaInicial || (this.eventos.find(e => e.id === this.resaltado)?.start ?? '').slice(0, 10);
                    // El evento resaltado va primero en su día, para que no quede oculto en +N más.
                    this.eventos = this.eventos.map(e => e.id === this.resaltado ? { ...e, orden: 0 } : e);
                    this.calendar = new window.FullCalendar.Calendar(this.$refs.calendario, {
                        plugins: [window.FullCalendar.dayGridPlugin, window.FullCalendar.timeGridPlugin, window.FullCalendar.listPlugin, window.FullCalendar.interactionPlugin],
                        initialView: window.innerWidth < 640 ? 'listWeek' : (this.bloque ? 'dayGridWeek' : 'dayGridMonth'),
                        initialDate: destino || undefined,
                        eventDidMount: (info) => {
                            if (this.resaltado && info.event.id === this.resaltado) {
                                info.el.classList.add('evento-resaltado');
                            }
                        },
                        dayCellClassNames: (info) => destino && info.date.getFullYear() + '-' + String(info.date.getMonth() + 1).padStart(2, '0') + '-' + String(info.date.getDate()).padStart(2, '0') === destino ? ['dia-enfocado'] : [],
                        locale: window.FullCalendar.esLocale ?? 'es',
                        firstDay: 1,
                        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Agenda' },
                        headerToolbar: { left: 'prev,next today', center: 'title', right: this.bloque ? 'dayGridMonth,dayGridWeek,listWeek' : 'dayGridMonth,timeGridWeek,listWeek' },
                        // Profesor: la semana en una fila (sin la grilla de 24 horas) y el rango «26 oct – 1 nov»; la hora de cada actividad se ve en el bloque por curso.
                        views: this.bloque ? {
                            // Con 3 o más cursos: máximo 3 actividades por día y «+N más» abre el resto en un recuadro (también en el mes).
                            dayGridWeek: { titleFormat: { day: 'numeric', month: 'short' }, dayMaxEventRows: false, dayMaxEvents: this.bloque.cursos.length >= {{ \App\Services\Calendario\SeccionesProfesor::CURSOS_CON_REFERENCIA }} ? 3 : false },
                            ...(this.bloque.cursos.length >= {{ \App\Services\Calendario\SeccionesProfesor::CURSOS_CON_REFERENCIA }} ? { dayGridMonth: { dayMaxEventRows: false, dayMaxEvents: 3 } } : {}),
                            listWeek: { titleFormat: { day: 'numeric', month: 'short' } },
                        } : {},
                        datesSet: () => this.bloque && this.actualizarSemana(),
                        height: 'auto',
                        displayEventTime: false,
                        eventDisplay: 'block',
                        // Primero lo que se evalúa o se entrega; las clases al final.
                        eventOrder: 'orden,start,title',
                        dayMaxEventRows: 3,
                        noEventsText: 'No hay actividades en este periodo.',
                        events: this.visibles(),
                        dateClick: (info) => window.dispatchEvent(new CustomEvent('abrir-modal-evento', { detail: { fecha: info.dateStr } })),
                        eventClick: (info) => {
                            if (info.event.url) {
                                info.jsEvent.preventDefault();
                                window.location.href = info.event.url;
                                return;
                            }
                            if (! info.event.extendedProps.propio) return;
                            window.dispatchEvent(new CustomEvent('ver-evento', { detail: {
                                id: info.event.extendedProps.idNumerico,
                                titulo: info.event.title,
                                fecha: info.event.start ? new Date(info.event.start).toLocaleString('es-PE', { dateStyle: 'long', timeStyle: 'short' }) : '',
                            } }));
                        },
                    });
                    this.calendar.render();

                    $wire.on('evento-creado', ({ evento }) => { this.eventos.push(evento); this.aplicar(); });
                    $wire.on('evento-eliminado', ({ id }) => { this.eventos = this.eventos.filter(e => e.id !== id); this.aplicar(); });
                },
                visibles() {
                    return this.eventos.filter(e => this.tiposSeleccionados.includes(e.extendedProps.tipo)
                        && (e.extendedProps.cursoId === null || this.cursosSeleccionados.includes(e.extendedProps.cursoId)));
                },
                aplicar() {
                    this.calendar.removeAllEventSources();
                    this.calendar.addEventSource(this.visibles());
                    if (this.bloque) {
                        this.guardarSeleccion();
                        this.recalcular();
                    }
                },
                // Muchos cursos: la selección se recuerda en este navegador; si no se puede guardar, todo funciona igual.
                guardarSeleccion() {
                    if (! this.bloque.muchos) return;
                    try {
                        localStorage.setItem(this.bloque.claveSeleccion, JSON.stringify(this.cursosSeleccionados));
                        this.seleccionRecordada = true;
                    } catch (error) {}
                },
                restablecerSeleccion() {
                    try { localStorage.removeItem(this.bloque.claveSeleccion); } catch (error) {}
                    this.cursosSeleccionados = this.bloque.cursos.map(c => c.id);
                    this.calendar.removeAllEventSources();
                    this.calendar.addEventSource(this.visibles());
                    this.seleccionRecordada = false;
                    this.recalcular();
                },
                seleccionar(ids) {
                    this.cursosSeleccionados = ids;
                    this.aplicar();
                },
                // Marca o desmarca todas las secciones de una materia.
                alternarMateria(ids) {
                    const todas = ids.every(id => this.cursosSeleccionados.includes(id));
                    this.seleccionar(todas ? this.cursosSeleccionados.filter(id => ! ids.includes(id)) : [...new Set([...this.cursosSeleccionados, ...ids])]);
                },
                normalizar(texto) {
                    return String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
                },
                // Filtro de muchos cursos: materias con las secciones que coinciden con la búsqueda (nombre o código).
                materiasDelFiltro() {
                    const buscado = this.normalizar(this.busquedaCurso);
                    const grupos = [];
                    for (const curso of this.bloque.cursos) {
                        if (buscado && ! curso.busqueda.includes(buscado)) continue;
                        let grupo = grupos.find(g => g.clave === curso.grupo);
                        if (! grupo) {
                            grupo = { clave: curso.grupo, nombre: curso.nombre, secciones: [] };
                            grupos.push(grupo);
                        }
                        grupo.secciones.push(curso);
                    }
                    return grupos;
                },
                materiaFiltroAbierta(clave) {
                    // Con búsqueda se ven todas las coincidencias; sin búsqueda, las materias empiezan colapsadas.
                    return this.busquedaCurso.trim() !== '' || (this.materiasFiltro[clave] ?? this.bloque.cursos.length <= 8);
                },
                alternar(lista, valor) {
                    this[lista] = this[lista].includes(valor) ? this[lista].filter(v => v !== valor) : [...this[lista], valor];
                    this.aplicar();
                },
                // Bloque semanal del Profesor: todo se calcula con los eventos ya cargados, sin pedir nada al servidor.
                fechaLocal(fecha) {
                    return fecha.getFullYear() + '-' + String(fecha.getMonth() + 1).padStart(2, '0') + '-' + String(fecha.getDate()).padStart(2, '0');
                },
                actualizarSemana() {
                    const inicio = new Date(this.calendar.getDate());
                    inicio.setDate(inicio.getDate() - (inicio.getDay() + 6) % 7);
                    const fin = new Date(inicio);
                    fin.setDate(inicio.getDate() + 6);
                    const formato = new Intl.DateTimeFormat('es', { day: 'numeric', month: 'short' });
                    this.semanaInicio = this.fechaLocal(inicio);
                    this.semanaFin = this.fechaLocal(fin);
                    this.rangoSemana = formato.format(inicio) + ' – ' + formato.format(fin);
                    this.recalcular();
                },
                numeroSemana(curso) {
                    if (! curso.inicio || ! this.semanaInicio) return null;
                    const numero = Math.round((Date.parse(this.semanaInicio) - Date.parse(curso.inicio)) / 604800000) + 1;
                    return numero >= 1 && numero <= curso.totalSemanas ? numero : null;
                },
                actividadesDe(cursoId, deLaSemana = null) {
                    const conFecha = (deLaSemana ? (deLaSemana[cursoId] ?? []) : this.eventos.filter(e => e.extendedProps.cursoId === cursoId && e.start.slice(0, 10) >= this.semanaInicio && e.start.slice(0, 10) <= this.semanaFin))
                        .filter(e => e.extendedProps.tipo !== 'propio' && this.tiposSeleccionados.includes(e.extendedProps.tipo))
                        .sort((a, b) => a.start.localeCompare(b.start) || a.orden - b.orden)
                        .map(e => ({ id: e.id, tipo: e.extendedProps.tipo, titulo: e.extendedProps.titulo ?? e.title, fecha: e.extendedProps.fechaCorta ?? '', estado: e.extendedProps.estado ?? null, detalle: e.extendedProps.detalle ?? null, porCalificar: e.extendedProps.porCalificar ?? 0, url: e.url }));
                    const sinFecha = this.tiposSeleccionados.includes('quiz')
                        ? this.bloque.sinFecha.filter(q => q.cursoId === cursoId && q.inicioSemana === this.semanaInicio).map(q => ({ ...q, fecha: null, detalle: null, porCalificar: 0 }))
                        : [];
                    return { conFecha, sinFecha, total: conFecha.length + sinFecha.length };
                },
                // Calcula una sola vez el bloque de la semana: eventos de la semana por curso (una pasada), actividades de cada
                // curso, materias con sus secciones y qué fila y qué materia empiezan abiertas.
                recalcular() {
                    if (! this.bloque || ! this.semanaInicio) return;
                    const deLaSemana = {};
                    for (const e of this.eventos) {
                        const dia = e.start.slice(0, 10);
                        if (e.extendedProps.cursoId !== null && dia >= this.semanaInicio && dia <= this.semanaFin) {
                            (deLaSemana[e.extendedProps.cursoId] ??= []).push(e);
                        }
                    }
                    const muchos = this.bloque.muchos;
                    const todos = this.bloque.cursos.map(c => ({ ...c, numero: this.numeroSemana(c), actividades: this.actividadesDe(c.id, deLaSemana) }));
                    const grupos = [];
                    for (const curso of todos.filter(c => this.cursosSeleccionados.includes(c.id))) {
                        let grupo = grupos.find(g => g.clave === curso.grupo);
                        if (! grupo) {
                            // Agrupado: con varias secciones, o con muchos cursos (cada materia en su propio grupo colapsable).
                            grupo = { clave: curso.grupo, nombre: curso.nombre, varias: curso.variasSecciones, agrupado: curso.variasSecciones || muchos, secciones: [] };
                            grupos.push(grupo);
                        }
                        grupo.secciones.push(curso);
                    }
                    const listos = grupos.map(g => ({
                        ...g,
                        total: g.secciones.reduce((suma, s) => suma + s.actividades.total, 0),
                        items: g.agrupado ? g.secciones.filter(s => s.actividades.total > 0) : g.secciones,
                        vacias: g.agrupado ? g.secciones.filter(s => s.actividades.total === 0) : [],
                    }));
                    const conActividades = listos.find(g => g.total > 0);
                    this.vista = {
                        grupos: listos,
                        // Solo la primera fila empieza abierta (con muchos cursos, la primera con actividades).
                        primera: (muchos ? conActividades?.items[0] : listos.flatMap(g => g.items)[0])?.id ?? null,
                        primerGrupo: conActividades?.clave ?? null,
                        conActividad: todos.filter(c => c.actividades.total > 0).map(c => c.id),
                    };
                },
                cursosDeLaSemana() {
                    return this.vista.grupos.flatMap(g => g.secciones);
                },
                gruposDeLaSemana() {
                    return this.vista.grupos;
                },
                primeraAbierta() {
                    return this.vista.primera;
                },
                estaAbierto(cursoId) {
                    return this.abiertos[cursoId] ?? cursoId === this.vista.primera;
                },
                // Muchos cursos: cada materia del bloque es colapsable; empieza abierta solo la primera con actividades.
                materiaAbierta(clave) {
                    return ! this.bloque.muchos || (this.materiasAbiertas[clave] ?? clave === this.vista.primerGrupo);
                },
                alternarMateriaBloque(clave) {
                    this.materiasAbiertas = { ...this.materiasAbiertas, [clave]: ! this.materiaAbierta(clave) };
                },
                expandirTodo(abrir) {
                    this.materiasAbiertas = Object.fromEntries(this.vista.grupos.map(g => [g.clave, abrir]));
                    this.abiertos = Object.fromEntries(this.vista.grupos.flatMap(g => g.items).map(s => [s.id, abrir]));
                },
                alternarCurso(cursoId) {
                    this.abiertos = { ...this.abiertos, [cursoId]: ! this.estaAbierto(cursoId) };
                },
                visiblesDe(curso) {
                    // Primero las que tienen fecha; si hay más de 5 en total, se muestran 5 hasta pulsar «Ver todas».
                    const todas = [...curso.actividades.conFecha.map(a => ({ ...a, grupo: 'conFecha' })), ...curso.actividades.sinFecha.map(a => ({ ...a, grupo: 'sinFecha' }))];
                    return this.expandidos[curso.id] ? todas : todas.slice(0, 5);
                },
                claseEstado(estado) {
                    if (estado.startsWith('Publicado')) return 'bg-exito-base/15 text-exito-oscuro';
                    if (estado.startsWith('Programado')) return 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
                    return 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200';
                },
            }"
            class="grid grid-cols-1 gap-6 lg:grid-cols-4">

            <aside class="order-2 space-y-4 lg:order-1 lg:col-span-1">
                <section class="{{ $tarjeta }} p-4">
                    @if ($rol === 'profesor' && $this->secciones->hayMuchos())
                        @include('livewire.evento-calendario._filtro-muchos-cursos')
                    @elseif ($rol === 'profesor' && $this->secciones->haySecciones())
                        @include('livewire.evento-calendario._filtro-secciones')
                    @else
                    <h2 class="text-sm font-semibold {{ $titulo }}">{{ $rol === 'supervisor' ? 'Cursos publicados' : 'Mis cursos' }}</h2>
                    <p class="mt-0.5 text-xs {{ $suave }}">Muestra u oculta las actividades de cada curso.</p>
                    <ul class="mt-3 space-y-1">
                        @forelse ($this->cursos as $curso)
                            <li>
                                <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm {{ ! $esAdminVista ? 'hover:bg-celeste/40 dark:hover:bg-azul-borde-oscuro/40' : 'hover:bg-arena-claro dark:hover:bg-stone-700' }}">
                                    <input type="checkbox" class="sr-only" x-on:change="alternar('cursosSeleccionados', {{ $curso->id }})" checked>
                                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 transition"
                                        style="border-color: {{ $this->coloresCursos[$curso->id] }}"
                                        :style="cursosSeleccionados.includes({{ $curso->id }}) ? 'background-color: {{ $this->coloresCursos[$curso->id] }}; border-color: {{ $this->coloresCursos[$curso->id] }}' : 'border-color: {{ $this->coloresCursos[$curso->id] }}'">
                                        <x-icono nombre="check" class="h-3 w-3 text-white" x-show="cursosSeleccionados.includes({{ $curso->id }})" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate {{ $titulo }}">{{ $curso->nombre }}</span>
                                        @if ($curso->codigo)
                                            <span class="block text-[11px] {{ $suave }}">{{ $curso->codigo }}</span>
                                        @endif
                                    </span>
                                </label>
                            </li>
                        @empty
                            <li class="text-sm {{ $suave }}">{{ $esEstudiante ? 'Aún no estás matriculado en cursos.' : 'No hay cursos para mostrar.' }}</li>
                        @endforelse
                    </ul>
                    @endif
                </section>

                <section class="{{ $tarjeta }} p-4">
                    <h2 class="text-sm font-semibold {{ $titulo }}">Tipos de actividad</h2>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($tipos as $clave => $etiqueta)
                            <button type="button" x-on:click="alternar('tiposSeleccionados', '{{ $clave }}')"
                                class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                :class="tiposSeleccionados.includes('{{ $clave }}') ? @js(! $esAdminVista ? 'border-azul-oscuro bg-azul-oscuro text-white' : 'border-menta-oscuro bg-menta-oscuro text-white') : @js(! $esAdminVista ? 'border-celeste text-slate-600 dark:border-azul-borde-oscuro dark:text-slate-300' : 'border-arena-medio text-stone-600 dark:border-stone-600 dark:text-stone-300')"
                                :aria-pressed="tiposSeleccionados.includes('{{ $clave }}')">
                                {{ $etiqueta }}
                            </button>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs {{ $suave }}">El color de cada actividad es el de su curso. Tus eventos personales usan tonos neutros.</p>
                </section>
            </aside>

            @if ($rol === 'profesor')
                {{-- Profesor: el bloque semanal va justo debajo del calendario, en la misma columna (sin hueco por la altura de los filtros). --}}
                <div class="order-1 min-w-0 space-y-6 lg:order-2 lg:col-span-3">
                    <div class="calendario-learnup calendario-profesor {{ $tarjeta }} p-4 text-stone-700 dark:text-stone-200 {{ $variablesCalendario }}">
                        <div x-ref="calendario"></div>
                    </div>

                    @include('livewire.evento-calendario._semana-por-curso')
                </div>
            @else
                <div class="calendario-learnup order-1 {{ $tarjeta }} p-4 lg:order-2 lg:col-span-3 {{ $esEstudiante ? 'text-slate-700 dark:text-slate-200' : 'text-stone-700 dark:text-stone-200' }} {{ $variablesCalendario }}">
                    <div x-ref="calendario"></div>
                </div>
            @endif
        </div>

        {{-- Eventos propios --}}
        @php $propios = auth()->user()->eventosCalendario()->where('fecha', '>=', now()->startOfDay())->orderBy('fecha')->limit(10)->get(); @endphp
        <section class="{{ $tarjeta }} p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold {{ $titulo }}">Tus próximos eventos personales</h2>
                <button type="button" x-on:click="modalAbierto = true" class="text-xs font-semibold {{ $acento }} hover:underline">Agregar</button>
            </div>
            <ul class="mt-3 divide-y {{ $divisor }}">
                @forelse ($propios as $evento)
                    <li class="flex items-center justify-between gap-3 py-2 text-sm" wire:key="propio-{{ $evento->id }}">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ \App\Livewire\EventoCalendario\Index::COLORES_PROPIOS[$evento->tipo] ?? '#57534E' }}"></span>
                            <span class="truncate {{ $titulo }}">{{ $evento->titulo }}</span>
                            <span class="shrink-0 text-xs {{ $suave }}">{{ $evento->fecha->translatedFormat('D d M, H:i') }}</span>
                        </span>
                        <button type="button" wire:click="eliminar({{ $evento->id }})" wire:confirm="¿Eliminar el evento {{ $evento->titulo }}?"
                            class="shrink-0 text-xs font-semibold text-red-600 hover:underline dark:text-red-400">Eliminar</button>
                    </li>
                @empty
                    <li class="py-3 text-sm {{ $suave }}">No tienes eventos personales próximos. Haz clic en un día del calendario para crear uno.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- Modal: nuevo evento (botón o clic en un día) --}}
    <div x-show="modalAbierto" x-cloak x-on:keydown.escape.window="modalAbierto = false" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="modalAbierto" x-transition.opacity x-on:click="modalAbierto = false" class="absolute inset-0 bg-slate-900/40"></div>
        <div x-show="modalAbierto" x-transition role="dialog" aria-modal="true" aria-labelledby="titulo-nuevo-evento"
            class="relative w-full max-w-sm p-5 {{ $tarjeta }} shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 id="titulo-nuevo-evento" class="font-semibold {{ $titulo }}">Nuevo evento personal</h2>
                <button type="button" x-on:click="modalAbierto = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" aria-label="Cerrar">
                    <x-icono nombre="cerrar" class="h-5 w-5" />
                </button>
            </div>

            <form wire:submit="crear" class="space-y-3">
                <div>
                    <x-input-label for="nuevoTitulo" value="Título" />
                    <input wire:model="nuevoTitulo" id="nuevoTitulo" type="text" class="{{ $campo }}" placeholder="Repasar la unidad 2">
                    <x-input-error :messages="$errors->get('nuevoTitulo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="nuevaFecha" value="Fecha y hora" />
                    {{-- wire:ignore: Flatpickr controla este subárbol; el valor llega por wire:model. --}}
                    <div wire:ignore>
                        <input wire:model="nuevaFecha" x-ref="nuevaFecha" id="nuevaFecha" type="text" class="{{ $campo }}">
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ([['9:00 AM', 9, 0], ['12:00 PM', 12, 0], ['3:00 PM', 15, 0], ['6:00 PM', 18, 0], ['8:00 PM', 20, 0]] as [$etiqueta, $hora24, $minuto])
                                <button type="button" x-on:click="ponerHora({{ $hora24 }}, {{ $minuto }})"
                                    class="rounded-md border px-2 py-1 text-[11px] font-medium transition {{ ! $esAdminVista ? 'border-celeste text-azul-oscuro hover:bg-celeste/30 dark:border-azul-borde-oscuro dark:text-azul-fondo' : 'border-arena-medio text-menta-oscuro hover:bg-arena-claro dark:border-stone-600 dark:text-menta-base' }}">
                                    {{ $etiqueta }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('nuevaFecha')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="nuevoTipo" value="Tipo" />
                    <select wire:model="nuevoTipo" id="nuevoTipo" class="{{ $campo }}">
                        <option value="bloque_estudio">{{ $esEstudiante ? 'Bloque de estudio' : 'Bloque de trabajo' }}</option>
                        <option value="recordatorio">Recordatorio</option>
                        <option value="evento">Evento</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" x-on:click="modalAbierto = false" class="rounded-md px-4 py-2 text-sm font-semibold {{ $suave }} hover:bg-slate-100 dark:hover:bg-slate-700">Cancelar</button>
                    <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white {{ ! $esAdminVista ? 'bg-azul-oscuro hover:bg-azul-oscuro/90' : 'bg-menta-oscuro hover:bg-menta-oscuro/90' }}">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Detalle de un evento personal --}}
    <div x-show="detalleEvento" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="detalleEvento" x-transition.opacity x-on:click="detalleEvento = null" class="absolute inset-0 bg-slate-900/40"></div>
        <div x-show="detalleEvento" x-transition role="dialog" aria-modal="true" class="relative w-full max-w-sm p-5 {{ $tarjeta }} shadow-xl">
            <template x-if="detalleEvento">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-semibold {{ $titulo }}" x-text="detalleEvento.titulo"></h2>
                        <button type="button" x-on:click="detalleEvento = null" class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" aria-label="Cerrar">
                            <x-icono nombre="cerrar" class="h-5 w-5" />
                        </button>
                    </div>
                    <p class="text-sm {{ $suave }}" x-text="detalleEvento.fecha"></p>
                    <div class="flex justify-end pt-2">
                        <button type="button" x-on:click="$wire.eliminar(detalleEvento.id); detalleEvento = null"
                            class="text-xs font-semibold text-red-600 hover:underline dark:text-red-400">Eliminar evento</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <style>
        .calendario-learnup .fc-toolbar-title { font-size: 1.05rem; font-weight: 700; }
        .calendario-learnup .fc-toolbar-title::first-letter { text-transform: uppercase; }
        .calendario-learnup .fc-button { font-size: 0.78rem; font-weight: 600; border-radius: 0.5rem; padding: 0.35rem 0.7rem; text-transform: capitalize; box-shadow: none !important; }
        .calendario-learnup .fc-col-header-cell-cushion { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.5rem 0; }
        .calendario-learnup .fc-daygrid-day-number { font-size: 0.8rem; padding: 0.35rem 0.5rem; }
        .calendario-learnup .fc-daygrid-event { padding: 2px 5px; margin-top: 2px; border-radius: 0.375rem; white-space: normal; }
        .calendario-learnup .fc-event-title { font-size: 0.72rem; font-weight: 500; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .calendario-learnup .evento-evaluacion { box-shadow: inset 3px 0 0 rgba(255, 255, 255, 0.85); font-weight: 700; }
        .calendario-learnup .fc-list-event-title a { font-size: 0.85rem; }
        /* Profesor: el recuadro de «+N más» con fondo de tarjeta (el calendario usa fondo transparente). */
        .calendario-profesor .fc-popover { background-color: #ffffff; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15); max-width: 22rem; }
        .dark .calendario-profesor .fc-popover { background-color: #12232F; }
        .calendario-profesor .fc-popover-header { border-radius: 0.75rem 0.75rem 0 0; font-size: 0.8rem; font-weight: 600; }
        .calendario-profesor .fc-popover-body { max-height: 18rem; overflow-y: auto; }
        @media (max-width: 640px) {
            .calendario-learnup .fc-toolbar { flex-direction: column; gap: 0.5rem; }
        }
    </style>
</div>
