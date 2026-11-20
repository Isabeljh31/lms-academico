<?php

namespace App\Services\Asistencia;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\SesionClase;
use App\Models\User;
use App\Notifications\EnlaceRemotoFaltante;
use App\Services\ConfiguracionPlataforma;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Enlace de reunión de las clases remotas, sin integrar ningún servicio de
 * videoconferencia: el Profesor pega un enlace de Teams, Meet, Zoom u otro
 * de la lista blanca (ConfiguracionPlataforma::dominiosEnlaceRemoto).
 *
 * - Validación: solo https, hasta 500 caracteres, sin credenciales, sin IP
 *   ni puerto propio y con un dominio permitido (o un subdominio suyo).
 * - Resolución: el enlace de la clase y, si no tiene, el del curso. Se
 *   calcula al leer (no se copia) y se vuelve a validar, por si la lista
 *   blanca cambió o el valor llegó a la BD por otra vía.
 * - Ventana: el botón «Unirse» se habilita desde 15 minutos antes del
 *   inicio hasta el fin de la clase.
 * - Solo es clase en línea la modalidad efectiva «remoto»; «virtual» es
 *   asíncrono y no tiene clases programadas.
 */
class EnlacesClaseRemota
{
    public const TIPO_BITACORA = 'enlace_remoto';

    public const LARGO_MAXIMO = 500;

    public const MINUTOS_ANTES = 15;

    /** Duración usada si la clase no la tiene registrada. */
    public const DURACION_PREDETERMINADA = 90;

    public function __construct(protected ConfiguracionPlataforma $configuracion) {}

    /**
     * Normaliza y valida el enlace. Vacío equivale a quitarlo (null).
     *
     * @throws ValidationException
     */
    public function validar(?string $enlace, string $campo = 'enlace_remoto'): ?string
    {
        $enlace = trim((string) $enlace);

        if ($enlace === '') {
            return null;
        }

        $error = $this->error($enlace);

        if ($error !== null) {
            throw ValidationException::withMessages([$campo => $error]);
        }

        return $enlace;
    }

    public function esValido(?string $enlace): bool
    {
        return filled($enlace) && $this->error(trim($enlace)) === null;
    }

    /** La clase se dicta en vivo por videollamada. */
    public function esRemota(SesionClase $sesion): bool
    {
        return $sesion->modalidadEfectiva() === 'remoto';
    }

    /** Enlace vigente de la clase: el suyo o, si no tiene, el del curso. */
    public function resolver(SesionClase $sesion): ?string
    {
        foreach ([$sesion->enlace_remoto, $sesion->curso?->enlace_remoto] as $enlace) {
            if (filled($enlace)) {
                return $this->esValido($enlace) ? trim($enlace) : null;
            }
        }

        return null;
    }

    /**
     * Momento de la clase respecto del botón «Unirse».
     *
     * @return 'antes'|'abierta'|'terminada'
     */
    public function ventana(SesionClase $sesion, ?CarbonInterface $ahora = null): string
    {
        $ahora ??= now();
        $inicio = $sesion->fecha->copy();
        $fin = $inicio->copy()->addMinutes($sesion->duracion_minutos ?: self::DURACION_PREDETERMINADA);

        return match (true) {
            $ahora->lt($inicio->copy()->subMinutes(self::MINUTOS_ANTES)) => 'antes',
            $ahora->lte($fin) => 'abierta',
            default => 'terminada',
        };
    }

    /** Enlace para unirse ahora: solo si la clase es remota y está en su ventana. */
    public function enlaceParaUnirse(SesionClase $sesion, ?CarbonInterface $ahora = null): ?string
    {
        if (! $this->esRemota($sesion) || $this->ventana($sesion, $ahora) !== 'abierta') {
            return null;
        }

        return $this->resolver($sesion);
    }

    /** «Enlace de esta clase» en el menú ⋮: clase remota futura o en curso (con o sin lista). */
    public function admiteEnlacePropio(SesionClase $sesion, ?CarbonInterface $ahora = null): bool
    {
        return $this->esRemota($sesion) && $this->ventana($sesion, $ahora) !== 'terminada';
    }

    /**
     * Tras pasar una clase a remota: si no hay enlace (ni de la clase ni del
     * curso), avisa a los docentes activos del curso, una sola vez por clase.
     */
    public function avisarSiFaltaEnlace(SesionClase $sesion): void
    {
        if (! $this->esRemota($sesion) || $this->resolver($sesion) !== null) {
            return;
        }

        $docentes = $sesion->curso->docentes()
            ->filter(fn (User $docente) => $docente->activo)
            ->reject(fn (User $docente) => $docente->notifications()
                ->where('type', EnlaceRemotoFaltante::class)
                ->where('data', 'like', '{"sesion_id":'.$sesion->id.',%')
                ->exists());

        Notification::send($docentes, new EnlaceRemotoFaltante($sesion));
    }

