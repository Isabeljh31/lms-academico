<div class="py-8">
    <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Importar usuarios" icono="subir"
            descripcion="Crea cuentas en bloque desde un libro de Excel (.xlsx) o un archivo CSV. Cada cuenta recibe una contraseña temporal y un correo de bienvenida." />

        @if (! $procesado)
            <x-gestion.tarjeta titulo="Formato del archivo" descripcion="Primera fila con encabezados; se lee la primera hoja del libro. En CSV, separador coma (,) o punto y coma (;).">
                <pre class="overflow-x-auto rounded-lg border border-arena-medio bg-arena-claro/40 p-4 font-mono text-xs text-stone-700 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-300">nombres,apellidos,dni,correo,rol
Ana,García López,45678123,ana.garcia@escuela.edu.pe,estudiante
Carlos,Mendoza Ríos,07123456,c.mendoza@escuela.edu.pe,estudiante
Rosa,Díaz Vela,41239876,r.diaz@escuela.edu.pe,profesor</pre>
                <p class="mt-3 text-xs text-stone-500 dark:text-stone-400">
                    Columnas obligatorias: <code class="font-mono">nombres</code>, <code class="font-mono">apellidos</code>, <code class="font-mono">dni</code> (8 dígitos) y <code class="font-mono">correo</code>.
                    Roles válidos: <code class="font-mono">estudiante</code> y <code class="font-mono">profesor</code>; si la columna rol está vacía se usa estudiante.
                    En Excel, guarda la columna DNI como <strong>texto</strong> para no perder los ceros iniciales.
                    Las filas con errores se informan y no impiden crear las filas válidas.
                </p>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta>
                <x-gestion.campo etiqueta="Archivo Excel o CSV" nombre="archivo" requerido ayuda="Máximo 2 MB y 1000 filas.">
                    <input id="archivo" wire:model="archivo" type="file" accept=".xlsx,.csv,.txt"
                        class="block w-full cursor-pointer text-sm text-stone-600 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-menta-oscuro file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-menta-oscuro/90 dark:text-stone-300">
                </x-gestion.campo>
                <p wire:loading wire:target="archivo" class="mt-2 text-xs text-stone-500">Leyendo archivo...</p>
            </x-gestion.tarjeta>

            @if ($mostrarPreview)
                <x-gestion.tarjeta :relleno="false" titulo="Vista previa" descripcion="Primeras {{ count($preview) }} filas del archivo.">
                    @if (count($preview) === 0)
                        <div class="p-4">
                            <x-gestion.vacio icono="documento" titulo="El archivo no tiene filas de datos" descripcion="Verifica que la primera fila sea el encabezado y que haya al menos un usuario." />
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[480px] text-sm">
                                <thead class="bg-arena-claro/50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                                    <tr>
                                        <th class="px-4 py-2">Nombre</th>
                                        <th class="px-4 py-2">DNI</th>
                                        <th class="px-4 py-2">Correo</th>
                                        <th class="px-4 py-2">Rol</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                                    @foreach ($preview as $fila)
                                        <tr wire:key="preview-{{ $loop->index }}">
                                            <td class="px-4 py-2 text-stone-700 dark:text-stone-200">{{ $nombreVista($fila) ?: '—' }}</td>
                                            <td class="px-4 py-2 font-mono text-stone-500 dark:text-stone-400">{{ ($fila['dni'] ?? '') ?: '—' }}</td>
                                            <td class="px-4 py-2 text-stone-500 dark:text-stone-400">{{ $fila['correo'] ?? $fila['email'] ?? '—' }}</td>
                                            <td class="px-4 py-2 text-stone-500 dark:text-stone-400">{{ ($fila['rol'] ?? '') ?: 'estudiante' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="flex justify-end border-t border-arena-medio px-4 py-3 dark:border-stone-700">
                            <x-gestion.boton icono="subir" wire:click="importar" wire:loading.attr="disabled" wire:target="importar"
                                wire:confirm="Se crearán las cuentas válidas del archivo y se enviarán los correos de bienvenida. ¿Continuar?">
                                Importar y crear cuentas
                            </x-gestion.boton>
                        </div>
                    @endif
                </x-gestion.tarjeta>
            @endif
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-gestion.indicador etiqueta="Cuentas creadas" :valor="count($creados)" icono="check-circulo" tono="exito" />
                <x-gestion.indicador etiqueta="Omitidas (ya existían)" :valor="count($omitidos)" icono="info" tono="cafe" />
                <x-gestion.indicador etiqueta="Filas con error" :valor="count($errores)" icono="alerta" tono="alerta" />
            </div>

            @if (count($creados) > 0)
                <x-gestion.tarjeta :relleno="false" titulo="Cuentas creadas" descripcion="Guarda las contraseñas temporales: no se volverán a mostrar.">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead class="bg-arena-claro/50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                                <tr>
                                    <th class="px-4 py-2">Nombre</th>
                                    <th class="px-4 py-2">DNI</th>
                                    <th class="px-4 py-2">Correo</th>
                                    <th class="px-4 py-2">Rol</th>
                                    <th class="px-4 py-2">Contraseña temporal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                                @foreach ($creados as $c)
                                    <tr wire:key="creado-{{ $loop->index }}">
                                        <td class="px-4 py-2 font-medium text-stone-800 dark:text-stone-100">{{ $c['nombre'] }}</td>
                                        <td class="px-4 py-2 font-mono text-stone-500 dark:text-stone-400">{{ $c['dni'] }}</td>
                                        <td class="px-4 py-2 text-stone-500 dark:text-stone-400">{{ $c['correo'] }}</td>
                                        <td class="px-4 py-2 text-stone-500 dark:text-stone-400">{{ $c['rol'] }}</td>
                                        <td class="px-4 py-2"><code class="rounded bg-arena-claro px-2 py-0.5 font-mono font-bold text-stone-900 dark:bg-stone-900 dark:text-stone-100">{{ $c['password'] }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-gestion.tarjeta>
            @endif

            @if (count($errores) > 0)
                <x-gestion.tarjeta :relleno="false" titulo="Filas con error" descripcion="Corrige estas filas y vuelve a importar solo esas líneas.">
                    <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                        @foreach ($errores as $e)
                            <li wire:key="error-{{ $loop->index }}" class="flex items-start gap-3 px-4 py-2 text-sm">
                                <x-icono nombre="alerta" class="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                                <div>
                                    <p class="font-medium text-stone-800 dark:text-stone-100">Línea {{ $e['linea'] }}: {{ $e['error'] }}</p>
                                    @if ($e['datos'])
                                        <p class="text-xs text-stone-500">{{ $e['datos'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-gestion.tarjeta>
            @endif

            @if (count($omitidos) > 0)
                <x-gestion.tarjeta :relleno="false" titulo="Filas omitidas">
                    <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                        @foreach ($omitidos as $o)
                            <li wire:key="omitido-{{ $loop->index }}" class="px-4 py-2 text-sm text-stone-600 dark:text-stone-300">
                                Línea {{ $o['linea'] }} - {{ $o['nombre'] }} ({{ $o['correo'] }}): {{ $o['razon'] }}
                            </li>
                        @endforeach
                    </ul>
                </x-gestion.tarjeta>
            @endif

            <div class="flex justify-end gap-2">
                <x-gestion.boton variante="secundario" icono="subir" wire:click="reiniciar">Importar otro archivo</x-gestion.boton>
                <x-gestion.boton :href="route('supervisor.estudiantes.index')">Ir a usuarios</x-gestion.boton>
            </div>
        @endif
    </div>
</div>
