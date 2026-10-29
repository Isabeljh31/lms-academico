<div class="py-8">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Configuración general" icono="engranaje"
            descripcion="Parámetros que se aplican a toda la plataforma. Cada cambio queda registrado en la bitácora de auditoría." />

        <x-gestion.alertas />

        <form wire:submit="guardar" class="space-y-6">
            <x-gestion.tarjeta titulo="Evaluación" descripcion="La nota final de un curso aprueba si es mayor o igual a este valor. Se aplica a todos los cursos.">
                <x-gestion.campo etiqueta="Nota mínima aprobatoria (0 a 20)" nombre="notaMinima" requerido class="sm:w-1/2">
                    <x-gestion.control id="notaMinima" type="number" step="0.1" min="0" max="20" wire:model="notaMinima" />
                </x-gestion.campo>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Institución y formato" descripcion="La zona horaria y el formato de fecha se aplican en toda la plataforma: plazos, calendario, notificaciones y exportaciones.">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Nombre de la institución" nombre="nombreInstitucion" requerido class="sm:col-span-2">
                        <x-gestion.control id="nombreInstitucion" wire:model="nombreInstitucion" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Zona horaria" nombre="zonaHoraria" requerido ayuda="Define la hora en que abren y cierran tareas, quizzes y comunicados programados.">
                        <x-gestion.control tipo="select" id="zonaHoraria" wire:model="zonaHoraria">
                            @foreach ($zonasHorarias as $zona)
                                <option value="{{ $zona }}">{{ $zona }}</option>
                            @endforeach
                        </x-gestion.control>
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Formato de fecha" nombre="formatoFecha" requerido ayuda="Hoy se vería así: {{ now()->format($formatoFecha) }}">
                        <x-gestion.control tipo="select" id="formatoFecha" wire:model.live="formatoFecha">
                            @foreach ($formatosFecha as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </x-gestion.control>
                    </x-gestion.campo>
                </div>
            </x-gestion.tarjeta>

            <div class="flex justify-end">
                <x-gestion.boton type="submit" icono="check">Guardar configuración</x-gestion.boton>
            </div>
        </form>

        <x-gestion.tarjeta titulo="Asistente de IA">
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-stone-600 dark:text-stone-300">
                    Al desactivarlo, el asistente deja de estar disponible para todos los roles.
                    Estado actual: <strong>{{ $botActivo ? 'activado' : 'desactivado' }}</strong>.
                </p>
                <button type="button" wire:click="alternarBot" role="switch" aria-checked="{{ $botActivo ? 'true' : 'false' }}" aria-label="Activar o desactivar el asistente de IA"
                    wire:confirm="{{ $botActivo ? '¿Desactivar el asistente de IA para toda la plataforma?' : '¿Activar el asistente de IA?' }}"
                    class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition {{ $botActivo ? 'bg-menta-oscuro' : 'bg-stone-300 dark:bg-stone-600' }}">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white transition {{ $botActivo ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>
        </x-gestion.tarjeta>
    </div>
</div>
