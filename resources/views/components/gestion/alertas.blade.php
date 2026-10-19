{{-- Mensajes flash de las pantallas de Profesor y Supervisor (ERS RNF-07). --}}
@php
    $tipos = [
        'estado' => ['icono' => 'check-circulo', 'clases' => 'border-exito-base/40 bg-exito-base/10 text-exito-oscuro dark:text-green-300'],
        'advertencia' => ['icono' => 'alerta', 'clases' => 'border-amber-300/60 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200'],
        'error' => ['icono' => 'x-circulo', 'clases' => 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200'],
    ];
@endphp

@foreach ($tipos as $clave => $tipo)
    @if (session()->has($clave))
        <div role="alert" class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $tipo['clases'] }}">
            <x-icono :nombre="$tipo['icono']" class="h-5 w-5 shrink-0" />
            <p class="flex-1">{{ session($clave) }}</p>
        </div>
    @endif
@endforeach