    /** Dominios aceptados, para mostrarlos al Profesor junto al campo. */
    public function textoDominiosPermitidos(): string
    {
        return implode(', ', $this->configuracion->dominiosEnlaceRemoto());
    }

    /** Solo el dominio del enlace (lo que se muestra como estado, nunca la URL completa). */
    public function dominioDe(?string $enlace): ?string
    {
        $host = filled($enlace) ? parse_url(trim($enlace), PHP_URL_HOST) : null;

        return is_string($host) ? strtolower($host) : null;
    }

    /**
     * El Profesor del curso (titular o co-docente) guarda el enlace fijo.
     *
     * @throws ValidationException
     */
    public function guardarDelCurso(User $docente, Curso $curso, ?string $enlace): Curso
    {
        Gate::forUser($docente)->authorize('editarEnlaceRemoto', $curso);
        $this->asegurarCursoEditable($curso);

        $nuevo = $this->validar($enlace);
        $anterior = $curso->enlace_remoto;

        if ($nuevo === $anterior) {
            return $curso;
        }

        $curso->update(['enlace_remoto' => $nuevo]);
        $this->registrar($docente, $curso, 'Enlace de clases remotas del curso '.$this->accion($anterior, $nuevo).'.', $anterior, $nuevo);

        return $curso;
    }

    /**
     * Enlace distinto para una clase remota puntual que aún no termina.
     *
     * @throws ValidationException
     */
    public function guardarDeClase(User $docente, SesionClase $sesion, ?string $enlace): SesionClase
    {
        $curso = $sesion->curso;
        Gate::forUser($docente)->authorize('editarEnlaceRemoto', $curso);
        $this->asegurarCursoEditable($curso);

        $error = match (true) {
            ! $this->esRemota($sesion) => 'Solo una clase remota puede tener enlace propio.',
            ! $this->admiteEnlacePropio($sesion) => 'La clase ya terminó.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['enlace_remoto' => $error]);
        }

        $nuevo = $this->validar($enlace);
        $anterior = $sesion->enlace_remoto;

        if ($nuevo === $anterior) {
            return $sesion;
        }

        $sesion->update(['enlace_remoto' => $nuevo]);
        $this->registrar($docente, $curso, 'Enlace de la clase '.$sesion->id.' '.$this->accion($anterior, $nuevo).'.', $anterior, $nuevo);

        return $sesion;
    }

    /** Motivo del rechazo o null si el enlace es aceptable. */
    protected function error(string $enlace): ?string
    {
        if (mb_strlen($enlace) > self::LARGO_MAXIMO) {
            return 'El enlace admite hasta '.self::LARGO_MAXIMO.' caracteres.';
        }

        // Espacios, controles y barras invertidas: los navegadores los interpretan distinto que parse_url.
        if (preg_match('/[\s\x00-\x1F\x7F\\\\]/u', $enlace) || filter_var($enlace, FILTER_VALIDATE_URL) === false) {
            return 'Escribe un enlace válido que empiece con https://.';
        }

        $partes = parse_url($enlace);

        if ($partes === false || strtolower($partes['scheme'] ?? '') !== 'https' || blank($partes['host'] ?? null)) {
            return 'El enlace debe empezar con https://.';
        }

        if (isset($partes['user']) || isset($partes['pass'])) {
            return 'El enlace no puede incluir usuario ni contraseña.';
        }

        if (isset($partes['port']) && (int) $partes['port'] !== 443) {
            return 'El enlace no puede indicar otro puerto.';
        }

        $host = rtrim(strtolower($partes['host']), '.');

        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false || ! $this->dominioPermitido($host)) {
            return 'Usa un enlace de un servicio permitido: '.$this->textoDominiosPermitidos().'.';
        }

        return null;
    }

    protected function dominioPermitido(string $host): bool
    {
        foreach ($this->configuracion->dominiosEnlaceRemoto() as $dominio) {
            if ($host === $dominio || str_ends_with($host, '.'.$dominio)) {
                return true;
            }
        }

        return false;
    }

    protected function asegurarCursoEditable(Curso $curso): void
    {
        if ($curso->estaFinalizado()) {
            throw ValidationException::withMessages(['enlace_remoto' => 'El curso está archivado y no admite cambios.']);
        }
    }

    protected function accion(?string $anterior, ?string $nuevo): string
    {
        return match (true) {
            $nuevo === null => 'eliminado',
            $anterior === null => 'agregado',
            default => 'actualizado',
        };
    }

    /** Bitácora sin la URL completa: solo el dominio antes y después. */
    protected function registrar(User $docente, Curso $curso, string $descripcion, ?string $anterior, ?string $nuevo): void
    {
        BitacoraAuditoria::registrarAccionAdmin(
            $docente,
            self::TIPO_BITACORA,
            $descripcion,
            curso: $curso,
            valorAnterior: $anterior ? ['dominio' => $this->dominioDe($anterior)] : null,
            valorNuevo: $nuevo ? ['dominio' => $this->dominioDe($nuevo)] : null,
        );
    }
}
