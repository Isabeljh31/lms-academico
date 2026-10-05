<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Libro de calificaciones" icono="lista" :descripcion="$curso->nombre.' · Nota final ponderada por rubros (escala 0 a 20)'"
            :volver="route('cursos.show', $curso)" volver-texto="Volver al curso">
            <x-gestion.boton variante="secundario" icono="descargar" wire:click="exportarExcel">Exportar a Excel</x-gestion.boton>
        </x-gestion.encabezado>

        <x-gestion.alertas />

        @if ($rubros->isEmpty())
            <x-gestion.vacio icono="grafico" titulo="El curso todavía no tiene esquema de evaluación"
                descripcion="El Supervisor debe definir los rubros y sus pesos antes de registrar notas.">
                @can('administrar', $curso)
                    <x-gestion.boton icono="grafico" :href="route('cursos.esquema', $curso)">Definir esquema</x-gestion.boton>
                @endcan
            </x-gestion.vacio>
        @else
            @unless ($this->esquemaValido)
                <div role="alert" class="flex items-start gap-3 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                    <x-icono nombre="alerta" class="h-5 w-5 shrink-0" />
                    <p>Los pesos del esquema suman {{ number_format($sumaPesos, 2) }} %. Hasta que sumen 100 % no se pueden registrar notas.</p>
                </div>
            @endunless
            @unless ($this->puedeCalificar)
                <div class="flex items-start gap-3 rounded-xl border border-arena-medio bg-white px-4 py-3 text-sm text-stone-600 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300">
                    <x-icono nombre="info" class="h-5 w-5 shrink-0 text-menta-oscuro" />
                    <p>{{ $curso->estaFinalizado() ? 'Curso archivado: las notas se muestran en modo consulta.' : 'Modo consulta: solo el profesor del curso registra y edita notas.' }}</p>
                </div>
            @endunless

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-gestion.indicador etiqueta="Promedio a la fecha" :valor="$promedioCurso !== null ? $promedioCurso : '—'" icono="grafico"
                    :tono="$promedioCurso !== null && $promedioCurso < $notaMinima ? 'alerta' : 'menta'" detalle="Rubros ya calificados" />
                <x-gestion.indicador etiqueta="Aprobación" :valor="$tasaAprobacion !== null ? $tasaAprobacion.' %' : '—'" icono="check-circulo" tono="exito"
                    :detalle="$tasaAprobacion !== null ? $aprobados.' aprobados' : $enCurso.' en curso: se calcula al completar los rubros'" />
                <x-gestion.indicador etiqueta="Rendimiento máx. / mín." :valor="($notaMax ?? '—').' / '.($notaMin ?? '—')" icono="lista" tono="cafe" />
                <x-gestion.indicador etiqueta="Nota mínima aprobatoria" :valor="$notaMinima" icono="escudo" tono="terracota" />
            </div>

            <div class="flex gap-1 border-b border-arena-medio dark:border-stone-700" role="tablist">
                @foreach (['rubros' => 'Evaluación por rubros', 'actividades' => 'Tareas y quizzes (práctica)'] as $clave => $etiqueta)
                    <button type="button" role="tab" wire:click="$set('vistaActiva', '{{ $clave }}')" aria-selected="{{ $vistaActiva === $clave ? 'true' : 'false' }}"
                        class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ $vistaActiva === $clave ? 'border-menta-oscuro text-menta-oscuro dark:border-menta-base dark:text-menta-base' : 'border-transparent text-stone-500 hover:text-stone-700' }}">
                        {{ $etiqueta }}
                    </button>
                @endforeach
            </div>

            @if ($vistaActiva === 'rubros')
                <x-gestion.tarjeta :relleno="false">
                    @if ($filasRubros->isEmpty())
                        <div class="p-4"><x-gestion.vacio icono="usuarios" titulo="No hay estudiantes matriculados" /></div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-arena-claro/50 text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                                    <tr>
                                        <th class="sticky left-0 z-10 min-w-[200px] bg-arena-claro px-4 py-3 text-left dark:bg-stone-900">Estudiante</th>
                                        @foreach ($rubros as $rubro)
                                            <th class="min-w-[110px] px-3 py-3 text-center">
                                                <span class="block normal-case text-stone-700 dark:text-stone-200">{{ $rubro->nombre }}</span>
                                                <span class="font-normal normal-case">{{ (float) $rubro->peso }} %@if ($rubro->semana) · sem. {{ $rubro->semana }} @endif</span>
                                            </th>
                                        @endforeach
                                        <th class="min-w-[110px] px-3 py-3 text-center">Nota final</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                                    @foreach ($filasRubros as $fila)
                                        <tr wire:key="fila-{{ $fila['estudiante']->id }}">
                                            <td class="sticky left-0 z-10 bg-white px-4 py-2 dark:bg-stone-800">
                                                <p class="font-medium text-stone-800 dark:text-stone-100">{{ $fila['estudiante']->name }}</p>
                                                <p class="text-xs text-stone-500">{{ $fila['estudiante']->email }}</p>
                                            </td>
                                            @foreach ($rubros as $rubro)
                                                @php $nota = $fila['notas'][$rubro->id] ?? null; @endphp
                                                <td class="px-3 py-2 text-center">
                                                    @if ($this->puedeCalificar && $this->esquemaValido)
                                                        <button type="button" wire:click="abrirModalNota({{ $rubro->id }}, {{ $fila['estudiante']->id }})"
                                                            class="min-w-[3.5rem] rounded-lg border border-dashed px-2 py-1 text-sm font-semibold transition hover:border-menta-oscuro
                                                                {{ $nota === null ? 'border-arena-medio text-stone-400 dark:border-stone-600' : ($nota < $notaMinima ? 'border-transparent bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'border-transparent bg-exito-base/10 text-exito-oscuro dark:text-green-300') }}"
                                                            aria-label="Nota de {{ $fila['estudiante']->name }} en {{ $rubro->nombre }}">
                                                            {{ $nota !== null ? number_format($nota, 1) : 'Registrar' }}
                                                        </button>
                                                    @else
                                                        <span class="font-semibold {{ $nota !== null && $nota < $notaMinima ? 'text-red-600' : 'text-stone-700 dark:text-stone-200' }}">{{ $nota !== null ? number_format($nota, 1) : '—' }}</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="px-3 py-2 text-center">
                                                @if ($fila['condicion'] === 'en_curso')
                                                    {{-- Faltan rubros: nota acumulada, sin aprobar ni desaprobar todavía. --}}
                                                    <span class="inline-flex min-w-[3.5rem] justify-center rounded-full bg-arena-claro px-2.5 py-1 text-sm font-bold text-stone-700 dark:bg-stone-700 dark:text-stone-200"
                                                        title="Nota acumulada con {{ $fila['rubros_con_nota'] }} de {{ $rubros->count() }} rubros. Rendimiento a la fecha: {{ number_format($fila['rendimiento'], 1) }}">
                                                        {{ number_format($fila['nota_final'], 1) }}
                                                    </span>
                                                    <p class="mt-1 text-[11px] text-stone-500">En curso · {{ $fila['rubros_con_nota'] }}/{{ $rubros->count() }}</p>
                                                @elseif ($fila['nota_final'] !== null)
                                                    <span class="inline-flex min-w-[3.5rem] justify-center rounded-full px-2.5 py-1 text-sm font-bold {{ $fila['aprobado'] ? 'bg-exito-base/15 text-exito-oscuro dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300' }}">
                                                        {{ number_format($fila['nota_final'], 1) }}
                                                    </span>
                                                @else
                                                    <span class="text-stone-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-gestion.tarjeta>
            @endif
        @endif

        @if ($vistaActiva === 'actividades')
            <x-gestion.tarjeta :relleno="false" titulo="Notas de práctica" descripcion="No se promedian ni se suman automáticamente a la nota final: el profesor decide cómo considerarlas en cada rubro.">
                @if ($tareas->isEmpty() && $quizzes->isEmpty())
                    <div class="p-4"><x-gestion.vacio icono="tarea" titulo="El curso todavía no tiene tareas ni quizzes" /></div>
                @elseif ($estudiantes->isEmpty())
                    <div class="p-4"><x-gestion.vacio icono="usuarios" titulo="No hay estudiantes matriculados" /></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-arena-claro/50 text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                                <tr>
                                    <th class="sticky left-0 z-10 min-w-[200px] bg-arena-claro px-4 py-3 text-left dark:bg-stone-900">Estudiante</th>
                                    @foreach ($tareas as $tarea)
                                        <th class="min-w-[110px] px-3 py-3 text-center normal-case"><span class="inline-flex items-center gap-1"><x-icono nombre="tarea" class="h-3.5 w-3.5" /> {{ \Illuminate\Support\Str::limit($tarea->titulo, 22) }}</span></th>
                                    @endforeach
                                    @foreach ($quizzes as $quiz)
                                        <th class="min-w-[110px] px-3 py-3 text-center normal-case"><span class="inline-flex items-center gap-1"><x-icono nombre="quiz" class="h-3.5 w-3.5" /> {{ \Illuminate\Support\Str::limit($quiz->tema, 22) }}</span></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                                @foreach ($estudiantes as $estudiante)
                                    <tr wire:key="act-{{ $estudiante->id }}">
                                        <td class="sticky left-0 z-10 bg-white px-4 py-2 font-medium text-stone-800 dark:bg-stone-800 dark:text-stone-100">{{ $estudiante->name }}</td>
                                        @foreach ($tareas as $tarea)
                                            @php $entrega = $entregas["{$estudiante->id}_{$tarea->id}"] ?? null; @endphp
                                            <td class="px-3 py-2 text-center">
                                                @if ($this->puedeCalificar)
                                                    <button type="button" wire:click="abrirEdicionNota({{ $estudiante->id }}, {{ $tarea->id }})" class="rounded px-2 py-1 hover:bg-arena-claro dark:hover:bg-stone-700">
                                                        {{ $entrega?->nota !== null ? $entrega->nota : '—' }}
                                                    </button>
                                                @else
                                                    {{ $entrega?->nota ?? '—' }}
                                                @endif
                                                <x-badge-estado :estado="$tarea->estadoPara($entrega)" class="mt-1 !px-1.5 !text-[10px]" />
                                            </td>
                                        @endforeach
                                        @foreach ($quizzes as $quiz)
                                            @php $puntaje = $resultados["{$estudiante->id}_{$quiz->id}"] ?? null; @endphp
                                            <td class="px-3 py-2 text-center text-stone-700 dark:text-stone-200">{{ $puntaje !== null ? $puntaje : '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-gestion.tarjeta>
        @endif
    </div>

    @if ($mostrarModalNota && $rubroModal && $estudianteModal)
        <x-gestion.modal titulo="Nota de {{ $rubroModal->nombre }}" cerrar="$set('mostrarModalNota', false)" ancho="max-w-md"
            descripcion="{{ $estudianteModal->name }} · Peso {{ (float) $rubroModal->peso }} % · Escala 0 a 20">
            <x-gestion.campo etiqueta="Nota" nombre="notaInput" requerido>
                <x-gestion.control id="notaInput" type="number" step="0.1" min="0" max="20" wire:model="notaInput" autofocus />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Motivo del ajuste" nombre="motivoInput" ayuda="Obligatorio si modificas una nota ya registrada. Queda en la bitácora de auditoría.">
                <x-gestion.control tipo="textarea" id="motivoInput" rows="2" wire:model="motivoInput" />
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalNota', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton icono="check" wire:click="guardarNotaRubro">Guardar nota</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarModalTarea)
        <x-gestion.modal titulo="Nota de tarea" cerrar="$set('mostrarModalTarea', false)" ancho="max-w-md">
            <x-gestion.campo etiqueta="Nota (0 a 20)" nombre="notaTareaInput" requerido>
                <x-gestion.control id="notaTareaInput" type="number" step="0.01" min="0" max="20" wire:model="notaTareaInput" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Comentario" nombre="feedbackTareaInput">
                <x-gestion.control tipo="textarea" id="feedbackTareaInput" rows="2" wire:model="feedbackTareaInput" />
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalTarea', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton icono="check" wire:click="guardarNotaTarea">Guardar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
