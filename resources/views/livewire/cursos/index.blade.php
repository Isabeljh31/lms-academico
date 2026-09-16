<div class="py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Cursos</h1>
                <p class="text-sm text-stone-500 dark:text-stone-400">
                    @if (auth()->user()->hasRole('profesor'))
                        Tus cursos como profesor.
                    @else
                        Catálogo de cursos disponibles.
                    @endif
                </p>
            </div>

            @if ($puedeCrear)
                <a href="{{ route('cursos.crear') }}" wire:navigate
                    class="inline-flex items-center px-4 py-2 bg-menta-oscuro text-white text-sm font-semibold rounded-md hover:bg-menta-oscuro/90">
                    + Nuevo curso
                </a>
            @endif
        </div>

        <input wire:model.live.debounce.300ms="busqueda" type="search" placeholder="Buscar curso…"
            class="w-full sm:w-80 rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">

        @if ($cursos->isEmpty())
            <p class="text-stone-500 dark:text-stone-400">No hay cursos todavía.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($cursos as $curso)
                    <a href="{{ route('cursos.show', $curso) }}" wire:navigate wire:key="curso-{{ $curso->id }}"
                        class="block bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 overflow-hidden shadow-sm hover:shadow-md transition">
                        <div class="h-32 bg-gradient-to-br from-menta-fondo to-arena-medio dark:from-stone-700 dark:to-stone-800"
                            @if ($curso->imagen_portada_url) style="background-image:url('{{ $curso->imagen_portada_url }}');background-size:cover;background-position:center;" @endif>
                        </div>
                        <div class="p-4">
                            <h2 class="font-semibold text-stone-800 dark:text-stone-100">{{ $curso->nombre }}</h2>
                            <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Prof. {{ $curso->profesor?->name }}</p>
                            <div class="flex items-center justify-between mt-3 text-xs text-stone-500 dark:text-stone-400">
                                <span>{{ $curso->modulos->count() }} unidades</span>
                                <span>{{ $curso->inscripciones_count }} inscritos</span>
                            </div>
                            @if ($inscritos->has($curso->id))
                                <span class="mt-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-menta-fondo text-menta-oscuro">
                                    Inscrito
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div>{{ $cursos->links() }}</div>
        @endif
    </div>
</div>
