<div class="py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Métricas globales</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">Visión general de cuentas, cursos y uso del asistente.</p>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase text-stone-400 mb-3">Usuarios registrados</h2>
            <div class="grid grid-cols-3 gap-4">
                @foreach ($usuariosPorRol as $rol => $total)
                    <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                        <p class="text-2xl font-bold text-stone-800 dark:text-stone-100">{{ $total }}</p>
                        <p class="text-xs text-stone-500 dark:text-stone-400 capitalize">{{ $rol }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase text-stone-400 mb-3">Cursos e inscripciones</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-bold text-stone-800 dark:text-stone-100">{{ $totalCursos }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Cursos publicados</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-bold text-stone-800 dark:text-stone-100">{{ $totalInscripciones }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Inscripciones totales</p>
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase text-stone-400 mb-3">Cursos con más atraso</h2>
            @if ($cursosConMasAtraso->isEmpty())
                <p class="text-sm text-stone-500 dark:text-stone-400">Ningún curso tiene entregas atrasadas por ahora.</p>
            @else
                <div class="space-y-2">
                    @foreach ($cursosConMasAtraso as $fila)
                        <div class="flex items-center justify-between bg-white dark:bg-stone-800 rounded-lg border border-arena-medio dark:border-stone-700 px-4 py-3 text-sm">
                            <span class="text-stone-800 dark:text-stone-100">{{ $fila['curso']->nombre }}</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-terracota-fondo text-terracota-oscuro">
                                {{ $fila['atrasos'] }} entregas atrasadas
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase text-stone-400 mb-3">Uso del bot de sugerencias</h2>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-stone-800 dark:text-stone-100">{{ $usoBot['conversaciones'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Conversaciones</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-stone-800 dark:text-stone-100">{{ $usoBot['mensajes'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Mensajes</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-stone-800 dark:text-stone-100">{{ $usoBot['propuestas_pendientes'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Propuestas pendientes</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-exito-oscuro">{{ $usoBot['propuestas_aprobadas'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Aceptadas</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-terracota-oscuro">{{ $usoBot['propuestas_rechazadas'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Rechazadas</p>
                </div>
            </div>
        </div>
    </div>
</div>
