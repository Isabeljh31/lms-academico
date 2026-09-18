@if (auth()->user()->hasRole('estudiante'))
    {{-- Rediseño visual + interacción del rol estudiante: paleta fría nueva,
         botón "+ Nuevo evento" (modal) en vez de un formulario siempre
         visible, clic en un día del calendario para crear, y detalle al
         hacer clic en un evento. Ver ARQUITECTURA.md § nueva paleta de
         color + calendario más intuitivo. La lógica de crear/eliminar
         (métodos del componente) no cambió. --}}
    <div class="py-8" x-data="{
            modalAbierto: false,
            detalleEvento: null,
            fp: null,
            init() {
                const cargarFlatpickr = () => new Promise((resolve) => {
                    if (window.flatpickr) { resolve(); return; }
                    const css = document.createElement('link');
                    css.rel = 'stylesheet';
                    css.href = 'https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css';
                    document.head.appendChild(css);

                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js';
                    script.onload = () => {
                        const locale = document.createElement('script');
                        locale.src = 'https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/es.js';
                        locale.onload = () => resolve();
                        document.head.appendChild(locale);
                    };
                    document.head.appendChild(script);
                });

                cargarFlatpickr().then(() => {
                    this.fp = window.flatpickr(this.$refs.nuevaFecha, {
                        enableTime: true,
                        time_24hr: false,
                        dateFormat: 'Y-m-d\\TH:i',
                        altInput: true,
                        altFormat: 'j \\d\\e F \\d\\e Y, h:i K',
                        altInputClass: 'mt-1 block w-full rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo text-sm',
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
                    const hora = previa ? previa.getHours() : 9;
                    const minuto = previa ? previa.getMinutes() : 0;
                    this.fp.setDate(new Date(y, m - 1, d, hora, minuto), true);
                    this.fp.open();
                    this.fp.hourElement?.focus();
                    this.fp.hourElement?.select();
                });
            },
            ponerHora(hora, minuto) {
                if (! this.fp) return;
                const base = this.fp.selectedDates[0] ? new Date(this.fp.selectedDates[0]) : new Date();
                base.setHours(hora, minuto, 0, 0);
                this.fp.setDate(base, true);
            },
        }"
        x-effect="if (! modalAbierto) { fp?.clear() }">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-texto dark:text-slate-100">Calendario</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Tus bloques de estudio y recordatorios, más las fechas límite de tus tareas.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-azul-oscuro"></span> Bloque de estudio</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-coral-oscuro"></span> Recordatorio / tarea</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-lavanda-oscuro"></span> Evento</span>
            </div>

            <div wire:ignore
                x-data="{
                    calendar: null,
                    init() {
                        this.calendar = new window.FullCalendar.Calendar(this.$refs.calendario, {
                            plugins: [window.FullCalendar.dayGridPlugin, window.FullCalendar.timeGridPlugin, window.FullCalendar.listPlugin, window.FullCalendar.interactionPlugin],
                            initialView: 'dayGridMonth',
                            headerToolbar: { left: 'prev,next today', center: 'title', right: 'nuevoEvento dayGridMonth,timeGridWeek,listWeek' },
                            customButtons: {
                                nuevoEvento: {
                                    text: '+ Nuevo evento',
                                    click: () => window.dispatchEvent(new CustomEvent('abrir-modal-evento')),
                                },
                            },
                            height: 650,
                            displayEventTime: false,
                            dayMaxEventRows: 3,
                            events: @js($this->eventos),
                            dateClick: (info) => {
                                window.dispatchEvent(new CustomEvent('abrir-modal-evento', { detail: { fecha: info.dateStr } }));
                            },
                            eventClick: (info) => {
                                if (info.event.url) {
                                    info.jsEvent.preventDefault();
                                    window.location.href = info.event.url;
                                    return;
                                }
                                window.dispatchEvent(new CustomEvent('ver-evento', {
                                    detail: {
                                        id: info.event.extendedProps.idNumerico,
                                        titulo: info.event.title,
                                        fecha: info.event.start
                                            ? new Date(info.event.start).toLocaleString('es-PE', { dateStyle: 'long', timeStyle: 'short' })
                                            : '',
                                    },
                                }));
                            },
                        });
                        this.calendar.render();

                        $wire.on('evento-creado', ({ evento }) => this.calendar.addEvent(evento));
                        $wire.on('evento-eliminado', ({ id }) => this.calendar.getEventById(id)?.remove());
                    },
                }"
                @abrir-modal-evento.window="$event.detail?.fecha ? abrirDesdeFecha($event.detail.fecha) : modalAbierto = true"
                @ver-evento.window="detalleEvento = $event.detail"
                class="calendario-estudiante bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-4 text-slate-700 dark:text-slate-200
                    [--fc-button-bg-color:#2F5A78] [--fc-button-border-color:#2F5A78]
                    [--fc-button-hover-bg-color:#7FA9C9] [--fc-button-hover-border-color:#7FA9C9]
                    [--fc-button-active-bg-color:#2F5A78] [--fc-button-active-border-color:#2F5A78]
                    [--fc-event-bg-color:#2F5A78] [--fc-event-border-color:#2F5A78]
                    [--fc-border-color:#B6D5E5] [--fc-page-bg-color:transparent]
                    [--fc-neutral-bg-color:#B6D5E5] [--fc-neutral-text-color:#374151]
                    [--fc-today-bg-color:rgba(127,169,201,0.20)] [--fc-highlight-color:rgba(127,169,201,0.25)]
                    [--fc-more-link-bg-color:#B6D5E5] [--fc-more-link-text-color:#2F5A78]
                    dark:[--fc-border-color:#1A3242] dark:[--fc-neutral-bg-color:#12232F] dark:[--fc-neutral-text-color:#d6d3d1]
                    dark:[--fc-today-bg-color:rgba(127,169,201,0.15)] dark:[--fc-highlight-color:rgba(127,169,201,0.2)]
                    dark:[--fc-more-link-bg-color:#1A3242] dark:[--fc-more-link-text-color:#e7e5e4]"
            >
                <div x-ref="calendario"></div>
            </div>

            {{-- Modal: nuevo evento (botón "+ Nuevo evento" o clic en un día vacío) --}}
            <div x-show="modalAbierto" x-cloak @keydown.escape.window="modalAbierto = false" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div x-show="modalAbierto" x-transition.opacity @click="modalAbierto = false" class="absolute inset-0 bg-slate-900/40"></div>
                <div x-show="modalAbierto" x-transition class="relative bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-xl w-full max-w-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-semibold text-texto dark:text-slate-100">Nuevo evento</h2>
                        <button type="button" @click="modalAbierto = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <x-icono nombre="cerrar" class="h-5 w-5" />
                        </button>
                    </div>

                    <form wire:submit="crear" class="space-y-3">
                        <div>
                            <x-input-label for="nuevoTitulo" value="Título" />
                            <x-text-input wire:model="nuevoTitulo" id="nuevoTitulo" class="mt-1 block w-full" placeholder="Repasar unidad 2…" />
                            <x-input-error :messages="$errors->get('nuevoTitulo')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="nuevaFecha" value="Fecha y hora" />
                            {{-- wire:ignore: Flatpickr toma este subárbol (crea su propio input
                                 visible + calendario emergente); si Livewire lo re-renderizara
                                 tras cada submit perdería esa UI. El valor sigue llegando al
                                 servidor normalmente vía wire:model, que ya escuchaba el evento
                                 "change" del input original antes de este wire:ignore. --}}
                            <div wire:ignore>
                                <input wire:model="nuevaFecha" x-ref="nuevaFecha" id="nuevaFecha" type="text"
                                    class="mt-1 block w-full rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo text-sm">
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach ([['9:00 AM', 9, 0], ['12:00 PM', 12, 0], ['3:00 PM', 15, 0], ['6:00 PM', 18, 0], ['8:00 PM', 20, 0]] as [$etiqueta, $hora24, $minuto])
                                        <button type="button" @click="ponerHora({{ $hora24 }}, {{ $minuto }})"
                                            class="px-2 py-1 rounded-md text-[11px] font-medium border border-celeste dark:border-azul-borde-oscuro text-azul-oscuro dark:text-azul-fondo hover:bg-celeste/30 dark:hover:bg-azul-borde-oscuro/40 transition">
                                            {{ $etiqueta }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('nuevaFecha')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="nuevoTipo" value="Tipo" />
                            <select wire:model="nuevoTipo" id="nuevoTipo"
                                class="mt-1 block w-full rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo text-sm">
                                <option value="bloque_estudio">Bloque de estudio</option>
                                <option value="recordatorio">Recordatorio</option>
                                <option value="evento">Evento</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modalAbierto = false" class="px-4 py-2 rounded-md text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-celeste/30 dark:hover:bg-azul-borde-oscuro/40 transition">Cancelar</button>
                            <x-primary-button type="submit">Agregar</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Panel: detalle de un evento propio (clic en el calendario) --}}
            <div x-show="detalleEvento" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div x-show="detalleEvento" x-transition.opacity @click="detalleEvento = null" class="absolute inset-0 bg-slate-900/40"></div>
                <div x-show="detalleEvento" x-transition @click.outside="detalleEvento = null"
                    class="relative bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-xl w-full max-w-sm p-5">
                    <template x-if="detalleEvento">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="font-semibold text-texto dark:text-slate-100" x-text="detalleEvento.titulo"></h2>
                                <button type="button" @click="detalleEvento = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 shrink-0">
                                    <x-icono nombre="cerrar" class="h-5 w-5" />
                                </button>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400" x-text="detalleEvento.fecha"></p>
                            <div class="flex justify-end pt-2">
                                <button type="button" @click="$wire.eliminar(detalleEvento.id); detalleEvento = null"
                                    class="text-xs font-semibold text-coral-oscuro hover:underline">Eliminar evento</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">Tus eventos propios</h2>
                <ul class="space-y-1">
                    @forelse (auth()->user()->eventosCalendario()->orderBy('fecha')->get() as $evento)
                        <li class="flex items-center justify-between bg-white dark:bg-azul-superficie-oscura rounded-lg border border-celeste dark:border-azul-borde-oscuro px-3 py-2 text-sm">
                            <span>{{ $evento->titulo }} — <span class="text-slate-400">{{ $evento->fecha->format('d/m/Y H:i') }}</span></span>
                            <button wire:click="eliminar({{ $evento->id }})" wire:confirm="¿Eliminar este evento?" class="text-xs text-coral-oscuro hover:underline">Eliminar</button>
                        </li>
                    @empty
                        <li class="text-sm text-slate-400">Todavía no has creado eventos propios.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <style>
            .calendario-estudiante .fc-event-title {
                font-size: 0.72rem;
                line-height: 1.15;
                white-space: normal;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .calendario-estudiante .fc-daygrid-event {
                padding: 2px 4px;
                margin-top: 2px;
                white-space: normal;
            }
            .calendario-estudiante .fc-daygrid-day-number {
                font-size: 0.8rem;
            }
        </style>
    </div>
@else
    {{-- Vista de profesor/administrador: sin cambios respecto a la versión anterior. --}}
    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Calendario</h1>
                <p class="text-sm text-stone-500 dark:text-stone-400">
                    Tus bloques de estudio y recordatorios, más las fechas límite de tus tareas.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs text-stone-500 dark:text-stone-400">
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-menta-oscuro"></span> Bloque de estudio</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background:#B2604A"></span> Recordatorio / tarea</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background:#86715E"></span> Evento</span>
            </div>

            <form wire:submit="crear" class="bg-white dark:bg-menta-superficie-oscura rounded-xl border border-arena-medio dark:border-menta-borde-oscuro shadow-sm dark:shadow-none p-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[180px]">
                    <x-input-label for="nuevoTitulo" value="Título" />
                    <x-text-input wire:model="nuevoTitulo" id="nuevoTitulo" class="mt-1 block w-full" placeholder="Repasar unidad 2…" />
                    <x-input-error :messages="$errors->get('nuevoTitulo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="nuevaFecha" value="Fecha y hora" />
                    <input wire:model="nuevaFecha" id="nuevaFecha" type="datetime-local"
                        class="mt-1 block rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                    <x-input-error :messages="$errors->get('nuevaFecha')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="nuevoTipo" value="Tipo" />
                    <select wire:model="nuevoTipo" id="nuevoTipo"
                        class="mt-1 block rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                        <option value="bloque_estudio">Bloque de estudio</option>
                        <option value="recordatorio">Recordatorio</option>
                        <option value="evento">Evento</option>
                    </select>
                </div>
                <x-primary-button type="submit">Agregar</x-primary-button>
            </form>

            <div wire:ignore
                x-data="{
                    calendar: null,
                    init() {
                        this.calendar = new window.FullCalendar.Calendar(this.$refs.calendario, {
                            plugins: [window.FullCalendar.dayGridPlugin, window.FullCalendar.timeGridPlugin, window.FullCalendar.listPlugin, window.FullCalendar.interactionPlugin],
                            initialView: 'dayGridMonth',
                            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
                            height: 650,
                            events: @js($this->eventos),
                            eventClick: (info) => {
                                if (info.event.url) {
                                    info.jsEvent.preventDefault();
                                    window.location.href = info.event.url;
                                }
                            },
                        });
                        this.calendar.render();

                        $wire.on('evento-creado', ({ evento }) => this.calendar.addEvent(evento));
                        $wire.on('evento-eliminado', ({ id }) => this.calendar.getEventById(id)?.remove());
                    },
                }"
                class="bg-white dark:bg-menta-superficie-oscura rounded-xl border border-arena-medio dark:border-menta-borde-oscuro shadow-sm dark:shadow-none p-4 text-stone-700 dark:text-stone-200
                    [--fc-button-bg-color:#3C7A75] [--fc-button-border-color:#3C7A75]
                    [--fc-button-hover-bg-color:#7FB3AE] [--fc-button-hover-border-color:#7FB3AE]
                    [--fc-button-active-bg-color:#3C7A75] [--fc-button-active-border-color:#3C7A75]
                    [--fc-event-bg-color:#3C7A75] [--fc-event-border-color:#3C7A75]
                    [--fc-border-color:#E7D8C9] [--fc-page-bg-color:transparent]
                    [--fc-neutral-bg-color:#EEE4E1] [--fc-neutral-text-color:#57534e]
                    [--fc-today-bg-color:rgba(127,179,174,0.20)] [--fc-highlight-color:rgba(127,179,174,0.25)]
                    [--fc-more-link-bg-color:#E7D8C9] [--fc-more-link-text-color:#44403c]
                    dark:[--fc-border-color:#20433c] dark:[--fc-neutral-bg-color:#16302c] dark:[--fc-neutral-text-color:#d6d3d1]
                    dark:[--fc-today-bg-color:rgba(127,179,174,0.15)] dark:[--fc-highlight-color:rgba(127,179,174,0.2)]
                    dark:[--fc-more-link-bg-color:#20433c] dark:[--fc-more-link-text-color:#e7e5e4]"
            >
                <div x-ref="calendario"></div>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-stone-600 dark:text-stone-300 mb-2">Tus eventos propios</h2>
                <ul class="space-y-1">
                    @forelse (auth()->user()->eventosCalendario()->orderBy('fecha')->get() as $evento)
                        <li class="flex items-center justify-between bg-white dark:bg-menta-superficie-oscura rounded-lg border border-arena-medio dark:border-menta-borde-oscuro px-3 py-2 text-sm">
                            <span>{{ $evento->titulo }} — <span class="text-stone-400">{{ $evento->fecha->format('d/m/Y H:i') }}</span></span>
                            <button wire:click="eliminar({{ $evento->id }})" wire:confirm="¿Eliminar este evento?" class="text-xs text-terracota-oscuro hover:underline">Eliminar</button>
                        </li>
                    @empty
                        <li class="text-sm text-stone-400">Todavía no has creado eventos propios.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endif
