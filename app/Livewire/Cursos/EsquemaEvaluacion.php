<?php

namespace App\Livewire\Cursos;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\EsquemaEvaluacion as EsquemaModel;
use App\Models\NotaRubro;
use App\Models\RubroEvaluacion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Gestión del Esquema de Evaluación de un Curso.
 * ERS RF-54 a RF-65: solo el Supervisor define rubros y pesos.
 */
#[Layout('layouts.app')]
class EsquemaEvaluacion extends Component
{
    public Curso $curso;

    // Modal de rubro
    public bool $mostrarModal = false;
    public ?int $rubroId = null;
    public string $nombre = '';
    public string $semana = '';
    public string $peso = '';
    public int $orden = 1;

    // Modal de nota por estudiante
    public bool $mostrarModalNota = false;
    public ?int $rubroIdNota = null;
    public ?int $estudianteIdNota = null;
    public string $notaInput = '';
    public string $motivoInput = '';

    public function mount(Curso $curso): void
    {
        // Solo el Supervisor puede gestionar el esquema
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        $this->curso = $curso;

        // Crear esquema si no existe aún
        EsquemaModel::firstOrCreate(['curso_id' => $curso->id]);
    }

    // ==========================================
    // CRUD DE RUBROS
    // ==========================================

    public function abrirModalNuevo(): void
    {
        $this->reset(['rubroId', 'nombre', 'semana', 'peso']);
        $this->orden = ($this->curso->esquemaEvaluacion->rubros()->max('orden') ?? 0) + 1;
        $this->mostrarModal = true;
    }

    public function abrirModalEditar(int $rubroId): void
    {
        $rubro = RubroEvaluacion::findOrFail($rubroId);
        $this->rubroId = $rubro->id;
        $this->nombre  = $rubro->nombre;
        $this->semana  = (string) ($rubro->semana ?? '');
        $this->peso    = (string) $rubro->peso;
        $this->orden   = $rubro->orden;
        $this->mostrarModal = true;
    }

    public function guardarRubro(): void
    {
        // ERS RF-62: no editar esquema de curso finalizado
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está finalizado.');

        $this->validate([
            'nombre' => 'required|string|max:100',
            'semana' => 'nullable|integer|min:1|max:52',
            'peso'   => 'required|numeric|min:0.01|max:100',
        ]);

        $esquema = $this->curso->esquemaEvaluacion;

        if ($this->rubroId) {
            $rubro = RubroEvaluacion::findOrFail($this->rubroId);
            $rubro->update([
                'nombre' => $this->nombre,
                'semana' => $this->semana ?: null,
                'peso'   => $this->peso,
                'orden'  => $this->orden,
            ]);
        } else {
            $esquema->rubros()->create([
                'nombre' => $this->nombre,
                'semana' => $this->semana ?: null,
                'peso'   => $this->peso,
                'orden'  => $this->orden,
            ]);
        }

        // ERS RF-56: validar que la suma sea 100%
        $esquema->refresh();
        if (!$esquema->pesosValidos()) {
            session()->flash('advertencia', "Suma de pesos: {$esquema->sumaPesos()}%. Debe ser exactamente 100% para guardar el esquema como válido.");
        }

        // ERS RF-96: auditar modificación del esquema
        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'modificacion_esquema',
            descripcion: ($this->rubroId ? 'Editó' : 'Creó') . " rubro '{$this->nombre}' (peso: {$this->peso}%) en curso: {$this->curso->nombre}",
            curso: $this->curso
        );

