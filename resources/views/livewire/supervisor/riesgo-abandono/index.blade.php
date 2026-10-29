<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Riesgo de abandono y participación" icono="alerta"
            descripcion="Estudiantes de cursos en marcha con asistencia menor a 50 % o rendimiento a la fecha bajo la nota mínima aprobatoria, e indicadores de participación." />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-gestion.indicador etiqueta="Estudiantes activos" :valor="$totalEstudiantes" icono="usuarios" />
            <x-gestion.indicador etiqueta="Cursos por estudiante" :valor="$promedioInscripciones" icono="libro" tono="cafe" detalle="Promedio de matrículas activas" />
            <x-gestion.indicador etiqueta="Mensajes" :valor="$totalMensajes" icono="sobre" tono="menta" detalle="Enviados en la plataforma" />
            <x-gestion.indicador etiqueta="Asistente de IA" :valor="$totalInteraccionesIA" icono="chat" tono="menta" detalle="Conversaciones iniciadas" />
        </div>

        <x-gestion.tarjeta :relleno="false" :titulo="'Estudiantes en riesgo ('.$estudiantesEnRiesgo->count().')'"
            :descripcion="'Riesgo alto: en más de un curso. Nota mínima aprobatoria: '.$notaMinima.'.'">
            @if ($estudiantesEnRiesgo->isEmpty())
                <div class="p-4">
                    <x-gestion.vacio icono="check-circulo" titulo="No se detectan estudiantes en riesgo" descripcion="Ningún estudiante activo cumple las condiciones de riesgo en este momento." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="bg-arena-claro/50 text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                            <tr>
                                <th class="px-4 py-3">Estudiante</th>
                                <th class="px-4 py-3">Nivel</th>
                                <th class="px-4 py-3">Motivos</th>
                                <th class="px-4 py-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($estudiantesEnRiesgo as $riesgo)
                                <tr wire:key="riesgo-{{ $riesgo['estudiante']->id }}">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-stone-800 dark:text-stone-100">{{ $riesgo['estudiante']->name }}</p>
                                        <p class="text-xs text-stone-500">{{ $riesgo['estudiante']->email }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $riesgo['nivel'] === 'Alto' ? 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-arena-claro text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo' }}">
                                            <x-icono nombre="alerta" class="h-3 w-3" /> {{ $riesgo['nivel'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <ul class="list-disc space-y-0.5 pl-4 text-xs text-stone-600 dark:text-stone-300">
                                            @foreach ($riesgo['motivos'] as $motivo)
                                                <li>{{ $motivo }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="mailto:{{ $riesgo['estudiante']->email }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-stone-600 hover:bg-arena-claro dark:text-stone-300 dark:hover:bg-stone-700">
                                            <x-icono nombre="sobre" class="h-3.5 w-3.5" /> Contactar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-gestion.tarjeta>
    </div>
</div>
