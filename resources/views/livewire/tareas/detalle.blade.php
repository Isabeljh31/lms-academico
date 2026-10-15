@php
    // Paleta fría institucional (ver /DISEÑO FIGMA y ARQUITECTURA.md)
    // aplicada al estudiante. El profesor/admin conserva la paleta cálida.
    $paletaFria = auth()->user()->hasRole('estudiante');
@endphp

<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if (session('estado') && $paletaFria)
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm font-medium">{{ session('estado') }}</div>
        @endif

        <div class="bg-white rounded-xl border p-6 {{ $paletaFria ? 'dark:bg-azul-superficie-oscura border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none' : 'dark:bg-stone-800 border-arena-medio dark:border-stone-700' }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $tarea->curso->nombre }}</span>
                    <h1 class="text-2xl font-bold mt-1 {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100' }}">{{ $tarea->titulo }}</h1>
                </div>
                <div class="text-right">
                    <span class="text-xs text-slate-400 block">Vencimiento</span>
                    <span class="text-sm font-medium {{ $paletaFria ? 'text-texto dark:text-slate-200' : 'text-stone-700 dark:text-stone-200' }}">
                        {{ $tarea->fecha_entrega?->formatoFechaHora() ?? 'Sin fecha límite' }}
                    </span>
                    <span class="text-xs text-slate-400 block mt-1">Peso: {{ (float) $tarea->peso_nota }} %</span>
                </div>
            </div>

            @if ($tarea->descripcion)
                <p class="mt-4 {{ $paletaFria ? 'text-slate-600 dark:text-slate-300' : 'text-stone-600 dark:text-stone-300' }}">{{ $tarea->descripcion }}</p>
            @endif
            @if ($tarea->instrucciones)
                <p class="mt-4 whitespace-pre-line {{ $paletaFria ? 'text-slate-600 dark:text-slate-300' : 'text-stone-600 dark:text-stone-300' }}"><span class="font-semibold">Instrucciones:</span> {{ $tarea->instrucciones }}</p>
            @endif
            @if ($tarea->fecha_inicio)
                <span class="text-xs text-slate-400 block mt-2">Disponible desde: {{ $tarea->fecha_inicio->formatoFechaHora() }} · Intentos: {{ $tarea->intentos_permitidos }}</span>
            @endif
        </div>

        @if ($paletaFria)
            {{-- Rediseño visual del rol estudiante: entregar o ver mi entrega calificada. --}}
            <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-6">
                @if ($tarea->esGrupal())
                    <p class="inline-flex items-center gap-1.5 text-xs font-semibold text-lavanda-oscuro bg-lavanda-fondo/30 rounded-full px-2.5 py-1 mb-4">
                        <x-icono nombre="usuarios" class="h-3.5 w-3.5" /> Entrega grupal
                    </p>
                @endif

                @if ($this->miEntrega)
                    {{-- Recibo de confirmación de entrega del sistema LearnUp --}}
                    <div class="rounded-xl border border-exito-base/40 bg-exito-base/10 dark:bg-emerald-950/30 p-4 mb-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-exito-oscuro text-white text-xs font-bold"><x-icono nombre="check" class="h-4 w-4" /></span>
                                <div>
                                    <h3 class="text-sm font-bold text-exito-oscuro dark:text-emerald-300">Confirmación de Entrega Oficial</h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-300">
                                        Número de confirmación: <span class="font-mono font-bold text-slate-800 dark:text-slate-100">REC-{{ str_pad($this->miEntrega->id, 6, '0', STR_PAD_LEFT) }}-{{ $this->miEntrega->created_at->format('Y') }}</span>
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400 bg-white/60 dark:bg-black/20 px-2 py-1 rounded">
                                Fecha: {{ $this->miEntrega->created_at->formatoFechaHora(true) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-texto dark:text-slate-100">
                            {{ $tarea->esGrupal() ? 'Entrega de tu grupo' : 'Tu entrega' }}
                        </h2>
                        <x-badge-estado :estado="$this->miEstado" />
                    </div>
                    @if ($tarea->esGrupal() && $this->miGrupo)
                        <p class="text-xs text-slate-400 mt-1">{{ $this->miGrupo->nombre }} — {{ $this->miGrupo->estudiantes->pluck('name')->implode(', ') }}</p>
                    @endif
                    @if ($this->miEntrega->archivo_url)
                    <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($this->miEntrega->archivo_url) }}" target="_blank" rel="noopener noreferrer" class="text-sm text-azul-oscuro dark:text-azul-fondo hover:underline flex items-center gap-1 mt-2">
                        <x-icono nombre="material" class="h-4 w-4" /> Ver archivo entregado
                    </a>
                    @endif
                    @if ($this->miEntrega->enlace)
                    <a href="{{ $this->miEntrega->enlace }}" target="_blank" rel="noopener noreferrer" class="text-sm text-azul-oscuro dark:text-azul-fondo hover:underline flex items-center gap-1 mt-2">
                        <x-icono nombre="enlace" class="h-4 w-4" /> Ver enlace entregado
                    </a>
                    @endif
                    @if ($this->miEntrega->respuesta_texto)
                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-2 whitespace-pre-line">{{ $this->miEntrega->respuesta_texto }}</p>
                    @endif
                    @if ($this->miEntrega->comentario)
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">"{{ $this->miEntrega->comentario }}"</p>
                    @endif

                    @if ($this->miEntrega->estado === 'calificado')
                        <div class="mt-4 pt-4 border-t border-celeste dark:border-azul-borde-oscuro">
                            <div class="flex items-center justify-between">
                                <p class="text-base font-bold text-texto dark:text-slate-100">
                                    Nota: <span class="text-azul-oscuro dark:text-cielo font-black text-lg">{{ $this->miEntrega->nota }}</span> / 20
                                </p>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $this->miEntrega->nota >= $this->notaMinima ? 'bg-verde-fondo/60 text-verde-oscuro' : 'bg-terracota-base/20 text-terracota-oscuro' }}">
                                    {{ $this->miEntrega->nota >= $this->notaMinima ? 'Aprobado' : 'Desaprobado' }}
                                </span>
                            </div>
                            @if ($this->miEntrega->feedback)
                                <div class="mt-2 bg-slate-50 dark:bg-slate-800/60 rounded-lg p-3 border border-slate-100 dark:border-slate-700">
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-0.5">Comentarios del docente:</span>
                                    <p class="text-sm text-slate-700 dark:text-slate-200">{{ $this->miEntrega->feedback }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                    @if ($this->miEntrega->estado === 'devuelto' && $this->miEntrega->feedback)
                        <div class="mt-2 bg-slate-50 dark:bg-slate-800/60 rounded-lg p-3 border border-slate-100 dark:border-slate-700">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-0.5">Comentarios del docente:</span>
                            <p class="text-sm text-slate-700 dark:text-slate-200">{{ $this->miEntrega->feedback }}</p>
                        </div>
                    @endif
                    @if ($this->puedeReentregar)
                        <div class="mt-4 pt-4 border-t border-celeste dark:border-azul-borde-oscuro">
                            <h2 class="font-semibold text-texto dark:text-slate-100 mb-4">Volver a entregar (intento {{ $this->miEntrega->intentos_usados + 1 }})</h2>
                            @include('livewire.tareas.partials.formulario-entrega')
                        </div>
                    @endif
                @elseif ($tarea->esGrupal() && ! $this->miGrupo)
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Todavía no perteneces a ningún grupo en este curso. Pide a tu profesor que te agregue a uno antes de poder entregar esta tarea.
                    </p>
                @else
                    <h2 class="font-semibold text-texto dark:text-slate-100 mb-1">Entregar tarea</h2>
                    @if ($tarea->esGrupal() && $this->miGrupo)
                        <p class="text-xs text-slate-400 mb-4">
                            Entregarás por {{ $this->miGrupo->nombre }}: {{ $this->miGrupo->estudiantes->pluck('name')->implode(', ') }}.
                        </p>
                    @else
                        <div class="mb-4"></div>
                    @endif
                    @if (! $tarea->haIniciado())
                        <p class="text-sm text-slate-500 dark:text-slate-400">La entrega se habilita el {{ $tarea->fecha_inicio->formatoFechaHora() }}.</p>
                    @else
                        @include('livewire.tareas.partials.formulario-entrega')
                    @endif
                @endif
            </div>
        @else
            {{-- Vista de Profesor / Supervisor: revisar, calificar o devolver entregas (RF-49).
                 En una tarea grupal, las entregas del mismo grupo se califican juntas. --}}
            @php
                $entregasAgrupadas = $entregas->groupBy(fn ($e) => $e->grupo_id ?? 'individual-'.$e->id);
            @endphp

            <x-gestion.alertas />

            <div class="flex flex-wrap items-center gap-2">
                <x-gestion.boton variante="secundario" tamano="sm" icono="flecha-izquierda" :href="route('cursos.tareas.index', $tarea->curso)">Tareas del curso</x-gestion.boton>
                @if ($this->puedeGestionar && ! $tarea->curso->estaFinalizado())
                    <x-gestion.boton variante="secundario" tamano="sm" icono="lapiz" :href="route('cursos.tareas.editar', [$tarea->curso, $tarea])">Editar tarea</x-gestion.boton>
                @endif
                <span class="text-xs text-stone-500">{{ $tarea->etiquetaEstadoPublicacion() }} · {{ $tarea->esGrupal() ? 'Grupal' : 'Individual' }} · {{ $tarea->intentos_permitidos }} {{ $tarea->intentos_permitidos === 1 ? 'intento' : 'intentos' }}</span>
            </div>

            @unless ($this->puedeCalificar)
                <div class="flex items-start gap-3 rounded-xl border border-arena-medio bg-white px-4 py-3 text-sm text-stone-600 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300">
                    <x-icono nombre="info" class="h-5 w-5 shrink-0 text-menta-oscuro" />
                    <p>{{ $tarea->curso->estaFinalizado() ? 'El curso está archivado: las calificaciones ya no se pueden modificar.' : 'Solo el profesor del curso califica las entregas.' }}</p>
                </div>
            @endunless

            <x-gestion.tarjeta titulo="Entregas ({{ $entregasAgrupadas->count() }})" :relleno="false">
                @if ($entregasAgrupadas->isEmpty())
                    <div class="p-4">
                        <x-gestion.vacio icono="tarea" titulo="Nadie ha entregado esta tarea todavía" />
                    </div>
                @else
                    <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                        @foreach ($entregasAgrupadas as $miembrosDelGrupo)
                            @php $entrega = $miembrosDelGrupo->first(); @endphp
                            <li class="space-y-3 p-4" wire:key="entrega-{{ $entrega->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-medium text-stone-800 dark:text-stone-100">
                                            @if ($entrega->grupo_id)
                                                {{ $entrega->grupo?->nombre }}
                                                <span class="text-xs font-normal text-stone-500">({{ $miembrosDelGrupo->pluck('estudiante.name')->implode(', ') }})</span>
                                            @else
                                                {{ $entrega->estudiante?->name }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-stone-500">Entregado el {{ $entrega->updated_at?->formatoFechaHora() }} · intento {{ $entrega->intentos_usados }}</p>
                                    </div>
                                    <x-badge-estado :estado="$tarea->estadoPara($entrega)" />
                                </div>

                                <div class="space-y-1 text-sm">
                                    @if ($entrega->archivo_url)
                                        <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($entrega->archivo_url) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-menta-oscuro hover:underline dark:text-menta-base">
                                            <x-icono nombre="material" class="h-4 w-4" /> Ver archivo
                                        </a>
                                    @endif
                                    @if ($entrega->enlace)
                                        <a href="{{ $entrega->enlace }}" target="_blank" rel="noopener noreferrer" class="ml-3 inline-flex items-center gap-1 text-menta-oscuro hover:underline dark:text-menta-base">
                                            <x-icono nombre="enlace" class="h-4 w-4" /> Ver enlace
                                        </a>
                                    @endif
                                    @if ($entrega->respuesta_texto)
                                        <p class="whitespace-pre-line rounded-lg bg-arena-claro/50 p-3 text-stone-700 dark:bg-stone-900 dark:text-stone-300">{{ $entrega->respuesta_texto }}</p>
                                    @endif
                                    @if ($entrega->comentario)
                                        <p class="text-stone-500 dark:text-stone-400">Comentario: "{{ $entrega->comentario }}"</p>
                                    @endif
                                </div>

                                @if ($this->puedeCalificar)
                                    <form wire:submit="calificar({{ $entrega->id }})" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                                        <div>
                                            <label class="sr-only" for="nota-{{ $entrega->id }}">Nota sobre 20</label>
                                            <x-gestion.control id="nota-{{ $entrega->id }}" type="number" step="0.01" min="0" max="20" class="sm:w-28"
                                                wire:model="notas.{{ $entrega->id }}" placeholder="Nota /20" />
                                            @error('notas.'.$entrega->id) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="flex-1">
                                            <label class="sr-only" for="feedback-{{ $entrega->id }}">Comentario para el estudiante</label>
                                            <x-gestion.control id="feedback-{{ $entrega->id }}" wire:model="feedbacks.{{ $entrega->id }}" placeholder="Comentario para el estudiante" />
                                            @error('feedbacks.'.$entrega->id) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="flex gap-2">
                                            <x-gestion.boton type="submit" tamano="sm" icono="check">Calificar</x-gestion.boton>
                                            @if ($entrega->estado !== 'calificado')
                                                <x-gestion.boton variante="secundario" tamano="sm" icono="refrescar" wire:click="devolver({{ $entrega->id }})"
                                                    wire:confirm="Se devolverá la entrega para que el estudiante la corrija y vuelva a entregar. ¿Continuar?">Devolver</x-gestion.boton>
                                            @endif
                                        </div>
                                    </form>
                                @elseif ($entrega->nota !== null)
                                    <p class="text-sm text-stone-700 dark:text-stone-200">Nota: <strong>{{ $entrega->nota }}</strong> / 20 @if ($entrega->feedback) · {{ $entrega->feedback }} @endif</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Sin entregar ({{ $sinEntregar->count() }})" descripcion="{{ $tarea->estaVencida() ? 'El plazo venció: figuran como No entregado.' : 'El plazo sigue abierto: figuran como Pendiente.' }}">
                @if ($sinEntregar->isEmpty())
                    <p class="text-sm text-stone-500">Todos los estudiantes matriculados entregaron.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sinEntregar as $estudiante)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-arena-claro px-2.5 py-1 text-xs text-stone-700 dark:bg-stone-700 dark:text-stone-200" wire:key="sin-{{ $estudiante->id }}">
                                {{ $estudiante->name }} <x-badge-estado :estado="$tarea->estaVencida() ? 'no_entregado' : 'pendiente'" />
                            </span>
                        @endforeach
                    </div>
                @endif
            </x-gestion.tarjeta>
        @endif
    </div>
</div>
