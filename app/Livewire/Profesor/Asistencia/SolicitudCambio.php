<?php

namespace App\Livewire\Profesor\Asistencia;

use App\Models\Curso;
use App\Models\Salon;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Services\Asistencia\DisponibilidadSalones;
use App\Services\Asistencia\SolicitudesCambioClase;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * «Reprogramar clase» del Profesor (Asistencia, menú ⋮ de una clase): modal
 * para cambiar la fecha y hora (con las horas disponibles y los salones
 * libres de la misma sede, tipo y capacidad) o dictarla en remoto, y «Ver
 * reprogramación» con la decisión del Supervisor y la respuesta a un
 * sustento solicitado. Si se cumplen todas las reglas se aplica sola; si no,
 * va a revisión del Supervisor. Solo la monta la vista del Profesor
 * (docente/asistencia/index); la lógica está en SolicitudesCambioClase y
 * DisponibilidadSalones.
 */
class SolicitudCambio extends Component
{
    use WithFileUploads;

    /** Textos del Profesor para el tipo de reprogramación. @var array<string, string> */
    public const TIPOS = [
        'reprogramar' => 'Cambiar fecha y hora',
        'remoto' => 'Dictarla en remoto',
    ];

    /** Textos del Profesor para la categoría del motivo. @var array<string, string> */
    public const MOTIVOS = [
        'salud' => 'Salud',
        'personal' => 'Personal',
        'institucional' => 'Institucional',
        'otro' => 'Otro',
    ];

    public const AYUDA_MOTIVO = 'No necesitas dar detalles privados ni subir documentos; el supervisor puede pedirlos si hace falta.';

    public const AVISO_SIN_SALON = 'Esta clase no tiene salón asignado: tu propuesta pasará a revisión del supervisor, que confirmará el salón.';

    public const SIN_SALONES_LIBRES = 'No hay salones libres de la misma sede, del mismo tipo y con capacidad suficiente a esa hora. Elige otra hora u otra fecha.';

    /** Clase sin salón cuyo curso tiene sede: se ofrecen los salones de esa sede. */
    public const SIN_SALONES_LIBRES_SEDE = 'No hay salones libres de la sede del curso con capacidad suficiente a esa hora. Elige otra hora u otra fecha.';

    public Curso $curso;

    #[Locked]
    public ?int $sesionId = null;

    /** true = formulario nuevo aunque la clase tenga una reprogramación rechazada o revertida («Reprogramar de nuevo»). */
    #[Locked]
    public bool $nueva = false;

    public bool $abierto = false;

    public string $tipo = 'reprogramar';

    public string $motivo = '';

    public string $detalle = '';

    /** Nueva fecha (Y-m-d) y hora de inicio (H:i), solo al cambiar fecha y hora. */
    public string $fechaPropuesta = '';

    public string $horaPropuesta = '';

    /** Id del salón libre elegido ('' = sin elegir). */
    public string $salonId = '';

    public string $respuestaSustento = '';

    /** @var TemporaryUploadedFile|null */
    public $archivoSustento = null;

    /** Confirmación tras enviar, cancelar o responder (aviso flotante fuera del modal). */
    public ?string $aviso = null;

    public function mount(Curso $curso): void
    {
        Gate::authorize('gestionar', $curso);
        $this->curso = $curso;
    }

    #[On('abrir-solicitud-cambio')]
    public function abrir(int $sesion, bool $nueva = false): void
    {
        Gate::authorize('gestionar', $this->curso);
        $clase = $this->curso->sesionesClase()->findOrFail($sesion);

        $this->resetErrorBag();
        $this->reset(['tipo', 'motivo', 'detalle', 'fechaPropuesta', 'horaPropuesta', 'salonId', 'respuestaSustento', 'archivoSustento', 'aviso']);
        $this->sesionId = $clase->id;
        $this->nueva = $nueva;
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->sesionId = null;
        $this->resetErrorBag();
    }

    /** Al cambiar de fecha se vuelve a elegir hora y salón. */
    public function updatedFechaPropuesta(): void
    {
        $this->reset(['horaPropuesta', 'salonId']);
        $this->resetErrorBag(['horario', 'horaPropuesta', 'salonId']);
    }

    public function updatedHoraPropuesta(): void
    {
        $this->salonId = '';
        $this->resetErrorBag(['horario', 'salonId']);
    }

    public function updatedTipo(): void
    {
        $this->reset(['fechaPropuesta', 'horaPropuesta', 'salonId']);
        $this->resetErrorBag();
    }

    public function enviar(SolicitudesCambioClase $servicio): void
    {
        $clase = $this->clase();
        $reprogramar = $this->tipo === 'reprogramar';
        $presencial = $reprogramar && $this->esPresencial($clase);
        $conSalon = $presencial && app(DisponibilidadSalones::class)->conSalones($clase);

        $this->validate([
            'fechaPropuesta' => $reprogramar ? ['required', 'date_format:Y-m-d'] : ['nullable'],
            'horaPropuesta' => $reprogramar ? ['required', 'date_format:H:i'] : ['nullable'],
            'salonId' => $conSalon ? ['required'] : ['nullable'],
        ], [
            'fechaPropuesta.required' => 'Elige la nueva fecha.',
            'horaPropuesta.required' => 'Elige la hora de inicio.',
            'salonId.required' => 'Elige un salón libre.',
        ]);

        try {
            $solicitud = $servicio->solicitar(auth()->user(), $clase, [
                'tipo' => $this->tipo,
                'motivo' => $this->motivo,
                'detalle' => trim($this->detalle),
                'modalidad' => $reprogramar ? ($presencial ? 'presencial' : 'remoto') : 'remoto',
                'fecha_propuesta' => $reprogramar ? "{$this->fechaPropuesta} {$this->horaPropuesta}" : null,
                'salon_id' => $conSalon && ctype_digit($this->salonId) ? (int) $this->salonId : null,
            ]);
        } catch (ValidationException $error) {
            // Otro tomó el salón entre tanto: se vuelve a elegir con la lista ya actualizada.
            if (array_key_exists('horario', $error->errors())) {
                $this->salonId = '';
            }

            throw $error;
        }

        $this->terminar($solicitud->estado === 'aprobada'
            ? 'Clase reprogramada. Se avisó a los estudiantes.'
            : 'Enviada al supervisor para revisión. Tu clase sigue en su horario hasta que decida.');
    }

