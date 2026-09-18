<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Encabezado --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-menta-oscuro/10 text-menta-oscuro">
                        <x-icono nombre="usuarios" class="h-5 w-5" />
                    </span>
                    Gestión de Profesores y Carga Académica
                </h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                    Supervisión institucional: asignación de cursos, carga horaria y monitoreo de calificaciones.
                </p>
            </div>

            <x-primary-button wire:click="$set('mostrarModalCrear', true)" class="gap-1.5">
                <span>+</span> Registrar profesor
            </x-primary-button>
        </div>

        {{-- Alertas de estado --}}
        @if (session('estado'))
            <div class="rounded-xl bg-exito-base/15 border border-exito-base/30 text-exito-oscuro p-4 text-sm flex items-center justify-between shadow-sm">
                <span>{{ session('estado') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-exito-oscuro/60 hover:text-exito-oscuro">&times;</button>
            </div>
        @endif

        {{-- Tarjetas KPI de supervisión --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase text-stone-500">Total Docentes</span>
                    <span class="h-8 w-8 rounded-lg bg-menta-fondo flex items-center justify-center text-menta-oscuro font-bold text-sm">
                        👨‍🏫
                    </span>
                </div>
                <div class="text-3xl font-black text-stone-900 dark:text-stone-100 mt-2">{{ $totalProfesores }}</div>
                <div class="text-xs text-stone-500 mt-1">Profesores activos en el claustro</div>
            </div>

            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase text-stone-500">Cursos Totales</span>
                    <span class="h-8 w-8 rounded-lg bg-arena-claro flex items-center justify-center text-stone-700 font-bold text-sm">
                        📚
                    </span>
                </div>
                <div class="text-3xl font-black text-stone-900 dark:text-stone-100 mt-2">{{ $totalCursos }}</div>
                <div class="text-xs text-stone-500 mt-1">Materias en el catálogo actual</div>
            </div>

            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase text-stone-500">Calificaciones Pendientes</span>
                    <span class="h-8 w-8 rounded-lg bg-terracota-fondo flex items-center justify-center text-terracota-oscuro font-bold text-sm">
                        ⏳
                    </span>
                </div>
                <div class="text-3xl font-black text-terracota-oscuro mt-2">{{ $entregasTotalesPendientes }}</div>
                <div class="text-xs text-stone-500 mt-1">Entregas por revisar en toda la institución</div>
            </div>

            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase text-stone-500">En Custodia Admin</span>
                    <span class="h-8 w-8 rounded-lg bg-stone-100 flex items-center justify-center text-stone-700 font-bold text-sm">
                        🛡️
                    </span>
                </div>
                <div class="text-3xl font-black text-stone-900 dark:text-stone-100 mt-2">{{ $cursosSinProfesor }}</div>
                <div class="text-xs text-stone-500 mt-1">Cursos administrados directamente</div>
            </div>
        </div>

        {{-- Barra de Búsqueda --}}
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 shadow-sm">
            <div class="relative">
                <input wire:model.live.debounce.300ms="busqueda" type="text"
                    placeholder="Buscar docente por nombre o correo institucional..."
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 focus:border-menta-base focus:ring-menta-base text-sm pl-10">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-stone-400">
                    <x-icono nombre="lupa" class="h-4 w-4" />
                </div>
            </div>
        </div>

        {{-- Directorio de Profesores con Carga Académica --}}
        <div class="space-y-4">
            @forelse ($profesoresConMetricas as $fila)
                @php $prof = $fila['usuario']; @endphp
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6 shadow-sm hover:shadow-md transition" wire:key="prof-{{ $prof->id }}">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                        {{-- Datos del Profesor --}}
                        <div class="lg:col-span-4 flex items-start gap-4">
                            <img src="{{ $prof->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($prof->name).'&background=3C7A75&color=fff' }}"
                                alt="{{ $prof->name }}" class="h-14 w-14 rounded-full object-cover border-2 border-arena-medio shrink-0">
                            <div>
                                <h2 class="text-lg font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                                    {{ $prof->name }}
                                </h2>
                                <p class="text-sm text-stone-500 dark:text-stone-400">{{ $prof->email }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-menta-fondo text-menta-oscuro">
                                        Docente Titular
                                    </span>
                                    <span class="text-xs text-stone-400">
                                        {{ $fila['estudiantes_count'] }} estudiantes asignados
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Asignaturas y Carga Horaria --}}
                        <div class="lg:col-span-4 space-y-2 border-t lg:border-t-0 lg:border-l border-arena-medio/60 dark:border-stone-700 lg:pl-6 pt-4 lg:pt-0">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold uppercase tracking-wider text-stone-500">Cursos a cargo ({{ $fila['cursos']->count() }})</span>
                                <button wire:click="abrirModalAsignar({{ $prof->id }})" class="text-xs font-semibold text-menta-oscuro dark:text-menta-base hover:underline">
                                    + Asignar curso
                                </button>
                            </div>

                            @if ($fila['cursos']->isEmpty())
                                <p class="text-xs text-stone-400 italic">Sin cursos asignados actualmente.</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($fila['cursos'] as $c)
                                        <div class="inline-flex items-center gap-1.5 rounded-lg border border-arena-medio dark:border-stone-600 bg-arena-claro/40 dark:bg-stone-700/50 px-2.5 py-1 text-xs" wire:key="curso-tag-{{ $c->id }}">
                                            <a href="{{ route('cursos.show', $c) }}" wire:navigate class="font-medium text-stone-800 dark:text-stone-200 hover:underline">
                                                {{ $c->nombre }}
                                            </a>
                                            <button wire:click="desasignarCurso({{ $c->id }})" wire:confirm="¿Desvincular al docente de '{{ $c->nombre }}'?" class="text-stone-400 hover:text-terracota-oscuro" title="Desvincular">&times;</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Monitoreo de Desempeño y Calificaciones --}}
                        <div class="lg:col-span-4 space-y-2 border-t lg:border-t-0 lg:border-l border-arena-medio/60 dark:border-stone-700 lg:pl-6 pt-4 lg:pt-0">
                            <span class="text-xs font-bold uppercase tracking-wider text-stone-500">Supervisión de Calificaciones</span>

                            <div class="space-y-1.5 mt-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-stone-600 dark:text-stone-400">Tasa de cumplimiento:</span>
                                    <span class="font-bold {{ $fila['tasa_calificacion'] >= 80 ? 'text-exito-oscuro' : ($fila['tasa_calificacion'] >= 50 ? 'text-amber-600' : 'text-terracota-oscuro') }}">
                                        {{ $fila['tasa_calificacion'] }}%
                                    </span>
                                </div>

                                <div class="w-full bg-arena-medio/50 dark:bg-stone-700 h-2 rounded-full overflow-hidden">
                                    <div class="h-2 rounded-full {{ $fila['tasa_calificacion'] >= 80 ? 'bg-exito-base' : ($fila['tasa_calificacion'] >= 50 ? 'bg-amber-500' : 'bg-terracota-base') }}"
                                        style="width: {{ $fila['tasa_calificacion'] }}%"></div>
                                </div>

                                <div class="flex items-center justify-between text-[11px] text-stone-500 pt-1">
                                    <span>Revisadas: <strong>{{ $fila['calificadas'] }}</strong></span>
                                    <span>Pendientes: <strong class="{{ $fila['pendientes'] > 0 ? 'text-terracota-oscuro' : '' }}">{{ $fila['pendientes'] }}</strong></span>
                                    <span>Total: <strong>{{ $fila['total_entregas'] }}</strong></span>
                                </div>

                                @if ($fila['atrasos_calificacion'] > 0)
                                    <div class="mt-2 rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-2 text-[11px] text-amber-800 dark:text-amber-300 flex items-center gap-1.5">
                                        <span>⚠️</span>
                                        <span>{{ $fila['atrasos_calificacion'] }} {{ $fila['atrasos_calificacion'] === 1 ? 'entrega con más de 3 días sin revisar' : 'entregas con más de 3 días sin revisar' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-8 text-center text-stone-500">
                    No se encontraron profesores que coincidan con la búsqueda.
                </div>
            @endforelse

            <div class="mt-4">
                {{ $paginacion->links() }}
            </div>
        </div>

    </div>

    {{-- Modal: Registrar Nuevo Profesor --}}
    @if ($mostrarModalCrear)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 p-4">
            <div class="bg-white dark:bg-stone-800 rounded-2xl border border-arena-medio dark:border-stone-700 max-w-md w-full p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b pb-3 border-arena-medio/50">
                    <h3 class="text-lg font-bold text-stone-900 dark:text-stone-100">Registrar Nuevo Docente</h3>
                    <button wire:click="$set('mostrarModalCrear', false)" class="text-stone-400 hover:text-stone-600 text-xl font-bold">&times;</button>
                </div>

                <form wire:submit="crearProfesor" class="space-y-4">
                    <div>
                        <x-input-label for="nuevoNombre" value="Nombre Completo" />
                        <x-text-input wire:model="nuevoNombre" id="nuevoNombre" class="mt-1 block w-full" placeholder="Ej: Prof. Carlos Mendoza" required autofocus />
                        <x-input-error :messages="$errors->get('nuevoNombre')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="nuevoEmail" value="Correo Institucional" />
                        <x-text-input wire:model="nuevoEmail" id="nuevoEmail" type="email" class="mt-1 block w-full" placeholder="docente@lms.edu.pe" required />
                        <x-input-error :messages="$errors->get('nuevoEmail')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="nuevoPassword" value="Contraseña Inicial" />
                        <x-text-input wire:model="nuevoPassword" id="nuevoPassword" type="password" class="mt-1 block w-full" placeholder="Mínimo 8 caracteres" required />
                        <x-input-error :messages="$errors->get('nuevoPassword')" class="mt-1" />
                        <p class="text-xs text-stone-400 mt-1">Se le asignará automáticamente el rol de Profesor.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button wire:click="$set('mostrarModalCrear', false)" type="button">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar y Activar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Asignar Curso a Docente --}}
    @if ($mostrarModalAsignar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 p-4">
            <div class="bg-white dark:bg-stone-800 rounded-2xl border border-arena-medio dark:border-stone-700 max-w-md w-full p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b pb-3 border-arena-medio/50">
                    <h3 class="text-lg font-bold text-stone-900 dark:text-stone-100">Asignar Asignatura a Docente</h3>
                    <button wire:click="$set('mostrarModalAsignar', false)" class="text-stone-400 hover:text-stone-600 text-xl font-bold">&times;</button>
                </div>

                <form wire:submit="asignarCurso" class="space-y-4">
                    <div>
                        <x-input-label for="cursoAAsignarId" value="Seleccionar Asignatura / Curso" />
                        <select wire:model="cursoAAsignarId" id="cursoAAsignarId"
                            class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                            <option value="">-- Seleccionar curso --</option>
                            @foreach ($cursosDisponibles as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }} (Actual: {{ $c->profesor?->name ?? 'Sin asignar' }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('cursoAAsignarId')" class="mt-1" />
                        <p class="text-xs text-stone-400 mt-1">Al asignar, este docente asumirá la titularidad del curso y la supervisión de sus tareas y quizzes.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button wire:click="$set('mostrarModalAsignar', false)" type="button">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Confirmar Asignación</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
