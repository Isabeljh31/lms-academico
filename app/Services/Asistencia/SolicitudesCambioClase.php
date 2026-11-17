<?php

namespace App\Services\Asistencia;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Salon;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Models\User;
use App\Notifications\ClaseReprogramada;
use App\Notifications\ReprogramacionRevertida;
use App\Notifications\ReprogramacionSustentoRespondido;
use App\Notifications\ReprogramacionSustentoSolicitado;
use App\Notifications\SolicitudCambioClaseRecibida;
use App\Notifications\SolicitudCambioClaseResuelta;
use App\Services\Multimedia\SubidorArchivos;
use App\Services\Reportes\AsistenciaProfesor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Reprogramación de clases (extensión fuera del ERS; en la base y el código
 * sigue llamándose «solicitud de cambio de clase»). El Profesor del curso
 * pide mover una clase futura sin lista (o dictarla en remoto). Si cumple
 * todas las reglas de DisponibilidadSalones se aplica al instante (origen
 * «automatica»); si no, queda pendiente («En revisión») con la razón y la
 * decide el Supervisor, que también puede pedir sustento y revertir una
 * reprogramación aplicada.
 *
 * Al aplicar se mueve la MISMA fila de sesiones_clase (conserva su número y
 * su historial) y se guarda en la solicitud la clase tal como estaba, para
 * poder revertirla. Dentro de la transacción se bloquea siempre en este
 * orden: docentes del curso, salón, clase y solicitud. Cada paso queda en la
 * bitácora (tipo cambio_clase). Las fechas se guardan como hora de pared en
 * la zona de la aplicación, igual que las clases.
 */
class SolicitudesCambioClase
{
    public const TIPO_BITACORA = 'cambio_clase';

    /** Archivo de sustento: opcional, solo PDF o imagen. */
    public const EXTENSIONES_SUSTENTO = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

    public const CARPETA_SUSTENTOS = 'sustentos-reprogramacion';

    public function __construct(protected DisponibilidadSalones $disponibilidad) {}

