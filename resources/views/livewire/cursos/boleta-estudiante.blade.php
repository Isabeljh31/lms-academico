<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto space-y-6 print:py-0 print:max-w-none">

    {{-- Encabezado --}}
    <div class="flex items-center justify-end print:hidden">
        <button onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-lg border border-stone-300 dark:border-stone-600 px-3 py-1.5 text-xs text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
            <x-icono nombre="imprimir" class="h-4 w-4" /> Imprimir / PDF
        </button>
    </div>

    {{-- Tarjeta de boleta --}}
    <div class="bg-white dark:bg-stone-800 rounded-2xl border border-stone-200 dark:border-stone-700 shadow-sm overflow-hidden print:shadow-none print:border-0">

        {{-- Header de la boleta --}}
        <div class="bg-gradient-to-r from-azul-oscuro to-azul-fondo p-6 text-white print:bg-azul-oscuro">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-bold flex items-center gap-2"><x-icono nombre="lista" class="h-5 w-5" /> Boleta de Calificaciones</h1>
                    <p class="text-celeste/80 text-sm mt-1">{{ $curso->nombre }}</p>
                    @if($curso->fecha_inicio)
                    <p class="text-celeste/60 text-xs mt-1">
                        <x-icono nombre="calendario" class="inline h-3 w-3 -mt-0.5" /> {{ $curso->fecha_inicio->formatoFecha() }}
                        @if($curso->fecha_fin) – {{ $curso->fecha_fin->formatoFecha() }} @endif
                    </p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-celeste/60 mt-0.5">{{ auth()->user()->email }}</p>
                    <p class="text-xs text-celeste/40 mt-0.5">Generado: {{ now()->formatoFechaHora() }}</p>
                </div>
            </div>
        </div>

        {{-- Alerta si esquema incompleto --}}
        @if(!$esquema || $rubros->isEmpty())
        <div class="px-6 py-4 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-700">
            <p class="text-sm text-amber-700 dark:text-amber-400">
                <x-icono nombre="alerta" class="inline h-4 w-4 -mt-0.5" /> El Esquema de Evaluación aún no está configurado por el Supervisor.
            </p>
        </div>
        @elseif(!$pesosOk)
        <div class="px-6 py-4 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-700">
            <p class="text-sm text-amber-700 dark:text-amber-400">
                <x-icono nombre="alerta" class="inline h-4 w-4 -mt-0.5" /> El Esquema de Evaluación tiene una suma de pesos de {{ number_format($rubros->sum('peso'), 1) }}% (debe ser 100%). Las notas pueden no ser definitivas.
            </p>
        </div>
        @endif

        {{-- Tabla de rubros --}}
        @if($rubros->isNotEmpty())
        <table class="w-full text-sm">
            <thead class="border-b border-stone-100 dark:border-stone-700">
                <tr class="text-xs font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wider">
                    <th class="px-6 py-3 text-left">Rubro</th>
                    <th class="px-4 py-3 text-center">Semana</th>
                    <th class="px-4 py-3 text-center">Peso</th>
                    <th class="px-4 py-3 text-center">Nota</th>
                    <th class="px-4 py-3 text-center">Aporte</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                @foreach($filas as $f)
                <tr class="hover:bg-stone-50 dark:hover:bg-stone-700/20">
                    <td class="px-6 py-4 font-medium text-stone-800 dark:text-stone-200">{{ $f['rubro']->nombre }}
                        @if ($f['rubro']->esAutomatico())
                            <p class="mt-1 text-xs font-normal text-stone-500 dark:text-stone-400">{{ $f['rubro']->modo_calculo->etiqueta() }} @if ($f['nota'] === null) · Actividades pendientes @endif</p>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-center text-stone-500 dark:text-stone-400">
                        {{ $f['rubro']->semana ? "Sem. {$f['rubro']->semana}" : '—' }}
                    </td>
                    <td class="px-4 py-4 text-center">
                        <span class="inline-flex items-center rounded-full bg-stone-100 dark:bg-stone-700 px-2.5 py-0.5 text-xs font-medium text-stone-600 dark:text-stone-300">
                            {{ (float) $f['rubro']->peso }}%
                        </span>
                    </td>
                    <td class="px-4 py-4 text-center">
                        @if($f['nota'] !== null)
                        <span class="inline-flex items-center justify-center w-14 rounded-full px-2 py-1 text-sm font-bold
                            {{ $f['aprobado']
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400' }}">
                            {{ number_format($f['nota'], 1) }}
                        </span>
                        @else
                        <span class="text-stone-300 dark:text-stone-600">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-center text-stone-500 dark:text-stone-400">
                        {{ $f['aporte'] !== null ? number_format($f['aporte'], 2) : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Nota final --}}
        <div class="px-6 py-5 border-t border-stone-200 dark:border-stone-700 bg-stone-50 dark:bg-stone-700/20">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-stone-700 dark:text-stone-300">Nota Final Ponderada</p>
                    <p class="text-xs text-stone-400 dark:text-stone-500 mt-0.5">
                        Nota mínima aprobatoria: {{ $notaMinima }} / 20
                    </p>
                </div>
                <div class="text-right">
                    @if($notaFinal !== null && $condicion === 'en_curso')
                    <span class="text-4xl font-black text-stone-700 dark:text-stone-200">
                        {{ number_format($notaFinal, 1) }}
                    </span>
                    <span class="text-lg text-stone-400 dark:text-stone-500"> / 20</span>
                    <p class="text-sm font-semibold mt-1 text-stone-500 dark:text-stone-400">
                        <x-icono nombre="reloj" class="inline h-4 w-4 -mt-0.5" /> En curso: {{ $rubrosConNota }} de {{ $rubros->count() }} rubros calificados
                    </p>
                    @elseif($notaFinal !== null)
                    <span class="text-4xl font-black {{ $aprobado ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ number_format($notaFinal, 1) }}
                    </span>
                    <span class="text-lg text-stone-400 dark:text-stone-500"> / 20</span>
                    <p class="text-sm font-semibold mt-1 {{ $aprobado ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        <x-icono :nombre="$aprobado ? 'check' : 'cerrar'" class="inline h-4 w-4 -mt-0.5" /> {{ $aprobado ? 'Aprobado' : 'Desaprobado' }}
                    </p>
                    @else
                    <span class="text-2xl font-black text-stone-300 dark:text-stone-600">Sin notas</span>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Nota legal --}}
    <p class="text-xs text-stone-400 dark:text-stone-500 text-center print:block">
        Este documento es informativo. Las calificaciones están sujetas a revisión por el Supervisor.
    </p>
</div>
