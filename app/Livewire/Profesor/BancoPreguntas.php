<?php

namespace App\Livewire\Profesor;

use App\Models\BancoPregunta;
use App\Models\Curso;
use App\Models\Quiz;
use App\Services\Evaluacion\EstadisticaFallosBanco;
use App\Services\Evaluacion\ImportadorBancoPreguntas;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ERS RF-47: banco de preguntas del curso, organizado por tema, para
 * reutilizarlas en distintos quizzes (ver Quizzes\Formulario).
 *
 * Al agregar una pregunta del banco a un quiz se COPIA (enunciado, opciones,
 * correctas, puntaje y retroalimentación) dentro del quiz: editar o eliminar
 * la pregunta del banco no cambia los quizzes que ya la tienen. "Usada en N
 * quizzes" cuenta los quizzes del curso con una copia del mismo enunciado.
 *
 * Extensión: carga masiva desde Excel/CSV (ImportadorBancoPreguntas) con
 * vista previa antes de guardar y reporte de las filas no importadas.
 *
 * Se usa como página propia (ruta cursos.banco-preguntas) o dentro de la
 * pestaña "Banco de preguntas" de Actividades ($incrustado).
 */
#[Layout('layouts.app')]
class BancoPreguntas extends Component
{
    use WithFileUploads;
    use WithPagination;

    public const POR_PAGINA = 15;

    public Curso $curso;

    #[Locked]
    public bool $incrustado = false;

    /**
     * Unidad en curso (por defecto la primera del curso). Las anteriores se
     * consideran archivadas. La elección vive en el navegador (localStorage,
     * por curso) y la vista la envía al cargar; no se guarda en la base.
     */
    public string $unidadActual = '';

    /** Muestra también las unidades archivadas, agrupadas por unidad. */
    public bool $verArchivadas = false;

    public string $filtroTema = '';

    public string $filtroTipo = '';

    public string $busqueda = '';

    /** @var array<int, int|string> ids marcados para eliminar en lote */
    public array $seleccionadas = [];

    public bool $mostrarConfirmacionLote = false;

    public bool $mostrarModal = false;

    #[Locked]
    public ?int $preguntaId = null;

    public string $unidad = '';

    public string $tema = '';

    public string $pregunta = '';

    public string $tipo = 'opcion_unica';

    /** @var array<int, string> */
    public array $opciones = ['', ''];

    /** @var array<int, int|string> */
    public array $correctas = [0];

    public string $explicacion = '';

    public string $dificultad = '';

    public string $puntaje = '';

    // Importación desde Excel/CSV
    public bool $mostrarImportacion = false;

    public $archivoImportacion = null;

    /** Resumen de la vista previa o del resultado: válidas, errores y duplicadas. */
    #[Locked]
    public ?array $analisis = null;

    #[Locked]
    public bool $importado = false;

    #[Locked]
    public int $importadas = 0;

