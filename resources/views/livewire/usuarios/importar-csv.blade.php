<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('usuarios.index') }}" wire:navigate
                class="text-xs text-stone-400 hover:text-stone-700 flex items-center gap-1 mb-2 transition">
                ← Volver a Usuarios
            </a>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Importar Usuarios CSV</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">RF-11, RF-12, RF-13 · Crea cuentas en bloque con contraseña temporal automática.</p>
        </div>
    </div>

    {{-- Instrucciones --}}
    @if(!$procesado)
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5 space-y-3">
        <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300">Formato del archivo CSV</h2>
        <p class="text-xs text-stone-500 dark:text-stone-400">
            El archivo debe tener la primera fila como encabezado con las columnas:
            <code class="bg-stone-100 dark:bg-stone-900 px-1.5 py-0.5 rounded font-mono text-stone-700 dark:text-stone-300">nombre</code>,
            <code class="bg-stone-100 dark:bg-stone-900 px-1.5 py-0.5 rounded font-mono text-stone-700 dark:text-stone-300">correo</code>,
            <code class="bg-stone-100 dark:bg-stone-900 px-1.5 py-0.5 rounded font-mono text-stone-700 dark:text-stone-300">rol</code>
        </p>
        <pre class="text-xs bg-stone-50 dark:bg-stone-900 rounded-lg border border-stone-200 dark:border-stone-700 p-4 font-mono text-stone-600 dark:text-stone-300 overflow-x-auto">nombre,correo,rol
Ana García López,ana.garcia@escuela.edu.pe,estudiante
Carlos Mendoza,c.mendoza@escuela.edu.pe,estudiante
Profesora Díaz,p.diaz@escuela.edu.pe,profesor</pre>
        <p class="text-xs text-stone-400 dark:text-stone-500">
            Roles válidos: <code class="font-mono">estudiante</code>, <code class="font-mono">profesor</code>, <code class="font-mono">supervisor</code>
        </p>
    </div>

    {{-- Subir archivo --}}
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5">
        <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-2">
            Archivo CSV
        </label>
        <input wire:model="archivo" type="file" accept=".csv,.txt"
            class="w-full text-sm text-stone-600 dark:text-stone-400
                file:mr-4 file:rounded-lg file:border-0 file:bg-terracota-base
                file:px-4 file:py-2 file:text-white file:text-sm hover:file:bg-terracota-oscuro
                file:cursor-pointer cursor-pointer">
        @error('archivo') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
    </div>

    {{-- Preview --}}
    @if($mostrarPreview && count($preview) > 0)
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-stone-100 dark:border-stone-700">
            <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300">
                Vista previa (primeras {{ count($preview) }} filas)
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-stone-50 dark:bg-stone-700/50 text-stone-500 dark:text-stone-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Nombre</th>
                        <th class="px-4 py-2 text-left">Correo</th>
                        <th class="px-4 py-2 text-left">Rol</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                    @foreach($preview as $fila)
                    <tr>
                        <td class="px-4 py-2 text-stone-700 dark:text-stone-300">{{ $fila['nombre'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-stone-500 dark:text-stone-400">{{ $fila['correo'] ?? $fila['email'] ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-stone-100 dark:bg-stone-700 text-stone-600 dark:text-stone-300">
                                {{ $fila['rol'] ?? 'estudiante' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-stone-100 dark:border-stone-700 flex justify-end">
            <button wire:click="importar"
                class="rounded-lg bg-terracota-base px-6 py-2 text-sm font-semibold text-white hover:bg-terracota-oscuro transition flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                Importar y crear cuentas
            </button>
        </div>
    </div>
    @endif
    @endif

    {{-- Resultados (ERS RF-13) --}}
    @if($procesado)
    <div class="space-y-4">
        {{-- Resumen --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-xl p-4 text-center">
                <p class="text-2xl font-black text-green-600 dark:text-green-400">{{ count($creados) }}</p>
                <p class="text-xs text-green-700 dark:text-green-500 mt-1">Cuentas creadas</p>
            </div>
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4 text-center">
                <p class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ count($omitidos) }}</p>
                <p class="text-xs text-amber-700 dark:text-amber-500 mt-1">Omitidos (ya existen)</p>
            </div>
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl p-4 text-center">
                <p class="text-2xl font-black text-red-600 dark:text-red-400">{{ count($errores) }}</p>
                <p class="text-xs text-red-700 dark:text-red-500 mt-1">Con error</p>
            </div>
        </div>

        {{-- Cuentas creadas con passwords temporales --}}
        @if(count($creados) > 0)
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-stone-100 dark:border-stone-700 bg-green-50 dark:bg-green-900/20">
                <h3 class="text-sm font-semibold text-green-700 dark:text-green-400">
                    ✅ {{ count($creados) }} cuentas creadas — guarda las contraseñas temporales
                </h3>
                <p class="text-xs text-green-600 dark:text-green-500 mt-0.5">Estas contraseñas no se volverán a mostrar.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-stone-50 dark:bg-stone-700/50 text-stone-500 dark:text-stone-400">
                        <tr>
                            <th class="px-4 py-2 text-left">Nombre</th>
                            <th class="px-4 py-2 text-left">Correo</th>
                            <th class="px-4 py-2 text-left">Rol</th>
                            <th class="px-4 py-2 text-left">Contraseña temporal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                        @foreach($creados as $c)
                        <tr>
                            <td class="px-4 py-2 text-stone-700 dark:text-stone-300 font-medium">{{ $c['nombre'] }}</td>
                            <td class="px-4 py-2 text-stone-500 dark:text-stone-400">{{ $c['correo'] }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-stone-100 dark:bg-stone-700 text-stone-600 dark:text-stone-300">{{ $c['rol'] }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <code class="font-mono font-bold text-terracota-oscuro dark:text-terracota-claro bg-terracota-base/10 px-2 py-0.5 rounded">{{ $c['password'] }}</code>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Errores --}}
        @if(count($errores) > 0)
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-red-200 dark:border-red-700/50 overflow-hidden">
            <div class="px-4 py-3 border-b border-red-100 dark:border-red-700/30 bg-red-50 dark:bg-red-900/20">
                <h3 class="text-sm font-semibold text-red-700 dark:text-red-400">❌ {{ count($errores) }} filas con error</h3>
            </div>
            <ul class="divide-y divide-stone-50 dark:divide-stone-700/50">
                @foreach($errores as $e)
                <li class="px-4 py-3 flex items-start gap-3">
                    <span class="text-red-500 font-mono text-xs shrink-0">Fila {{ $e['linea'] }}</span>
                    <div>
                        <p class="text-xs text-stone-600 dark:text-stone-400">{{ $e['datos'] }}</p>
                        <p class="text-xs text-red-600 dark:text-red-400 font-medium mt-0.5">{{ $e['error'] }}</p>
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="flex justify-end">
            <button wire:click="$set('procesado', false)"
                class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                Importar otro archivo
            </button>
        </div>
    </div>
    @endif

</div>