    /**
     * Crea la reprogramación. Se aplica sola si se eligió la nueva fecha (y
     * el salón, si es presencial) y se cumplen todas las reglas; si no,
     * queda en revisión y se avisa a los Supervisores activos.
     *
     * @param  array{tipo?: mixed, motivo?: mixed, detalle?: mixed, modalidad?: mixed, fecha_propuesta?: mixed, salon_id?: mixed}  $datos
     */
    public function solicitar(User $profesor, SesionClase $sesion, array $datos): SolicitudCambioClase
    {
        Gate::forUser($profesor)->authorize('solicitar', [SolicitudCambioClase::class, $sesion]);
        $this->asegurarClaseCambiable($sesion);

        $validos = Validator::make($datos, [
            'tipo' => ['required', Rule::in(array_keys(SolicitudCambioClase::TIPOS))],
            'motivo' => ['required', Rule::in(array_keys(SolicitudCambioClase::MOTIVOS))],
            'detalle' => ['required', 'string', 'min:10', 'max:500'],
            'modalidad' => ['required_if:tipo,reprogramar', 'nullable', Rule::in(array_keys(SolicitudCambioClase::MODALIDADES))],
            'fecha_propuesta' => ['nullable', 'date', 'after:now'],
            'salon_id' => ['nullable', 'integer', Rule::exists('salones', 'id')],
        ], [
            'tipo.required' => 'Elige qué necesitas.',
            'motivo.required' => 'Elige el motivo.',
            'detalle.required' => 'Explica brevemente el motivo (puede ser general).',
            'detalle.min' => 'La explicación debe tener al menos 10 caracteres.',
            'detalle.max' => 'El detalle admite hasta 500 caracteres.',
            'modalidad.required_if' => 'Elige la modalidad de la clase nueva.',
            'fecha_propuesta.after' => 'La fecha que propones debe ser futura.',
            'salon_id.exists' => 'El salón elegido no existe.',
        ])->validate();

        // "Dictarla en remoto" es en la misma fecha y hora: sin fecha propuesta ni salón.
        $enRemoto = $validos['tipo'] === 'remoto';
        $modalidad = $enRemoto ? 'remoto' : $validos['modalidad'];
        $presencial = $modalidad === 'presencial';
        $propuesta = $enRemoto || blank($validos['fecha_propuesta'] ?? null) ? null : Carbon::parse($validos['fecha_propuesta']);
        $salonId = $presencial && filled($validos['salon_id'] ?? null) ? (int) $validos['salon_id'] : null;

        return DB::transaction(function () use ($profesor, $sesion, $validos, $enRemoto, $modalidad, $presencial, $propuesta, $salonId) {
            $salon = $this->disponibilidad->bloquear($sesion->curso, $salonId);
            // Bloquea la clase: dos pedidos a la vez no crean dos pendientes (además del índice único parcial).
            $sesion = SesionClase::query()->with(['curso', 'salon'])->whereKey($sesion->id)->lockForUpdate()->firstOrFail();

            if ($sesion->solicitudCambioPendiente()->exists()) {
                throw ValidationException::withMessages(['solicitud' => 'Esta clase ya tiene una reprogramación pendiente.']);
            }

            $inicio = $enRemoto ? $sesion->fecha->copy() : $propuesta;
            $duracion = (int) $sesion->duracion_minutos;
            $razones = ['No se eligió la nueva fecha y hora.'];

            if ($inicio !== null) {
                $evaluacion = $this->disponibilidad->evaluar($sesion, $inicio, $duracion, $presencial, $salon);

                if ($evaluacion['salon_ocupado']) {
                    throw ValidationException::withMessages(['horario' => DisponibilidadSalones::MENSAJE_HORARIO_TOMADO]);
                }

                $razones = $evaluacion['razones'];
            }

            $solicitud = SolicitudCambioClase::create([
                'sesion_id' => $sesion->id,
                'curso_id' => $sesion->curso_id,
                'solicitado_por' => $profesor->id,
                'tipo' => $validos['tipo'],
                'motivo' => $validos['motivo'],
                'detalle' => $validos['detalle'],
                'modalidad_solicitada' => $modalidad,
                'fecha_propuesta' => $enRemoto ? null : $propuesta,
                'fecha_original' => $sesion->fecha,
                'duracion_original' => $duracion,
                'salon_nuevo_id' => $salon?->id,
                'razon_revision' => $razones === [] ? null : Str::limit(implode(' ', $razones), 255, ''),
                'estado' => 'pendiente',
            ]);

            if ($razones === []) {
                [$anterior, $nuevo] = $this->aplicar($solicitud, $sesion, $inicio, $duracion, $modalidad, $salon, null, 'automatica', null);
                $this->registrar($profesor, $solicitud, 'Reprogramación aplicada automáticamente', $anterior, $nuevo);
                Notification::send($this->estudiantesActivos($sesion->curso), new ClaseReprogramada($solicitud));

                return $solicitud;
            }

            $this->registrar($profesor, $solicitud, 'Reprogramación enviada a revisión', null, [
                ...$solicitud->only(['tipo', 'motivo', 'modalidad_solicitada', 'fecha_propuesta', 'salon_nuevo_id']),
                'razon_revision' => $solicitud->razon_revision,
            ]);

            Notification::send($this->supervisoresActivos(), new SolicitudCambioClaseRecibida($solicitud));

            return $solicitud;
        });
    }

    /** El Profesor retira su pedido mientras siga pendiente. */
    public function cancelar(User $profesor, SolicitudCambioClase $solicitud): SolicitudCambioClase
    {
        Gate::forUser($profesor)->authorize('cancelar', $solicitud);

        return DB::transaction(function () use ($profesor, $solicitud) {
            $solicitud = $this->bloquearPendiente($solicitud);
            $solicitud->update(['estado' => 'cancelada']);
            $this->registrar($profesor, $solicitud, 'Reprogramación cancelada', ['estado' => 'pendiente'], ['estado' => 'cancelada']);

            return $solicitud;
        });
    }