        $this->mostrarModal = false;
        $this->reset(['rubroId', 'nombre', 'semana', 'peso']);
        session()->flash('estado', 'Rubro guardado.');
    }

    public function eliminarRubro(int $rubroId): void
    {
        abort_if($this->curso->estaFinalizado(), 403);
        $rubro = RubroEvaluacion::findOrFail($rubroId);
        $nombre = $rubro->nombre;
        $rubro->delete();

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'modificacion_esquema',
            descripcion: "Eliminó rubro '{$nombre}' del esquema de '{$this->curso->nombre}'",
            curso: $this->curso
        );

        session()->flash('estado', 'Rubro eliminado.');
    }

    // ==========================================
    // REGISTRO DE NOTAS POR RUBRO (Profesor)
    // ERS RF-57, RF-65
    // ==========================================

    public function abrirModalNota(int $rubroId, int $estudianteId): void
    {
        abort_unless(
            auth()->user()->hasAnyRole(['supervisor', 'profesor']),
            403
        );
        $nota = NotaRubro::where('rubro_id', $rubroId)
            ->where('estudiante_id', $estudianteId)
            ->first();

        $this->rubroIdNota     = $rubroId;
        $this->estudianteIdNota = $estudianteId;
        $this->notaInput       = $nota ? (string) $nota->nota : '';
        $this->motivoInput     = '';
        $this->mostrarModalNota = true;
    }

    public function guardarNota(): void
    {
        $this->validate([
            'notaInput'    => 'required|numeric|min:0|max:20',
            'motivoInput'  => 'nullable|string|max:500',
        ]);

        $notaAnterior = NotaRubro::where('rubro_id', $this->rubroIdNota)
            ->where('estudiante_id', $this->estudianteIdNota)
            ->value('nota');

        $notaRubro = NotaRubro::updateOrCreate(
            ['rubro_id' => $this->rubroIdNota, 'estudiante_id' => $this->estudianteIdNota],
            ['nota' => $this->notaInput, 'registrado_por' => auth()->id()]
        );

        // ERS RF-65 / RF-95: auditar cambio de nota
        $rubro = RubroEvaluacion::find($this->rubroIdNota);
        $estudiante = User::find($this->estudianteIdNota);

        BitacoraAuditoria::registrarCambioNota(
            ejecutor: auth()->user(),
            curso: $this->curso,
            estudiante: $estudiante,
            entidad: 'nota_rubro',
            entidadId: $notaRubro->id,
            valorAnterior: $notaAnterior,
            valorNuevo: $this->notaInput,
            motivo: $this->motivoInput ?: null
        );

        $this->mostrarModalNota = false;
        $this->reset(['rubroIdNota', 'estudianteIdNota', 'notaInput', 'motivoInput']);
        session()->flash('estado', 'Nota registrada.');
    }

    // ==========================================
    // RENDER
    // ==========================================

    public function render()
    {
        $esquema  = $this->curso->esquemaEvaluacion()->with('rubros')->first();
        $rubros   = $esquema?->rubros ?? collect();
        $sumaPesos = $esquema?->sumaPesos() ?? 0;
        $pesosOk   = abs($sumaPesos - 100) < 0.01;

        $estudiantes = $this->curso->inscripcionesActivas()
            ->with('estudiante')
            ->get()
            ->pluck('estudiante')
            ->filter();

        // Notas agrupadas por [rubro_id][estudiante_id]
        $rubroIds = $rubros->pluck('id');
        $estudianteIds = $estudiantes->pluck('id');

        $notasMap = NotaRubro::whereIn('rubro_id', $rubroIds)
            ->whereIn('estudiante_id', $estudianteIds)
            ->get()
            ->keyBy(fn ($n) => "{$n->rubro_id}_{$n->estudiante_id}");

        // Calcular nota final ponderada por estudiante (ERS RF-58)
        $notaMinima = config('app.nota_minima_aprobatoria', 10.5);

        $filas = $estudiantes->map(function (User $est) use ($rubros, $notasMap, $notaMinima) {
            $sumaTotal  = 0;
            $pesoTotal  = 0;
            $notas      = [];

            foreach ($rubros as $rubro) {
                $key  = "{$rubro->id}_{$est->id}";
                $nota = $notasMap->get($key);
                $notas[$rubro->id] = $nota;
                if ($nota && $nota->nota !== null) {
                    $sumaTotal += $nota->nota * ($rubro->peso / 100);
                    $pesoTotal += $rubro->peso;
                }
            }

            $notaFinal = $pesoTotal > 0 ? round($sumaTotal, 1) : null;

            return [
                'estudiante' => $est,
                'notas'      => $notas,
                'nota_final' => $notaFinal,
                'aprobado'   => $notaFinal !== null && $notaFinal >= $notaMinima,
            ];
        });

        return view('livewire.cursos.esquema-evaluacion', [
            'esquema'   => $esquema,
            'rubros'    => $rubros,
            'sumaPesos' => $sumaPesos,
            'pesosOk'   => $pesosOk,
            'filas'     => $filas,
            'notaMinima' => $notaMinima,
        ])->title("Esquema de Evaluación — {$this->curso->nombre}");
    }
}
