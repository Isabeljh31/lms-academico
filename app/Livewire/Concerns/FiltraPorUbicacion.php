<?php

namespace App\Livewire\Concerns;

use App\Models\Curso;
use App\Models\Facultad;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Listados del Supervisor (Profesores y Estudiantes) ordenados por sede y
 * facultad, según los cursos de cada persona:
 *
 * - presencial: primero se elige la sede y luego la facultad; recién ahí se
 *   muestra el listado (quien tiene cursos presenciales de esa facultad).
 * - virtual: cursos virtuales y remotos, con filtro opcional por facultad.
 * - sin_cursos: quien no tiene cursos (para no perder a los recién registrados).
 *
 * Una persona aparece en cada listado que le toque.
 */
trait FiltraPorUbicacion
{
    /** @var array<string, string> */
    public const VISTAS_UBICACION = ['presencial' => 'Presenciales', 'virtual' => 'Virtuales y remotos', 'sin_cursos' => 'Sin cursos'];

    #[Url(as: 'vista', except: 'presencial')]
    public string $vistaUbicacion = 'presencial';

    #[Url(as: 'sede', except: '')]
    public string $sedeUbicacion = '';

    #[Url(as: 'facultad', except: '')]
    public string $facultadUbicacion = '';

    /** Caché de ubicacionActiva() durante una petición (no es estado del componente). */
    protected ?bool $ubicacionActiva = null;

    /** «Sin cursos» filtra a quien no tiene cursos (solo con el catálogo de facultades en uso). */
    protected function soloSinCursos(): bool
    {
        return $this->ubicacionActiva() && $this->vistaUbicacion === 'sin_cursos';
    }

    public function verVistaUbicacion(string $vista): void
    {
        $this->vistaUbicacion = array_key_exists($vista, self::VISTAS_UBICACION) ? $vista : 'presencial';
        $this->sedeUbicacion = '';
        $this->facultadUbicacion = '';
        $this->resetPage();
    }

    public function updatedSedeUbicacion(): void
    {
        $this->facultadUbicacion = '';
        $this->resetPage();
    }

    public function updatedFacultadUbicacion(): void
    {
        $this->resetPage();
    }

    /**
     * El orden por sede y facultad se usa cuando hay facultades registradas;
     * sin catálogo, el listado se muestra completo como siempre.
     */
    public function ubicacionActiva(): bool
    {
        return $this->ubicacionActiva ??= Facultad::where('activa', true)->exists();
    }

    /** En presenciales, falta elegir sede o facultad: aún no se muestra el listado. */
    public function faltaUbicacion(): bool
    {
        return $this->ubicacionActiva() && $this->vistaUbicacion === 'presencial' && ($this->sedeUbicacion === '' || $this->facultadUbicacion === '');
    }

    /**
     * Cursos del listado elegido (subconsulta de ids), o null si no se filtra
     * por cursos («sin cursos» o sin catálogo de facultades).
     *
     * @return Builder<Curso>|null
     */
    protected function cursosDeUbicacion(): ?Builder
    {
        if (! $this->ubicacionActiva()) {
            return null;
        }

        return match ($this->vistaUbicacion) {
            'virtual' => Curso::query()->select('id')->whereIn('modalidad', ['virtual', 'remoto'])
                ->when($this->facultadUbicacion !== '', fn (Builder $consulta) => $consulta->where('facultad_id', (int) $this->facultadUbicacion)),
            'sin_cursos' => null,
            default => Curso::query()->select('id')->where('modalidad', 'presencial')
                ->where('sede_id', (int) $this->sedeUbicacion)->where('facultad_id', (int) $this->facultadUbicacion),
        };
    }

    /**
     * Opciones de los filtros: sedes activas y facultades (de la sede elegida
     * en presenciales; de todas las sedes en virtuales).
     *
     * @return array{sedes: array<string, string>, facultades: array<string, string>}
     */
    protected function opcionesUbicacion(): array
    {
        $facultades = match ($this->vistaUbicacion) {
            'presencial' => $this->sedeUbicacion === '' ? collect() : Facultad::where('sede_id', (int) $this->sedeUbicacion)->where('activa', true)->orderBy('nombre')->get(['id', 'nombre'])
                ->mapWithKeys(fn (Facultad $facultad) => [(string) $facultad->id => $facultad->nombre]),
            'virtual' => Facultad::where('activa', true)->with('sede:id,nombre')->orderBy('nombre')->get(['id', 'nombre', 'sede_id'])
                ->mapWithKeys(fn (Facultad $facultad) => [(string) $facultad->id => "{$facultad->nombre} · {$facultad->sede->nombre}"]),
            default => collect(),
        };

        return [
            'sedes' => Sede::where('activa', true)->orderBy('nombre')->get(['id', 'nombre'])
                ->mapWithKeys(fn (Sede $sede) => [(string) $sede->id => $sede->nombre])->all(),
            'facultades' => $facultades->all(),
        ];
    }
}