    /**
     * El Supervisor aprueba: mueve la clase a la nueva fecha, hora y modalidad
     * y, si es presencial, al salón elegido (salon_id, que debe estar libre)
     * o a un aula escrita a mano. Avisa al Profesor y a los estudiantes.
     *
     * @param  array{fecha?: mixed, hora_inicio?: mixed, hora_fin?: mixed, modalidad?: mixed, aula?: mixed, salon_id?: mixed}  $datos
     */
    public function aprobar(User $supervisor, SolicitudCambioClase $solicitud, array $datos): SolicitudCambioClase
    {
        Gate::forUser($supervisor)->authorize('decidir', $solicitud);

        $validos = Validator::make($datos, [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'modalidad' => ['required', Rule::in(array_keys(SolicitudCambioClase::MODALIDADES))],
            'salon_id' => ['nullable', 'integer', Rule::exists('salones', 'id')],
            'aula' => [
                Rule::requiredIf(fn () => ($datos['modalidad'] ?? null) === 'presencial' && blank($datos['salon_id'] ?? null)),
                'nullable', 'string', 'max:50',
            ],
        ], [
            'fecha.required' => 'Indica la fecha de la clase.',
            'hora_inicio.required' => 'Indica la hora de inicio.',
            'hora_fin.required' => 'Indica la hora de fin.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'modalidad.required' => 'Elige la modalidad.',
            'aula.required' => 'Indica el aula: la clase es presencial.',
            'salon_id.exists' => 'El salón elegido no existe.',
        ])->validate();

        $inicio = Carbon::createFromFormat('Y-m-d H:i', "{$validos['fecha']} {$validos['hora_inicio']}");
        $fin = Carbon::createFromFormat('Y-m-d H:i', "{$validos['fecha']} {$validos['hora_fin']}");

        if (! $inicio->isFuture()) {
            throw ValidationException::withMessages(['fecha' => 'La nueva fecha y hora deben ser futuras.']);
        }

        $duracion = (int) $inicio->diffInMinutes($fin);
        $presencial = $validos['modalidad'] === 'presencial';
        $salonId = $presencial && filled($validos['salon_id'] ?? null) ? (int) $validos['salon_id'] : null;
        $aula = $presencial && $salonId === null ? trim((string) $validos['aula']) : null;

        return DB::transaction(function () use ($supervisor, $solicitud, $validos, $inicio, $duracion, $salonId, $aula) {
            $salon = $this->disponibilidad->bloquear($solicitud->curso, $salonId);
            $sesion = SesionClase::query()->with('curso')->whereKey($solicitud->sesion_id)->lockForUpdate()->firstOrFail();
            $solicitud = $this->bloquearPendiente($solicitud);

            // La misma fila se mueve solo si sigue sin lista (así el criterio de asistencia no cambia).
            if ($sesion->asistencias()->exists()) {
                throw ValidationException::withMessages(['solicitud' => 'La clase ya tiene asistencia registrada: no se puede mover.']);
            }

            if ($salon !== null && ! $this->disponibilidad->salonLibre($salon, $inicio, $duracion, $sesion->id)) {
                throw ValidationException::withMessages(['horario' => DisponibilidadSalones::MENSAJE_HORARIO_TOMADO]);
            }

            [$anterior, $nuevo] = $this->aplicar($solicitud, $sesion, $inicio, $duracion, $validos['modalidad'], $salon, $aula, 'supervisor', $supervisor);
            $this->registrar($supervisor, $solicitud, 'Reprogramación aprobada: clase reprogramada', $anterior, $nuevo);

            $solicitud->solicitante?->notify(new SolicitudCambioClaseResuelta($solicitud));
            Notification::send($this->estudiantesActivos($sesion->curso), new ClaseReprogramada($solicitud));

            return $solicitud;
        });
    }

    /** El Supervisor rechaza con un comentario obligatorio; la clase no cambia. */
    public function rechazar(User $supervisor, SolicitudCambioClase $solicitud, ?string $comentario): SolicitudCambioClase
    {
        Gate::forUser($supervisor)->authorize('decidir', $solicitud);

        $validos = Validator::make(['comentario' => $comentario], [
            'comentario' => ['required', 'string', 'max:1000'],
        ], [
            'comentario.required' => 'Escribe por qué se rechaza.',
        ])->validate();

        return DB::transaction(function () use ($supervisor, $solicitud, $validos) {
            $solicitud = $this->bloquearPendiente($solicitud);
            $solicitud->update([
                'estado' => 'rechazada',
                'decidido_por' => $supervisor->id,
                'decidido_en' => now(),
                'comentario_decision' => $validos['comentario'],
            ]);

            $this->registrar($supervisor, $solicitud, 'Reprogramación rechazada', ['estado' => 'pendiente'], ['estado' => 'rechazada'], $validos['comentario']);
            $solicitud->solicitante?->notify(new SolicitudCambioClaseResuelta($solicitud));

            return $solicitud;
        });
    }

