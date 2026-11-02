<?php

namespace App\Livewire\Supervisor\Profesores\Concerns;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\User;
use App\Services\Multimedia\SubidorArchivos;
use App\Services\Usuarios\GestorCuentas;
use Livewire\Attributes\Locked;

/**
 * Acciones sobre un profesor (editar, activar/desactivar, eliminar, asignar
 * curso) que comparten el directorio (Index) y la ficha de detalle (Show),
 * para no duplicar la misma lógica en los dos componentes.
 */
trait GestionaProfesor
{
    public bool $mostrarModalEditar = false;

    #[Locked]
    public ?int $profesorEditarId = null;

    public string $editarNombres = '';

    public string $editarApellidos = '';

    public string $editarDni = '';

    public string $editarEmail = '';

    public string $editarEspecialidad = '';

    public string $editarCelular = '';

    public string $editarAnioNacimiento = '';

    public string $editarCorreoPersonal = '';

    public string $editarGradoAcademico = '';

    public string $editarColegiatura = '';

    public string $editarFechaInicioLabores = '';

    public $editarCv = null;

    // Modal para reasignar / vincular curso
    public bool $mostrarModalAsignar = false;

    #[Locked]
    public ?int $profesorSeleccionadoId = null;

    public ?int $cursoAAsignarId = null;

    /**
     * true cuando el modal se abrió desde un curso sin profesor (se elige el
     * docente), false cuando se abrió desde la ficha de un profesor (se
     * elige el curso).
     */
    public bool $asignarPorCurso = false;

    public ?int $profesorParaAsignarId = null;

    // Ventana de confirmación compartida (reemplaza los wire:confirm nativos
    // del navegador) para desactivar, eliminar y reasignar un curso.
    public bool $mostrarConfirmacion = false;

    public string $confirmacionTitulo = '';

    public string $confirmacionMensaje = '';

    public string $confirmacionTextoBoton = 'Confirmar';

    public string $confirmacionVariante = 'primario';

    /** 'cambiarEstado' | 'eliminarProfesor' | 'asignarCurso' | 'desasignarCurso' */
    public string $confirmacionAccion = '';

    public ?int $confirmacionObjetivoId = null;

    /**
     * Envuelve un valor en negrita para los mensajes de confirmación,
     * escapando el contenido (puede venir de datos editables por el
     * Supervisor) antes de que el modal lo renderice como HTML.
     */
    private function resaltar(string $texto): string
    {
        return '<strong>'.e($texto).'</strong>';
    }

    public function cerrarConfirmacion(): void
    {
        $this->reset([
            'mostrarConfirmacion', 'confirmacionTitulo', 'confirmacionMensaje',
            'confirmacionTextoBoton', 'confirmacionAccion', 'confirmacionObjetivoId',
        ]);
        $this->confirmacionVariante = 'primario';
    }

    /**
     * Despacha a la acción real según lo que dejó el método "confirmar*" que
     * abrió la ventana. $confirmacionAccion solo puede tomar los valores
     * fijos de arriba, nunca un método arbitrario del cliente.
     */
    public function ejecutarConfirmacion(GestorCuentas $gestor): void
    {
        $accion = $this->confirmacionAccion;
        $objetivoId = $this->confirmacionObjetivoId;

        match ($accion) {
            'cambiarEstado' => $this->cambiarEstado($objetivoId),
            'eliminarProfesor' => $this->eliminarProfesor($objetivoId, $gestor),
            'asignarCurso' => $this->asignarCurso(),
            'desasignarCurso' => $this->desasignarCurso($objetivoId),
            default => null,
        };

        $this->cerrarConfirmacion();
    }

    public function confirmarCambiarEstado(int $id): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        $profesor = User::role(Rol::Profesor->value)->findOrFail($id);

