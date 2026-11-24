<?php

namespace App\Livewire\Admin\SedesSalones;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Facultad;
use App\Models\Salon;
use App\Models\Sede;
use App\Models\TipoSalon;
use App\Services\Asistencia\AsignacionSalones;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Módulo del Supervisor «Sedes y salones» (extensión fuera del ERS):
 * catálogo de sedes, tipos de salón y salones (tabla, filtros, formulario,
 * activar/desactivar) y la acción «Asignar salón a las clases futuras de un
 * curso» (AsignacionSalones). Un salón con clases futuras no se borra: se
 * desactiva. Cada alta, cambio, baja o asignación queda en la bitácora
 * (tipo catalogo_salones). Diseño del Supervisor (layouts.app y x-gestion.*).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public const TIPO_BITACORA = 'catalogo_salones';

    /** @var array<string, string> */
    public const PESTANAS = [
        'salones' => 'Salones',
        'sedes' => 'Sedes',
        'facultades' => 'Facultades',
        'tipos' => 'Tipos de salón',
        'asignar' => 'Asignar salones a un curso',
    ];

    /** @var array<string, string> */
    public const ENTIDADES = ['sede' => 'sede', 'facultad' => 'facultad', 'tipo' => 'tipo de salón', 'salon' => 'salón'];

    #[Url(as: 'ver', except: 'salones')]
    public string $pestana = 'salones';

    #[Url(as: 'sede', except: '')]
    public string $filtroSede = '';

    #[Url(as: 'tipo', except: '')]
    public string $filtroTipo = '';

    /** Formulario abierto: sede | tipo | salon (null = cerrado). */
    #[Locked]
    public ?string $formulario = null;

    #[Locked]
    public ?int $editandoId = null;

    public string $sedeNombre = '';

    public string $sedeCodigo = '';

    public string $tipoNombre = '';

    public string $facultadSedeId = '';

    public string $facultadNombre = '';

    public string $facultadCodigo = '';

    public string $salonSedeId = '';

    public string $salonCodigo = '';

    public string $salonNombre = '';

    public string $salonTipoId = '';

    public string $salonCapacidad = '';

    public string $salonEquipamiento = '';

    /** Curso elegido en «Asignar salones a un curso». */
    public string $cursoAsignar = '';

    public function mount(): void
    {
        Gate::authorize('gestionar', Salon::class);

        if (! array_key_exists($this->pestana, self::PESTANAS)) {
            $this->pestana = 'salones';
        }
    }

    public function verPestana(string $pestana): void
    {
        $this->pestana = array_key_exists($pestana, self::PESTANAS) ? $pestana : 'salones';
        $this->cerrarFormulario();
        $this->resetPage();
    }

    public function updatedFiltroSede(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function nuevo(string $entidad): void
    {
        Gate::authorize('gestionar', Salon::class);
        abort_unless(array_key_exists($entidad, self::ENTIDADES), 404);

        $this->limpiarCampos();
        $this->formulario = $entidad;
        $this->editandoId = null;
        $this->salonSedeId = $this->filtroSede;
        $this->facultadSedeId = $this->filtroSede;
        $this->salonTipoId = $this->filtroTipo;
    }

    public function editar(string $entidad, int $id): void
    {
        Gate::authorize('gestionar', Salon::class);
        $registro = $this->registro($entidad, $id);

        $this->limpiarCampos();
        $this->formulario = $entidad;
        $this->editandoId = $registro->id;

        match ($entidad) {
            'sede' => [$this->sedeNombre, $this->sedeCodigo] = [$registro->nombre, $registro->codigo],
            'facultad' => [$this->facultadSedeId, $this->facultadNombre, $this->facultadCodigo] = [(string) $registro->sede_id, $registro->nombre, $registro->codigo],
            'tipo' => $this->tipoNombre = $registro->nombre,
            'salon' => [$this->salonSedeId, $this->salonCodigo, $this->salonNombre, $this->salonTipoId, $this->salonCapacidad, $this->salonEquipamiento]
                = [(string) $registro->sede_id, $registro->codigo, $registro->nombre, (string) $registro->tipo_salon_id, (string) $registro->capacidad, (string) $registro->equipamiento],
        };
    }

    public function cerrarFormulario(): void
    {
        $this->formulario = null;
        $this->editandoId = null;
        $this->limpiarCampos();
    }

    public function guardar(): void
    {
        Gate::authorize('gestionar', Salon::class);
        abort_if($this->formulario === null, 404);

        $entidad = $this->formulario;
        $registro = $this->editandoId !== null ? $this->registro($entidad, $this->editandoId) : null;
        $datos = $this->validarDatos($entidad, $registro);
        $anterior = $registro?->only(array_keys($datos));

        if ($registro === null) {
            $registro = match ($entidad) {
                'sede' => Sede::create($datos),
                'facultad' => Facultad::create($datos),
                'tipo' => TipoSalon::create($datos),
                'salon' => Salon::create($datos),
            };
        } else {
            $registro->update($datos);
        }

        $accion = $anterior === null ? 'Alta' : 'Cambio';
        $this->registrar("{$accion} de {$this->nombreEntidad($entidad)} «{$this->etiqueta($registro)}»", $anterior, $registro->only(array_keys($datos)));

        $this->cerrarFormulario();
        session()->flash('estado', $accion === 'Alta' ? 'Registro creado.' : 'Cambios guardados.');
    }

    public function alternarActivo(string $entidad, int $id): void
    {
        Gate::authorize('gestionar', Salon::class);
        $registro = $this->registro($entidad, $id);
        $campo = in_array($entidad, ['sede', 'facultad'], true) ? 'activa' : 'activo';
        $activo = ! $registro->{$campo};

        $registro->update([$campo => $activo]);
        $this->registrar(($activo ? 'Activación' : 'Desactivación')." de {$this->nombreEntidad($entidad)} «{$this->etiqueta($registro)}»", [$campo => ! $activo], [$campo => $activo]);
        session()->flash('estado', $activo ? 'Quedó activo.' : 'Quedó desactivado: ya no se ofrece para reprogramar.');
    }

    /**
     * Borra solo lo que no está en uso: un salón sin clases futuras, una sede
     * sin salones ni cursos y un tipo sin salones. Lo demás se desactiva.
     */
    public function eliminar(string $entidad, int $id): void
    {
        Gate::authorize('gestionar', Salon::class);
        $registro = $this->registro($entidad, $id);

        $bloqueo = match ($entidad) {
            'salon' => $registro->sesiones()->where('fecha', '>', now())->exists()
                ? 'Este salón tiene clases futuras asignadas: no se puede borrar, desactívalo.' : null,
            'sede' => $registro->salones()->exists() || $registro->cursos()->exists() || $registro->facultades()->exists()
                ? 'Esta sede tiene salones, facultades o cursos: no se puede borrar, desactívala.' : null,
            'facultad' => $registro->cursos()->exists()
                ? 'Esta facultad tiene cursos: no se puede borrar, desactívala.' : null,
            'tipo' => $registro->salones()->exists()
                ? 'Este tipo tiene salones: no se puede borrar, desactívalo.' : null,
        };

        if ($bloqueo !== null) {
            session()->flash('error', $bloqueo);

            return;
        }

        $etiqueta = $this->etiqueta($registro);
        $anterior = $registro->toArray();
        $registro->delete();

        $this->registrar("Baja de {$this->nombreEntidad($entidad)} «{$etiqueta}»", $anterior, null);
        session()->flash('estado', 'Registro eliminado.');
    }

    public function updatedCursoAsignar(): void
    {
        $this->resetErrorBag('asignacion');
    }

    public function asignarSalones(AsignacionSalones $asignacion): void
    {
        $curso = Curso::findOrFail((int) $this->cursoAsignar);
        $resultado = $asignacion->asignar(auth()->user(), $curso);

        $mensaje = "Se asignó salón a {$resultado['asignadas']} clases futuras de {$curso->nombre}.";
        $mensaje .= $resultado['sedes_distintas']
            ? ' Los salones son de sedes distintas: la sede del curso no se cambió.'
            : ' La sede del curso quedó fijada según el salón asignado.';

        session()->flash('estado', $mensaje.' Respaldo: '.$resultado['respaldo'].'.');
    }

    /** @return array<string, mixed> */
    protected function validarDatos(string $entidad, ?Model $registro): array
    {
        $mensajes = [
            'required' => 'Este campo es obligatorio.',
            'unique' => 'Ya existe un registro con ese valor.',
            'max' => 'Admite hasta :max caracteres.',
            'exists' => 'Elige una opción de la lista.',
            'salonCapacidad.integer' => 'La capacidad debe ser un número entero.',
            'salonCapacidad.min' => 'La capacidad debe ser al menos :min.',
            'salonCapacidad.max' => 'La capacidad admite hasta :max plazas.',
        ];

        $this->sedeCodigo = mb_strtoupper(trim($this->sedeCodigo));
        $this->facultadCodigo = mb_strtoupper(trim($this->facultadCodigo));
        $this->salonCodigo = trim($this->salonCodigo);

        return match ($entidad) {
            'sede' => (fn (array $v) => ['nombre' => trim($v['sedeNombre']), 'codigo' => $v['sedeCodigo']])($this->validate([
                'sedeNombre' => ['required', 'string', 'max:100'],
                'sedeCodigo' => ['required', 'string', 'max:10', Rule::unique('sedes', 'codigo')->ignore($registro?->id)],
            ], $mensajes)),
            'facultad' => (fn (array $v) => ['sede_id' => (int) $v['facultadSedeId'], 'nombre' => trim($v['facultadNombre']), 'codigo' => $v['facultadCodigo']])($this->validate([
                'facultadSedeId' => ['required', Rule::exists('sedes', 'id')],
                'facultadNombre' => ['required', 'string', 'max:120'],
                'facultadCodigo' => ['required', 'string', 'max:15', Rule::unique('facultades', 'codigo')->ignore($registro?->id)],
            ], $mensajes)),
            'tipo' => (fn (array $v) => ['nombre' => trim($v['tipoNombre'])])($this->validate([
                'tipoNombre' => ['required', 'string', 'max:80', Rule::unique('tipos_salon', 'nombre')->ignore($registro?->id)],
            ], $mensajes)),
            'salon' => (fn (array $v) => [
                'sede_id' => (int) $v['salonSedeId'],
                'codigo' => $v['salonCodigo'],
                'nombre' => trim($v['salonNombre']),
                'tipo_salon_id' => (int) $v['salonTipoId'],
                'capacidad' => (int) $v['salonCapacidad'],
                'equipamiento' => filled($v['salonEquipamiento']) ? trim($v['salonEquipamiento']) : null,
            ])($this->validate([
                'salonSedeId' => ['required', Rule::exists('sedes', 'id')],
                'salonCodigo' => ['required', 'string', 'max:20', Rule::unique('salones', 'codigo')->ignore($registro?->id)],
                'salonNombre' => ['required', 'string', 'max:100'],
                'salonTipoId' => ['required', Rule::exists('tipos_salon', 'id')],
                'salonCapacidad' => ['required', 'integer', 'min:1', 'max:1000'],
                'salonEquipamiento' => ['nullable', 'string', 'max:1000'],
            ], $mensajes)),
        };
    }

    protected function registro(string $entidad, int $id): Model
    {
        return match ($entidad) {
            'sede' => Sede::findOrFail($id),
            'facultad' => Facultad::findOrFail($id),
            'tipo' => TipoSalon::findOrFail($id),
            'salon' => Salon::findOrFail($id),
            default => abort(404),
        };
    }

    protected function nombreEntidad(string $entidad): string
    {
        return self::ENTIDADES[$entidad];
    }

    protected function etiqueta(Model $registro): string
    {
        return $registro instanceof TipoSalon ? $registro->nombre : "{$registro->codigo} · {$registro->nombre}";
    }

    /**
     * @param  array<string, mixed>|null  $anterior
     * @param  array<string, mixed>|null  $nuevo
     */
    protected function registrar(string $descripcion, ?array $anterior, ?array $nuevo): void
    {
        BitacoraAuditoria::registrarAccionAdmin(auth()->user(), self::TIPO_BITACORA, $descripcion.'.', null, null, $anterior, $nuevo);
    }

    protected function limpiarCampos(): void
    {
        $this->reset(['sedeNombre', 'sedeCodigo', 'facultadSedeId', 'facultadNombre', 'facultadCodigo', 'tipoNombre', 'salonSedeId', 'salonCodigo', 'salonNombre', 'salonTipoId', 'salonCapacidad', 'salonEquipamiento']);
        $this->resetErrorBag();
    }

    public function render(AsignacionSalones $asignacion): View
    {
        $sedes = Sede::query()->withCount(['salones', 'cursos', 'facultades'])->orderBy('nombre')->get();
        $facultades = $this->pestana === 'facultades'
            ? Facultad::query()->with('sede:id,nombre,codigo')->withCount('cursos')
                ->when($this->filtroSede !== '', fn ($consulta) => $consulta->where('sede_id', (int) $this->filtroSede))
                ->orderBy('nombre')->get()
            : collect();
        $tipos = TipoSalon::query()->withCount('salones')->orderBy('nombre')->get();

        $salones = $this->pestana === 'salones'
            ? Salon::query()
                ->with(['sede:id,nombre,codigo', 'tipo:id,nombre'])
                ->withCount(['sesiones as clases_futuras' => fn ($consulta) => $consulta->where('fecha', '>', now())])
                ->when($this->filtroSede !== '', fn ($consulta) => $consulta->where('sede_id', (int) $this->filtroSede))
                ->when($this->filtroTipo !== '', fn ($consulta) => $consulta->where('tipo_salon_id', (int) $this->filtroTipo))
                ->orderBy('codigo')
                ->paginate(20)
            : null;

        $cursoElegido = $this->pestana === 'asignar' && $this->cursoAsignar !== '' ? Curso::with('sede:id,nombre')->find((int) $this->cursoAsignar) : null;

        return view('livewire.admin.sedes-salones.index', [
            'sedes' => $sedes,
            'facultades' => $facultades,
            'tipos' => $tipos,
            'salones' => $salones,
            'opcionesSede' => $sedes->mapWithKeys(fn (Sede $sede) => [(string) $sede->id => "{$sede->nombre} ({$sede->codigo})"])->all(),
            'opcionesTipo' => $tipos->mapWithKeys(fn (TipoSalon $tipo) => [(string) $tipo->id => $tipo->nombre])->all(),
            'opcionesCurso' => $this->pestana === 'asignar'
                ? Curso::query()->where('estado', '!=', 'archivado')->orderBy('nombre')->get(['id', 'nombre', 'codigo'])
                    ->mapWithKeys(fn (Curso $curso) => [(string) $curso->id => trim(($curso->codigo ? $curso->codigo.' · ' : '').$curso->nombre)])->all()
                : [],
            'cursoElegido' => $cursoElegido,
            'vistaPrevia' => $cursoElegido ? $asignacion->vistaPrevia($cursoElegido) : null,
        ])->title('Sedes, salones y facultades — LearnUp');
    }
}
