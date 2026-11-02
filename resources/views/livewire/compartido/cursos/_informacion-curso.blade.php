@if ($esAdminVista || $carpetas->isNotEmpty())
    <x-gestion.tarjeta titulo="Información del curso" descripcion="Documentos y enlaces administrativos (sílabo, actas, etc.), organizados en carpetas.">
        @if ($esAdminVista)
            <x-slot name="acciones">
                <x-gestion.boton variante="secundario" tamano="sm" icono="mas" wire:click="abrirCrearCarpeta">Nueva carpeta</x-gestion.boton>
            </x-slot>
        @endif

        <div class="space-y-2">
            @forelse ($carpetas as $carpeta)
                <details class="rounded-lg border border-arena-medio/60 dark:border-stone-700" wire:key="carpeta-{{ $carpeta->id }}">
                    <summary class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2.5 text-sm font-semibold text-stone-800 dark:text-stone-100">
                        <span class="flex items-center gap-2">
                            <x-icono nombre="archivo" class="h-4 w-4 shrink-0 text-stone-400" />
                            {{ $carpeta->nombre }}
                            <span class="text-xs font-normal text-stone-400">({{ count($carpeta->recursos ?? []) }})</span>
                        </span>
                        @if ($esAdminVista)
                            <button type="button" wire:click.stop="eliminarCarpeta({{ $carpeta->id }})"
                                wire:confirm="¿Eliminar la carpeta &quot;{{ $carpeta->nombre }}&quot; y todo su contenido?"
                                class="shrink-0 text-stone-400 hover:text-red-600" aria-label="Eliminar carpeta {{ $carpeta->nombre }}">
                                <x-icono nombre="papelera" class="h-4 w-4" />
                            </button>
                        @endif
                    </summary>
                    <div class="border-t border-arena-medio/60 px-3 py-2 dark:border-stone-700">
                        @forelse ($carpeta->recursos ?? [] as $i => $recurso)
                            <div class="flex items-center justify-between gap-2 py-1.5 text-sm" wire:key="recurso-{{ $carpeta->id }}-{{ $i }}">
                                <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($recurso['url']) }}" target="_blank" rel="noopener"
                                    class="flex min-w-0 items-center gap-1.5 truncate {{ $esAdminVista ? 'text-menta-oscuro dark:text-menta-base' : 'text-azul-oscuro dark:text-azul-fondo' }} hover:underline">
                                    <x-icono :nombre="\App\Services\Multimedia\SubidorArchivos::esEnlaceExterno($recurso['url']) ? 'enlace' : 'documento'" class="h-4 w-4 shrink-0" />
                                    <span class="truncate">{{ $recurso['nombre'] }}</span>
                                </a>
                                @if ($esAdminVista)
                                    <button type="button" wire:click="eliminarRecurso({{ $carpeta->id }}, {{ $i }})"
                                        wire:confirm="¿Eliminar &quot;{{ $recurso['nombre'] }}&quot;?"
                                        class="shrink-0 text-stone-400 hover:text-red-600" aria-label="Eliminar {{ $recurso['nombre'] }}">
                                        <x-icono nombre="papelera" class="h-3.5 w-3.5" />
                                    </button>
                                @endif
                            </div>
                        @empty
                            <p class="py-1.5 text-xs text-stone-400">Sin archivos ni enlaces todavía.</p>
                        @endforelse
                        @if ($esAdminVista)
                            <x-gestion.boton variante="fantasma" tamano="sm" icono="mas" wire:click="abrirAgregarRecurso({{ $carpeta->id }})" class="mt-2">Agregar archivo o enlace</x-gestion.boton>
                        @endif
                    </div>
                </details>
            @empty
                <p class="text-sm text-stone-500">Aún no hay carpetas.</p>
            @endforelse
        </div>
    </x-gestion.tarjeta>
@endif
