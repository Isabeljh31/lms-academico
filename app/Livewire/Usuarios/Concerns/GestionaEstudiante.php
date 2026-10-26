<?php

namespace App\Livewire\Usuarios\Concerns;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\User;
use App\Services\Asistencia\CalculadoraAsistencia;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

/**
 * Acciones sobre un estudiante (editar, activar/desactivar, eliminar) que
 * comparten el directorio (Index) y la ficha de detalle (Show), para no
 * duplicar la misma lógica en los dos componentes.
 */
trait GestionaEstudiante
{
    /** Mismo umbral que Admin\Metricas\Index::ASISTENCIA_MINIMA (pantalla de riesgo de abandono). */
    private const ASISTENCIA_MINIMA_RIESGO = 50;

    public bool $mostrarModalEditar = false;

    #[Locked]
    public ?int $usuarioEditarId = null;

    public string $editarNombres = '';

    public string $editarApellidos = '';

    public string $editarDni = '';

    public string $editarEmail = '';

    public string $editarAnioNacimiento = '';

    public string $editarCorreoPersonal = '';

    public string $editarCelular = '';

    public string $editarApoderadoNombre = '';

    public string $editarApoderadoCelular = '';

    /** Solo se puede corregir si la cuenta aún no tiene historial (ver UsuarioPolicy::corregirRol). */
    public string $editarRol = '';

    // Ventana de confirmación compartida (reemplaza los wire:confirm nativos
    // del navegador) para desactivar y eliminar.
    public bool $mostrarConfirmacion = false;

    public string $confirmacionTitulo = '';

    public string $confirmacionMensaje = '';

    public string $confirmacionTextoBoton = 'Confirmar';

    public string $confirmacionVariante = 'primario';

    /** 'cambiarEstado' | 'eliminarCuenta' */
    public string $confirmacionAccion = '';

    public ?int $confirmacionObjetivoId = null;

    private function resaltar(string $texto): string
    {
        return '<strong>'.e($texto).'</strong>';
    }

    /**
     * Normaliza a minúsculas sin tildes para que la búsqueda no distinga
     * mayúsculas ni acentos (ej. "matematicas" encuentra "Matemática").
     */
    private function normalizar(?string $valor): string
    {
        return Str::of((string) $valor)->ascii()->lower()->toString();
    }

    /**
     * Compara en ambos sentidos para tolerar plurales simples.
     */
    private function coincide(?string $campo, string $busqueda): bool
    {
        $campo = $this->normalizar($campo);

        return $campo !== '' && (str_contains($campo, $busqueda) || str_contains($busqueda, $campo));
    }

    /**
     * Mismo criterio que la pantalla "Riesgo de abandono" (Admin\Metricas\Index):
     * un curso es crítico si la asistencia acumulada es menor a 50 % (en cursos
     * que registran asistencia) o si la nota final ponderada está bajo la nota
     * mínima aprobatoria. Nivel Alto: riesgo en más de un curso.
     *
     * @return array{nivel: ?string, motivos: list<string>}
     */
    private function calcularRiesgo(User $estudiante, Collection $cursosActivos, CalculadoraAsistencia $asistencia, CalculadoraNotas $notas): array
    {
        $notaMinima = $notas->notaMinima();
        $motivos = [];
        $cursosCriticos = 0;

        foreach ($cursosActivos as $curso) {
            /** @var Curso $curso */
            $critico = false;

            if ($curso->usa_asistencia) {
                $porcentaje = $asistencia->porcentajeDe($estudiante, $curso);

                if ($porcentaje !== null && $porcentaje < self::ASISTENCIA_MINIMA_RIESGO) {
                    $critico = true;
                    $motivos[] = "Asistencia de {$porcentaje} % en {$curso->nombre}";
                }
            }

            $rendimiento = $notas->rendimientoDe($estudiante, $curso);

            if ($rendimiento !== null && $rendimiento < $notaMinima) {
                $critico = true;
                $motivos[] = "Rendimiento de {$rendimiento} en {$curso->nombre}";
            }

            $cursosCriticos += $critico ? 1 : 0;
        }

        return [
            'nivel' => $cursosCriticos > 0 ? ($cursosCriticos > 1 ? 'Alto' : 'Medio') : null,
            'motivos' => $motivos,
        ];
    }

    public function cerrarConfirmacion(): void
    {
        $this->reset([
            'mostrarConfirmacion', 'confirmacionTitulo', 'confirmacionMensaje',
            'confirmacionTextoBoton', 'confirmacionAccion', 'confirmacionObjetivoId',
        ]);
        $this->confirmacionVariante = 'primario';
    }

