@php
    $etiquetasEstado = ['borrador' => 'Borrador', 'publicado' => 'Publicados', 'despublicado' => 'Ocultos', 'archivado' => 'Completados (archivados)'];
    $iconosAuditoria = [
        'cambio_nota' => 'lapiz', 'creacion_cuenta' => 'usuario-mas', 'baja_estudiante' => 'prohibido',
        'matricula_api' => 'enlace', 'matricula_manual' => 'usuario-mas', 'modificacion_esquema' => 'grafico',
    ];
@endphp

<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Dashboard institucional" icono="grafico"
            descripcion="Indicadores académicos y reportes administrativos de toda la plataforma.">
            <label for="periodo" class="sr-only">Período</label>
            <x-gestion.control tipo="select" id="periodo" class="!w-auto" wire:model.live="periodo">
                <option value="7">Últimos 7 días</option>
                <option value="30">Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
                <option value="365">Último año</option>
            </x-gestion.control>
        </x-gestion.encabezado>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-gestion.indicador etiqueta="Nuevos estudiantes" :valor="$nuevosEstudiantes" icono="usuario-mas" :detalle="'En los últimos '.$periodo.' días'" />
            <x-gestion.indicador etiqueta="Estudiantes activos / inactivos" :valor="$estudiantesActivos.' / '.$estudiantesInactivos" icono="usuarios" tono="cafe" />
            <x-gestion.indicador etiqueta="Cursos completados" :valor="$cursosPorEstado['archivado'] ?? 0" icono="archivo" tono="exito" detalle="Cursos archivados" />
            <x-gestion.indicador etiqueta="Tasa de abandono" :valor="$tasaAbandono.' %'" icono="prohibido" :tono="$tasaAbandono > 20 ? 'alerta' : 'terracota'"
                :detalle="'Bajas sobre '.$totalInscripciones.' matrículas'" />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-gestion.tarjeta titulo="Usuarios por rol">
                <ul class="space-y-2 text-sm">
                    @foreach ($usuariosPorRol as $rol => $total)
                        <li class="flex justify-between"><span class="text-stone-600 dark:text-stone-300">{{ $rol }}</span><strong>{{ $total }}</strong></li>
                    @endforeach
                    <li class="flex justify-between border-t border-arena-medio pt-2 dark:border-stone-700">
                        <span class="text-stone-600 dark:text-stone-300">Primer ingreso pendiente</span><strong>{{ $usuariosPrimerLogin }}</strong>
                    </li>
                </ul>
            </x-gestion.tarjeta>
            <x-gestion.tarjeta titulo="Cursos por estado">
                <ul class="space-y-2 text-sm">
                    @foreach ($etiquetasEstado as $estado => $etiqueta)
                        <li class="flex justify-between"><span class="text-stone-600 dark:text-stone-300">{{ $etiqueta }}</span><strong>{{ $cursosPorEstado[$estado] ?? 0 }}</strong></li>
                    @endforeach
                    <li class="flex justify-between border-t border-arena-medio pt-2 dark:border-stone-700">
                        <span class="text-stone-600 dark:text-stone-300">Sin esquema de evaluación</span><strong class="{{ $cursosSinEsquema > 0 ? 'text-terracota-oscuro' : '' }}">{{ $cursosSinEsquema }}</strong>
                    </li>
                </ul>
            </x-gestion.tarjeta>
            <x-gestion.tarjeta titulo="Distribución de notas por rubro" :descripcion="$totalNotas.' notas registradas · mínima aprobatoria '.$notaMinima">
                @if ($totalNotas === 0)
                    <p class="text-sm text-stone-500">Aún no hay notas registradas.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($distribucion as $rango => $cantidad)
                            @php $pct = (int) round($cantidad / $totalNotas * 100); @endphp
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-24 shrink-0 text-stone-600 dark:text-stone-300">{{ $rango }}</span>
                                <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-arena-claro dark:bg-stone-700">
                                    <div class="h-full rounded-full {{ $loop->last ? 'bg-terracota-oscuro' : 'bg-menta-oscuro' }}" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="w-16 shrink-0 text-right text-stone-500">{{ $cantidad }} ({{ $pct }} %)</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-gestion.tarjeta>
        </div>

        <x-gestion.tarjeta :relleno="false" titulo="Promedio y aprobación por curso" descripcion="Promedio a la fecha (rubros ya calificados) de los estudiantes activos. La aprobación se calcula cuando todos los rubros tienen nota.">
            @if ($rendimiento->isEmpty())
                <div class="p-4"><x-gestion.vacio icono="libro" titulo="No hay cursos registrados" /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="bg-arena-claro/50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                            <tr>
                                <th class="px-4 py-3">Curso</th>
                                <th class="px-3 py-3">Profesor</th>
                                <th class="px-3 py-3 text-center">Activos</th>
                                <th class="px-3 py-3 text-center">Promedio</th>
                                <th class="px-3 py-3 text-center">Aprobación</th>
                                <th class="px-3 py-3 text-center">Abandono</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($rendimiento as $fila)
                                <tr wire:key="rend-{{ $fila['curso']->id }}">
                                    <td class="px-4 py-2"><a href="{{ route('cursos.show', $fila['curso']) }}" wire:navigate class="font-medium text-menta-oscuro hover:underline dark:text-menta-base">{{ $fila['curso']->nombre }}</a></td>
                                    <td class="px-3 py-2 text-stone-600 dark:text-stone-300">{{ $fila['curso']->profesor?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $fila['activos'] }}</td>
                                    <td class="px-3 py-2 text-center font-semibold {{ $fila['promedio'] !== null && $fila['promedio'] < $notaMinima ? 'text-red-600' : '' }}">{{ $fila['promedio'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $fila['aprobacion'] !== null ? $fila['aprobacion'].' %' : '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $fila['abandono'] !== null ? $fila['abandono'].' %' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-gestion.tarjeta>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-gestion.tarjeta titulo="Cursos con más atraso" descripcion="Tareas vencidas sin entrega de estudiantes activos.">
                @if ($cursosConMasAtraso->isEmpty())
                    <p class="text-sm text-stone-500">Ningún curso tiene entregas atrasadas por ahora.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($cursosConMasAtraso as $fila)
                            <li class="flex items-center justify-between gap-2 text-sm" wire:key="atraso-{{ $fila['curso']->id }}">
                                <span class="text-stone-800 dark:text-stone-100">{{ $fila['curso']->nombre }}</span>
                                <span class="rounded-full bg-arena-claro px-2.5 py-0.5 text-xs font-semibold text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo">{{ $fila['atrasos'] }} entregas atrasadas</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Uso del asistente de IA">
                <ul class="grid grid-cols-2 gap-3 text-sm">
                    @foreach ($usoBot as $etiqueta => $total)
                        <li class="rounded-lg bg-arena-claro/50 p-3 dark:bg-stone-900"><p class="text-xs text-stone-500">{{ $etiqueta }}</p><p class="text-lg font-bold text-stone-800 dark:text-stone-100">{{ $total }}</p></li>
                    @endforeach
                </ul>
            </x-gestion.tarjeta>
        </div>

        <x-gestion.tarjeta :relleno="false" titulo="Actividad reciente de auditoría">
            <x-slot name="acciones">
                <x-gestion.boton variante="fantasma" tamano="sm" icono="flecha-derecha" :href="route('admin.auditoria')">Ver bitácora</x-gestion.boton>
            </x-slot>
            @if ($auditoriasRecientes->isEmpty())
                <div class="p-4"><x-gestion.vacio icono="escudo" titulo="Sin registros de auditoría" /></div>
            @else
                <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                    @foreach ($auditoriasRecientes as $log)
                        <li class="flex items-start gap-3 px-4 py-3 text-sm" wire:key="audit-{{ $log->id }}">
                            <x-icono :nombre="$iconosAuditoria[$log->tipo_evento] ?? 'engranaje'" class="mt-0.5 h-4 w-4 shrink-0 text-stone-400" />
                            <div class="min-w-0">
                                <p class="truncate text-stone-700 dark:text-stone-200"><strong>{{ $log->ejecutadoPor?->name ?? 'Sistema externo' }}</strong> · {{ $log->descripcion ?? $log->etiquetaTipo() }}</p>
                                <p class="text-xs text-stone-500">{{ $log->created_at?->diffForHumans() }}@if ($log->curso) · {{ $log->curso->nombre }}@endif</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-gestion.tarjeta>
    </div>
</div>