    public function cancelarSolicitud(SolicitudesCambioClase $servicio): void
    {
        $pendiente = $this->clase()->solicitudCambioPendiente;
        abort_if($pendiente === null, 404);

        $servicio->cancelar(auth()->user(), $pendiente);
        $this->terminar('Reprogramación cancelada. La clase sigue en su horario.');
    }

    /** Responde una sola vez al sustento que pidió el Supervisor (texto y, opcional, un PDF o una imagen). */
    public function responderSustento(SolicitudesCambioClase $servicio): void
    {
        $this->validate(
            ['archivoSustento' => SubidorArchivos::reglas(SolicitudesCambioClase::EXTENSIONES_SUSTENTO, requerido: false)],
            SubidorArchivos::mensajes('archivoSustento'),
        );

        $pendiente = $this->clase()->solicitudCambioPendiente;
        abort_if($pendiente === null, 404);

        $servicio->responderSustento(auth()->user(), $pendiente, trim($this->respuestaSustento), $this->archivoSustento);
        $this->reset(['respuestaSustento', 'archivoSustento']);
        $this->terminar('Sustento enviado al supervisor. La reprogramación sigue en revisión.');
    }

    /** Clase del curso abierto (protección IDOR). */
    protected function clase(): SesionClase
    {
        abort_if($this->sesionId === null, 404);

        return $this->curso->sesionesClase()->with(['curso', 'salon'])->findOrFail($this->sesionId);
    }

    protected function esPresencial(SesionClase $clase): bool
    {
        return ($clase->modalidad ?? $this->curso->modalidad) === 'presencial';
    }

    protected function terminar(string $mensaje): void
    {
        $this->cerrar();
        $this->aviso = $mensaje;
        // El tablero vuelve a pintar las marcas de reprogramación y el menú ⋮.
        $this->dispatch('asistencia-actualizada');
    }

    /**
     * Horas del día elegido (cada 30 min, 07:00–22:00, sin domingos) con su
     * disponibilidad, y salones libres de la hora elegida. Sin salón actual ni
     * sede del curso, la disponibilidad solo mira a los docentes y no hay
     * lista de salones.
     *
     * @return array{horas: list<array{hora: string, disponible: bool}>, salones: Collection<int, Salon>, domingo: bool}
     */
    protected function disponibilidad(DisponibilidadSalones $disponibilidad, SesionClase $clase, bool $presencial): array
    {
        $vacio = ['horas' => [], 'salones' => collect(), 'domingo' => false];

        try {
            $dia = Carbon::createFromFormat('!Y-m-d', $this->fechaPropuesta);
        } catch (\Throwable) {
            return $vacio;
        }

        if (! $dia) {
            return $vacio;
        }

        $conSalon = $presencial && $disponibilidad->conSalones($clase);
        $horas = $disponibilidad->horas($clase, $dia, $conSalon);
        $salones = collect();

        if ($conSalon && preg_match('/^\d{2}:\d{2}$/', $this->horaPropuesta) === 1) {
            $salones = $disponibilidad->salonesLibres($clase, $dia->copy()->setTimeFromTimeString($this->horaPropuesta), (int) $clase->duracion_minutos);
        }

        return ['horas' => $horas, 'salones' => $salones, 'domingo' => $dia->isSunday()];
    }

    public function render(DisponibilidadSalones $disponibilidad): View
    {
        $clase = $this->abierto && $this->sesionId !== null
            ? $this->curso->sesionesClase()->with(['curso', 'salon.tipo', 'solicitudCambioVigente.decisor:id,name', 'solicitudCambioVigente.salonNuevo'])->find($this->sesionId)
            : null;
        $vigente = $clase?->solicitudCambioVigente;
        // «Reprogramar de nuevo» tras un rechazo o una reversión: se muestra el formulario, no la anterior.
        $solicitud = $this->nueva && in_array($vigente?->estado, ['rechazada', 'revertida'], true) ? null : $vigente;
        $presencial = $clase !== null && $this->esPresencial($clase);
        $formulario = $clase !== null && $solicitud === null && $this->tipo === 'reprogramar'
            ? $this->disponibilidad($disponibilidad, $clase, $presencial)
            : ['horas' => [], 'salones' => collect(), 'domingo' => false];

        return view('livewire.profesor.asistencia.solicitud-cambio', [
            'clase' => $clase,
            'solicitud' => $solicitud,
            'presencial' => $presencial,
            // Sin salón ni sede del curso: no hay salones que ofrecer y la propuesta va a revisión.
            'sinSalon' => $presencial && $clase !== null && ! $disponibilidad->conSalones($clase),
            'salonDeLaClase' => $clase?->salon !== null,
            'horas' => $formulario['horas'],
            'salonesLibres' => $formulario['salones'],
            'esDomingo' => $formulario['domingo'],
            'tipos' => self::TIPOS,
            'motivos' => self::MOTIVOS,
            'modalidades' => SolicitudCambioClase::MODALIDADES,
        ]);
    }
}