    public function ejecutarConfirmacion(GestorCuentas $gestor): void
    {
        $accion = $this->confirmacionAccion;
        $objetivoId = $this->confirmacionObjetivoId;

        match ($accion) {
            'cambiarEstado' => $this->cambiarEstado($objetivoId),
            'eliminarCuenta' => $this->eliminarCuenta($objetivoId, $gestor),
            default => null,
        };

        $this->cerrarConfirmacion();
    }

    public function confirmarCambiarEstado(int $id): void
    {
        Gate::authorize('verListado', User::class);
        abort_if(auth()->id() === $id, 403, 'No puedes desactivar tu propia cuenta.');
        $estudiante = User::role(Rol::Estudiante->value)->findOrFail($id);

        $this->confirmacionAccion = 'cambiarEstado';
        $this->confirmacionObjetivoId = $id;
        $this->confirmacionTitulo = $estudiante->activo ? 'Desactivar cuenta' : 'Reactivar cuenta';
        $this->confirmacionMensaje = $estudiante->activo
            ? '¿Desactivar la cuenta de '.$this->resaltar($estudiante->name).'? No podrá iniciar sesión, pero su historial académico se conserva y la cuenta puede reactivarse.'
            : '¿Reactivar la cuenta de '.$this->resaltar($estudiante->name).'?';
        $this->confirmacionTextoBoton = $estudiante->activo ? 'Desactivar' : 'Reactivar';
        $this->confirmacionVariante = $estudiante->activo ? 'peligro' : 'primario';
        $this->mostrarConfirmacion = true;
    }

    public function cambiarEstado(int $id): void
    {
        Gate::authorize('verListado', User::class);
        abort_if(auth()->id() === $id, 403, 'No puedes desactivar tu propia cuenta.');
        $estudiante = User::role(Rol::Estudiante->value)->findOrFail($id);
        $estudiante->update(['activo' => ! $estudiante->activo]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: $estudiante->activo ? 'reactivacion_cuenta' : 'desactivacion_cuenta',
            descripcion: "Cuenta de estudiante actualizada: {$estudiante->name} ({$estudiante->email}).",
            usuarioAfectado: $estudiante,
        );

        session()->flash('estado', $estudiante->activo
            ? "Cuenta de {$estudiante->name} reactivada."
            : "Cuenta de {$estudiante->name} desactivada. Su historial académico se conserva.");
    }

    public function confirmarEliminarCuenta(int $id): void
    {
        $estudiante = User::role(Rol::Estudiante->value)->findOrFail($id);

        $this->confirmacionAccion = 'eliminarCuenta';
        $this->confirmacionObjetivoId = $id;
        $this->confirmacionTitulo = 'Eliminar estudiante';
        $this->confirmacionMensaje = '¿Eliminar definitivamente a '.$this->resaltar($estudiante->name).'? Esta acción no se puede deshacer. Las cuentas con historial académico deben desactivarse.';
        $this->confirmacionTextoBoton = 'Eliminar';
        $this->confirmacionVariante = 'peligro';
        $this->mostrarConfirmacion = true;
    }

    public function eliminarCuenta(int $id, GestorCuentas $gestor): void
    {
        $gestor->eliminar(User::role(Rol::Estudiante->value)->findOrFail($id));
        $this->resetErrorBag();
        session()->flash('estado', 'Cuenta eliminada correctamente.');
    }

    public function abrirEditar(int $id): void
    {
        Gate::authorize('verListado', User::class);

        $estudiante = User::role(Rol::Estudiante->value)->findOrFail($id);
        $this->resetErrorBag();
        $this->usuarioEditarId = $estudiante->id;
        $this->editarNombres = (string) $estudiante->nombres;
        $this->editarApellidos = (string) $estudiante->apellidos;
        $this->editarDni = (string) $estudiante->dni;
        $this->editarEmail = $estudiante->email;
        $this->editarAnioNacimiento = (string) ($estudiante->anio_nacimiento ?? '');
        $this->editarCorreoPersonal = (string) $estudiante->correo_personal;
        $this->editarCelular = (string) $estudiante->celular;
        $this->editarApoderadoNombre = (string) $estudiante->apoderado_nombre;
        $this->editarApoderadoCelular = (string) $estudiante->apoderado_celular;
        $this->editarRol = (string) $estudiante->rolPrincipal();
        $this->mostrarModalEditar = true;
    }

