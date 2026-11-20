{{--
    Bloque «¿A qué evaluación aporta?» de los formularios de tarea y quiz
    abiertos desde el asistente de Actividades (Cursos\Concerns\EligeTipoYEvaluacion).
    Recibe $evaluacion = datosDeEvaluacion().
--}}
<x-gestion.tarjeta titulo="¿A qué evaluación aporta?" descripcion="Su nota contará para la evaluación que elijas. Puedes cambiarla después en Actividades.">
    @if ($evaluacion['opciones'] === [])
        <p class="rounded-lg border border-dashed border-celeste px-3 py-2.5 text-sm text-slate-600 dark:border-azul-borde-oscuro dark:text-slate-300">
            {{ $evaluacion['excluidas'] === []
                ? 'El Supervisor todavía no definió las evaluaciones del curso. Puedes crearla ahora y asignarla después desde Actividades.'
                : 'Todas las evaluaciones del curso se califican a mano. Puedes crearla ahora y asignarla después desde Actividades.' }}
        </p>
    @else
        <div class="space-y-3">
            <x-gestion.campo etiqueta="Evaluación" nombre="evaluacionId" requerido>
                <x-gestion.desplegable id="evaluacionId" etiqueta="Evaluación" wire:model.live="evaluacionId" :seleccionado="$evaluacionId"
                    placeholder="Elige la evaluación" :opciones="$evaluacion['opciones']" />
            </x-gestion.campo>

            @if ($evaluacion['sugerencia'])
                <p class="flex items-start gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                    <x-icono nombre="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" /> {{ $evaluacion['sugerencia'] }}
                </p>
            @endif

            @if ($evaluacion['aviso'])
                <p role="status" class="flex items-start gap-2 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    <x-icono nombre="alerta" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $evaluacion['aviso'] }}
                </p>
            @endif

            @if ($evaluacion['reparto'])
                <p class="rounded-lg bg-fondo px-3 py-2 text-xs text-slate-600 dark:bg-azul-pagina-oscura dark:text-slate-300">{{ $evaluacion['reparto'] }}</p>
            @endif

            @if ($evaluacion['excluidas'] !== [])
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    No aparecen {{ implode(', ', $evaluacion['excluidas']) }}: su nota la escribes tú en el libro de calificaciones.
                </p>
            @endif
        </div>
    @endif
</x-gestion.tarjeta>
