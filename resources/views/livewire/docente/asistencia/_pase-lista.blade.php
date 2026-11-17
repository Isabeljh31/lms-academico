{{--
    Pase de lista / Ver lista de una clase (vista 'pase_lista' de Asistencia\Index)
    con el diseño del Profesor. Misma lógica que la vista compartida: las marcas
    viven en $marcas (entangle diferido: marcar no consulta al servidor),
    «Todos presentes» es marcarTodos('presente') y «Guardar asistencia» es
    guardarAsistencia(), que valida y aplica la regla de clases futuras.
    Solo lectura: curso no editable o clase de un día futuro.
--}}
@use('App\Services\Reportes\AsistenciaProfesor')
@use('App\Services\Reportes\CursosProfesor')
@use('App\Services\Reportes\DetalleCursoProfesor')
@use('App\Services\Reportes\PanelProfesor')
@php
    $estilosMarca = [
        'presente' => ['texto' => 'Presente', 'plural' => 'Presentes', 'color' => '#136E61', 'tinta' => '#ffffff'],
        'tardanza' => ['texto' => 'Tardanza', 'plural' => 'Tardanzas', 'color' => '#F2B233', 'tinta' => '#3f2a00'],
        'justificado' => ['texto' => 'Justificado', 'plural' => 'Justificados', 'color' => '#8A9BB3', 'tinta' => '#ffffff'],
        'ausente' => ['texto' => 'Ausente', 'plural' => 'Ausentes', 'color' => '#E04B55', 'tinta' => '#ffffff'],
    ];
    $bloqueada = ! $sesionActiva->permiteTomarLista();
    $soloLectura = ! $this->editable || $bloqueada;
    $aula = AsistenciaProfesor::aula($sesionActiva);
    $fin = $sesionActiva->fecha->copy()->addMinutes((int) $sesionActiva->duracion_minutos);
    $semanaSesion = CursosProfesor::semanaActual($curso, $curso->totalSemanas(), $sesionActiva->fecha);
    $ids = $estudiantes->pluck('id')->map(fn ($id) => (string) $id)->values();
    $guardadasJs = collect($guardadas)->mapWithKeys(fn ($estado, $id) => [(string) $id => $estado]);
@endphp

