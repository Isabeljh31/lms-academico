<?php

namespace App\Livewire\Docente\Cursos;

use App\Models\Curso;
use App\Services\Calendario\SeccionesProfesor;
use App\Services\Reportes\CursosProfesor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Pantalla Cursos del Profesor (mockups "Cursos" y "CursosLista"): sus
 * cursos en tarjetas o en lista compacta, con filtro por estado, búsqueda y
 * orden. Con más de UMBRAL_LISTA cursos se abre en lista; el interruptor
 * permite cambiarlo. Se muestran por tandas de POR_TANDA.
 */
class ListaCursos extends Component
{
    /** Con más cursos que esto la pantalla se abre en lista. */
    public const UMBRAL_LISTA = 8;

    /** Cursos que se muestran por tanda («Ver los N restantes»). */
    public const POR_TANDA = 12;

    /** Desde cuántos cursos aparecen los filtros, el buscador, el orden y el interruptor de vista. */
    public const MIN_CONTROLES = 4;

    /** Hasta cuántos cursos la tarjeta ocupa todo el ancho, con la línea de semanas. */
    public const MAX_TARJETA_ANCHA = 2;

    public const ESTADOS = ['todos' => 'Todos', 'publicado' => 'Publicados', 'borrador' => 'Borradores', 'despublicado' => 'Despublicados', 'archivado' => 'Archivados'];

    public const ORDENES = ['pendientes' => 'Más por calificar', 'cierre' => 'Próximo cierre', 'nombre' => 'Nombre'];

    public string $estado = 'todos';

    public string $busqueda = '';

    public string $orden = 'pendientes';

    /** cuadricula o lista; null = según la cantidad de cursos. */
    public ?string $vista = null;

    public int $mostrar = self::POR_TANDA;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('profesor'), 403);
    }

    public function updated(string $propiedad): void
    {
        $validos = [
            'estado' => array_keys(self::ESTADOS),
            'orden' => array_keys(self::ORDENES),
            'vista' => ['cuadricula', 'lista'],
        ];

        if (isset($validos[$propiedad]) && ! in_array($this->{$propiedad}, $validos[$propiedad], true)) {
            $this->reset($propiedad);
        }

        if (in_array($propiedad, ['estado', 'busqueda', 'orden'], true)) {
            $this->mostrar = self::POR_TANDA;
        }
    }

    public function verMas(): void
    {
        $this->mostrar += self::POR_TANDA;
    }

    public function limpiarFiltros(): void
    {
        $this->reset('estado', 'busqueda', 'mostrar');
    }

    public function render(CursosProfesor $servicio): View
    {
        $cursos = $servicio->cursos(auth()->user());
        $secciones = new SeccionesProfesor($cursos);
        $resumen = $servicio->resumen($cursos, $cursos);

        // Con pocos cursos no hay controles: tampoco se aplican filtros que el profesor no podría quitar.
        $conControles = $cursos->count() >= self::MIN_CONTROLES;
        $texto = $conControles ? Curso::normalizarNombre($this->busqueda) : '';
        $estado = $conControles ? $this->estado : 'todos';
        $buscados = $texto === ''
            ? $cursos
            : $cursos->filter(fn (Curso $curso) => str_contains($secciones->textoBusqueda($curso->id), $texto));

        $conteos = collect(self::ESTADOS)->keys()->mapWithKeys(fn (string $estado) => [
            $estado => $estado === 'todos' ? $buscados->count() : $buscados->where('estado', $estado)->count(),
        ]);

        $filtrados = $this->ordenar(
            $estado === 'todos' ? $buscados : $buscados->where('estado', $estado),
            $resumen,
            $secciones->posiciones(),
        );

        return view('livewire.docente.cursos.lista-cursos', [
            'total' => $cursos->count(),
            'conteos' => $conteos,
            'cursos' => $filtrados->take($this->mostrar),
            'filtrados' => $filtrados->count(),
            'resumen' => $resumen,
            'vistaActual' => $conControles ? ($this->vista ?? ($cursos->count() > self::UMBRAL_LISTA ? 'lista' : 'cuadricula')) : 'cuadricula',
            'conControles' => $conControles,
            'tarjetaAncha' => $cursos->count() <= self::MAX_TARJETA_ANCHA,
        ]);
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
