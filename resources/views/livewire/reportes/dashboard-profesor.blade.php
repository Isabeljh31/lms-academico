@php
    $esAdminVista = auth()->user()->hasRole('supervisor');
    $etiquetas = $filas->map(fn ($f) => $f['curso']->nombre)->values();
    $promedios = $filas->map(fn ($f) => $f['promedio_final'] ?? 0)->values();
    $avances = $filas->map(fn ($f) => $f['progreso_promedio'])->values();
    $totalEstudiantes = $filas->sum('inscritos');
    $totalBajo = $filas->sum(fn ($f) => $f['bajo_rendimiento']->count());
    $conNota = $filas->pluck('promedio_final')->filter(fn ($v) => $v !== null);
@endphp

<div class="py-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Reportes de cursos" icono="grafico"
            :descripcion="auth()->user()->hasRole('supervisor') ? 'Indicadores académicos de todos los cursos.' : 'Indicadores académicos de tus cursos.'" />

        @if ($filas->isEmpty())
            <x-gestion.vacio icono="grafico" titulo="No hay cursos para mostrar todavía"
                descripcion="Cuando tengas cursos asignados verás aquí su progreso, notas y aprobación." />
        @else
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-gestion.indicador etiqueta="Cursos" :valor="$filas->count()" icono="libro" />
                <x-gestion.indicador etiqueta="Estudiantes" :valor="$totalEstudiantes" icono="usuarios" tono="cafe" />
                <x-gestion.indicador etiqueta="Promedio general" :valor="$conNota->isNotEmpty() ? round($conNota->avg(), 1) : '—'" icono="lista" tono="exito" detalle="Promedio a la fecha por rubros" />
                <x-gestion.indicador etiqueta="Bajo rendimiento" :valor="$totalBajo" icono="alerta" :tono="$totalBajo > 0 ? 'alerta' : 'exito'" :detalle="'Rendimiento bajo '.$notaMinima.' o 2+ tareas vencidas sin entregar'" />
            </div>

            <x-gestion.tarjeta titulo="Comparativo por curso">
                <div wire:ignore
                    x-data="{
                        init() {
                            new window.Chart(this.$refs.grafico, {
                                type: 'bar',
                                data: {
                                    labels: @js($etiquetas),
                                    datasets: [
                                        { label: 'Promedio a la fecha (0-20)', data: @js($promedios), backgroundColor: '#3C7A75' },
                                        { label: 'Avance promedio (%)', data: @js($avances), backgroundColor: '#B2967D' },
                                    ],
                                },
                                options: { responsive: true, maintainAspectRatio: false },
                            });
                        },
                    }" class="h-64">
                    <canvas x-ref="grafico" aria-label="Gráfico comparativo por curso" role="img"></canvas>
                </div>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta :relleno="false" titulo="Detalle por curso">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide {{ $esAdminVista ? 'bg-arena-claro/50 text-stone-500 dark:bg-stone-900/40 dark:text-stone-400' : 'bg-celeste/50 text-slate-500 dark:bg-azul-pagina-oscura dark:text-slate-400' }}">
                            <tr>
                                <th class="px-4 py-3">Curso</th>
                                <th class="px-3 py-3 text-center">Estudiantes</th>
                                <th class="px-3 py-3 text-center">Avance prom.</th>
                                <th class="px-3 py-3 text-center">Promedio a la fecha</th>
                                <th class="px-3 py-3 text-center">Aprobación</th>
                                <th class="px-3 py-3 text-center">Entregas</th>
                                <th class="px-3 py-3 text-center">Tasa de entrega</th>
                                <th class="px-3 py-3"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y {{ $esAdminVista ? 'divide-arena-medio/60 dark:divide-stone-700' : 'divide-celeste/60 dark:divide-azul-borde-oscuro' }}">
                            @foreach ($filas as $fila)
                                <tr wire:key="curso-{{ $fila['curso']->id }}">
                                    <td class="px-4 py-3 font-medium text-stone-800 dark:text-stone-100">{{ $fila['curso']->nombre }}</td>
                                    <td class="px-3 py-3 text-center">{{ $fila['inscritos'] }}</td>
                                    <td class="px-3 py-3 text-center">{{ $fila['progreso_promedio'] }} %</td>
                                    <td class="px-3 py-3 text-center font-semibold {{ $fila['promedio_final'] !== null && $fila['promedio_final'] < $notaMinima ? 'text-red-600' : 'text-stone-700 dark:text-stone-200' }}">{{ $fila['promedio_final'] ?? '—' }}</td>
                                    <td class="px-3 py-3 text-center">{{ $fila['tasa_aprobacion'] !== null ? $fila['tasa_aprobacion'].' %' : '—' }}</td>
                                    <td class="px-3 py-3 text-center">{{ $fila['entregas'] }} <span class="text-xs text-stone-500">({{ $fila['por_calificar'] }} por calificar)</span></td>
                                    <td class="px-3 py-3 text-center">{{ $fila['tasa_entrega'] }} %</td>
                                    <td class="px-3 py-3 text-right">
                                        <x-gestion.boton variante="fantasma" tamano="sm" icono="flecha-derecha" :href="route('cursos.libro-calificaciones', $fila['curso'])">Libro</x-gestion.boton>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-gestion.tarjeta>

            @php $bajo = $filas->flatMap(fn ($f) => $f['bajo_rendimiento']->map(fn ($b) => [...$b, 'curso' => $f['curso']])); @endphp
            <x-gestion.tarjeta :relleno="false" titulo="Estudiantes con bajo rendimiento" :descripcion="'Rendimiento a la fecha bajo '.$notaMinima.', dos o más tareas vencidas sin entregar o, en cursos cerrados, avance menor a 50 %.'">
                @if ($bajo->isEmpty())
                    <div class="p-4"><x-gestion.vacio icono="check-circulo" titulo="Ningún estudiante con bajo rendimiento" /></div>
                @else
                    <ul class="divide-y {{ $esAdminVista ? 'divide-arena-medio/60 dark:divide-stone-700' : 'divide-celeste/60 dark:divide-azul-borde-oscuro' }}">
                        @foreach ($bajo as $item)
                            <li class="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between" wire:key="bajo-{{ $item['curso']->id }}-{{ $item['estudiante']->id }}">
                                <span class="font-medium text-stone-800 dark:text-stone-100">{{ $item['estudiante']->name }} <span class="font-normal text-stone-500">- {{ $item['curso']->nombre }}</span></span>
                                <span class="text-xs text-stone-600 dark:text-stone-300">
                                    Rendimiento a la fecha: <strong class="{{ $item['nota_final'] !== null && $item['nota_final'] < $notaMinima ? 'text-red-600' : '' }}">{{ $item['nota_final'] ?? '—' }}</strong>
                                    - Avance: <strong>{{ $item['avance'] }} %</strong>
                                    @if (($item['vencidas'] ?? 0) > 0) - Vencidas sin entregar: <strong class="text-red-600">{{ $item['vencidas'] }}</strong> @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-gestion.tarjeta>
        @endif
    </div>
</div>