    public function guardarEdicion(): void
    {
        Gate::authorize('verListado', User::class);

        $this->editarEmail = User::normalizarEmail($this->editarEmail);
        $this->editarDni = trim($this->editarDni);
        $anioActual = (int) now()->format('Y');

        $this->validate(
            [
                ...GestorCuentas::reglasIdentidad('editarNombres', 'editarApellidos', 'editarDni', 'editarEmail', $this->usuarioEditarId),
                'editarAnioNacimiento' => ['nullable', 'integer', 'min:'.($anioActual - 100), 'max:'.$anioActual],
                'editarCorreoPersonal' => ['nullable', 'email', 'max:255'],
                'editarCelular' => ['nullable', 'regex:/^9\d{8}$/'],
                'editarApoderadoNombre' => ['nullable', 'string', 'max:120'],
                'editarApoderadoCelular' => ['nullable', 'regex:/^9\d{8}$/'],
            ],
            [
                ...GestorCuentas::mensajesIdentidad('editarDni', 'editarEmail'),
                'editarDni.unique' => 'Ya existe otra cuenta con este DNI.',
                'editarEmail.unique' => 'Ya existe otra cuenta con este correo.',
                'editarAnioNacimiento.max' => 'Revisa el año de nacimiento.',
                'editarCelular.regex' => 'El celular debe tener 9 dígitos y empezar en 9.',
                'editarApoderadoCelular.regex' => 'El celular del apoderado debe tener 9 dígitos y empezar en 9.',
            ],
            [
                'editarNombres' => 'nombres',
                'editarApellidos' => 'apellidos',
                'editarDni' => 'DNI',
                'editarEmail' => 'correo electrónico',
                'editarAnioNacimiento' => 'año de nacimiento',
                'editarCorreoPersonal' => 'correo personal',
                'editarCelular' => 'celular',
                'editarApoderadoNombre' => 'nombre del apoderado',
                'editarApoderadoCelular' => 'celular del apoderado',
            ],
        );

        $estudiante = User::role(Rol::Estudiante->value)->findOrFail($this->usuarioEditarId);
        $rolAnterior = $estudiante->rolPrincipal();
        $corrigeRol = $this->editarRol !== '' && $this->editarRol !== $rolAnterior;

        if ($corrigeRol) {
            $this->validate(['editarRol' => [Rule::in(GestorCuentas::ROLES_CREABLES)]]);

            if (Gate::denies('corregirRol', $estudiante)) {
                $this->addError('editarRol', 'El rol ya no se puede cambiar: la cuenta tiene historial académico (cursos, matrículas, entregas o notas).');

                return;
            }
        }

        $campos = ['nombres', 'apellidos', 'dni', 'email', 'anio_nacimiento', 'correo_personal', 'celular', 'apoderado_nombre', 'apoderado_celular'];
        $anterior = $estudiante->only($campos);

        $estudiante->update([
            'nombres' => $this->editarNombres,
            'apellidos' => $this->editarApellidos,
            'dni' => $this->editarDni,
            'email' => $this->editarEmail,
            'anio_nacimiento' => $this->editarAnioNacimiento ?: null,
            'correo_personal' => $this->editarCorreoPersonal ?: null,
            'celular' => $this->editarCelular ?: null,
            'apoderado_nombre' => $this->editarApoderadoNombre ?: null,
            'apoderado_celular' => $this->editarApoderadoCelular ?: null,
        ]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'edicion_datos_usuario',
            descripcion: "Datos editados de {$estudiante->name}.",
            usuarioAfectado: $estudiante,
            valorAnterior: $anterior,
            valorNuevo: $estudiante->only($campos),
        );

        if ($corrigeRol) {
            $estudiante->syncRoles([$this->editarRol]);

            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: auth()->user(),
                tipoEvento: 'cambio_rol',
                descripcion: "Rol corregido (cuenta sin historial académico): {$estudiante->name} ({$estudiante->email}).",
                usuarioAfectado: $estudiante,
                valorAnterior: $rolAnterior,
                valorNuevo: $this->editarRol,
            );
        }

        $this->mostrarModalEditar = false;
        $this->reset([
            'usuarioEditarId', 'editarNombres', 'editarApellidos', 'editarDni', 'editarEmail',
            'editarAnioNacimiento', 'editarCorreoPersonal', 'editarCelular', 'editarApoderadoNombre', 'editarApoderadoCelular', 'editarRol',
        ]);
        session()->flash('estado', "Datos de {$estudiante->name} actualizados correctamente.");
    }
}
