<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                Mensajería
                @if($noLeidos > 0)
                <span class="inline-flex items-center justify-center rounded-full bg-terracota-base text-white text-xs font-bold w-5 h-5">
                    {{ $noLeidos }}
                </span>
                @endif
            </h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Comunicación privada entre estudiantes y profesores</p>
        </div>

        <div class="flex gap-2">
            <div class="flex rounded-lg border border-stone-200 dark:border-stone-700 overflow-hidden text-sm">
                <button wire:click="$set('vista','recibidos')"
                    class="px-4 py-2 transition {{ $vista === 'recibidos' ? 'bg-menta-oscuro text-white' : 'bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700' }}">
                    Recibidos @if($noLeidos > 0) <span class="ml-1 text-xs bg-terracota-base text-white rounded-full px-1.5">{{ $noLeidos }}</span> @endif
                </button>
                <button wire:click="$set('vista','enviados')"
                    class="px-4 py-2 transition border-l border-stone-200 dark:border-stone-700 {{ $vista === 'enviados' ? 'bg-menta-oscuro text-white' : 'bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700' }}">
                    Enviados
                </button>
            </div>
            <button wire:click="$set('vista','nuevo')"
                class="rounded-lg bg-terracota-base px-4 py-2 text-sm font-semibold text-white hover:bg-terracota-oscuro transition">
                + Nuevo
            </button>
        </div>
    </div>

    @if(session('estado'))
    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-700 dark:text-green-400">
        {{ session('estado') }}
    </div>
    @endif

    {{-- ============================== NUEVO MENSAJE ============================== --}}
    @if($vista === 'nuevo')
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300">Nuevo mensaje</h2>

        <div>
            <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Curso destino *</label>
            <select wire:model.live="cursoContextoId"
                class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
                <option value="">-- Primero selecciona el curso --</option>
                @foreach($misCursos as $c)
                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                @endforeach
            </select>
            @error('cursoContextoId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        @if($cursoContextoId)
            @if(auth()->user()->hasRole('profesor'))
            <div class="flex items-center gap-3">
                <input wire:model.live="enviarATodos" type="checkbox" id="enviar-todos" class="rounded text-terracota-base focus:ring-terracota-base">
                <label for="enviar-todos" class="text-sm text-stone-700 dark:text-stone-300">
                    Enviar a todos los estudiantes de este curso (aviso general)
                </label>
            </div>
            @endif

            @if(!$enviarATodos)
            <div>
                <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Destinatario *</label>
                <select wire:model="destinatarioId"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
                    <option value="">-- Seleccionar persona --</option>
                    @foreach($contactos as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->email }})</option>
                    @endforeach
                </select>
                @error('destinatarioId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            @endif
        @endif

        <div>
            <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Asunto *</label>
            <input wire:model="asunto" type="text" placeholder="Consulta sobre..."
                class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
            @error('asunto') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Mensaje *</label>
            <textarea wire:model="cuerpo" rows="5"
                class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-terracota-base"></textarea>
            @error('cuerpo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Adjunto (máx. 10 MB)</label>
            <input wire:model="adjunto" type="file"
                class="w-full text-sm text-stone-500 file:mr-4 file:rounded-lg file:border-0 file:bg-stone-100 dark:file:bg-stone-700 file:px-3 file:py-1.5 file:text-sm file:text-stone-700 dark:file:text-stone-300">
            @error('adjunto') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <button wire:click="$set('vista','recibidos')"
                class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                Cancelar
            </button>
            <button wire:click="enviar"
                class="rounded-lg bg-terracota-base px-4 py-2 text-sm font-semibold text-white hover:bg-terracota-oscuro transition">
                Enviar mensaje
            </button>
        </div>
    </div>
    @endif

    {{-- ============================== RECIBIDOS / ENVIADOS ============================== --}}
    @if(in_array($vista, ['recibidos', 'enviados']))
    @php $mensajes = $vista === 'recibidos' ? $recibidos : $enviados; @endphp

    @if($mensajeAbierto)
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button wire:click="$set('mensajeAbiertoId', null)" class="text-xs text-stone-400 hover:text-stone-700 transition font-medium">Cerrar</button>
                <button wire:click="responder" class="text-xs text-menta-oscuro dark:text-menta-base hover:underline transition font-medium">Responder mensaje</button>
            </div>
            <span class="text-xs text-stone-400">{{ $mensajeAbierto->created_at->diffForHumans() }}</span>
        </div>
        <div class="border-b border-stone-100 dark:border-stone-700 pb-4">
            <h3 class="text-lg font-bold text-stone-800 dark:text-stone-100">{{ $mensajeAbierto->asunto }}</h3>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                De: <strong>{{ $mensajeAbierto->remitente?->name }}</strong>
                → Para: <strong>{{ $mensajeAbierto->destinatario?->name }}</strong>
                @if($mensajeAbierto->curso) · Curso: {{ $mensajeAbierto->curso->nombre }} @endif
            </p>
        </div>
        <div class="text-sm text-stone-700 dark:text-stone-300 whitespace-pre-wrap leading-relaxed">{{ $mensajeAbierto->cuerpo }}</div>
        @if($mensajeAbierto->archivo_url)
        <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($mensajeAbierto->archivo_url) }}" target="_blank" rel="noopener noreferrer"
            class="inline-flex items-center gap-2 text-sm text-menta-oscuro dark:text-menta-base hover:underline">
            Ver adjunto
        </a>
        @endif
    </div>
    @endif

    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
        @if($mensajes->isEmpty())
        <p class="py-12 text-center text-sm text-stone-400 dark:text-stone-500">
            {{ $vista === 'recibidos' ? 'No tienes mensajes recibidos.' : 'No has enviado mensajes aún.' }}
        </p>
        @else
        <ul class="divide-y divide-stone-50 dark:divide-stone-700/50">
            @foreach($mensajes as $m)
            <li wire:key="msg-{{ $m->id }}">
                <button wire:click="abrirMensaje({{ $m->id }})"
                    class="w-full flex items-start gap-4 px-4 py-4 text-left hover:bg-stone-50 dark:hover:bg-stone-700/20 transition
                        {{ ($vista === 'recibidos' && !$m->estaLeido()) ? 'font-semibold' : '' }}">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            @if($vista === 'recibidos' && !$m->estaLeido())
                            <span class="w-2 h-2 rounded-full bg-terracota-base shrink-0"></span>
                            @endif
                            <p class="text-sm text-stone-800 dark:text-stone-200 truncate">
                                {{ $vista === 'recibidos' ? $m->remitente?->name : $m->destinatario?->name }}
                            </p>
                            @if($m->curso) <span class="text-xs text-stone-400 shrink-0">· {{ $m->curso->nombre }}</span> @endif
                        </div>
                        <p class="text-sm truncate mt-0.5 {{ ($vista === 'recibidos' && !$m->estaLeido()) ? 'text-stone-800 dark:text-stone-200' : 'text-stone-500 dark:text-stone-400' }}">
                            {{ $m->asunto }}
                        </p>
                        <p class="text-xs text-stone-400 dark:text-stone-500 truncate mt-0.5">{{ Str::limit($m->cuerpo, 80) }}</p>
                    </div>
                    <span class="text-xs text-stone-400 dark:text-stone-500 shrink-0">{{ $m->created_at->diffForHumans(null, true) }}</span>
                </button>
            </li>
            @endforeach
        </ul>
        <div class="px-4 py-3 border-t border-stone-100 dark:border-stone-700">
            {{ $mensajes->links() }}
        </div>
        @endif
    </div>
    @endif

</div>