    public function mount(Curso $curso, bool $incrustado = false): void
    {
        Gate::authorize('gestionar', $curso);
        $this->curso = $curso;
        $this->incrustado = $incrustado;
        $this->unidadActual = BancoPregunta::unidadesDe($curso)[0] ?? '';
    }

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['unidadActual', 'verArchivadas', 'filtroTema', 'filtroTipo', 'busqueda'], true)) {
            $this->resetPage();
            $this->seleccionadas = [];
        }
    }

    /** Solo se acepta una unidad del curso; los temas dependen de ella. */
    public function updatedUnidadActual(): void
    {
        if (! in_array($this->unidadActual, BancoPregunta::unidadesDe($this->curso), true)) {
            $this->unidadActual = BancoPregunta::unidadesDe($this->curso)[0] ?? '';
        }

        $this->filtroTema = '';
    }

    public function updatedVerArchivadas(): void
    {
        $this->filtroTema = '';
    }

    /**
     * Unidades que se listan, en el orden en que se muestran: la actual, las
     * posteriores no (se ven al cambiar la unidad actual) y, con "Ver unidades
     * archivadas", las anteriores de la más reciente a la más antigua.
     *
     * @return list<string>
     */
    protected function unidadesVisibles(): array
    {
        return [
            $this->unidadActual,
            ...($this->verArchivadas ? array_reverse(BancoPregunta::unidadesArchivadas($this->curso, $this->unidadActual)) : []),
        ];
    }

    /** Preguntas visibles: las de las unidades visibles y las sin unidad válida (para reasignarlas). */
    protected function consultaVisible(): HasMany
    {
        $validas = BancoPregunta::unidadesDe($this->curso);

        return $this->curso->bancoPreguntasCurso()->where(fn ($q) => $q
            ->whereIn('unidad', $this->unidadesVisibles())
            ->orWhereNull('unidad')
            ->orWhereNotIn('unidad', $validas));
    }

    public function updatedTipo(string $tipo): void
    {
        if ($tipo === 'verdadero_falso') {
            $this->opciones = ['Verdadero', 'Falso'];
            $this->correctas = [0];
        }

        if ($tipo !== 'opcion_multiple') {
            $this->correctas = [(int) ($this->correctas[0] ?? 0)];
        }
    }

    public function nueva(): void
    {
        Gate::authorize('gestionar', $this->curso);
        $this->resetErrorBag();
        $this->reset(['preguntaId', 'pregunta', 'explicacion', 'dificultad', 'puntaje']);
        $this->unidad = $this->unidadActual;
        $this->tema = $this->filtroTema;
        $this->tipo = 'opcion_unica';
        $this->opciones = ['', ''];
        $this->correctas = [0];
        $this->mostrarModal = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('gestionar', $this->curso);

        $item = $this->curso->bancoPreguntasCurso()->findOrFail($id);
        $this->resetErrorBag();
        $this->preguntaId = $item->id;
        $this->unidad = $item->unidad ?? '';
        $this->tema = $item->tema;
        $this->pregunta = $item->pregunta;
        $this->tipo = $item->tipo;
        $this->opciones = array_values($item->opciones ?? []);
        $this->correctas = array_map('intval', $item->respuestas_correctas ?? [0]);
        $this->explicacion = $item->explicacion ?? '';
        $this->dificultad = $item->dificultad ?? '';
        $this->puntaje = $item->puntaje !== null ? rtrim(rtrim((string) $item->puntaje, '0'), '.') : '';
        $this->mostrarModal = true;
    }

    public function agregarOpcion(): void
    {
        if (count($this->opciones) < 8) {
            $this->opciones[] = '';
        }
    }

    public function quitarOpcion(int $indice): void
    {
        if (count($this->opciones) <= 2) {
            return;
        }

        unset($this->opciones[$indice]);
        $this->opciones = array_values($this->opciones);
        $this->correctas = array_values(array_filter(
            array_map(fn ($c) => (int) $c > $indice ? (int) $c - 1 : (int) $c, array_filter($this->correctas, fn ($c) => (int) $c !== $indice)),
            fn ($c) => $c < count($this->opciones)
        )) ?: [0];
    }

    public function guardar(): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->correctas = array_values(array_unique(array_map('intval', (array) $this->correctas)));
        $this->puntaje = str_replace(',', '.', trim($this->puntaje));

        $this->validate([
            'unidad' => ['required', Rule::in(BancoPregunta::unidadesDe($this->curso))],
            'tema' => ['required', 'string', 'max:150'],
            'pregunta' => ['required', 'string', 'max:1000'],
            'tipo' => ['required', Rule::in(array_keys(Quiz::TIPOS_PREGUNTA))],
            'opciones' => ['required', 'array', 'min:2', 'max:8'],
            'opciones.*' => ['required', 'string', 'max:255'],
            'correctas' => ['required', 'array', 'min:1', $this->tipo === 'opcion_multiple' ? 'max:8' : 'max:1'],
            'correctas.*' => ['integer', 'min:0', 'max:'.(count($this->opciones) - 1)],
            'explicacion' => ['nullable', 'string', 'max:1000'],
            'dificultad' => ['nullable', Rule::in(['baja', 'media', 'alta'])],
            'puntaje' => ['nullable', 'numeric', 'min:0.5', 'max:100'],
        ], [
            'unidad.required' => 'Elige la unidad.',
            'unidad.in' => 'La unidad debe ser una de las del curso: '.implode(', ', BancoPregunta::unidadesDe($this->curso)).'.',
            'opciones.*.required' => 'Completa el texto de todas las opciones.',
            'correctas.required' => 'Marca al menos una respuesta correcta.',
            'correctas.max' => 'Este tipo de pregunta admite una sola respuesta correcta.',
            'puntaje.numeric' => 'El puntaje debe ser un número.',
            'puntaje.min' => 'El puntaje mínimo es 0.5.',
            'puntaje.max' => 'El puntaje máximo es 100.',
        ]);

        $datos = [
            'unidad' => $this->unidad,
            'tema' => trim($this->tema),
            'pregunta' => $this->pregunta,
            'tipo' => $this->tipo,
            'opciones' => array_values($this->opciones),
            'respuestas_correctas' => $this->correctas,
            'explicacion' => $this->explicacion ?: null,
            'dificultad' => $this->dificultad ?: null,
            'puntaje' => $this->puntaje !== '' ? $this->puntaje : null,
        ];

        if ($this->preguntaId) {
            $this->curso->bancoPreguntasCurso()->findOrFail($this->preguntaId)->update($datos);
        } else {
            $this->curso->bancoPreguntasCurso()->create([...$datos, 'creado_por' => auth()->id()]);
        }

        $this->mostrarModal = false;
        session()->flash('estado', 'Pregunta guardada en el banco.');
    }

    public function eliminar(int $id): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->curso->bancoPreguntasCurso()->findOrFail($id)->delete();
        $this->seleccionadas = array_values(array_diff(array_map('intval', $this->seleccionadas), [$id]));
        $this->volverSiLaPaginaQuedoVacia();
        session()->flash('estado', 'Pregunta eliminada del banco. Los quizzes que ya la usan no cambian.');
    }

    /**
     * "Seleccionar esta página": marca o desmarca SOLO las preguntas que se
     * ven en la página actual, calculadas en el servidor con los mismos
     * filtros que el listado (no con ids guardados en el navegador).
     */
    public function alternarSeleccionPagina(): void
    {
        $pagina = $this->idsPagina();
        $marcadas = array_map('intval', $this->seleccionadas);

        $this->seleccionadas = array_diff($pagina, $marcadas) === []
            ? array_values(array_diff($marcadas, $pagina))
            : array_values(array_unique([...$marcadas, ...$pagina]));
    }

    public function confirmarEliminarSeleccionadas(): void
    {
        Gate::authorize('gestionar', $this->curso);
        $this->seleccionadas = $this->seleccionVisible();
        $this->mostrarConfirmacionLote = $this->seleccionadas !== [];
    }

    /**
     * Elimina en lote las preguntas marcadas: solo las del curso que además
     * se ven en la página actual (lo que el profesor tiene a la vista).
     */
    public function eliminarSeleccionadas(): void
    {
        Gate::authorize('gestionar', $this->curso);

        $ids = $this->seleccionVisible();
        $eliminadas = $ids === [] ? 0 : $this->curso->bancoPreguntasCurso()->whereIn('id', $ids)->delete();

        $this->seleccionadas = [];
        $this->mostrarConfirmacionLote = false;
        $this->volverSiLaPaginaQuedoVacia();
        session()->flash('estado', $eliminadas === 1
            ? 'Se eliminó 1 pregunta del banco. Los quizzes que ya la usan no cambian.'
            : "Se eliminaron {$eliminadas} preguntas del banco. Los quizzes que ya las usan no cambian.");
    }

    /** La selección cambia de sentido al cambiar de página: se limpia. */
    public function updatingPaginators(): void
    {
        $this->seleccionadas = [];
        $this->mostrarConfirmacionLote = false;
    }

    /**
     * @return list<int>
     */
    protected function idsPagina(): array
    {
        return $this->consultaListado()->forPage($this->getPage(), self::POR_PAGINA)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Ids marcados que siguen a la vista (del curso y de la página actual).
     *
     * @return list<int>
     */
    protected function seleccionVisible(): array
    {
        return array_values(array_intersect(array_map('intval', $this->seleccionadas), $this->idsPagina()));
    }

    /** Tras borrar, si la página quedó vacía se vuelve a la anterior. */
    protected function volverSiLaPaginaQuedoVacia(): void
    {
        if ($this->getPage() > 1 && $this->idsPagina() === []) {
            $this->previousPage();
        }
    }

    /** Listado del banco con los filtros actuales y el orden de los grupos de unidad. */
    protected function consultaListado(): HasMany
    {
        $visibles = $this->unidadesVisibles();
        // Grupos en el orden de unidadesVisibles(); las sin unidad válida van al final.
        $ordenUnidad = 'case '.implode(' ', array_map(fn () => 'when unidad = ? then ?', $visibles)).' else ? end';
        $enlaces = [...array_merge(...array_map(fn ($u, $i) => [$u, $i], $visibles, array_keys($visibles))), count($visibles)];

        return $this->consultaVisible()
            ->when($this->filtroTema, fn ($q) => $q->where('tema', $this->filtroTema))
            ->when($this->filtroTipo, fn ($q) => $q->where('tipo', $this->filtroTipo))
            ->when($this->busqueda, fn ($q) => $q->where('pregunta', 'like', "%{$this->busqueda}%"))
            ->orderByRaw($ordenUnidad, $enlaces)
            ->orderBy('tema')
            ->orderByDesc('id');
    }

    // ==========================================
    // IMPORTACIÓN DESDE EXCEL / CSV
    // ==========================================

    public function abrirImportacion(): void
    {
        Gate::authorize('gestionar', $this->curso);
        $this->reiniciarImportacion();
        $this->mostrarImportacion = true;
    }

    public function cerrarImportacion(): void
    {
        $this->reiniciarImportacion();
        $this->mostrarImportacion = false;
    }

    public function reiniciarImportacion(): void
    {
        $this->resetErrorBag('archivoImportacion');
        $this->reset(['archivoImportacion', 'analisis', 'importado', 'importadas']);
    }

    public function descargarPlantilla(ImportadorBancoPreguntas $importador): StreamedResponse
    {
        Gate::authorize('gestionar', $this->curso);

        return $importador->plantilla()->descargar('plantilla-banco-preguntas.xlsx');
    }

    /** Vista previa: analiza el archivo apenas se sube, sin guardar nada. */
    public function updatedArchivoImportacion(ImportadorBancoPreguntas $importador): void
    {
        Gate::authorize('gestionar', $this->curso);
        $this->reset(['analisis', 'importado', 'importadas']);
        $analisis = $this->analizarArchivo($importador);
        $this->analisis = $analisis ? $this->resumen($analisis) : null;
    }

    /** Vuelve a leer el archivo (no se confía en la vista previa) y guarda solo las filas válidas. */
    public function importar(ImportadorBancoPreguntas $importador): void
    {
        Gate::authorize('gestionar', $this->curso);

        $analisis = $this->analizarArchivo($importador);

        if ($analisis === null) {
            return;
        }

        DB::transaction(function () use ($analisis) {
            foreach ($analisis['validas'] as $fila) {
                $this->curso->bancoPreguntasCurso()->create([
                    ...collect($fila)->except('fila')->all(),
                    'creado_por' => auth()->id(),
                ]);
            }
        });

        $this->importadas = count($analisis['validas']);
        $this->analisis = $this->resumen($analisis);
        $this->importado = true;
        $this->archivoImportacion = null;
        $this->resetPage();
    }

    public function descargarReporte(ImportadorBancoPreguntas $importador): ?StreamedResponse
    {
        Gate::authorize('gestionar', $this->curso);

        if (! $this->analisis) {
            return null;
        }

        return $importador->reporte($this->analisis['errores'], $this->analisis['duplicadas'])
            ->descargar('reporte-importacion-banco.xlsx');
    }

    /**
     * @return array{validas: list<array<string, mixed>>, errores: list<array{fila: int, enunciado: string, motivo: string}>, duplicadas: list<array{fila: int, enunciado: string, motivo: string}>}|null
     */
    protected function analizarArchivo(ImportadorBancoPreguntas $importador): ?array
    {
        $this->validate(
            ['archivoImportacion' => ['required', 'file', 'extensions:csv,xlsx', 'max:'.ImportadorBancoPreguntas::MAX_KB]],
            [
                'archivoImportacion.required' => 'Selecciona un archivo de Excel (.xlsx) o CSV.',
                'archivoImportacion.extensions' => 'El archivo debe ser un libro de Excel (.xlsx) o un .csv.',
                'archivoImportacion.max' => 'El archivo no debe superar 2 MB.',
            ]
        );

        try {
            return $importador->analizar(
                $this->archivoImportacion->getRealPath(),
                $this->archivoImportacion->getClientOriginalExtension(),
                $this->curso,
            );
        } catch (RuntimeException $e) {
            $this->addError('archivoImportacion', $e->getMessage());

            return null;
        }
    }

    /**
     * Lo que se muestra de un análisis: conteo y muestra de las válidas, y el
     * detalle de las filas no importables (sin guardar las 500 filas en el estado).
     *
     * @param  array{validas: list<array<string, mixed>>, errores: list<array<string, mixed>>, duplicadas: list<array<string, mixed>>}  $analisis
     * @return array{validas: int, muestra: list<array<string, mixed>>, errores: list<array<string, mixed>>, duplicadas: list<array<string, mixed>>}
     */
    protected function resumen(array $analisis): array
    {
        return [
            'validas' => count($analisis['validas']),
            'muestra' => collect($analisis['validas'])->take(5)
                ->map(fn ($fila) => collect($fila)->only(['fila', 'unidad', 'tema', 'pregunta', 'tipo'])->all())->values()->all(),
            'errores' => $analisis['errores'],
            'duplicadas' => $analisis['duplicadas'],
        ];
    }

    /**
     * Cantidad de quizzes del curso que tienen una copia de cada enunciado
     * (una sola consulta para toda la página).
     *
     * @return array<string, int> enunciado normalizado => quizzes
     */
    protected function usosEnQuizzes(): array
    {
        $usos = [];

        foreach ($this->curso->quizzes()->get(['id', 'preguntas']) as $quiz) {
            $enunciados = collect($quiz->preguntas ?? [])
                ->map(fn ($p) => BancoPregunta::claveEnunciado((string) ($p['enunciado'] ?? '')))
                ->unique();

            foreach ($enunciados as $clave) {
                $usos[$clave] = ($usos[$clave] ?? 0) + 1;
            }
        }

        return $usos;
    }

    public function render()
    {
        $validas = BancoPregunta::unidadesDe($this->curso);
        $preguntas = $this->consultaListado()->paginate(self::POR_PAGINA);
        $idsPagina = $preguntas->pluck('id')->all();

        $usos = $this->usosEnQuizzes();
        $usosDe = fn (?string $enunciado) => $usos[BancoPregunta::claveEnunciado((string) $enunciado)] ?? 0;
        $conteo = $this->curso->bancoPreguntasCurso()->selectRaw('unidad, count(*) as total')->groupBy('unidad')->pluck('total', 'unidad');

        return view('livewire.profesor.banco-preguntas', [
            'preguntas' => $preguntas,
            // Marcada solo si están seleccionadas todas las preguntas que se ven en esta página.
            'paginaSeleccionada' => $idsPagina !== [] && array_diff($idsPagina, array_map('intval', $this->seleccionadas)) === [],
            'totalBanco' => $conteo->sum(),
            'totalVisible' => $this->consultaVisible()->count(),
            // Opciones del selector "Unidad actual": [unidad => "T1 (12)"], en el orden del curso.
            'opcionesUnidad' => collect($validas)->mapWithKeys(fn ($u) => [$u => $u.' ('.($conteo[$u] ?? 0).')'])->all(),
            'archivadas' => BancoPregunta::unidadesArchivadas($this->curso, $this->unidadActual),
            'unidadesValidas' => $validas,
            'sinUnidad' => $conteo->reject(fn ($total, $unidad) => in_array($unidad, $validas, true))->sum(),
            'temas' => $this->consultaVisible()->distinct()->orderBy('tema')->pluck('tema'),
            'temasModal' => $this->curso->bancoPreguntasCurso()
                ->when($this->unidad !== '', fn ($q) => $q->where('unidad', $this->unidad))
                ->distinct()->orderBy('tema')->pluck('tema'),
            'tipos' => Quiz::TIPOS_PREGUNTA,
            'usosDe' => $usosDe,
            'fallos' => app(EstadisticaFallosBanco::class)->paraCurso($this->curso),
            'usosEditada' => $this->preguntaId ? $usosDe($this->curso->bancoPreguntasCurso()->whereKey($this->preguntaId)->value('pregunta')) : 0,
            'maxFilas' => ImportadorBancoPreguntas::MAX_FILAS,
        ])->title("Banco de preguntas — {$this->curso->nombre}");
    }
}
