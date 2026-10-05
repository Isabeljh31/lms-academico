<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado :titulo="'Hola, '.auth()->user()->name" icono="inicio"
            :descripcion="$esSupervisor ? 'Resumen institucional de la plataforma.' : 'Resumen de tus cursos y pendientes.'">
            <span class="text-sm text-stone-500 dark:text-stone-400">Rol: <x-badge-estado :estado="auth()->user()->rolPrincipal() ?? 'profesor'" /></span>
        </x-gestion.encabezado>

        @foreach ($comunicados as $comunicado)
            <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $comunicado->prioridad === 'urgente' ? 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200' : 'border-arena-medio bg-white text-stone-700 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-200' }}" wire:key="banner-com-{{ $comunicado->id }}">
                <x-icono :nombre="$comunicado->prioridad === 'urgente' ? 'alerta' : 'megafono'" class="h-5 w-5 shrink-0" />
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide opacity-80">Comunicado institucional{{ $comunicado->prioridad === 'urgente' ? ' urgente' : '' }} · {{ $comunicado->created_at->diffForHumans() }}</p>
                    <p class="font-semibold">{{ $comunicado->titulo }}</p>
                    <p class="whitespace-pre-line text-xs opacity-90">{{ $comunicado->contenido }}</p>
                </div>
            </div>
        @endforeach

        @if ($esSupervisor)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                <x-gestion.indicador etiqueta="Estudiantes activos" :valor="$estudiantesActivos" icono="usuarios" />
                <x-gestion.indicador etiqueta="Profesores activos" :valor="$profesoresActivos" icono="birrete" tono="cafe" />
                <x-gestion.indicador etiqueta="Cursos publicados" :valor="$cursosActivos" icono="libro" tono="exito" />
                <x-gestion.indicador etiqueta="Tasa de finalización" :valor="$tasaFinalizacion !== null ? $tasaFinalizacion.' %' : '—'" icono="check-circulo" tono="exito"
                    detalle="Aprobados en cursos finalizados" />
                <x-gestion.indicador etiqueta="Entregas pendientes" :valor="$entregasPendientes" icono="reloj" tono="terracota" detalle="Por calificar en toda la plataforma" />
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-gestion.tarjeta titulo="Atención requerida">
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-stone-700 dark:text-stone-200"><x-icono nombre="grafico" class="h-4 w-4 text-terracota-oscuro" /> Cursos sin esquema de evaluación</span>
                            <strong>{{ $cursosSinEsquema }}</strong>
                        </li>
                        <li class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-stone-700 dark:text-stone-200"><x-icono nombre="llave" class="h-4 w-4 text-terracota-oscuro" /> Cuentas con primer ingreso pendiente</span>
                            <strong>{{ $cuentasPrimerIngreso }}</strong>
                        </li>
                    </ul>
                </x-gestion.tarjeta>
                <x-gestion.tarjeta titulo="Accesos rápidos">
                    <div class="flex flex-wrap gap-2">
                        <x-gestion.boton variante="secundario" tamano="sm" icono="usuario-mas" :href="route('admin.usuarios.index')">Usuarios</x-gestion.boton>
                        <x-gestion.boton variante="secundario" tamano="sm" icono="libro" :href="route('cursos.index')">Cursos</x-gestion.boton>
                        <x-gestion.boton variante="secundario" tamano="sm" icono="grafico" :href="route('admin.metricas')">Dashboard institucional</x-gestion.boton>
                        <x-gestion.boton variante="secundario" tamano="sm" icono="escudo" :href="route('admin.auditoria')">Auditoría</x-gestion.boton>
                    </div>
                </x-gestion.tarjeta>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                <x-gestion.indicador etiqueta="Cursos a cargo" :valor="$cursosActivos" icono="libro" />
                <x-gestion.indicador etiqueta="Estudiantes" :valor="$totalEstudiantes" icono="usuarios" tono="cafe" />
                <x-gestion.indicador etiqueta="Por calificar" :valor="$totalPendientes" icono="reloj" :tono="$totalPendientes > 0 ? 'terracota' : 'exito'" />
                <x-gestion.indicador etiqueta="Quizzes activos" :valor="$quizzesActivos" icono="quiz" tono="menta" />
                <x-gestion.indicador etiqueta="Promedio general" :valor="$promedioGeneral ?? '—'" icono="grafico"
                    :tono="$promedioGeneral !== null && $promedioGeneral < $notaMinima ? 'alerta' : 'exito'" detalle="Promedio a la fecha por rubros" />
            </div>

            <x-gestion.tarjeta titulo="Mis cursos" :relleno="false">
                <x-slot name="acciones">
                    <x-gestion.boton variante="secundario" tamano="sm" icono="tarea" :href="route('docente.por-calificar')">Ir a Por calificar</x-gestion.boton>
                </x-slot>
                @if ($cursos->isEmpty())
                    <div class="p-4">
                        <x-gestion.vacio icono="libro" titulo="Aún no tienes cursos asignados" descripcion="El Supervisor te asignará tus cursos." />
                    </div>
                @else
                    <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                        @foreach ($cursos as $curso)
                            <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="curso-panel-{{ $curso->id }}">
                                <span class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="font-medium text-menta-oscuro hover:underline dark:text-menta-base">{{ $curso->nombre }}</a>
                                    @if ($curso->estaFinalizado())
                                        <span class="rounded-full bg-arena-claro px-2 py-0.5 text-[11px] font-medium text-stone-600 dark:bg-stone-700 dark:text-stone-300">Archivado</span>
                                    @endif
                                </span>
                                <div class="flex flex-wrap gap-4 text-xs text-stone-500 dark:text-stone-400">
                                    <span>{{ $curso->inscripciones_activas_count }} {{ $curso->inscripciones_activas_count === 1 ? 'estudiante' : 'estudiantes' }}</span>
                                    <span>{{ $pendientesPorCurso[$curso->id] ?? 0 }} por calificar</span>
                                    @php $promedio = $promediosPorCurso[$curso->id] ?? null; @endphp
                                    <span>Promedio: <strong class="{{ $promedio !== null && $promedio < $notaMinima ? 'text-red-600' : '' }}">{{ $promedio ?? '—' }}</strong></span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-gestion.tarjeta>
        @endif
    </div>
</div>
