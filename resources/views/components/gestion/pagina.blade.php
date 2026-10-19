@props([
    'ancho' => 'normal',
])

@php
    // angosto: formularios y detalle de un solo registro. normal: listados y vistas de curso. amplio: tablas/dashboards densos.
    $anchos = [
        'angosto' => 'max-w-3xl',
        'normal' => 'max-w-6xl',
        'amplio' => 'max-w-7xl',
    ];
@endphp

<div class="py-8">
    <div {{ $attributes->merge(['class' => 'mx-auto '.($anchos[$ancho] ?? $anchos['normal']).' space-y-6 px-4 sm:px-6 lg:px-8']) }}>
        {{ $slot }}
    </div>
</div>
