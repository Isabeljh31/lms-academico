<div class="py-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Integración externa (API)" icono="enlace"
            descripcion="API Keys de las instituciones que sincronizan matrículas con LearnUp y registro de las solicitudes recibidas.">
            <x-gestion.boton icono="mas" wire:click="$set('mostrarModalCrear', true)">Nueva API Key</x-gestion.boton>
        </x-gestion.encabezado>

        <x-gestion.alertas />

        @if ($apiKeyVisible)
            <div role="alert" class="rounded-xl border border-terracota-base/50 bg-arena-claro p-4 dark:border-stone-600 dark:bg-stone-800">
                <div class="flex items-start gap-3">
                    <x-icono nombre="llave" class="h-5 w-5 shrink-0 text-terracota-oscuro" />
                    <div class="min-w-0 flex-1 space-y-2 text-sm">
                        <p class="font-semibold text-stone-800 dark:text-stone-100">API Key de "{{ $apiKeyNombre }}"</p>
                        <code class="block break-all rounded bg-white px-3 py-2 font-mono text-stone-900 dark:bg-stone-900 dark:text-stone-100">{{ $apiKeyVisible }}</code>
                        <p class="text-xs text-stone-600 dark:text-stone-400">Cópiala ahora y entrégala al sistema externo por un canal seguro. No se volverá a mostrar.</p>
                    </div>
                    <x-gestion.boton variante="secundario" tamano="sm" wire:click="ocultarKey">Ya la copié</x-gestion.boton>
                </div>
            </div>
        @endif

        <x-gestion.tarjeta titulo="Contrato de integración">
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase text-stone-500">Endpoint</dt>
                    <dd class="font-mono text-stone-800 dark:text-stone-200">POST {{ $endpoint }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-stone-500">Autenticación</dt>
                    <dd class="font-mono text-stone-800 dark:text-stone-200">Authorization: Bearer &lt;api_key&gt;</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase text-stone-500">Payload (JSON)</dt>
                    <dd>
                        <pre class="mt-1 overflow-x-auto rounded-lg bg-arena-claro/50 p-3 font-mono text-xs text-stone-700 dark:bg-stone-900 dark:text-stone-300">{"usuario": {"nombres": "Ana", "apellidos": "García", "correo": "ana@escuela.edu.pe", "rol": "estudiante"},
 "curso": {"id": 1} o {"codigo": "MAT-101"},
 "matricula": {"estado": "activo" | "inactivo"}}</pre>
                    </dd>
                </div>
            </dl>
        </x-gestion.tarjeta>

        <x-gestion.tarjeta titulo="API Keys" :relleno="false">
            @if ($integraciones->isEmpty())
                <div class="p-4">
                    <x-gestion.vacio icono="llave" titulo="Aún no hay integraciones"
                        descripcion="Crea una API Key para cada institución o empresa cliente que sincronizará matrículas." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-arena-claro/50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                            <tr>
                                <th class="px-4 py-3">Institución</th>
                                <th class="px-4 py-3">Prefijo</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Último uso</th>
                                <th class="px-4 py-3">Solicitudes</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($integraciones as $integracion)
                                <tr wire:key="integracion-{{ $integracion->id }}">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-stone-800 dark:text-stone-100">{{ $integracion->nombre }}</p>
                                        @if ($integracion->descripcion)
                                            <p class="text-xs text-stone-500">{{ $integracion->descripcion }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-stone-600 dark:text-stone-300">{{ $integracion->api_key_prefix }}…</td>
                                    <td class="px-4 py-3">
                                        @if ($integracion->activo)
                                            <span class="rounded-full bg-exito-base/15 px-2.5 py-0.5 text-xs font-medium text-exito-oscuro">Activa</span>
                                        @else
                                            <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">Revocada</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-stone-500">{{ $integracion->ultimo_uso?->formatoFechaHora() ?? 'Nunca' }}</td>
                                    <td class="px-4 py-3 text-xs text-stone-500">{{ $integracion->logs_count }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1">
                                            <x-gestion.boton variante="fantasma" tamano="sm" icono="refrescar" wire:click="regenerar({{ $integracion->id }})"
                                                wire:confirm="Se generará una API Key nueva para {{ $integracion->nombre }} y la actual dejará de funcionar de inmediato. ¿Continuar?">
                                                Regenerar
                                            </x-gestion.boton>
                                            @if ($integracion->activo)
                                                <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="prohibido" wire:click="revocar({{ $integracion->id }})"
                                                    wire:confirm="Las solicitudes de {{ $integracion->nombre }} serán rechazadas hasta regenerar la key. ¿Revocar?">
                                                    Revocar
                                                </x-gestion.boton>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-gestion.tarjeta>

        <x-gestion.tarjeta titulo="Registro de solicitudes" descripcion="Todas las llamadas al endpoint, incluidas las rechazadas." :relleno="false">
            <x-slot name="acciones">
                <label for="filtroLog" class="sr-only">Filtrar solicitudes</label>
                <x-gestion.control tipo="select" id="filtroLog" class="!w-auto py-1 text-xs" wire:model.live="filtroLog">
                    <option value="">Todas</option>
                    <option value="exito">Correctas</option>
                    <option value="error">Con error</option>
                </x-gestion.control>
            </x-slot>
            @if ($logs->isEmpty())
                <div class="p-4">
                    <x-gestion.vacio icono="lista" titulo="Sin solicitudes registradas" />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-arena-claro/50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                            <tr>
                                <th class="px-4 py-2">Fecha</th>
                                <th class="px-4 py-2">Integración</th>
                                <th class="px-4 py-2">IP</th>
                                <th class="px-4 py-2">Código</th>
                                <th class="px-4 py-2">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($logs as $log)
                                <tr wire:key="log-{{ $log->id }}">
                                    <td class="whitespace-nowrap px-4 py-2 text-xs text-stone-500">{{ $log->created_at?->formatoFechaHora(true) }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $log->integracion?->nombre ?? 'Sin identificar' }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-stone-500">{{ $log->ip }}</td>
                                    <td class="px-4 py-2">
                                        <span class="rounded px-2 py-0.5 font-mono text-xs {{ $log->status_code < 400 ? 'bg-exito-base/15 text-exito-oscuro' : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' }}">{{ $log->status_code }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-stone-600 dark:text-stone-300">{{ $log->error_mensaje ?? 'Correcta' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-arena-medio px-4 py-3 dark:border-stone-700">{{ $logs->links() }}</div>
            @endif
        </x-gestion.tarjeta>
    </div>

    @if ($mostrarModalCrear)
        <x-gestion.modal titulo="Nueva API Key" cerrar="$set('mostrarModalCrear', false)"
            descripcion="La key se mostrará una sola vez. En la base de datos solo se guarda su hash.">
            <x-gestion.campo etiqueta="Institución o empresa" nombre="nombre" requerido>
                <x-gestion.control id="nombre" wire:model="nombre" placeholder="Instituto San Martín" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Descripción" nombre="descripcion">
                <x-gestion.control tipo="textarea" id="descripcion" wire:model="descripcion" placeholder="Sistema de matrícula (SIS) del instituto" />
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalCrear', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton icono="llave" wire:click="crear">Generar API Key</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