    /**
     * El Supervisor pide sustento de una reprogramación en revisión: sigue
     * pendiente, se muestra como «Sustento solicitado» y se avisa al Profesor.
     * Se pide una sola vez.
     */
    public function pedirSustento(User $supervisor, SolicitudCambioClase $solicitud, ?string $pedido): SolicitudCambioClase
    {
        Gate::forUser($supervisor)->authorize('pedirSustento', $solicitud);

        $validos = Validator::make(['pedido' => $pedido], [
            'pedido' => ['required', 'string', 'max:1000'],
        ], [
            'pedido.required' => 'Indica qué sustento necesitas.',
        ])->validate();

        return DB::transaction(function () use ($supervisor, $solicitud, $validos) {
            $solicitud = $this->bloquearPendiente($solicitud);

            if ($solicitud->sustento_pedido_en !== null) {
                throw ValidationException::withMessages(['solicitud' => 'Ya se pidió sustento para esta reprogramación.']);
            }

            $solicitud->update(['sustento_pedido' => $validos['pedido'], 'sustento_pedido_en' => now()]);
            $this->registrar($supervisor, $solicitud, 'Sustento solicitado', null, ['sustento_pedido' => $validos['pedido']]);
            $solicitud->solicitante?->notify(new ReprogramacionSustentoSolicitado($solicitud));

            return $solicitud;
        });
    }

    /**
     * El Profesor responde el sustento pedido con un texto y, si quiere, un
     * PDF o una imagen (almacenamiento privado de SubidorArchivos; solo lo
     * descargan el Supervisor y el Profesor dueño). Se responde una sola vez
     * y se avisa a los Supervisores activos.
     */
    public function responderSustento(User $profesor, SolicitudCambioClase $solicitud, ?string $respuesta, ?UploadedFile $archivo = null): SolicitudCambioClase
    {
        Gate::forUser($profesor)->authorize('responderSustento', $solicitud);

        $validos = Validator::make(['respuesta' => $respuesta, 'archivo' => $archivo], [
            'respuesta' => ['required', 'string', 'max:1000'],
            'archivo' => SubidorArchivos::reglas(self::EXTENSIONES_SUSTENTO, requerido: false),
        ], [
            'respuesta.required' => 'Escribe tu respuesta al supervisor.',
            ...SubidorArchivos::mensajes('archivo'),
        ])->validate();

        $this->asegurarEsperaSustento($solicitud->fresh());
        $subidor = app(SubidorArchivos::class);
        $ruta = $archivo !== null ? $subidor->subir($archivo, self::CARPETA_SUSTENTOS, 'archivo', self::EXTENSIONES_SUSTENTO) : null;

        try {
            return DB::transaction(function () use ($profesor, $solicitud, $validos, $ruta) {
                $solicitud = $this->bloquearPendiente($solicitud);
                $this->asegurarEsperaSustento($solicitud);

                $solicitud->update([
                    'sustento_respuesta' => $validos['respuesta'],
                    'sustento_archivo' => $ruta,
                    'sustento_respondido_en' => now(),
                ]);

                $this->registrar($profesor, $solicitud, 'Sustento respondido', null, ['con_archivo' => $ruta !== null]);
                Notification::send($this->supervisoresActivos(), new ReprogramacionSustentoRespondido($solicitud));

                return $solicitud;
            });
        } catch (Throwable $error) {
            $subidor->eliminar($ruta);

            throw $error;
        }
    }

