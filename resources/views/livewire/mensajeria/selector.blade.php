<x-gestion.pagina ancho="normal">

    <div>
        <h1 class="text-2xl font-bold text-texto dark:text-slate-100">Mensajería</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Selecciona un curso para ver su mensajería</p>
    </div>

    @if($cursos->isEmpty())
        <x-gestion.vacio icono="usuarios" titulo="No tienes cursos todavía"
            descripcion="La mensajería aparece aquí una vez que tengas un curso asignado." />
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($cursos as $curso)
            <a href="{{ route('cursos.mensajeria', $curso) }}" wire:navigate wire:key="curso-mensajeria-{{ $curso->id }}"
                class="block bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro overflow-hidden shadow-sm dark:shadow-none hover:shadow-lg hover:-translate-y-0.5 transition p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-texto dark:text-slate-100 truncate">{{ $curso->nombre }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Prof. {{ $curso->docentes()->pluck('name')->join(', ') }}</p>
                    </div>
                    @if($noLeidosPorCurso->get($curso->id))
                    <span class="inline-flex items-center justify-center rounded-full bg-azul-oscuro text-white text-xs font-bold w-5 h-5 shrink-0">
                        {{ $noLeidosPorCurso->get($curso->id) }}
                    </span>
                    @endif
                </div>
                <p class="mt-3 text-xs font-medium text-azul-oscuro dark:text-azul-fondo">Ver mensajería →</p>
            </a>
            @endforeach
        </div>
    @endif

</x-gestion.pagina>
