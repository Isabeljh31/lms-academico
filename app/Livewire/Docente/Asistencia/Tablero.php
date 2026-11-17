<?php

namespace App\Livewire\Docente\Asistencia;

use App\Models\Curso;
use App\Services\Reportes\AsistenciaProfesor;
use App\Services\Reportes\CursosProfesor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Asistencia del Profesor (maquetas «Asistencia»): indicadores, pestañas
 * «Por semana» y «Resumen por estudiante». Solo lectura: «Pasar lista»,
 * «Ver lista» y «Eliminar» llaman a las acciones existentes del componente
 * padre (App\Livewire\Asistencia\Index) con $parent, sin duplicarlas.
 *
 * La semana elegida, la pestaña, la búsqueda y el filtro van en la URL
 * (?semana=11) para poder compartirla y que sobreviva al recargar.
 */
class Tablero extends Component
{
    use WithPagination;

    public const PESTANAS = ['semana', 'resumen'];

    public const FILTROS = ['todos', 'riesgo', 'atencion', 'al_dia'];

    public Curso $curso;

    #[Url(as: 'semana')]
    public ?int $semana = null;

    #[Url(as: 'ver', except: 'semana')]
    public string $pestana = 'semana';

    #[Url(as: 'buscar', except: '')]
    public string $buscar = '';

    #[Url(as: 'nivel', except: 'todos')]
    public string $filtro = 'todos';

    #[Locked]
    public int $totalSemanas = 1;

    #[Locked]
    public ?int $semanaActual = null;

    public function mount(Curso $curso): void
    {
        Gate::authorize('gestionar', $curso);

        $this->curso = $curso;
        $this->totalSemanas = max(1, $curso->totalSemanas());
        $this->semanaActual = CursosProfesor::semanaActual($curso, $this->totalSemanas);

        if (! in_array($this->pestana, self::PESTANAS, true)) {
            $this->pestana = 'semana';
        }

        if (! in_array($this->filtro, self::FILTROS, true)) {
            $this->filtro = 'todos';
        }

        if ($this->semana === null || $this->semana < 1 || $this->semana > $this->totalSemanas) {
            $this->semana = $this->semanaPredeterminada();
        }
    }

    /**
     * La semana actual; si el curso aún no empieza, la 1; si ya terminó, la última.
     */
    protected function semanaPredeterminada(): int
    {
        if ($this->semanaActual !== null) {
            return $this->semanaActual;
        }

        $inicio = $this->curso->rangoSemana(1)[0] ?? null;

        return $inicio === null || $inicio->isFuture() ? 1 : $this->totalSemanas;
    }

    public function irASemana(int $semana): void
    {
        $this->semana = max(1, min($this->totalSemanas, $semana));
        $this->resetPage('pagina');
    }

    public function semanaAnterior(): void
    {
        $this->irASemana((int) $this->semana - 1);
    }

    public function semanaSiguiente(): void
    {
        $this->irASemana((int) $this->semana + 1);
    }

    public function irAEstaSemana(): void
    {
        $this->pestana = 'semana';
        $this->irASemana($this->semanaPredeterminada());
    }

    public function verPestana(string $pestana): void
    {
        $this->pestana = in_array($pestana, self::PESTANAS, true) ? $pestana : 'semana';
        $this->resetPage('pagina');
    }

    public function verNivel(string $filtro): void
    {
        $this->pestana = 'resumen';
        $this->filtro = in_array($filtro, self::FILTROS, true) ? $filtro : 'todos';
        $this->resetPage('pagina');
    }

    public function updatedBuscar(): void
    {
        $this->resetPage('pagina');
    }

    public function updatedFiltro(): void
    {
        if (! in_array($this->filtro, self::FILTROS, true)) {
            $this->filtro = 'todos';
        }

        $this->resetPage('pagina');
    }

    /**
     * La página (Index) avisa con este evento al crear o eliminar una clase.
     */
    #[On('asistencia-actualizada')]
    public function refrescar(): void {}

    public function render(AsistenciaProfesor $servicio): View
    {
        $virtual = $this->curso->modalidad === 'virtual';
        $semana = (int) $this->semana;

        if ($virtual) {
            $lecciones = $servicio->leccionesCompletadas($this->curso, $this->pestana === 'semana' ? $semana : null, $this->buscar);

            return view('livewire.docente.asistencia.tablero', [
                'virtual' => true,
                'semanas' => $servicio->semanas($this->curso, collect(), $this->totalSemanas, now()),
                'lecciones' => $lecciones,
            ]);
        }

        $resumen = $servicio->resumen($this->curso, now(), $this->totalSemanas);

        return view('livewire.docente.asistencia.tablero', [
            'virtual' => false,
            'resumen' => $resumen,
            'semanas' => $resumen['semanas'],
            'estudiantes' => $this->pestana === 'resumen'
                ? $servicio->estudiantes($this->curso, $resumen['clases'], $this->buscar, $this->filtro)
                : null,
        ]);
    }
}