    /**
     * El Supervisor revierte una reprogramación aplicada: la clase vuelve a
     * su fecha, duración, salón, descripción y modalidad originales. Solo si
     * es la última aplicada de la clase, la clase sigue sin lista y sin otra
     * en revisión, la fecha original es futura y ese horario sigue libre
     * para los docentes y para el salón original.
     */
    public function revertir(User $supervisor, SolicitudCambioClase $solicitud, ?string $comentario = null): SolicitudCambioClase
    {
        Gate::forUser($supervisor)->authorize('revertir', $solicitud);

        $validos = Validator::make(['comentario' => $comentario], [
            'comentario' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return DB::transaction(function () use ($supervisor, $solicitud, $validos) {
            $this->disponibilidad->bloquear($solicitud->curso, $solicitud->salon_original_id);
            $sesion = SesionClase::query()->with('curso')->whereKey($solicitud->sesion_id)->lockForUpdate()->firstOrFail();
            $solicitud = SolicitudCambioClase::query()->with('salonOriginal')->whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            $original = $solicitud->fecha_original;
            $duracion = (int) $solicitud->duracion_original;

            $error = match (true) {
                $solicitud->estado !== 'aprobada' => 'Solo se puede revertir una reprogramación aplicada.',
                $solicitud->origen === null => 'Esta reprogramación no guardó la clase original: no se puede revertir.',
                $sesion->solicitudesCambio()->where('estado', 'aprobada')->where('id', '>', $solicitud->id)->exists() => 'Solo se puede revertir la última reprogramación de la clase.',
                $sesion->solicitudCambioPendiente()->exists() => 'La clase tiene una reprogramación en revisión: resuélvela antes de revertir.',
                $sesion->asistencias()->exists() => 'La clase ya tiene asistencia registrada: no se puede revertir.',
                ! $original->isFuture() => 'La fecha original ya pasó: no se puede revertir.',
                ! $this->disponibilidad->docentesLibres($sesion, $original, $duracion) => 'El horario original ya no está libre para un docente del curso: no se puede revertir.',
                $solicitud->salonOriginal !== null && ! $this->disponibilidad->salonLibre($solicitud->salonOriginal, $original, $duracion, $sesion->id) => 'El salón original ya está ocupado en ese horario: no se puede revertir.',
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages(['solicitud' => $error]);
            }

            $anterior = $this->estadoDe($sesion);
            $sesion->update([
                'fecha' => $original,
                'duracion_minutos' => $duracion,
                'salon_id' => $solicitud->salon_original_id,
                'descripcion' => $solicitud->descripcion_original,
                'modalidad' => $solicitud->modalidad_original,
            ]);

            $solicitud->update([
                'estado' => 'revertida',
                'revertida_por' => $supervisor->id,
                'revertida_en' => now(),
                'comentario_reversion' => filled($validos['comentario'] ?? null) ? $validos['comentario'] : null,
            ]);

            $this->registrar($supervisor, $solicitud, 'Reprogramación revertida', $anterior, $this->estadoDe($sesion), $solicitud->comentario_reversion);
            Notification::send($this->estudiantesActivos($sesion->curso), new ClaseReprogramada($solicitud, revertida: true));
            $solicitud->solicitante?->notify(new ReprogramacionRevertida($solicitud));

            return $solicitud;
        });
    }

    /**
     * Avisos NO bloqueantes para el Supervisor al elegir la nueva fecha: cruce
     * de salón (por salon_id y, en clases antiguas, por «Aula: CÓDIGO») o, sin
     * salón, el aviso suave de cruce de aula por texto; cruce del mismo
     * docente (titular o co-docente) y fecha fuera de las semanas del curso.
     * Una consulta por cada cruce.
     *
     * @return list<string>
     */
    public function avisos(SolicitudCambioClase $solicitud, Carbon $inicio, int $duracion, ?string $aula, ?Salon $salon = null): array
    {
        $fin = $inicio->copy()->addMinutes($duracion);
        $curso = $solicitud->curso;
        $avisos = [];

        if ($salon !== null) {
            $cruces = $this->clasesCercanas($solicitud, $inicio, $fin)
                ->where(fn ($consulta) => $consulta->where('sesiones_clase.salon_id', $salon->id)
                    ->orWhere(fn ($antiguas) => $antiguas->whereNull('sesiones_clase.salon_id')
                        ->where('sesiones_clase.descripcion', 'like', '%'.addcslashes($salon->codigo, '%_\\').'%')))
                ->get()
                ->filter(fn (SesionClase $sesion) => DisponibilidadSalones::seCruzan($sesion, $inicio, $fin) && DisponibilidadSalones::ocupa($sesion, $salon));

            foreach ($cruces as $sesion) {
                $avisos[] = "Cruce de salón: {$sesion->curso_nombre} tiene clase en el salón {$salon->codigo} el {$sesion->fecha->format('d/m/Y H:i')}.";
            }
        } elseif (filled($aula)) {
            $aula = trim($aula);
            $cruces = $this->clasesCercanas($solicitud, $inicio, $fin)
                ->where('sesiones_clase.descripcion', 'like', '%'.addcslashes($aula, '%_\\').'%')
                ->get()
                ->filter(fn (SesionClase $sesion) => DisponibilidadSalones::seCruzan($sesion, $inicio, $fin)
                    && mb_strtolower((string) AsistenciaProfesor::aula($sesion)) === mb_strtolower($aula));

            foreach ($cruces as $sesion) {
                $avisos[] = "Posible cruce de aula: {$sesion->curso_nombre} tiene clase en el aula {$aula} el {$sesion->fecha->format('d/m/Y H:i')}.";
            }
        }

        // Docentes del curso (titular y co-docentes) en subconsultas: una sola consulta.
        $docentes = DB::table('curso_profesor')->where('curso_id', $curso->id)->select('profesor_id')
            ->union(DB::table('cursos')->where('id', $curso->id)->select('profesor_id'));
        $cursosDeLosDocentes = DB::table('cursos')->select('id')
            ->whereIn('profesor_id', $docentes)
            ->orWhereIn('id', DB::table('curso_profesor')->select('curso_id')->whereIn('profesor_id', $docentes));

        $cruces = $this->clasesCercanas($solicitud, $inicio, $fin)
            ->whereIn('sesiones_clase.curso_id', $cursosDeLosDocentes)
            ->get()
            ->filter(fn (SesionClase $sesion) => DisponibilidadSalones::seCruzan($sesion, $inicio, $fin));

        foreach ($cruces as $sesion) {
            $avisos[] = "Posible cruce de docente: {$sesion->curso_nombre} tiene clase el {$sesion->fecha->format('d/m/Y H:i')}.";
        }

        $fueraDeSemanas = $this->disponibilidad->avisoFueraDeSemanas($curso, $inicio);

        if ($fueraDeSemanas !== null) {
            $avisos[] = $fueraDeSemanas;
        }

        return $avisos;
    }

    /** Aula actual de la clase (línea «Aula: X» de su descripción), para las pantallas que no son del Profesor. */
    public static function aulaDe(SesionClase $sesion): ?string
    {
        return AsistenciaProfesor::aula($sesion);
    }

    /** Línea que reemplaza al aula cuando la clase pasa a remota (no se deja un aula obsoleta). */
    public const LINEA_REMOTA = 'Modalidad: Remota';

    /**
     * Descripción de la clase con su ubicación: cambia la línea «Aula: X» o
     * «Modalidad: Remota» (o la agrega al inicio) y conserva el resto del
     * texto. Remota: la línea del aula pasa a «Modalidad: Remota»; sin aula y
     * sin remota, se quita.
     */
    public static function descripcionConAula(?string $descripcion, ?string $aula, bool $remota = false): ?string
    {
        $lineas = preg_split('/\R/u', (string) $descripcion);
        $esLineaAula = fn (string $linea) => preg_match('/^\s*(Aula:|Modalidad:\s*Remota\s*$)/iu', $linea) === 1;
        $posicion = collect($lineas)->search(fn (string $linea) => $esLineaAula($linea));
        $nueva = match (true) {
            $remota => self::LINEA_REMOTA,
            filled($aula) => 'Aula: '.trim($aula),
            default => null,
        };

        $resultado = collect($lineas)
            ->map(fn (string $linea, int $indice) => $indice === $posicion ? $nueva : ($esLineaAula($linea) ? null : $linea))
            ->filter(fn (?string $linea) => $linea !== null);

        if ($posicion === false && $nueva !== null) {
            $resultado->prepend($nueva);
        }

        $texto = trim($resultado->join("\n"));

        return $texto === '' ? null : $texto;
    }

    /**
     * Mueve la clase y marca la solicitud como aprobada, guardando la clase
     * tal como estaba (para revertir). Se llama con la clase y la solicitud
     * ya bloqueadas.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} estado anterior y nuevo de la clase
     */
    protected function aplicar(SolicitudCambioClase $solicitud, SesionClase $sesion, Carbon $inicio, int $duracion, string $modalidad, ?Salon $salon, ?string $aula, string $origen, ?User $supervisor): array
    {
        $anterior = $this->estadoDe($sesion);
        $presencial = $modalidad === 'presencial';
        $aula = $presencial ? ($salon?->codigo ?? $aula) : null;

        $solicitud->update([
            'fecha_original' => $sesion->fecha,
            'duracion_original' => (int) $sesion->duracion_minutos,
            'salon_original_id' => $sesion->salon_id,
            'descripcion_original' => $sesion->descripcion,
            'modalidad_original' => $sesion->modalidad,
        ]);

        $sesion->update([
            'fecha' => $inicio,
            'duracion_minutos' => $duracion,
            'modalidad' => $modalidad,
            'salon_id' => $presencial ? $salon?->id : null,
            'descripcion' => self::descripcionConAula($sesion->descripcion, $aula, ! $presencial),
        ]);

        $solicitud->update([
            'estado' => 'aprobada',
            'origen' => $origen,
            'decidido_por' => $supervisor?->id,
            'decidido_en' => now(),
            'fecha_nueva' => $inicio,
            'duracion_nueva' => $duracion,
            'modalidad_nueva' => $modalidad,
            'aula_nueva' => $aula,
            'salon_nuevo_id' => $presencial ? $salon?->id : null,
        ]);

        return [$anterior, $this->estadoDe($sesion)];
    }

    /** @return array<string, mixed> */
    protected function estadoDe(SesionClase $sesion): array
    {
        return [
            'fecha' => $sesion->fecha->format('Y-m-d H:i'),
            'duracion_minutos' => (int) $sesion->duracion_minutos,
            'modalidad' => $sesion->modalidad,
            'salon_id' => $sesion->salon_id,
            'descripcion' => $sesion->descripcion,
        ];
    }

    /** Reglas de la clase para pedir el cambio. */
    protected function asegurarClaseCambiable(SesionClase $sesion): void
    {
        $curso = $sesion->curso;

        $error = match (true) {
            $curso->estaFinalizado() => 'El curso está archivado y no admite cambios.',
            ! $curso->usa_asistencia => 'Este curso no registra asistencia por clase.',
            ! $sesion->fecha->isFuture() => 'Solo se puede pedir el cambio de una clase futura.',
            $sesion->asistencias()->exists() => 'La clase ya tiene asistencia registrada.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['solicitud' => $error]);
        }
    }

    protected function asegurarEsperaSustento(SolicitudCambioClase $solicitud): void
    {
        if (! $solicitud->esperaSustento()) {
            throw ValidationException::withMessages(['solicitud' => 'Esta reprogramación no espera un sustento.']);
        }
    }

    /** Vuelve a leer la solicitud con bloqueo de fila y exige que siga pendiente. */
    protected function bloquearPendiente(SolicitudCambioClase $solicitud): SolicitudCambioClase
    {
        $bloqueada = SolicitudCambioClase::query()->whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

        if (! $bloqueada->estaPendiente()) {
            throw ValidationException::withMessages(['solicitud' => 'Esta solicitud ya no está pendiente.']);
        }

        return $bloqueada;
    }

    /**
     * Clases (excepto la de la solicitud) que empiezan entre un día antes del
     * inicio y el fin, con el nombre de su curso en la misma consulta.
     *
     * @return Builder<SesionClase>
     */
    protected function clasesCercanas(SolicitudCambioClase $solicitud, Carbon $inicio, Carbon $fin): Builder
    {
        return SesionClase::query()
            ->join('cursos', 'cursos.id', '=', 'sesiones_clase.curso_id')
            ->where('sesiones_clase.id', '!=', $solicitud->sesion_id)
            ->whereBetween('sesiones_clase.fecha', [$inicio->copy()->subDay(), $fin])
            ->select('sesiones_clase.*', 'cursos.nombre as curso_nombre');
    }

    /** @return Collection<int, User> */
    protected function supervisoresActivos(): Collection
    {
        return User::role(Rol::Supervisor->value)->where('activo', true)->get();
    }

    /** @return Collection<int, User> */
    protected function estudiantesActivos(Curso $curso): Collection
    {
        return $curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter()->values();
    }

    /**
     * @param  array<string, mixed>|null  $anterior
     * @param  array<string, mixed>|null  $nuevo
     */
    protected function registrar(User $ejecutor, SolicitudCambioClase $solicitud, string $descripcion, ?array $anterior, ?array $nuevo, ?string $motivo = null): void
    {
        BitacoraAuditoria::registrarAccionAdmin(
            $ejecutor,
            self::TIPO_BITACORA,
            $descripcion.' (clase '.$solicitud->sesion_id.', solicitud '.$solicitud->id.').',
            $solicitud->solicitante,
            $solicitud->curso,
            $anterior,
            $nuevo,
            $motivo ?? (SolicitudCambioClase::MOTIVOS[$solicitud->motivo] ?? null),
        );
    }
}
