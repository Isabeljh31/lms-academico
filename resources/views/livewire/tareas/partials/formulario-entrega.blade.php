{{-- Formulario de entrega del estudiante (RF-42). Mismas clases que el formulario original del rol Estudiante. --}}
<form wire:submit="entregar" class="space-y-4">
    <div>
        <input type="file" wire:model="archivo"
            class="block w-full text-sm text-slate-600 dark:text-slate-400 file:me-4 file:rounded-md file:border-0 file:bg-azul-oscuro file:px-3 file:py-1.5 file:text-white file:text-sm hover:file:bg-azul-oscuro/90">
        <span wire:loading wire:target="archivo" class="text-xs text-slate-400">Subiendo…</span>
        <p class="text-xs text-slate-400 mt-1">
            Formatos permitidos: {{ implode(', ', $tarea->formatosPermitidos() ?: \App\Services\Multimedia\SubidorArchivos::extensionesPermitidas()) }}. Máximo {{ (int) round(config('archivos.max_kb') / 1024) }} MB.
        </p>
        <x-input-error :messages="$errors->get('archivo')" class="mt-2" />
    </div>
    <div>
        <textarea wire:model="respuestaTexto" rows="4" placeholder="Respuesta escrita (opcional)…"
            class="block w-full rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo"></textarea>
        <x-input-error :messages="$errors->get('respuestaTexto')" class="mt-2" />
    </div>
    <div>
        <input type="url" wire:model="enlace" placeholder="Enlace (opcional), por ejemplo https://docs.google.com/…"
            class="block w-full rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo">
        <x-input-error :messages="$errors->get('enlace')" class="mt-2" />
    </div>
    <div>
        <textarea wire:model="comentario" rows="2" placeholder="Comentario (opcional)…"
            class="block w-full rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo"></textarea>
    </div>
    <x-primary-button wire:loading.attr="disabled" wire:target="entregar,archivo">Entregar</x-primary-button>
</form>
