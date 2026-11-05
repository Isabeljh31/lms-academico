{{-- Datos de la tarjeta de un curso (pantalla Cursos). Recibe $curso, $datos y $cierreTexto. --}}
<dl class="grid grid-cols-2 gap-x-3 gap-y-2">
    <div>
        <dt class="text-xs text-slate-500">Matriculados</dt>
        <dd class="text-lg font-bold tabular-nums text-imperial">{{ $curso->inscripciones_activas_count }}{{ $curso->cupo_maximo ? '/'.$curso->cupo_maximo : '' }}</dd>
    </div>
    <div>
        <dt class="text-xs text-slate-500">Por calificar</dt>
        <dd class="text-lg font-bold tabular-nums {{ $datos['pendientes'] > 0 ? 'text-amber-800' : 'text-exito-oscuro' }}">{{ $datos['pendientes'] }}</dd>
    </div>
    <div class="col-span-2">
        <dt class="text-xs text-slate-500">Siguiente fecha límite</dt>
        <dd class="break-words text-[13px] font-bold leading-snug text-imperial">{{ $cierreTexto($curso, $datos) }}</dd>
    </div>
</dl>