        $this->confirmacionAccion = 'cambiarEstado';
        $this->confirmacionObjetivoId = $id;
        $this->confirmacionTitulo = $profesor->activo ? 'Desactivar profesor' : 'Reactivar profesor';
        $this->confirmacionMensaje = $profesor->activo
            ? '¿Desactivar la cuenta de '.$this->resaltar($profesor->name).'? No podrá iniciar sesión hasta que la reactives.'
            : '¿Reactivar la cuenta de '.$this->resaltar($profesor->name).'?';
        $this->confirmacionTextoBoton = $profesor->activo ? 'Desactivar' : 'Reactivar';
        $this->confirmacionVariante = $profesor->activo ? 'peligro' : 'primario';
        $this->mostrarConfirmacion = true;
    }

    public function confirmarEliminarProfesor(int $id): void
    {
        $profesor = User::role(Rol::Profesor->value)->findOrFail($id);

        $this->confirmacionAccion = 'eliminarProfesor';
        $this->confirmacionObjetivoId = $id;
        $this->confirmacionTitulo = 'Eliminar profesor';
        $this->confirmacionMensaje = '¿Eliminar definitivamente a '.$this->resaltar($profesor->name).'? Esta acción no se puede deshacer. Debes reasignar sus cursos antes de eliminarlo.';
        $this->confirmacionTextoBoton = 'Eliminar';
        $this->confirmacionVariante = 'peligro';
        $this->mostrarConfirmacion = true;
    }

    public function abrirEditar(int $id): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        $profesor = User::role(Rol::Profesor->value)->findOrFail($id);
        $this->resetErrorBag();
        $this->profesorEditarId = $profesor->id;
        $this->editarNombres = (string) $profesor->nombres;
        $this->editarApellidos = (string) $profesor->apellidos;
        $this->editarDni = (string) $profesor->dni;
        $this->editarEmail = $profesor->email;
        $this->editarEspecialidad = (string) $profesor->especialidad;
        $this->editarCelular = (string) $profesor->celular;
        $this->editarAnioNacimiento = (string) ($profesor->anio_nacimiento ?? '');
        $this->editarCorreoPersonal = (string) $profesor->correo_personal;
        $this->editarGradoAcademico = (string) $profesor->grado_academico;
        $this->editarColegiatura = (string) $profesor->colegiatura;
        $this->editarFechaInicioLabores = $profesor->fecha_inicio_labores?->format('Y-m-d') ?? '';
        $this->editarCv = null;
        $this->mostrarModalEditar = true;
    }

    public function guardarEdicion(SubidorArchivos $subidor): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        $profesor = User::role(Rol::Profesor->value)->findOrFail($this->profesorEditarId);
        $this->editarEmail = User::normalizarEmail($this->editarEmail);
        $this->editarDni = trim($this->editarDni);
        $anioActual = (int) now()->format('Y');

        $this->validate([
            ...GestorCuentas::reglasIdentidad('editarNombres', 'editarApellidos', 'editarDni', 'editarEmail', $profesor->id),
            'editarEspecialidad' => ['nullable', 'string', 'max:120'],
            'editarCelular' => ['nullable', 'regex:/^9\d{8}$/'],
            'editarAnioNacimiento' => ['required', 'integer', 'min:'.($anioActual - 100), 'max:'.($anioActual - 18)],
            'editarCorreoPersonal' => ['nullable', 'email', 'max:255'],
            'editarGradoAcademico' => ['nullable', 'string', 'max:120'],
            'editarColegiatura' => ['nullable', 'string', 'max:60'],
            'editarFechaInicioLabores' => ['nullable', 'date'],
            'editarCv' => SubidorArchivos::reglas(['pdf', 'doc', 'docx'], requerido: false),
        ], [
            ...GestorCuentas::mensajesIdentidad('editarDni', 'editarEmail'),
            'editarCelular.regex' => 'El celular debe tener 9 dígitos y empezar en 9.',
            'editarAnioNacimiento.required' => 'Indica el año de nacimiento.',
            'editarAnioNacimiento.min' => 'Revisa el año de nacimiento.',
            'editarAnioNacimiento.max' => 'El profesor debe ser mayor de edad.',
            ...SubidorArchivos::mensajes('editarCv'),
        ]);

        $cvPath = $profesor->cv_path;

        if ($this->editarCv) {
            $cvPath = $subidor->subir($this->editarCv, 'profesores/cv', 'editarCv', ['pdf', 'doc', 'docx']);
            $subidor->eliminar($profesor->cv_path);
        }

        $campos = ['nombres', 'apellidos', 'dni', 'email', 'especialidad', 'celular', 'anio_nacimiento', 'correo_personal', 'grado_academico', 'colegiatura', 'fecha_inicio_labores', 'cv_path'];
        $anterior = $profesor->only($campos);
        $profesor->update([
            'nombres' => $this->editarNombres,
            'apellidos' => $this->editarApellidos,
            'dni' => $this->editarDni,
            'email' => $this->editarEmail,
            'especialidad' => $this->editarEspecialidad ?: null,
            'celular' => $this->editarCelular ?: null,
            'anio_nacimiento' => $this->editarAnioNacimiento ?: null,
            'correo_personal' => $this->editarCorreoPersonal ?: null,
            'grado_academico' => $this->editarGradoAcademico ?: null,
            'colegiatura' => $this->editarColegiatura ?: null,
            'fecha_inicio_labores' => $this->editarFechaInicioLabores ?: null,
            'cv_path' => $cvPath,
        ]);
        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'edicion_datos_usuario',
            descripcion: "Datos editados de {$profesor->name}.",
            usuarioAfectado: $profesor,
            valorAnterior: $anterior,
            valorNuevo: $profesor->only($campos),
        );

        $this->reset([
            'editarNombres', 'editarApellidos', 'editarDni', 'editarEmail', 'editarEspecialidad',
            'editarCelular', 'editarAnioNacimiento', 'editarCorreoPersonal', 'editarGradoAcademico',
            'editarColegiatura', 'editarFechaInicioLabores', 'editarCv', 'profesorEditarId', 'mostrarModalEditar',
        ]);
        session()->flash('estado', 'Profesor actualizado correctamente.');
    }

    public function cambiarEstado(int $id): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        $profesor = User::role(Rol::Profesor->value)->findOrFail($id);
        abort_if($profesor->is(auth()->user()), 403);
        $profesor->update(['activo' => ! $profesor->activo]);
        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: $profesor->activo ? 'reactivacion_cuenta' : 'desactivacion_cuenta',
            descripcion: "Estado de cuenta actualizado: {$profesor->name}.",
            usuarioAfectado: $profesor,
        );
        session()->flash('estado', $profesor->activo ? 'Profesor reactivado.' : 'Profesor desactivado.');
    }

    public function eliminarProfesor(int $id, GestorCuentas $gestor): void
    {
        $gestor->eliminar(User::role(Rol::Profesor->value)->findOrFail($id));
        $this->resetErrorBag();
        session()->flash('estado', 'Profesor eliminado correctamente.');
    }

    public function abrirModalAsignar(int $profesorId): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->profesorSeleccionadoId = User::role(Rol::Profesor->value)->findOrFail($profesorId)->id;
        $this->cursoAAsignarId = null;
        $this->profesorParaAsignarId = null;
        $this->asignarPorCurso = false;
        $this->resetErrorBag();
        $this->mostrarModalAsignar = true;
    }

    /**
     * Abre el mismo modal, pero partiendo de un curso sin profesor: acá se
     * elige el docente en vez del curso.
     */
    public function abrirModalAsignarDesdeCurso(int $cursoId): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->cursoAAsignarId = Curso::findOrFail($cursoId)->id;
        $this->profesorSeleccionadoId = null;
        $this->profesorParaAsignarId = null;
        $this->asignarPorCurso = true;
        $this->resetErrorBag();
        $this->mostrarModalAsignar = true;
    }

    /**
     * Valida la selección y, si el curso ya tiene un profesor titular,
     * pide confirmación antes de reemplazarlo. Si no tenía (o estaba a
     * cargo del Supervisor), asigna directamente.
     */
    public function confirmarAsignarCurso(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $profesorId = $this->asignarPorCurso ? $this->profesorParaAsignarId : $this->profesorSeleccionadoId;

        $this->validate([
            'cursoAAsignarId' => ['required', 'exists:cursos,id'],
            ...($this->asignarPorCurso
                ? ['profesorParaAsignarId' => ['required', 'exists:users,id']]
                : ['profesorSeleccionadoId' => ['required', 'exists:users,id']]),
        ], [
            'cursoAAsignarId.required' => 'Selecciona un curso.',
            'profesorParaAsignarId.required' => 'Selecciona un profesor.',
        ]);

        $curso = Curso::findOrFail($this->cursoAAsignarId);
        $tieneProfesorTitular = $curso->profesor?->hasRole(Rol::Profesor->value) ?? false;

        if (! $tieneProfesorTitular) {
            $this->asignarCurso();

            return;
        }

        $nuevoProfesor = User::role(Rol::Profesor->value)->findOrFail($profesorId);

        $this->mostrarModalAsignar = false;
        $this->confirmacionAccion = 'asignarCurso';
        $this->confirmacionObjetivoId = null;
        $this->confirmacionTitulo = 'Reemplazar profesor del curso';
        $this->confirmacionMensaje = $this->resaltar($curso->nombre).' ya tiene como titular a '.$this->resaltar($curso->profesor->name).'. ¿Deseas reemplazarlo por '.$this->resaltar($nuevoProfesor->name).'?';
        $this->confirmacionTextoBoton = 'Reemplazar';
        $this->confirmacionVariante = 'peligro';
        $this->mostrarConfirmacion = true;
    }

    /**
     * ERS RF-23/RF-24: asignar o cambiar el profesor de un curso.
     */
    public function asignarCurso(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $profesorId = $this->asignarPorCurso ? $this->profesorParaAsignarId : $this->profesorSeleccionadoId;

        $this->validate([
            'cursoAAsignarId' => ['required', 'exists:cursos,id'],
            ...($this->asignarPorCurso
                ? ['profesorParaAsignarId' => ['required', 'exists:users,id']]
                : ['profesorSeleccionadoId' => ['required', 'exists:users,id']]),
        ], [
            'cursoAAsignarId.required' => 'Selecciona un curso.',
            'profesorParaAsignarId.required' => 'Selecciona un profesor.',
        ]);

        $curso = Curso::findOrFail($this->cursoAAsignarId);
        $profesor = User::role(Rol::Profesor->value)->findOrFail($profesorId);

        if ($curso->estaFinalizado()) {
            $this->addError('cursoAAsignarId', 'El curso está archivado (finalizado) y no admite cambios.');

            return;
        }

        $anterior = $curso->profesor?->name;
        $curso->update(['profesor_id' => $profesor->id]);
        // Si ya era profesor adicional, pasa a titular y deja de figurar como adicional.
        $curso->profesoresAdicionales()->detach($profesor->id);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'asignacion_profesor',
            descripcion: "Profesor asignado al curso \"{$curso->nombre}\".",
            usuarioAfectado: $profesor,
            curso: $curso,
            valorAnterior: $anterior,
            valorNuevo: $profesor->name,
        );

        $this->mostrarModalAsignar = false;
        $this->reset(['profesorSeleccionadoId', 'cursoAAsignarId', 'profesorParaAsignarId', 'asignarPorCurso']);
        session()->flash('estado', "Curso \"{$curso->nombre}\" asignado a {$profesor->name}.");
    }

    public function confirmarDesasignarCurso(int $cursoId): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        $curso = Curso::findOrFail($cursoId);
        $profesor = $curso->profesor;

        $this->confirmacionAccion = 'desasignarCurso';
        $this->confirmacionObjetivoId = $cursoId;
        $this->confirmacionTitulo = 'Desvincular profesor';
        $this->confirmacionMensaje = '¿Desvincular a '.$this->resaltar($profesor?->name ?? '').' del curso '.$this->resaltar($curso->nombre).'? El curso quedará a cargo del Supervisor.';
        $this->confirmacionTextoBoton = 'Desvincular';
        $this->confirmacionVariante = 'peligro';
        $this->mostrarConfirmacion = true;
    }

    /**
     * Retira al profesor titular del curso. Si el curso tiene profesores
     * adicionales (RF-23), el primero pasa a ser titular. Si no, el curso queda
     * a nombre del Supervisor (profesor_id es obligatorio) hasta asignar otro
     * profesor; mientras tanto nadie puede gestionar su contenido ni sus notas.
     */
    public function desasignarCurso(int $cursoId): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $curso = Curso::findOrFail($cursoId);

        if ($curso->estaFinalizado()) {
            session()->flash('error', "El curso \"{$curso->nombre}\" está archivado y no admite cambios.");

            return;
        }

        $anterior = $curso->profesor?->name;
        $sucesor = $curso->profesoresAdicionales()->orderBy('curso_profesor.id')->first();

        if ($sucesor) {
            $curso->update(['profesor_id' => $sucesor->id]);
            $curso->profesoresAdicionales()->detach($sucesor->id);
        } else {
            $curso->update(['profesor_id' => auth()->id()]);
        }

        $nuevoResponsable = $sucesor ?? auth()->user();

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'asignacion_profesor',
            descripcion: $sucesor
                ? "Profesor titular retirado del curso \"{$curso->nombre}\"; {$sucesor->name} pasa de profesor adicional a titular."
                : "Profesor retirado del curso \"{$curso->nombre}\"; el curso queda a cargo del Supervisor hasta asignar otro profesor.",
            curso: $curso,
            valorAnterior: $anterior,
            valorNuevo: $nuevoResponsable->name,
        );

        session()->flash('estado', $sucesor
            ? "Docente desvinculado del curso \"{$curso->nombre}\". {$sucesor->name} queda como profesor titular."
            : "Docente desvinculado del curso \"{$curso->nombre}\". Asigna otro profesor: mientras tanto nadie puede gestionar su contenido ni sus notas.");
    }
}
