<?php

namespace App\Livewire\Profesor\Cursos;

use App\Models\Curso;
use App\Models\SesionClase;
use App\Services\Calendario\SeccionesProfesor;
use App\Services\Reportes\CursosProfesor;
use App\Services\Reportes\PanelProfesor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla Cursos del Profesor (diseño "Tu mapa de trabajo"): sus cursos como
 * tarjetas, cada uno con su recorrido por semanas, y arriba lo que vence en
 * los próximos 7 días. Por defecto, los cursos activos; chips para lo que hay
 * que atender (sin contenido la próxima semana, borradores), búsqueda y
 * orden; los archivados se ven aparte. Se muestran por tandas de POR_TANDA.
 */
class ListaCursos extends Component
{
    /** Cursos que se muestran por tanda («Ver los N restantes»). */
    public const POR_TANDA = 12;

    /** Desde cuántos cursos aparecen el buscador y el orden. */
    public const MIN_CONTROLES = 4;

    /**
     * Filtros de la lista: "activos" es el predeterminado (sin chip); los chips
     * "sin_contenido" y "borradores" solo aparecen si hay cursos así, y
     * "archivados" se abre con el enlace del final.
     */
    public const FILTROS = [
        'activos' => 'Cursos activos',
        'sin_contenido' => 'Sin contenido la próxima semana',
        'borradores' => 'Borradores',
        'archivados' => 'Archivados',
    ];

    public const ORDENES = ['pendientes' => 'Más por calificar', 'cierre' => 'Próximo cierre', 'nombre' => 'Nombre'];

    #[Url]
    public string $filtro = 'activos';

    public string $busqueda = '';

    public string $orden = 'pendientes';

    public int $mostrar = self::POR_TANDA;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('profesor'), 403);
        $this->normalizarFiltro();
    }

    public function updated(string $propiedad): void
    {
        if ($propiedad === 'orden' && ! array_key_exists($this->orden, self::ORDENES)) {
            $this->reset('orden');
        }

        $this->normalizarFiltro();

        if (in_array($propiedad, ['filtro', 'busqueda', 'orden'], true)) {
            $this->mostrar = self::POR_TANDA;
        }
    }

    /** Un filtro inexistente (p. ej. de una URL editada) vuelve a "Activos". */
    protected function normalizarFiltro(): void
    {
        if (! array_key_exists($this->filtro, self::FILTROS)) {
            $this->filtro = 'activos';
        }
    }

    public function verMas(): void
    {
        $this->mostrar += self::POR_TANDA;
    }

    public function limpiarFiltros(): void
    {
        $this->reset('filtro', 'busqueda', 'mostrar');
    }

    public function render(CursosProfesor $servicio): View
    {
        $cursos = $servicio->cursos(auth()->user());
        $secciones = new SeccionesProfesor($cursos);
        $resumen = $servicio->resumen($cursos, $cursos);
        $activos = $cursos->filter(fn (Curso $curso) => PanelProfesor::esCursoActivo($curso));

        // Con pocos cursos no hay buscador ni orden; los chips y el enlace de archivados sí (solo si hay cursos así).
        $conControles = $cursos->count() >= self::MIN_CONTROLES;
        $texto = $conControles ? Curso::normalizarNombre($this->busqueda) : '';
        $filtro = $this->filtro;
        $buscados = $texto === ''
            ? $cursos
            : $cursos->filter(fn (Curso $curso) => str_contains($secciones->textoBusqueda($curso->id), $texto));

        $porFiltro = collect(self::FILTROS)->map(fn (string $etiqueta, string $clave) => self::filtrar($buscados, $clave, $resumen));

        $filtrados = $this->ordenar($porFiltro[$filtro], $resumen, $secciones->posiciones());

        return view('livewire.profesor.cursos.lista-cursos', [
            'total' => $activos->count(),
            'totalArchivados' => $cursos->count() - $activos->count(),
            'totalBorradores' => self::filtrar($cursos, 'borradores', $resumen)->count(),
            'tareasSemana' => $servicio->tareasDeLaSemanaActual($filtrados->take($this->mostrar), $resumen),
            'todosLosCursos' => $cursos->keyBy('id'),
            'matriculados' => (int) $cursos->sum('inscripciones_activas_count'),
            'porCalificar' => (int) collect($resumen)->sum('pendientes'),
            'sinContenidoProximaSemana' => self::filtrar($cursos, 'sin_contenido', $resumen)->count(),
            'siguienteClase' => SesionClase::query()
                ->whereIn('curso_id', $activos->modelKeys())
                ->where('fecha', '>=', now())
                ->orderBy('fecha')
                ->first(),
            'filtroActivo' => $filtro,
            'conteos' => $porFiltro->map->count(),
            'cursos' => $filtrados->take($this->mostrar),
            'filtrados' => $filtrados->count(),
            'conBusqueda' => $texto !== '',
            'resumen' => $resumen,
            'conControles' => $conControles,
        ]);
    }

    /**
     * Cursos de un filtro. "Sin contenido la próxima semana" usa el ítem
     * "semana_siguiente" de Para hacer (CursosProfesor::paraHacer), el mismo
     * cálculo del texto de la cabecera.
     *
     * @param  Collection<int, Curso>  $cursos
     * @param  array<int, array<string, mixed>>  $resumen
     * @return Collection<int, Curso>
     */
    public static function filtrar(Collection $cursos, string $filtro, array $resumen): Collection
    {
        if ($filtro === 'archivados') {
            return $cursos->reject(fn (Curso $curso) => PanelProfesor::esCursoActivo($curso))->values();
        }

        $activos = $cursos->filter(fn (Curso $curso) => PanelProfesor::esCursoActivo($curso));

        $elegidos = match ($filtro) {
            'sin_contenido' => $activos->filter(fn (Curso $curso) => collect($resumen[$curso->id]['para_hacer'] ?? [])->contains('tipo', 'semana_siguiente')),
            'borradores' => $activos->where('estado', 'borrador'),
            default => $activos,
        };

        return $elegidos->values();
    }

    /**
     * @param  Collection<int, Curso>  $cursos
     * @param  array<int, array<string, mixed>>  $resumen
     * @param  array<int, int>  $posiciones  orden por nombre y sección (SeccionesProfesor)
     * @return Collection<int, Curso>
     */
    protected function ordenar(Collection $cursos, array $resumen, array $posiciones): Collection
    {
        $porNombre = fn (Curso $a, Curso $b) => ($posiciones[$a->id] ?? 0) <=> ($posiciones[$b->id] ?? 0);

        return $cursos->sort(match ($this->orden) {
            'cierre' => fn (Curso $a, Curso $b) => [$resumen[$a->id]['cierre'] === null, $resumen[$a->id]['cierre']['fecha'] ?? null]
                <=> [$resumen[$b->id]['cierre'] === null, $resumen[$b->id]['cierre']['fecha'] ?? null] ?: $porNombre($a, $b),
            'nombre' => $porNombre,
            default => fn (Curso $a, Curso $b) => $resumen[$b->id]['pendientes'] <=> $resumen[$a->id]['pendientes'] ?: $porNombre($a, $b),
        })->values();
    }
}
