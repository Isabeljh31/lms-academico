<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('cursos.show', $modulo->curso_id) }}" wire:navigate
                class="text-xs text-stone-400 hover:text-stone-600 flex items-center gap-1 mb-1 transition">
                ← Volver al curso
            </a>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Lecciones</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Módulo: <strong>{{ $modulo->titulo }}</strong></p>
        </div>
        <button wire:click="abrirNueva"
            class="inline-flex items-center gap-2 rounded-lg bg-menta-oscuro px-4 py-2 text-sm font-semibold text-white hover:bg-menta-base transition">
            + Nueva lección
        </button>
    </div>

    @if(session('estado'))
    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-700 dark:text-green-400">
        {{ session('estado') }}
    </div>
    @endif

    {{-- Formulario (modal) --}}
    @if($mostrarFormulario)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-stone-800 rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 overflow-y-auto max-h-[90vh]">
            <h2 class="text-lg font-bold text-stone-800 dark:text-stone-100">
                {{ $leccionEditId ? 'Editar lección' : 'Nueva lección' }}
            </h2>

            <div>
                <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Título *</label>
                <input wire:model="titulo" type="text" placeholder="ej. Introducción al tema"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-menta-base">
                @error('titulo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Descripción</label>
                <textarea wire:model="descripcion" rows="3"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-menta-base"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">URL de Video</label>
                    <input wire:model="video_url" type="url" placeholder="https://youtube.com/..."
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-menta-base">
                    @error('video_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">URL de Material</label>
                    <input wire:model="material_url" type="url" placeholder="https://drive.google.com/..."
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-menta-base">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Estado *</label>
                    <select wire:model.live="estado"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
                        <option value="borrador">Borrador</option>
                        <option value="publicado">Publicado</option>
                        <option value="programado">Programado</option>
                    </select>
                </div>
                @if($estado === 'programado')
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Fecha de publicación *</label>
                    <input wire:model="fecha_publicacion" type="datetime-local"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
                    @error('fecha_publicacion') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                @endif
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button wire:click="$set('mostrarFormulario', false)"
                    class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    Cancelar
                </button>
                <button wire:click="guardar"
                    class="rounded-lg bg-menta-oscuro px-4 py-2 text-sm font-semibold text-white hover:bg-menta-base transition">
                    {{ $leccionEditId ? 'Guardar cambios' : 'Crear lección' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Lista de lecciones --}}
    @if($lecciones->isEmpty())
    <div class="text-center py-16 text-stone-400 dark:text-stone-500">
        <p class="text-4xl mb-4">📄</p>
        <p class="text-sm">No hay lecciones en este módulo aún.</p>
        <button wire:click="abrirNueva" class="mt-4 text-sm text-menta-oscuro hover:underline">Crear la primera lección →</button>
    </div>
    @else
    <div class="space-y-3">
        @foreach($lecciones as $leccion)
        <div wire:key="leccion-{{ $leccion->id }}"
            class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 flex items-start gap-4">

            {{-- Orden / reordenar --}}
            <div class="flex flex-col items-center gap-1 shrink-0 pt-0.5">
                <button wire:click="moverArriba({{ $leccion->id }})"
                    class="text-stone-300 hover:text-stone-600 dark:hover:text-stone-200 transition text-xs">▲</button>
                <span class="text-xs font-mono text-stone-400">{{ $loop->iteration }}</span>
                <button wire:click="moverAbajo({{ $leccion->id }})"
                    class="text-stone-300 hover:text-stone-600 dark:hover:text-stone-200 transition text-xs">▼</button>
            </div>

            {{-- Contenido --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-semibold text-stone-800 dark:text-stone-100">{{ $leccion->titulo }}</h3>
                    {{-- Badge estado RF-37 --}}
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium
                        {{ $leccion->estado === 'publicado' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                        : ($leccion->estado === 'programado' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                        : 'bg-stone-100 text-stone-600 dark:bg-stone-700 dark:text-stone-300') }}">
                        {{ ucfirst($leccion->estado) }}
                        @if($leccion->estado === 'programado' && $leccion->fecha_publicacion)
                        · {{ $leccion->fecha_publicacion->format('d/m H:i') }}
                        @endif
                    </span>
                </div>
                @if($leccion->descripcion)
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1 truncate">{{ $leccion->descripcion }}</p>
                @endif
                <div class="flex items-center gap-4 mt-2 text-xs text-stone-400 dark:text-stone-500">
                    @if($leccion->video_url)
                    <a href="{{ $leccion->video_url }}" target="_blank" class="flex items-center gap-1 hover:text-menta-oscuro transition">
                        🎥 Video
                    </a>
                    @endif
                    @if($leccion->material_url)
                    <a href="{{ $leccion->material_url }}" target="_blank" class="flex items-center gap-1 hover:text-menta-oscuro transition">
                        📎 Material
                    </a>
                    @endif
                </div>
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-2 shrink-0">
                <button wire:click="editar({{ $leccion->id }})"
                    class="text-xs text-stone-500 hover:text-stone-800 dark:hover:text-stone-200 px-3 py-1.5 rounded-lg hover:bg-stone-100 dark:hover:bg-stone-700 transition">
                    Editar
                </button>
                <button wire:click="eliminar({{ $leccion->id }})"
                    wire:confirm="¿Eliminar esta lección? Esta acción no se puede deshacer."
                    class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                    Eliminar
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
