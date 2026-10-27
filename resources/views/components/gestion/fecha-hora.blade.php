@props([
    'id',
    'placeholder' => 'dd/mm/aaaa hh:mm',
])

{{--
    Selector de fecha y hora del Profesor (formularios de quiz y de tarea), en
    lugar del calendario nativo de datetime-local. Usa el mismo Flatpickr del
    sistema (window.cargarFlatpickr, tema en app.css), sin dependencias nuevas.
    El componente x-selector-fecha-hora (Estudiante) no cambia.

    - Livewire recibe el mismo formato que el input nativo (Y-m-d\TH:i), en
      hora local: no hay conversión de zona horaria.
    - Se ve "dd/mm/aaaa hh:mm" (24 horas, en español) y se puede escribir.
    - "Quitar fecha" deja el valor vacío (= sin restricción).
    - Flatpickr cierra con Escape o con clic fuera, y el calendario se dibuja
      sobre la página, así no queda cortado por el scroll del formulario.
    - wire:ignore: Livewire no borra el campo visible que agrega Flatpickr; el
      borde rojo de error va en el contenedor, que sí se vuelve a renderizar.
--}}
@php
    $campo = $attributes->wire('model')->value();
    $invalido = $campo && $errors->has($campo);
    $clase = 'block w-full rounded-lg border-celeste bg-white py-2 pl-3 pr-16 text-sm text-texto shadow-sm focus:border-azul-oscuro focus:ring-azul-oscuro dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-100';
@endphp

<div @class(['rounded-lg', 'ring-1 ring-red-500 dark:ring-red-400' => $invalido])>
    <div wire:ignore class="relative"
        x-data="{
            fp: null,
            tieneValor: false,
            avisar() {
                this.tieneValor = this.$refs.valor.value !== '';
                this.$refs.valor.dispatchEvent(new Event('input', { bubbles: true }));
            },
            quitar() {
                this.fp?.clear();
                this.avisar();
                this.fp?.altInput?.focus();
            },
        }"
        x-init="
            window.cargarFlatpickr().then(() => {
                fp = window.flatpickr($refs.valor, {
                    enableTime: true,
                    time_24hr: true,
                    minuteIncrement: 5,
                    dateFormat: 'Y-m-d\\TH:i',
                    altInput: true,
                    altFormat: 'd/m/Y H:i',
                    altInputClass: @js($clase),
                    allowInput: true,
                    disableMobile: true,
                    locale: window.flatpickr.l10ns.es,
                    onReady: (fechas, texto, instancia) => {
                        tieneValor = $refs.valor.value !== '';
                        instancia.altInput.id = @js($id);
                        instancia.altInput.placeholder = @js($placeholder);
                        instancia.altInput.setAttribute('autocomplete', 'off');
                        @if ($invalido) instancia.altInput.setAttribute('aria-invalid', 'true'); @endif
                    },
                    onChange: () => avisar(),
                    onClose: () => avisar(),
                });
            })">
        {{-- Mientras carga Flatpickr se ve este campo con el mismo estilo; luego queda oculto y guarda el valor. --}}
        <input x-ref="valor" type="text" id="{{ $id }}-valor" placeholder="{{ $placeholder }}" autocomplete="off"
            {{ $attributes->merge(['class' => $clase]) }}>
        <button type="button" x-show="tieneValor" style="display: none" x-on:click="quitar()"
            class="absolute right-8 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-500 hover:bg-celeste hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro dark:text-slate-400 dark:hover:bg-azul-borde-oscuro"
            aria-label="Quitar fecha">
            <x-icono nombre="cerrar" class="h-4 w-4" />
        </button>
        <x-icono nombre="calendario" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-azul-oscuro/70 dark:text-azul-fondo" />
    </div>
</div>
