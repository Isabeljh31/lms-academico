@props(['estado'])

@php
    // Paleta condicionada al rol: Estudiante ya usa la paleta fría nueva,
    // Profesor/Administrador conservan la paleta cálida anterior hasta que
    // se migren en un pase aparte — ver ARQUITECTURA.md § nueva paleta de
    // color. Un solo componente, un solo lugar para editar el mapeo.
    $paletaFria = auth()->check() && auth()->user()->hasRole('estudiante');

    $estilos = $paletaFria
        // Colores semánticos (ámbar/rojo/verde), igual al Figma — separados
        // del azul de marca para que el estado se lea de un vistazo.
        ? match (strtolower($estado)) {
            'pendiente' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            'atrasado' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
            'entregado' => 'bg-celeste text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-celeste',
            'calificado', 'aprobado' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
            'rechazado' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
            'en_progreso', 'en progreso' => 'bg-lavanda-fondo/30 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo',
            default => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        }
        : match (strtolower($estado)) {
            'pendiente' => 'bg-arena-medio text-stone-800 dark:bg-stone-700 dark:text-stone-200',
            'atrasado' => 'bg-terracota-fondo text-terracota-oscuro dark:bg-terracota-oscuro/20 dark:text-terracota-fondo',
            'entregado' => 'bg-menta-fondo text-menta-oscuro dark:bg-menta-oscuro/20 dark:text-menta-fondo',
            'calificado', 'aprobado' => 'bg-exito-base/20 text-exito-oscuro dark:bg-exito-oscuro/30 dark:text-exito-base',
            'rechazado' => 'bg-terracota-oscuro/10 text-terracota-oscuro dark:bg-terracota-oscuro/30 dark:text-terracota-fondo',
            'en_progreso', 'en progreso' => 'bg-cafe-arcilla/20 text-cafe-arcilla dark:bg-cafe-arcilla/30 dark:text-arena-medio',
            default => 'bg-stone-200 text-stone-700 dark:bg-stone-700 dark:text-stone-200',
        };

    $etiquetas = [
        'pendiente' => 'Pendiente',
        'atrasado' => 'Atrasado',
        'entregado' => 'Entregado',
        'calificado' => 'Calificado',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado',
        'en_progreso' => 'En progreso',
    ];

    $texto = $etiquetas[strtolower($estado)] ?? \Illuminate\Support\Str::headline($estado);
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold $estilos"]) }}>
    {{ $texto }}
</span>
