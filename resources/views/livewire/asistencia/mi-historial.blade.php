{{-- ERS RF-72: historial de asistencia del estudiante. Mismo estilo que su Boleta (cursos/boleta-estudiante). --}}
@php
    $estilos = [
        'presente' => ['label' => 'Presente', 'color' => 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'],
        'tardanza' => ['label' => 'Tardanza', 'color' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400'],
        'justificado' => ['label' => 'Justificado', 'color' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'],
        'ausente' => ['label' => 'Ausente', 'color' => 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'],
    ];
@endphp

<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto space-y-6">

    <div class="bg-white dark:bg-stone-800 rounded-2xl border border-stone-200 dark:border-stone-700 shadow-sm overflow-hidden">
        <div class="bg-imperial p-6 text-white">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-bold flex items-center gap-2"><x-icono nombre="calendario" class="h-5 w-5" /> Mi asistencia</h1>
                    <p class="text-celeste/80 text-sm mt-1">{{ $curso->nombre }}</p>
                </div>
                <div class="text-right">
                    @if ($porcentaje !== null)
                        <span class="text-4xl font-black">{{ number_format($porcentaje, 1) }}%</span>
                        <p class="text-xs text-celeste/80 mt-0.5">asistencia acumulada</p>
                    @else
                        <span class="text-lg font-semibold">Sin sesiones</span>
                    @endif
                </div>
            </div>
        </div>

        @if (! $curso->usa_asistencia)
            <div class="px-6 py-4 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-700">
                <p class="text-sm text-amber-700 dark:text-amber-400">
                    <x-icono nombre="info" class="inline h-4 w-4 -mt-0.5" /> Este curso no registra asistencia.
                </p>
            </div>
        @elseif ($historial->isEmpty())
            <div class="px-6 py-8 text-center">
                <p class="text-sm text-stone-500 dark:text-stone-400">Aún no hay sesiones de clase realizadas en este curso.</p>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 px-6 py-4 border-b border-stone-100 dark:border-stone-700">
                @foreach ($estilos as $estado => $estilo)
                    <div class="text-center">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $estilo['color'] }}">{{ $estilo['label'] }}</span>
                        <p class="text-lg font-bold text-stone-800 dark:text-stone-200 mt-1">{{ $conteo[$estado] ?? 0 }}</p>
                    </div>
                @endforeach
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-stone-100 dark:border-stone-700">
                        <tr class="text-xs font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wider">
                            <th class="px-6 py-3 text-left">Sesión</th>
                            <th class="px-4 py-3 text-center">Fecha</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                        @foreach ($historial as $fila)
                            <tr class="hover:bg-stone-50 dark:hover:bg-stone-700/20" wire:key="sesion-{{ $fila['sesion']->id }}">
                                <td class="px-6 py-3">
                                    <p class="font-medium text-stone-800 dark:text-stone-200">#{{ $fila['sesion']->numero_sesion }} - {{ $fila['sesion']->titulo }}</p>
                                    @if ($fila['observacion'])
                                        <p class="text-xs text-stone-400 mt-0.5">{{ $fila['observacion'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-stone-500 dark:text-stone-400">{{ $fila['sesion']->fecha->formatoFechaHora() }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $estilos[$fila['estado']]['color'] ?? '' }}">
                                        {{ $estilos[$fila['estado']]['label'] ?? ucfirst($fila['estado']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <p class="text-xs text-stone-400 dark:text-stone-500 text-center">
        Presente y Tardanza cuentan como asistencia; las inasistencias justificadas no se consideran en el porcentaje.
    </p>
</div>
