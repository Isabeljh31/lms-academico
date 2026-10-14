<div class="py-8">
    <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Esquema de evaluación" icono="grafico" :descripcion="$curso->nombre"
            :volver="route('cursos.show', $curso)" volver-texto="Volver al curso">
            <x-gestion.boton variante="secundario" icono="lista" :href="route('cursos.libro-calificaciones', $curso)">Libro de calificaciones</x-gestion.boton>
        </x-gestion.encabezado>

        <x-gestion.alertas />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-gestion.indicador etiqueta="Suma de pesos" :valor="number_format($this->sumaPesos, 2).' %'" icono="grafico" :tono="$pesosOk ? 'exito' : 'alerta'"
                :detalle="$pesosOk ? 'Correcto: suma 100 %' : $this->mensajeSuma()" />
            <x-gestion.indicador etiqueta="Nota mínima aprobatoria" :valor="$notaMinima.' / 20'" icono="escudo" tono="cafe" detalle="Configuración general de la plataforma" />
            <x-gestion.indicador etiqueta="Notas registradas" :valor="$notasRegistradas" icono="lista" :detalle="$totalEstudiantes.' estudiantes matriculados'" />
        </div>

        @unless ($this->editable)
            <div role="alert" class="flex items-start gap-3 rounded-xl border border-terracota-base/50 bg-arena-claro px-4 py-3 text-sm text-terracota-oscuro dark:bg-stone-800 dark:text-terracota-fondo">
                <x-icono nombre="archivo" class="h-5 w-5 shrink-0" />
                <p>El curso está finalizado: su esquema de evaluación ya no se puede modificar.</p>
            </div>
        @endunless

        <form wire:submit="guardar">
            <x-gestion.tarjeta titulo="Rubros" descripcion="La nota final de cada estudiante es la suma de la nota de cada rubro (0 a 20) multiplicada por su peso. Las tareas y quizzes no se suman solos." :relleno="false">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="bg-arena-claro/50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                            <tr>
                                <th class="px-4 py-2">Rubro</th>
                                <th class="w-28 px-4 py-2">Semana</th>
                                <th class="w-32 px-4 py-2">Peso (%)</th>
                                @if ($this->editable) <th class="w-16 px-4 py-2"><span class="sr-only">Acciones</span></th> @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($rubros as $i => $rubro)
                                <tr wire:key="rubro-{{ $rubro['id'] ?? 'nuevo-'.$i }}">
                                    <td class="px-4 py-2 align-top">
                                        <label class="sr-only" for="rubro-nombre-{{ $i }}">Nombre del rubro</label>
                                        <x-gestion.control id="rubro-nombre-{{ $i }}" wire:model.blur="rubros.{{ $i }}.nombre" :disabled="! $this->editable" />
                                        @error('rubros.'.$i.'.nombre') <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-2 align-top">
                                        <label class="sr-only" for="rubro-semana-{{ $i }}">Semana</label>
                                        <x-gestion.control id="rubro-semana-{{ $i }}" type="number" min="1" max="{{ $curso->totalSemanas() }}" step="1" wire:model.blur="rubros.{{ $i }}.semana" :disabled="! $this->editable" />
                                        @error('rubros.'.$i.'.semana') <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-2 align-top">
                                        <label class="sr-only" for="rubro-peso-{{ $i }}">Peso</label>
                                        <x-gestion.control id="rubro-peso-{{ $i }}" type="number" step="0.01" min="0.01" max="100" wire:model.live.debounce.300ms="rubros.{{ $i }}.peso" :disabled="! $this->editable" />
                                        @error('rubros.'.$i.'.peso') <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    </td>
                                    @if ($this->editable)
                                        <td class="px-4 py-2 text-right align-top">
                                            <button type="button" wire:click="quitarRubro({{ $i }})" class="rounded p-2 text-stone-400 hover:bg-arena-claro hover:text-terracota-oscuro dark:hover:bg-stone-700"
                                                aria-label="Quitar rubro {{ $rubro['nombre'] }}" wire:confirm="¿Quitar el rubro {{ $rubro['nombre'] }}? El cambio se aplica al guardar.">
                                                <x-icono nombre="papelera" class="h-4 w-4" />
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-arena-medio dark:border-stone-700">
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-stone-700 dark:text-stone-200" colspan="2">Total</td>
                                <td class="px-4 py-3 font-bold {{ $pesosOk ? 'text-exito-oscuro' : 'text-red-600' }}">{{ number_format($this->sumaPesos, 2) }} %</td>
                                @if ($this->editable) <td></td> @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @error('rubros')
                    <p class="flex items-center gap-1 border-t border-arena-medio px-4 py-3 text-sm font-medium text-red-600 dark:border-stone-700"><x-icono nombre="alerta" class="h-4 w-4" /> {{ $message }}</p>
                @enderror
                @if ($this->editable)
                    <div class="flex flex-col gap-2 border-t border-arena-medio px-4 py-3 sm:flex-row sm:justify-between dark:border-stone-700">
                        <x-gestion.boton variante="secundario" icono="mas" wire:click="agregarRubro">Agregar rubro</x-gestion.boton>
                        <x-gestion.boton type="submit" icono="check" :disabled="! $pesosOk">Guardar esquema</x-gestion.boton>
                    </div>
                @endif
            </x-gestion.tarjeta>
        </form>
    </div>
</div>
