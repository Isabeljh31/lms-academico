@php
    // Acento de portada (código + fondo tenue, sin degradado) para el rol
    // estudiante, elegido de forma estable a partir del nombre del curso —
    // mismo criterio que el widget "Mis cursos" del panel (ver /DISEñO
    // FIGMA). El profesor conserva su fondo original sin cambios.
    $acentosCurso = [
        ['texto' => 'text-azul-oscuro dark:text-azul-fondo', 'fondo' => 'bg-azul-oscuro/10 dark:bg-azul-oscuro/20'],
        ['texto' => 'text-azul-fondo dark:text-cielo', 'fondo' => 'bg-azul-fondo/10 dark:bg-azul-fondo/20'],
        ['texto' => 'text-cielo dark:text-cielo', 'fondo' => 'bg-cielo/10 dark:bg-cielo/20'],
    ];
    $acentoCurso = $acentosCurso[crc32($curso->nombre) % count($acentosCurso)];
    // Str::ascii() quita tildes antes de recortar, para que el código quede
    // consistente (p. ej. "Física" -> "FIS", no "Fís" con strtoupper solo).
    $codigoCurso = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', \Illuminate\Support\Str::ascii($curso->nombre)), 0, 3));
@endphp

<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if (session('estado'))
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm">{{ session('estado') }}</div>
        @endif

        <div class="bg-white rounded-xl border overflow-hidden {{ auth()->user()->hasRole('estudiante') ? 'dark:bg-azul-superficie-oscura border-celeste dark:border-azul-borde-oscuro' : 'dark:bg-stone-800 border-arena-medio dark:border-stone-700' }}">
            <div class="relative h-40 overflow-hidden flex items-center justify-center {{ auth()->user()->hasRole('estudiante') ? $acentoCurso['fondo'] : 'bg-gradient-to-br from-menta-fondo to-arena-medio dark:from-stone-700 dark:to-stone-800' }}"
                @if ($curso->imagen_portada_url) style="background-image:url('{{ $curso->imagen_portada_url }}');background-size:cover;background-position:center;" @endif>
                @if (! $curso->imagen_portada_url && auth()->user()->hasRole('estudiante'))
                    {{-- Rediseño visual del rol estudiante: código del curso sobre fondo tenue del acento. --}}
                    <span class="text-5xl font-black tracking-tight {{ $acentoCurso['texto'] }} opacity-70 select-none">{{ $codigoCurso }}</span>
                @endif
            </div>
            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold {{ auth()->user()->hasRole('estudiante') ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100' }}">{{ $curso->nombre }}</h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Prof. {{ $curso->profesor?->name }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('cursos.tareas.index', $curso) }}" wire:navigate>
                            <x-secondary-button>Tareas</x-secondary-button>
                        </a>

                        <a href="{{ route('cursos.quizzes.index', $curso) }}" wire:navigate>
                            <x-secondary-button>Quizzes</x-secondary-button>
                        </a>

                        @if ($this->puedeGestionar)
                            <a href="{{ route('cursos.grupos.index', $curso) }}" wire:navigate>
                                <x-secondary-button>Grupos</x-secondary-button>
                            </a>

                            <a href="{{ route('cursos.editar', $curso) }}" wire:navigate>
                                <x-secondary-button>Editar</x-secondary-button>
                            </a>
                        @endif

                        @if (auth()->user()->hasRole('estudiante'))
                            @if ($this->estaInscrito)
                                <span class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-semibold bg-celeste/50 text-azul-oscuro">
                                    Ya estás inscrito
                                </span>
                            @else
                                <x-primary-button wire:click="inscribirme">Inscribirme</x-primary-button>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($curso->descripcion)
                    <p class="mt-4 {{ auth()->user()->hasRole('estudiante') ? 'text-slate-600 dark:text-slate-300' : 'text-stone-600 dark:text-stone-300' }}">{{ $curso->descripcion }}</p>
                @endif
            </div>
        </div>

        @if ($this->puedeGestionar || ! auth()->user()->hasRole('estudiante'))
            {{-- Vista de profesor/administrador: sin cambios respecto a la versión anterior. --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-stone-800 dark:text-stone-100">Unidades / Módulos</h2>

                @forelse ($modulos as $modulo)
                    <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4" wire:key="modulo-{{ $modulo->id }}">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-stone-800 dark:text-stone-100">{{ $modulo->titulo }}</h3>
                            @if ($this->puedeGestionar)
                                <button wire:click="eliminarModulo({{ $modulo->id }})" wire:confirm="¿Eliminar esta unidad y sus materiales?"
                                    class="text-xs text-terracota-oscuro hover:underline">Eliminar</button>
                            @endif
                        </div>

                        @if (! empty($modulo->materiales))
                            <ul class="mt-3 space-y-1">
                                @foreach ($modulo->materiales as $material)
                                    <li>
                                        <a href="{{ $material['url'] }}" target="_blank" class="text-sm text-menta-oscuro dark:text-menta-base hover:underline">
                                            📎 {{ $material['nombre'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($this->puedeGestionar)
                            <form wire:submit="subirMaterial({{ $modulo->id }})" class="mt-3 flex items-center gap-2">
                                <input type="file" wire:model="material"
                                    class="text-xs text-stone-500 dark:text-stone-400 file:me-2 file:rounded-md file:border-0 file:bg-arena-medio file:px-2 file:py-1 file:text-xs">
                                <button type="submit" class="text-xs font-semibold text-menta-oscuro dark:text-menta-base hover:underline">Subir</button>
                                <span wire:loading wire:target="material,subirMaterial" class="text-xs text-stone-400">Subiendo…</span>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-stone-500 dark:text-stone-400">Este curso todavía no tiene unidades.</p>
                @endforelse

                @if ($this->puedeGestionar)
                    <form wire:submit="agregarModulo" class="flex items-center gap-2">
                        <input wire:model="nuevoModuloTitulo" type="text" placeholder="Título de la nueva unidad…"
                            class="flex-1 rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                        <x-secondary-button type="submit">+ Unidad</x-secondary-button>
                    </form>
                    <x-input-error :messages="$errors->get('nuevoModuloTitulo')" />
                @endif
            </div>
        @else
            {{-- Rediseño visual del rol estudiante: pestañas (ver ARQUITECTURA.md). --}}
            @php
                $materialesDelCurso = $modulos->flatMap(fn ($m) => collect($m->materiales ?? [])
                    ->map(fn ($mat) => [...$mat, 'modulo' => $m->titulo]));
            @endphp

            <div x-data="{ pestaña: 'unidades' }">
                <div class="flex flex-wrap gap-1 border-b border-celeste dark:border-azul-borde-oscuro mb-4">
                    @foreach (['unidades' => 'Contenido', 'tareas' => 'Tareas', 'quizzes' => 'Quizzes', 'materiales' => 'Materiales', 'companeros' => 'Compañeros', 'migrupo' => 'Mi grupo'] as $clave => $etiqueta)
                        <button @click="pestaña = '{{ $clave }}'"
                            :class="pestaña === '{{ $clave }}' ? 'border-azul-oscuro text-azul-oscuro dark:text-azul-fondo' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700'"
                            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition">
                            {{ $etiqueta }}
                        </button>
                    @endforeach
                </div>

                {{-- Contenido / Unidades --}}
                <div x-show="pestaña === 'unidades'" class="space-y-3">
                    @forelse ($modulos as $modulo)
                        @php
                            $prog = $this->progresoModulos[$modulo->id] ?? null;
                            $pct = $prog && $prog['total'] > 0 ? (int) round(($prog['completadas'] / $prog['total']) * 100) : null;
                        @endphp
                        <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-4" wire:key="modulo-est-{{ $modulo->id }}">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-celeste/50 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste">
                                    <x-icono nombre="libro" class="h-5 w-5" />
                                </span>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-medium text-texto dark:text-slate-100">{{ $modulo->titulo }}</h3>
                                    @if ($pct !== null)
                                        <div class="flex items-center gap-2 mt-1">
                                            <div class="h-1.5 flex-1 max-w-[140px] rounded-full bg-celeste/50 dark:bg-azul-borde-oscuro overflow-hidden">
                                                <div class="h-full rounded-full bg-azul-oscuro dark:bg-azul-fondo" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <span class="text-xs text-slate-400">{{ $prog['completadas'] }}/{{ $prog['total'] }} tareas</span>
                                        </div>
                                    @endif
                                </div>
                                @if (! empty($modulo->materiales))
                                    <span class="text-xs text-slate-400 flex items-center gap-1">
                                        <x-icono nombre="material" class="h-3.5 w-3.5" /> {{ count($modulo->materiales) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">Este curso todavía no tiene unidades.</p>
                    @endforelse
                </div>

                {{-- Tareas --}}
                <div x-show="pestaña === 'tareas'" class="space-y-3">
                    @forelse ($this->resumenTareasEstudiante as $fila)
                        <a href="{{ route('cursos.tareas.show', [$curso, $fila['tarea']]) }}" wire:navigate wire:key="tarea-est-{{ $fila['tarea']->id }}"
                            class="flex items-center gap-3 rounded-xl border p-3 shadow-sm dark:shadow-none transition hover:shadow-md
                                {{ $fila['estado'] === 'atrasado' ? 'border-coral-oscuro/40 bg-coral-fondo/40 dark:bg-coral-oscuro/15' : 'border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura' }}">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-celeste/30 dark:bg-azul-borde-oscuro/60 text-slate-600 dark:text-slate-300">
                                <x-icono nombre="tarea" class="h-5 w-5" />
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-texto dark:text-slate-100 truncate">{{ $fila['tarea']->titulo }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">Vence {{ $fila['tarea']->fecha_entrega->format('d/m/Y') }}</span>
                            </span>
                            <x-badge-estado :estado="$fila['estado']" />
                        </a>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">Este curso todavía no tiene tareas.</p>
                    @endforelse
                </div>

                {{-- Quizzes --}}
                <div x-show="pestaña === 'quizzes'" class="space-y-3">
                    @forelse ($this->resumenQuizzesEstudiante as $fila)
                        <a href="{{ route('cursos.quizzes.show', [$curso, $fila['quiz']]) }}" wire:navigate wire:key="quiz-est-{{ $fila['quiz']->id }}"
                            class="flex items-center gap-3 rounded-xl border border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura p-3 shadow-sm dark:shadow-none transition hover:shadow-md">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo">
                                <x-icono nombre="quiz" class="h-5 w-5" />
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-texto dark:text-slate-100 truncate">{{ $fila['quiz']->tema }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ count($fila['quiz']->preguntas) }} preguntas</span>
                            </span>
                            @if ($fila['resultado'])
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-verde-fondo/50 text-verde-oscuro">
                                    {{ $fila['resultado']->puntaje }} / 20
                                </span>
                            @else
                                <x-badge-estado estado="pendiente" />
                            @endif
                        </a>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">Este curso todavía no tiene quizzes.</p>
                    @endforelse
                </div>

                {{-- Materiales --}}
                <div x-show="pestaña === 'materiales'" class="space-y-2">
                    @forelse ($materialesDelCurso as $material)
                        <a href="{{ $material['url'] }}" target="_blank" wire:key="material-{{ $loop->index }}"
                            class="flex items-center gap-3 rounded-xl border border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura p-3 shadow-sm dark:shadow-none transition hover:shadow-md">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-lavanda-fondo/25 text-lavanda-oscuro">
                                <x-icono nombre="material" class="h-5 w-5" />
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-texto dark:text-slate-100 truncate">{{ $material['nombre'] }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $material['modulo'] }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">Este curso todavía no tiene materiales.</p>
                    @endforelse
                </div>

                {{-- Compañeros: solo estudiantes ya inscritos ven quién más está en el curso. --}}
                <div x-show="pestaña === 'companeros'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @forelse ($this->companerosDelCurso as $companero)
                        @php $esUnoMismo = $companero->is(auth()->user()); @endphp
                        <div class="flex items-center gap-3 rounded-xl border border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura p-3" wire:key="companero-{{ $companero->id }}">
                            <img src="{{ $companero->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($companero->name).'&background=2F5A78&color=fff' }}"
                                class="h-9 w-9 shrink-0 rounded-full object-cover" alt="{{ $companero->name }}">
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-texto dark:text-slate-100 truncate">
                                    {{ $companero->name }} @if ($esUnoMismo) <span class="text-slate-400 font-normal">(tú)</span> @endif
                                </span>
                                @if ($companero->grupo_nombre)
                                    <span class="block text-xs text-slate-400 truncate">{{ $companero->grupo_nombre }}</span>
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400 sm:col-span-2">Todavía no hay más estudiantes inscritos en este curso.</p>
                    @endforelse
                </div>

                {{-- Mi grupo: nombre del grupo + integrantes, o aviso de que no tiene uno. --}}
                <div x-show="pestaña === 'migrupo'">
                    @if ($this->miGrupoDelCurso)
                        <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-5">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo">
                                    <x-icono nombre="usuarios" class="h-5 w-5" />
                                </span>
                                <h3 class="font-semibold text-texto dark:text-slate-100">{{ $this->miGrupoDelCurso->nombre }}</h3>
                            </div>

                            <p class="text-xs font-semibold uppercase text-slate-400 mb-2">Integrantes</p>
                            <div class="space-y-2">
                                @foreach ($this->miGrupoDelCurso->estudiantes as $integrante)
                                    @php $esUnoMismo = $integrante->is(auth()->user()); @endphp
                                    <div class="flex items-center gap-3" wire:key="integrante-{{ $integrante->id }}">
                                        <img src="{{ $integrante->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($integrante->name).'&background=2F5A78&color=fff' }}"
                                            class="h-8 w-8 shrink-0 rounded-full object-cover" alt="{{ $integrante->name }}">
                                        <span class="text-sm text-texto dark:text-slate-100">
                                            {{ $integrante->name }} @if ($esUnoMismo) <span class="text-slate-400">(tú)</span> @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-6 text-center text-slate-500 dark:text-slate-400 text-sm">
                            Todavía no tienes un grupo asignado en este curso. Cuando tu profesor te agregue a uno, aparecerá aquí.
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
