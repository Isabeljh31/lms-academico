<?php

namespace App\Livewire\Admin\SolicitudesCambioClase;

use App\Enums\Rol;
use App\Models\Salon;
use App\Models\SolicitudCambioClase;
use App\Services\Asistencia\DisponibilidadSalones;
use App\Services\Asistencia\SolicitudesCambioClase;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pantalla del Supervisor «Reprogramaciones» (extensión fuera del ERS;
 * evolución de la bandeja de solicitudes de cambio de clase). Pestañas En
 * revisión y Registro (aplicadas automáticamente y resueltas). Acciones:
 * aprobar eligiendo el salón de la lista real con disponibilidad (o, de
 * forma explícita, «Sin salón asignado» con el aula en texto), rechazar con
 * comentario, pedir sustento, ver y descargar el sustento y revertir. Diseño
 * del Supervisor (layouts.app y x-gestion.*). La lógica está en
 * SolicitudesCambioClase y DisponibilidadSalones.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    /** @var array<string, string> */
    public const PESTANAS = ['revision' => 'En revisión', 'registro' => 'Registro'];

    /** Opción explícita del desplegable de salón: aula escrita a mano, sin salón del catálogo. */
    public const SIN_SALON = 'sin';

    /** Aviso cuando la clase sigue en su hora original y esta ya pasó (CalculadoraAsistencia la cuenta). */
    public const AVISO_HORA_PASADA = 'La hora original ya pasó: mientras siga pendiente, esa clase cuenta como ausencia para todos.';

    #[Url(as: 'ver', except: 'revision')]
    public string $pestana = 'revision';

    #[Locked]
    public ?int $seleccionId = null;

    public string $fecha = '';

    public string $horaInicio = '';

    public string $horaFin = '';

    public string $modalidad = 'presencial';

    /** Id del salón elegido, SIN_SALON o '' (sin elegir). */
    public string $salonElegido = '';

    /** Aula en texto: solo con «Sin salón asignado». */
    public string $aula = '';

    public string $comentario = '';

    public string $pedidoSustento = '';

    public string $comentarioReversion = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole(Rol::Supervisor->value), 403);

        if (! array_key_exists($this->pestana, self::PESTANAS)) {
            $this->pestana = 'revision';
        }
    }

    public function verPestana(string $pestana): void
    {
        $this->pestana = array_key_exists($pestana, self::PESTANAS) ? $pestana : 'revision';
        $this->cerrarDetalle();
        $this->resetPage();
    }

    /**
     * Abre el detalle con la propuesta del Profesor como punto de partida:
     * su fecha (o la original), la misma duración, la modalidad pedida y el
     * salón que propuso (o el actual de la clase).
     */
    public function seleccionar(int $id): void
    {
        Gate::authorize('decidir', SolicitudCambioClase::class);
        $solicitud = SolicitudCambioClase::with('sesion')->findOrFail($id);

        $inicio = $solicitud->fecha_propuesta ?? $solicitud->fecha_original;
        $this->resetErrorBag();
        $this->seleccionId = $solicitud->id;
        $this->fecha = $inicio->format('Y-m-d');
        $this->horaInicio = $inicio->format('H:i');
        $this->horaFin = $inicio->copy()->addMinutes($solicitud->duracion_original)->format('H:i');
        $this->modalidad = $solicitud->modalidad_solicitada;
        $salon = $solicitud->salon_nuevo_id ?? $solicitud->sesion->salon_id;
        $this->salonElegido = $salon !== null ? (string) $salon : '';
        $this->aula = (string) (SolicitudesCambioClase::aulaDe($solicitud->sesion) ?? '');
        $this->reset(['comentario', 'pedidoSustento', 'comentarioReversion']);
    }

    public function cerrarDetalle(): void
    {
        $this->reset(['seleccionId', 'fecha', 'horaInicio', 'horaFin', 'modalidad', 'salonElegido', 'aula', 'comentario', 'pedidoSustento', 'comentarioReversion']);
        $this->resetErrorBag();
    }

    public function aprobar(SolicitudesCambioClase $servicio): void
    {
        $presencial = $this->modalidad === 'presencial';

        if ($presencial && $this->salonElegido === '') {
            $this->addError('salonElegido', 'Elige un salón de la lista o «Sin salón asignado».');

            return;
        }

        $sinSalon = $this->salonElegido === self::SIN_SALON;
        $solicitud = $servicio->aprobar(auth()->user(), $this->seleccionada(), [
            'fecha' => $this->fecha,
            'hora_inicio' => $this->horaInicio,
            'hora_fin' => $this->horaFin,
            'modalidad' => $this->modalidad,
            'salon_id' => $presencial && ! $sinSalon ? (int) $this->salonElegido : null,
            'aula' => $presencial && $sinSalon ? $this->aula : null,
        ]);

        $this->cerrarDetalle();
        session()->flash('estado', "Reprogramación aprobada: la clase pasó al {$solicitud->fecha_nueva->format('d/m/Y H:i')}. Se avisó al profesor y a los estudiantes.");
    }

    public function rechazar(SolicitudesCambioClase $servicio): void
    {
        $servicio->rechazar(auth()->user(), $this->seleccionada(), $this->comentario);

        $this->cerrarDetalle();
        session()->flash('estado', 'Reprogramación rechazada. Se avisó al profesor; la clase sigue en su horario.');
    }

    public function pedirSustento(SolicitudesCambioClase $servicio): void
    {
        $servicio->pedirSustento(auth()->user(), $this->seleccionada(), $this->pedidoSustento);

        $this->pedidoSustento = '';
        session()->flash('estado', 'Sustento solicitado. Se avisó al profesor; la reprogramación sigue en revisión.');
    }

    public function revertir(SolicitudesCambioClase $servicio): void
    {
        $servicio->revertir(auth()->user(), $this->seleccionada(), $this->comentarioReversion);

        $this->cerrarDetalle();
        session()->flash('estado', 'Reprogramación revertida: la clase volvió a su horario original. Se avisó al profesor y a los estudiantes.');
    }

    protected function seleccionada(): SolicitudCambioClase
    {
        Gate::authorize('decidir', SolicitudCambioClase::class);
        abort_if($this->seleccionId === null, 404);

        return SolicitudCambioClase::findOrFail($this->seleccionId);
    }

    /** Inicio y duración de la decisión en curso, o null si la fecha u horas no son válidas. */
    protected function horario(): ?array
    {
        try {
            $inicio = Carbon::createFromFormat('Y-m-d H:i', "{$this->fecha} {$this->horaInicio}");
            $fin = Carbon::createFromFormat('Y-m-d H:i', "{$this->fecha} {$this->horaFin}");
        } catch (\Throwable) {
            return null;
        }

        return $inicio && $fin && $fin->gt($inicio) ? [$inicio, (int) $inicio->diffInMinutes($fin)] : null;
    }

    /**
     * Avisos no bloqueantes de la decisión en curso y el de la hora original
     * ya pasada.
     *
     * @return list<string>
     */
    protected function avisos(SolicitudesCambioClase $servicio, SolicitudCambioClase $solicitud, ?array $horario, ?Salon $salon): array
    {
        $avisos = $solicitud->sesion->fecha->isPast() ? [self::AVISO_HORA_PASADA] : [];

        if ($horario === null) {
            return $avisos;
        }

        $presencial = $this->modalidad === 'presencial';
        $aula = $presencial && $this->salonElegido === self::SIN_SALON ? $this->aula : null;

        return [...$avisos, ...$servicio->avisos($solicitud, $horario[0], $horario[1], $aula, $presencial ? $salon : null, $this->modalidad)];
    }

    public function render(SolicitudesCambioClase $servicio, DisponibilidadSalones $disponibilidad): View
    {
        $relaciones = ['curso:id,nombre,codigo,fecha_inicio,semanas_duracion,sede_id', 'solicitante:id,name', 'decisor:id,name', 'revertidor:id,name', 'sesion.salon', 'salonNuevo', 'salonOriginal'];
        $enRevision = $this->pestana === 'revision';

        $solicitudes = SolicitudCambioClase::with($relaciones)
            ->when($enRevision,
                fn ($consulta) => $consulta->where('estado', 'pendiente')->orderBy('created_at')->orderBy('id'),
                fn ($consulta) => $consulta->where('estado', '!=', 'pendiente')->orderByDesc('updated_at')->orderByDesc('id'))
            ->paginate(15);

        $seleccion = $this->seleccionId !== null ? SolicitudCambioClase::with($relaciones)->find($this->seleccionId) : null;
        $pendiente = $seleccion?->estaPendiente() ?? false;
        $horario = $pendiente ? $this->horario() : null;

        /** @var Collection<int, Salon> $libres */
        $libres = $pendiente && $horario !== null && $this->modalidad === 'presencial'
            ? $disponibilidad->salonesParaSupervisor($seleccion->sesion, $horario[0], $horario[1])
            : collect();
        $salon = ctype_digit($this->salonElegido) ? ($libres->firstWhere('id', (int) $this->salonElegido) ?? Salon::find((int) $this->salonElegido)) : null;
        // Aviso (no bloqueo): salón de otro tipo que el que necesita la clase o con menos plazas que matriculados.
        $necesario = $pendiente ? $seleccion->sesion->salon?->tipo : null;
        $matriculados = $pendiente ? $disponibilidad->matriculados($seleccion->curso) : 0;
        $advertencias = fn (Salon $opcion): array => array_values(array_filter([
            $necesario !== null && $opcion->tipo_salon_id !== $necesario->id ? "Otro tipo (la clase usa {$necesario->nombre})" : null,
            $opcion->capacidad < $matriculados ? "Menos plazas que los {$matriculados} matriculados" : null,
        ]));
        $avisoSalon = $salon !== null && $this->modalidad === 'presencial' && $advertencias($salon) !== []
            ? ["Salón {$salon->codigo}: ".implode('; ', $advertencias($salon)).'.']
            : [];

        return view('livewire.admin.solicitudes-cambio-clase.index', [
            'solicitudes' => $solicitudes,
            'totalPendientes' => SolicitudCambioClase::where('estado', 'pendiente')->count(),
            'seleccion' => $seleccion,
            'avisos' => $pendiente ? [...$avisoSalon, ...$this->avisos($servicio, $seleccion, $horario, $salon)] : [],
            'opcionesSalon' => $libres->mapWithKeys(fn (Salon $libre) => [
                (string) $libre->id => implode(' · ', ["{$libre->codigo}", $libre->tipo->nombre, "{$libre->capacidad} plazas", 'Libre', ...array_map(fn (string $texto) => "⚠ {$texto}", $advertencias($libre))]),
            ])->all() + [self::SIN_SALON => 'Sin salón asignado (escribir el aula)'],
            'salonNoLibre' => $salon !== null && ! $libres->contains('id', $salon->id) ? $salon : null,
            'horarioValido' => $horario !== null,
            'tipos' => SolicitudCambioClase::TIPOS,
            'motivos' => SolicitudCambioClase::MOTIVOS,
            'modalidades' => SolicitudCambioClase::MODALIDADES,
            'estados' => SolicitudCambioClase::ESTADOS,
            'origenes' => SolicitudCambioClase::ORIGENES,
        ])->title('Reprogramaciones — LearnUp');
    }
}
