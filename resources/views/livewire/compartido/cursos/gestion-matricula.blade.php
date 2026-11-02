<div class="py-8">
    @php
        $esAdminVista = auth()->user()->hasRole('supervisor');
        $claseBadgeOrigen = $esAdminVista ? 'bg-arena-medio text-stone-700 dark:bg-stone-700 dark:text-stone-200' : 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200';
        $claseBadgeAcento = $esAdminVista ? 'bg-menta-fondo text-menta-oscuro dark:bg-stone-700 dark:text-menta-base' : 'bg-azul-oscuro/10 text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-azul-fondo';
    @endphp
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Matrícula del curso" icono="usuario-mas" :descripcion="$curso->nombre" />

        <x-gestion.alertas />

        @unless ($this->puedeMatricular)
            <div class="flex items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm {{ $esAdminVista ? 'border-arena-medio text-stone-600 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300' : 'border-celeste text-slate-600 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-300' }}">
                <x-icono nombre="info" class="h-5 w-5 shrink-0 {{ $esAdminVista ? 'text-menta-oscuro' : 'text-azul-oscuro' }}" />
                <p>
                    @if ($curso->estaFinalizado())
                        El curso está archivado: la matrícula queda en modo consulta.
                    @elseif ($curso->cupoLleno())
                        El curso alcanzó su cupo máximo de {{ $curso->cupo_maximo }} estudiantes. Da de baja a alguien o aumenta el cupo desde "Editar curso" para matricular a más.
                    @else
                        Las matrículas las registra el Supervisor. Puedes dar de baja a estudiantes de tu curso; las matrículas que llegaron por integración externa solo las desactiva el Supervisor.
                    @endif
                </p>
            </div>
        @endunless

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-gestion.indicador etiqueta="Matriculados activos" :valor="$totalActivos" icono="usuarios" tono="exito" />
            <x-gestion.indicador etiqueta="Bajas o sin acceso" :valor="$totalBajas" icono="prohibido" tono="alerta" />
            <x-gestion.indicador etiqueta="Desde API externa" :valor="$totalApi" icono="enlace" tono="cafe" />
        </div>

        @if ($this->puedeMatricular)
            <x-gestion.tarjeta titulo="Matricular estudiante" descripcion="Escribe al menos 2 caracteres del nombre, correo o DNI para buscar. El estudiante recibirá una notificación y un correo.">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div class="relative flex-1">
                        <label for="busquedaEstudiante" class="sr-only">Buscar estudiante</label>
                        <x-gestion.control id="busquedaEstudiante" wire:model.live.debounce.350ms="busquedaEstudiante" placeholder="Nombre, correo o DNI del estudiante" autocomplete="off" />
                        @if ($estudiantesBusqueda->isNotEmpty())
                            <div class="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-xl border bg-white shadow-lg {{ $esAdminVista ? 'border-arena-medio dark:border-stone-600 dark:bg-stone-800' : 'border-celeste dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura' }}">
                                @foreach ($estudiantesBusqueda as $est)
                                    <button type="button" wire:click="seleccionarEstudiante({{ $est->id }})" wire:key="busqueda-{{ $est->id }}"
                                        class="flex w-full items-center gap-3 border-b px-4 py-2.5 text-left last:border-0 {{ $esAdminVista ? 'border-arena-medio/60 hover:bg-arena-claro dark:border-stone-700 dark:hover:bg-stone-700' : 'border-celeste/60 hover:bg-celeste dark:border-azul-borde-oscuro dark:hover:bg-azul-borde-oscuro' }}">
                                        <x-icono nombre="usuarios" class="h-4 w-4 shrink-0 text-stone-400" />
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-stone-800 dark:text-stone-100">{{ $est->name }}</span>
                                            <span class="block truncate text-xs text-stone-500">{{ $est->email }} · DNI {{ $est->dni ?? 'sin registrar' }}</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif (mb_strlen(trim($busquedaEstudiante)) >= 2 && ! $estudianteSeleccionadoId)
                            <p class="mt-2 text-xs text-stone-500">Sin resultados: puede que ya esté matriculado, que su cuenta esté desactivada o que no tenga rol Estudiante.</p>
                        @endif
                        @error('estudianteSeleccionadoId')
                            <p class="mt-1 flex items-center gap-1 text-xs font-medium text-red-600"><x-icono nombre="alerta" class="h-3.5 w-3.5" /> {{ $message }}</p>
                        @enderror
                    </div>
                    <x-gestion.boton icono="usuario-mas" wire:click="matricularEstudiante" :disabled="! $estudianteSeleccionadoId"
                        wire:loading.attr="disabled" wire:target="matricularEstudiante">
                        Matricular
                    </x-gestion.boton>
                </div>
            </x-gestion.tarjeta>
        @endif

        <x-gestion.tarjeta :relleno="false" titulo="Estudiantes">
            <x-slot name="acciones">
                <label for="filtroEstado" class="sr-only">Filtrar</label>
                <x-gestion.control tipo="select" id="filtroEstado" class="!w-auto py-1 text-xs" wire:model.live="filtroEstado">
                    <option value="activo">Activos</option>
                    <option value="baja">Bajas o sin acceso</option>
                    <option value="api">Origen API</option>
                    <option value="todos">Todos</option>
                </x-gestion.control>
            </x-slot>

            @if ($inscripciones->isEmpty())
                <div class="p-4">
                    <x-gestion.vacio icono="usuarios" titulo="No hay estudiantes en esta vista"
                        descripcion="{{ $filtroEstado === 'activo' ? 'Aún no hay estudiantes matriculados en este curso.' : 'Prueba con otro filtro.' }}" />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide {{ $esAdminVista ? 'bg-arena-claro/50 text-stone-500 dark:bg-stone-900/40 dark:text-stone-400' : 'bg-celeste/50 text-slate-500 dark:bg-azul-pagina-oscura dark:text-slate-400' }}">
                            <tr>
                                <th class="px-4 py-3">Estudiante</th>
                                <th class="px-4 py-3">Origen</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Fecha</th>
                                @if ($this->puedeDarDeBaja) <th class="px-4 py-3 text-right">Acciones</th> @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y {{ $esAdminVista ? 'divide-arena-medio/60 dark:divide-stone-700' : 'divide-celeste/60 dark:divide-azul-borde-oscuro' }}">
                            @foreach ($inscripciones as $insc)
                                <tr wire:key="insc-{{ $insc->id }}">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-stone-800 dark:text-stone-100">{{ $insc->estudiante?->name }}</p>
                                        <p class="text-xs text-stone-500">{{ $insc->estudiante?->email }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $insc->esDeApiExterna() ? $claseBadgeOrigen : $claseBadgeAcento }}">
                                            <x-icono :nombre="$insc->esDeApiExterna() ? 'enlace' : 'usuarios'" class="h-3 w-3" />
                                            {{ $insc->esDeApiExterna() ? 'API externa' : 'Manual' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($insc->estaActiva())
                                            <span class="rounded-full bg-exito-base/15 px-2.5 py-0.5 text-xs font-medium text-exito-oscuro">Activo</span>
                                        @elseif ($insc->esDeApiExterna() && $insc->estado === 'activo')
                                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Acceso local desactivado</span>
                                        @else
                                            <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">Baja</span>
                                        @endif
                                        @if ($insc->fecha_baja)
                                            <p class="mt-1 text-xs text-stone-500">{{ $insc->fecha_baja->formatoFecha() }}{{ $insc->dadoDeBajaPor ? ' - '.$insc->dadoDeBajaPor->name : '' }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-stone-500">{{ $insc->fecha_inscripcion?->formatoFecha() }}</td>
                                    @if ($this->puedeDarDeBaja)
                                        <td class="px-4 py-3 text-right">
                                            @if ($this->puedeDarDeBajaA($insc))
                                                <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="prohibido" wire:click="confirmarBaja({{ $insc->id }})">
                                                    {{ $insc->esDeApiExterna() ? 'Desactivar acceso' : 'Dar de baja' }}
                                                </x-gestion.boton>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t px-4 py-3 {{ $esAdminVista ? 'border-arena-medio dark:border-stone-700' : 'border-celeste dark:border-azul-borde-oscuro' }}">{{ $inscripciones->links() }}</div>
            @endif
        </x-gestion.tarjeta>
    </div>

    @if ($mostrarModalBaja && $inscripcionBaja)
        <x-gestion.modal titulo="{{ $inscripcionBaja->esDeApiExterna() ? 'Desactivar acceso local' : 'Dar de baja del curso' }}"
            cerrar="$set('mostrarModalBaja', false)" ancho="max-w-md">
            <div class="flex items-start gap-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                <x-icono nombre="alerta" class="h-5 w-5 shrink-0 text-amber-600" />
                <p>
                    @if ($inscripcionBaja->esDeApiExterna())
                        {{ $inscripcionBaja->estudiante?->name }} perderá el acceso a este curso en LearnUp. La matrícula pertenece al sistema externo y no se modificará allí.
                    @else
                        Esta acción retirará a {{ $inscripcionBaja->estudiante?->name }} del curso {{ $curso->nombre }}. Sus notas, entregas y asistencia se conservan.
                    @endif
                </p>
            </div>
            <x-gestion.campo etiqueta="Motivo (opcional)" nombre="motivoBaja" ayuda="Queda registrado en la bitácora de auditoría.">
                <x-gestion.control tipo="textarea" id="motivoBaja" rows="2" wire:model="motivoBaja" />
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalBaja', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton variante="peligro" icono="prohibido" wire:click="ejecutarBaja">Confirmar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
