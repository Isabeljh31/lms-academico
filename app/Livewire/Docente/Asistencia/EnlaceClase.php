<?php

namespace App\Livewire\Docente\Asistencia;

use App\Models\Curso;
use App\Models\SesionClase;
use App\Services\Asistencia\EnlacesClaseRemota;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * «Enlace de esta clase» del Profesor (Asistencia, menú ⋮ de una clase
 * remota futura o en curso): modal pequeño para darle a la clase un enlace
 * distinto al del curso o volver a heredarlo. El enlace guardado no se
 * expone como propiedad pública: el estado (propio, heredado o sin enlace,
 * con solo el dominio) se calcula en render(). La lógica está en
 * EnlacesClaseRemota.
 */
class EnlaceClase extends Component
{
    public Curso $curso;

    #[Locked]
    public ?int $sesionId = null;

    public bool $abierto = false;

    /** Enlace que el Profesor escribe (vacío al abrir; nunca se precarga el guardado). */
    public string $enlaceNuevo = '';

    /** Confirmación tras guardar o quitar (aviso flotante fuera del modal). */
    public ?string $aviso = null;

    public function mount(Curso $curso): void
    {
        Gate::authorize('editarEnlaceRemoto', $curso);
        $this->curso = $curso;
    }

    #[On('abrir-enlace-clase')]
    public function abrir(int $sesion): void
    {
        Gate::authorize('editarEnlaceRemoto', $this->curso);
        $clase = $this->curso->sesionesClase()->findOrFail($sesion);

        $this->resetErrorBag();
        $this->reset(['enlaceNuevo', 'aviso']);
        $this->sesionId = $clase->id;
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->sesionId = null;
        $this->resetErrorBag();
    }

    public function guardar(EnlacesClaseRemota $enlaces): void
    {
        // Guardar vacío no borra nada: quitar el enlace propio es la acción «Usar el enlace del curso».
        if (trim($this->enlaceNuevo) === '') {
            $this->addError('enlace_remoto', 'Pega el enlace de la reunión o usa «Usar el enlace del curso».');

            return;
        }

        $enlaces->guardarDeClase(auth()->user(), $this->clase(), $this->enlaceNuevo);

        $this->terminar('Enlace de la clase guardado.');
    }

    /** Quita el enlace propio: la clase vuelve a usar el del curso. */
    public function heredar(EnlacesClaseRemota $enlaces): void
    {
        $enlaces->guardarDeClase(auth()->user(), $this->clase(), null);

        $this->terminar('La clase vuelve a usar el enlace del curso.');
    }

    public function render(EnlacesClaseRemota $enlaces): View
    {
        $clase = $this->abierto && $this->sesionId !== null && Gate::allows('editarEnlaceRemoto', $this->curso)
            ? $this->curso->sesionesClase()->with('curso')->find($this->sesionId)
            : null;

        return view('livewire.docente.asistencia.enlace-clase', [
            'clase' => $clase,
            'dominioPropio' => $enlaces->dominioDe($clase?->enlace_remoto),
            'dominioCurso' => $clase ? $enlaces->dominioDe($this->curso->enlace_remoto) : null,
            'dominiosPermitidos' => $enlaces->textoDominiosPermitidos(),
        ]);
    }

    /** Clase del curso abierto (protección IDOR). */
    protected function clase(): SesionClase
    {
        abort_if($this->sesionId === null, 404);

        return $this->curso->sesionesClase()->with('curso')->findOrFail($this->sesionId);
    }

    protected function terminar(string $mensaje): void
    {
        $this->cerrar();
        $this->reset('enlaceNuevo');
        $this->aviso = $mensaje;
    }
}