<section aria-labelledby="titulo-pase-lista" class="flex flex-col gap-4"
    x-data="{
        marcas: $wire.entangle('marcas'),
        guardadas: @js($guardadasJs),
        ids: @js($ids),
        buscar: '',
        soloLectura: @js($soloLectura),
        semana: @js($semanaSesion),
        valor(id) { return (this.marcas && this.marcas[id]) || ''; },
        get conteo() {
            const conteo = { presente: 0, tardanza: 0, justificado: 0, ausente: 0, sin: 0 };
            this.ids.forEach(id => { const estado = this.valor(id); estado in conteo ? conteo[estado]++ : conteo.sin++; });
            return conteo;
        },
        get marcados() { return this.ids.length - this.conteo.sin; },
        get sinGuardar() { return this.ids.some(id => this.valor(id) !== (this.guardadas[id] || '')); },
        marcar(id, estado) { if (this.soloLectura) return; this.marcas = { ...(this.marcas || {}), [id]: estado }; },
        coincide(nombre) {
            const texto = this.buscar.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
            return texto === '' || nombre.includes(texto);
        },
        volver() {
            if (this.semana) {
                const url = new URL(window.location.href);
                url.searchParams.set('semana', this.semana);
                history.replaceState(history.state, '', url);
            }
            $wire.set('vista', 'sesiones');
        },
        async guardar() {
            await $wire.guardarAsistencia();
            this.$nextTick(() => { if (! document.getElementById('errores-pase-lista')) this.volver(); });
        },
    }">
    <div class="{{ $tarjeta }} overflow-hidden">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 border-b border-[#e8f0ee] px-6 py-4">
            <button type="button" x-on:click="volver()" class="{{ $botonContorno }}">
                <x-icono nombre="flecha-izquierda" class="h-4 w-4" /> Volver a la semana
            </button>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 id="titulo-pase-lista" class="truncate text-[22px] font-extrabold tracking-[-0.01em] text-[var(--p900)]">{{ $sesionActiva->titulo }}</h1>
                    @if ($soloLectura)
                        <span class="rounded-full bg-[#eef3f1] px-2.5 py-0.5 text-[12px] font-bold text-[#3d5753]">Solo lectura</span>
                    @endif
                </div>
                <p class="text-[13.5px] text-[#4b625e]">
                    {{ collect([
                        DetalleCursoProfesor::fechaCorta($sesionActiva->fecha).' · '.$sesionActiva->fecha->format('H:i').' – '.$fin->format('H:i'),
                        $aula ? 'Aula '.$aula : null,
                    ])->filter()->join(' · ') }}
                    · <span x-text="marcados">{{ collect($marcas)->filter()->count() }}</span> de {{ $estudiantes->count() }} marcados
                </p>
            </div>
            @if (! $soloLectura && $estudiantes->isNotEmpty())
                <button type="button" wire:click="marcarTodos('presente')" class="{{ $botonContorno }}">
                    <x-icono nombre="check" class="h-4 w-4" /> Todos presentes
                </button>
            @endif
        </div>

        @if ($bloqueada)
            <p role="alert" class="flex items-center gap-2 border-b border-[#e8f0ee] bg-[var(--warn100)] px-6 py-3 text-[13.5px] font-semibold text-[var(--warn800)]">
                <x-icono nombre="reloj" class="h-4 w-4 shrink-0" /> {{ \App\Models\SesionClase::MENSAJE_CLASE_FUTURA }}
            </p>
        @elseif (! $this->editable)
            <p class="border-b border-[#e8f0ee] bg-[#fafcfb] px-6 py-2.5 text-[12.5px] text-[#4b625e]">Este curso no admite cambios en la asistencia.</p>
        @endif

        @if ($estudiantes->isEmpty())
            <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
                <x-icono nombre="usuarios" class="h-7 w-7 text-[var(--p700)]" />
                <p class="font-bold text-[var(--p900)]">No hay estudiantes matriculados</p>
                <p class="text-[13px] text-[#4b625e]">Cuando el curso tenga estudiantes con matrícula activa, aparecerán aquí.</p>
            </div>
        @else
            {{-- Resumen en vivo: se calcula en el navegador a partir de las marcas. --}}
            <div class="flex flex-col gap-2.5 border-b border-[#e8f0ee] px-6 py-4">
                <div class="flex h-2.5 overflow-hidden rounded-full bg-[#e1ebe8]" role="img" aria-label="Distribución de las marcas"
                    x-bind:aria-label="`${conteo.presente} presentes, ${conteo.tardanza} tardanzas, ${conteo.justificado} justificados, ${conteo.ausente} ausentes, ${conteo.sin} sin marcar`">
                    @foreach ($estilosMarca as $estado => $estilo)
                        <span class="h-full transition-[width] duration-200 motion-reduce:transition-none" style="background-color: {{ $estilo['color'] }}; width: 0%"
                            x-bind:style="`background-color: {{ $estilo['color'] }}; width: ${ids.length ? conteo.{{ $estado }} * 100 / ids.length : 0}%`"></span>
                    @endforeach
                </div>
                <ul class="flex flex-wrap gap-x-5 gap-y-1.5 text-[13px] text-[#3d5753]" aria-live="polite">
                    @foreach ($estilosMarca as $estado => $estilo)
                        <li class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $estilo['color'] }}" aria-hidden="true"></span>
                            {{ $estilo['plural'] }} <strong class="text-[var(--p900)]" x-text="conteo.{{ $estado }}">{{ collect($marcas)->filter(fn ($marca) => $marca === $estado)->count() }}</strong>
                        </li>
                    @endforeach
                    <li class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-sm bg-[#d5dfdc]" aria-hidden="true"></span>
                        Sin marcar <strong class="text-[var(--p900)]" x-text="conteo.sin">{{ collect($marcas)->reject()->count() }}</strong>
                    </li>
                </ul>
                <label class="mt-1 flex h-10 w-full max-w-[360px] items-center gap-2.5 rounded-xl bg-[#eef6f3] px-3.5 text-[#5b706c] focus-within:ring-[3px] focus-within:ring-[rgba(19,110,97,0.16)]">
                    <x-icono nombre="lupa" class="h-4 w-4 shrink-0" />
                    <span class="sr-only">Buscar estudiante en la lista</span>
                    <input type="search" x-model="buscar" placeholder="Buscar estudiante" autocomplete="off"
                        class="w-full min-w-0 border-0 bg-transparent p-0 text-[14px] text-[var(--ink)] placeholder:text-[#5b706c] focus:outline-none focus:ring-0">
                </label>
            </div>

            @if ($errors->any())
                <p id="errores-pase-lista" role="alert" class="border-b border-[#e8f0ee] bg-[var(--err100)] px-6 py-2.5 text-[13px] font-semibold text-[var(--err700)]">
                    Marca el estado de todos los estudiantes antes de guardar.
                </p>
            @endif

            <ul class="divide-y divide-[#eef4f2]">
                @foreach ($estudiantes as $estudiante)
                    @php $nombreBusqueda = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($estudiante->name)); @endphp
                    <li class="flex flex-col gap-2 px-6 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="marca-{{ $estudiante->id }}"
                        x-show="coincide(@js($nombreBusqueda))">
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--mint)] text-[11.5px] font-bold text-[var(--p700)]" aria-hidden="true">{{ PanelProfesor::iniciales($estudiante->name) }}</span>
                            <span class="truncate text-[14.5px] font-semibold text-[var(--ink)]">{{ $estudiante->name }}</span>
                        </span>
                        <div class="flex flex-col items-start gap-1 sm:items-end">
                            <div class="inline-flex flex-wrap gap-1.5" role="group" aria-label="Asistencia de {{ $estudiante->name }}">
                                @foreach ($estilosMarca as $estado => $estilo)
                                    @php $elegido = ($marcas[$estudiante->id] ?? '') === $estado; @endphp
                                    <button type="button" @disabled($soloLectura)
                                        x-on:click="marcar('{{ $estudiante->id }}', '{{ $estado }}')"
                                        aria-pressed="{{ $elegido ? 'true' : 'false' }}"
                                        x-bind:aria-pressed="valor('{{ $estudiante->id }}') === '{{ $estado }}' ? 'true' : 'false'"
                                        @if ($elegido) style="background-color: {{ $estilo['color'] }}; border-color: {{ $estilo['color'] }}; color: {{ $estilo['tinta'] }}" @endif
                                        x-bind:style="valor('{{ $estudiante->id }}') === '{{ $estado }}' ? 'background-color: {{ $estilo['color'] }}; border-color: {{ $estilo['color'] }}; color: {{ $estilo['tinta'] }}' : ''"
                                        class="inline-flex h-9 items-center rounded-full border-[1.5px] border-[#d6e6e2] bg-white px-3.5 text-[13px] font-bold text-[#3d5753] transition-colors hover:bg-[var(--mint)] disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:bg-white motion-reduce:transition-none {{ $enfoque }}">
                                        {{ $estilo['texto'] }}
                                    </button>
                                @endforeach
                            </div>
                            @error('marcas.'.$estudiante->id) <p class="text-xs font-semibold text-[var(--err700)]">{{ $message }}</p> @enderror
                        </div>
                    </li>
                @endforeach
            </ul>
            <p x-show="ids.length > 0 && ! @js($estudiantes->pluck('name')->map(fn ($nombre) => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($nombre)))->values()).some(nombre => coincide(nombre))" style="display: none"
                class="px-6 py-6 text-center text-[13.5px] text-[#4b625e]">Ningún estudiante coincide con la búsqueda.</p>
        @endif
    </div>

    {{-- Barra fija para guardar: queda sobre el dock inferior (el dock está más arriba en el apilamiento). --}}
    @if (! $soloLectura && $estudiantes->isNotEmpty())
        <div class="sticky bottom-[calc(96px+env(safe-area-inset-bottom))] z-[35] flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3 shadow-[0_8px_28px_rgba(9,59,59,0.18)]">
            <p class="text-[13.5px] text-[#4b625e]" aria-live="polite">
                <span x-show="sinGuardar" style="display: none" class="inline-flex items-center gap-2 font-bold text-[var(--warn800)]">
                    <span class="h-2 w-2 rounded-full bg-[#F2B233]" aria-hidden="true"></span> Tienes cambios sin guardar
                </span>
                <span x-show="! sinGuardar">Sin cambios pendientes</span>
            </p>
            <button type="button" x-on:click="guardar()" wire:loading.attr="disabled" wire:target="guardarAsistencia" class="{{ $botonOscuro }} disabled:opacity-60">
                <x-icono nombre="check" class="h-4 w-4" /> Guardar asistencia
            </button>
        </div>
    @endif
</section>
