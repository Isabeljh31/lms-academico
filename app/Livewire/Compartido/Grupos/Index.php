<?php

namespace App\Livewire\Compartido\Grupos;

use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * ERS RF-74: crear, renombrar y eliminar grupos del curso.
 * ERS RF-75: agregar y mover estudiantes matriculados entre grupos
 *            (un estudiante pertenece a lo más a un grupo por curso).
 *
 * La pantalla es un tablero: el panel «Sin grupo» y los grupos se ven a la
 * vez y los estudiantes se arrastran entre ellos (SortableJS), se mueven con
 * el teclado o con el menú «Mover a…». Todo termina en mover(), que guarda
 * al soltar y deja un «Deshacer» para el último cambio.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public Curso $curso;

    public string $nuevoGrupoNombre = '';

    public int $tamanoGrupoAuto = 4;

    /** Máximo de integrantes por grupo; vacío = sin límite. */
    public string $tamanoMaximo = '';

    #[Locked]
    public ?int $grupoRenombrarId = null;

    public string $nombreRenombrar = '';

    /** Mensaje breve del último cambio (barra inferior). */
    #[Locked]
    public ?string $aviso = null;

    #[Locked]
    public string $avisoTipo = 'exito';

    /** Cambia en cada aviso para que la barra vuelva a mostrarse. */
    #[Locked]
    public int $avisoNumero = 0;

    /**
     * Lo necesario para deshacer el último cambio.
     *
     * @var array{tipo: 'mover', previos: array<int, int|null>}|array{tipo: 'eliminar', nombre: string, estudiantes: list<int>, entregas: list<int>}|null
     */
    #[Locked]
    public ?array $deshacer = null;

    /** @var list<int> estudiantes que acaban de entrar a un grupo (se animan). */
    #[Locked]
    public array $recienMovidos = [];

    public function mount(Curso $curso): void
    {
        // Consulta: Profesor del curso y Supervisor. Cambios: solo el Profesor (RF-74, RF-75).
        Gate::authorize('supervisar', $curso);
        $this->curso = $curso;
        $this->tamanoMaximo = (string) ($curso->tamano_maximo_grupo ?? '');
        $this->tamanoGrupoAuto = $curso->tamano_maximo_grupo ?? 4;
    }

    public function getEditableProperty(): bool
    {
        return Gate::allows('crear', [Grupo::class, $this->curso]) && ! $this->curso->estaFinalizado();
    }

    public function crearGrupo(): void
    {
        $this->autorizar();

        $this->validate([
            'nuevoGrupoNombre' => ['required', 'string', 'max:255', Rule::unique('grupos', 'nombre')->where('curso_id', $this->curso->id)],
        ], [
            'nuevoGrupoNombre.required' => 'Escribe el nombre del grupo.',
            'nuevoGrupoNombre.unique' => 'Ya existe un grupo con ese nombre en el curso.',
        ]);

        $this->curso->grupos()->create(['nombre' => trim($this->nuevoGrupoNombre)]);
        $this->avisar("Grupo «{$this->nuevoGrupoNombre}» creado.");
        $this->nuevoGrupoNombre = '';
    }

    public function empezarRenombrar(int $grupoId): void
    {
        $this->autorizar();
        $this->resetErrorBag('nombreRenombrar');
        $grupo = $this->curso->grupos()->findOrFail($grupoId);
        $this->grupoRenombrarId = $grupo->id;
        $this->nombreRenombrar = $grupo->nombre;
    }

    public function cancelarRenombrar(): void
    {
        $this->autorizar();
        $this->reset(['grupoRenombrarId', 'nombreRenombrar']);
        $this->resetErrorBag('nombreRenombrar');
    }

    public function renombrar(): void
    {
        $this->autorizar();
        $grupo = $this->curso->grupos()->findOrFail($this->grupoRenombrarId);

        $this->validate([
            'nombreRenombrar' => ['required', 'string', 'max:255', Rule::unique('grupos', 'nombre')->where('curso_id', $this->curso->id)->ignore($grupo->id)],
        ], [
            'nombreRenombrar.required' => 'Escribe el nombre del grupo.',
            'nombreRenombrar.unique' => 'Ya existe un grupo con ese nombre en el curso.',
        ]);

        $grupo->update(['nombre' => trim($this->nombreRenombrar)]);
        $this->reset(['grupoRenombrarId', 'nombreRenombrar']);
        $this->avisar('Grupo renombrado.');
    }

    /** Sus integrantes vuelven a «Sin grupo»; las entregas se conservan. */
    public function eliminarGrupo(int $grupoId): void
    {
        $this->autorizar();
        $grupo = $this->curso->grupos()->with('estudiantes')->findOrFail($grupoId);
        Gate::authorize('gestionar', $grupo);

        $estudiantes = $grupo->estudiantes->modelKeys();
        $entregas = Entrega::where('grupo_id', $grupo->id)->pluck('id')->all();
        $grupo->delete();

        $cantidad = count($estudiantes);
        $this->avisar("Grupo «{$grupo->nombre}» eliminado.".match ($cantidad) {
            0 => '',
            1 => ' Su integrante volvió a Sin grupo.',
            default => " Sus {$cantidad} integrantes volvieron a Sin grupo.",
        }, deshacer: ['tipo' => 'eliminar', 'nombre' => $grupo->nombre, 'estudiantes' => $estudiantes, 'entregas' => $entregas]);
    }

    /**
     * Mueve estudiantes matriculados al grupo ($grupoId) o los devuelve a
     * «Sin grupo» (null). Antes se les quita de cualquier otro grupo del
     * curso. Respeta el máximo de integrantes.
     *
     * @param  array<int, int|string>  $estudianteIds
     */
    public function mover(array $estudianteIds, ?int $grupoId = null): void
    {
        $this->autorizar();
        $ids = collect($estudianteIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        abort_if($ids->isEmpty() || $ids->count() > 200, 422);
        abort_unless($this->curso->inscripcionesActivas()->whereIn('estudiante_id', $ids)->count() === $ids->count(), 403, 'El estudiante no está matriculado en el curso.');

        $grupo = null;
        if ($grupoId !== null) {
            $grupo = $this->curso->grupos()->find($grupoId) ?? abort(404);
            Gate::authorize('gestionar', $grupo);
            $quedarian = $grupo->estudiantes()->whereNotIn('users.id', $ids)->count() + $ids->count();
            $maximo = $this->curso->tamano_maximo_grupo;
            if ($maximo !== null && $quedarian > $maximo) {
                $libres = max(0, $maximo - ($quedarian - $ids->count()));
                $this->avisar("{$grupo->nombre} admite {$maximo} integrantes".($libres > 0 ? " y solo le queda espacio para {$libres}." : ' y ya está completo.'), 'error');

                return;
            }
        }

        $previos = $this->gruposActualesDe($ids);
        if ($ids->every(fn ($id) => $previos[$id] === $grupo?->id)) {
            return;
        }

        $this->asignarA($ids->all(), $grupo?->id);

        $nombres = User::whereKey($ids)->orderBy('name')->pluck('name');
        $quien = $nombres->count() === 1 ? $nombres->first() : "{$nombres->count()} estudiantes";
        $verbo = $nombres->count() === 1 ? 'está' : 'están';
        $this->recienMovidos = $grupo ? $ids->all() : [];
        $this->avisar($grupo ? "{$quien} ahora {$verbo} en {$grupo->nombre}." : "{$quien} ".($nombres->count() === 1 ? 'volvió' : 'volvieron').' a Sin grupo.',
            deshacer: ['tipo' => 'mover', 'previos' => $previos]);
    }

    public function deshacerUltimo(): void
    {
        $this->autorizar();
        abort_if($this->deshacer === null, 404);

        if ($this->deshacer['tipo'] === 'mover') {
            $matriculados = $this->curso->inscripcionesActivas()->whereIn('estudiante_id', array_keys($this->deshacer['previos']))->pluck('estudiante_id');
            $existentes = $this->curso->grupos()->pluck('id');
            DB::transaction(function () use ($matriculados, $existentes) {
                foreach ($matriculados as $estudianteId) {
                    $previo = $this->deshacer['previos'][$estudianteId] ?? null;
                    $this->asignarA([$estudianteId], $existentes->contains($previo) ? $previo : null);
                }
            });
        } else {
            DB::transaction(function () {
                $nombre = $this->deshacer['nombre'];
                if ($this->curso->grupos()->where('nombre', $nombre)->exists()) {
                    $nombre .= ' (recuperado)';
                }
                $grupo = $this->curso->grupos()->create(['nombre' => $nombre]);
                $sinGrupo = $this->curso->inscripcionesActivas()->whereIn('estudiante_id', $this->deshacer['estudiantes'])
                    ->whereNotIn('estudiante_id', DB::table('grupo_estudiante')->whereIn('grupo_id', $this->curso->grupos()->select('id'))->select('estudiante_id'))
                    ->pluck('estudiante_id');
                $grupo->estudiantes()->attach($sinGrupo->all());
                Entrega::whereKey($this->deshacer['entregas'])->whereNull('grupo_id')->update(['grupo_id' => $grupo->id]);
            });
        }

        $this->recienMovidos = [];
        $this->avisar('Cambio deshecho.');
    }

    /**
     * Reparte en grupos nuevos a los estudiantes matriculados que todavía no
     * están en ninguno, en bloques del tamaño indicado.
     */
    public function generarAutomatico(): void
    {
        $this->autorizar();
        $maximo = $this->curso->tamano_maximo_grupo ?? 20;
        $this->validate(['tamanoGrupoAuto' => ['required', 'integer', 'min:2', "max:{$maximo}"]], [
            'tamanoGrupoAuto.max' => "El máximo por grupo es {$maximo}.",
        ]);

        $sinGrupo = $this->curso->inscripcionesActivas()
            ->whereNotIn('estudiante_id', DB::table('grupo_estudiante')->whereIn('grupo_id', $this->curso->grupos()->select('id'))->select('estudiante_id'))
            ->pluck('estudiante_id')
            ->shuffle()
            ->values();

        if ($sinGrupo->isEmpty()) {
            $this->addError('tamanoGrupoAuto', 'No hay estudiantes sin grupo para repartir.');

            return;
        }

        $existentes = $this->curso->grupos()->pluck('nombre');
        $numero = $this->curso->grupos()->count();
        $bloques = $sinGrupo->chunk($this->tamanoGrupoAuto)->values();

        DB::transaction(function () use ($bloques, $existentes, &$numero) {
            foreach ($bloques as $bloque) {
                do {
                    $nombre = 'Grupo '.++$numero;
                } while ($existentes->contains($nombre));
                $this->curso->grupos()->create(['nombre' => $nombre])->estudiantes()->attach($bloque->all());
            }
        });

        $this->recienMovidos = [];
        $this->avisar($bloques->count() === 1 ? '1 grupo generado.' : "{$bloques->count()} grupos generados.");
    }

    public function guardarTamanoMaximo(): void
    {
        $this->autorizar();
        $this->validate(['tamanoMaximo' => ['nullable', 'integer', 'min:2', 'max:20']], [
            'tamanoMaximo.integer' => 'Escribe un número entre 2 y 20, o déjalo vacío.',
            'tamanoMaximo.min' => 'Escribe un número entre 2 y 20, o déjalo vacío.',
            'tamanoMaximo.max' => 'Escribe un número entre 2 y 20, o déjalo vacío.',
        ]);

        $maximo = $this->tamanoMaximo === '' ? null : (int) $this->tamanoMaximo;
        $this->curso->update(['tamano_maximo_grupo' => $maximo]);
        if ($maximo !== null) {
            $this->tamanoGrupoAuto = min($this->tamanoGrupoAuto, $maximo);
        }
        $this->avisar($maximo === null ? 'Los grupos ya no tienen límite de integrantes.' : "Máximo por grupo: {$maximo} integrantes.");
    }

    /**
     * Grupo actual (o null) de cada estudiante dentro de este curso.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $ids
     * @return array<int, int|null>
     */
    protected function gruposActualesDe($ids): array
    {
        $actuales = DB::table('grupo_estudiante')
            ->whereIn('grupo_id', $this->curso->grupos()->select('id'))
            ->whereIn('estudiante_id', $ids)
            ->pluck('grupo_id', 'estudiante_id');

        return $ids->mapWithKeys(fn ($id) => [$id => isset($actuales[$id]) ? (int) $actuales[$id] : null])->all();
    }

    /** @param  list<int>  $estudianteIds */
    protected function asignarA(array $estudianteIds, ?int $grupoId): void
    {
        DB::transaction(function () use ($estudianteIds, $grupoId) {
            DB::table('grupo_estudiante')
                ->whereIn('grupo_id', $this->curso->grupos()->select('id'))
                ->whereIn('estudiante_id', $estudianteIds)
                ->delete();

            if ($grupoId !== null) {
                Grupo::findOrFail($grupoId)->estudiantes()->attach($estudianteIds);
            }
        });
    }

    /** @param  array<string, mixed>|null  $deshacer */
    protected function avisar(string $mensaje, string $tipo = 'exito', ?array $deshacer = null): void
    {
        $this->dispatch('grupos-actualizados');
        $this->aviso = $mensaje;
        $this->avisoTipo = $tipo;
        $this->avisoNumero++;
        if ($tipo === 'exito') {
            $this->deshacer = $deshacer;
        }
    }

    protected function autorizar(): void
    {
        Gate::authorize('crear', [Grupo::class, $this->curso]);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado y no admite cambios.');
    }

    public function render()
    {
        /** @var Collection<int, Grupo> $grupos */
        $grupos = $this->curso->grupos()->with(['estudiantes' => fn ($q) => $q->orderBy('name')])->get()
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $grupoDe = $grupos->mapWithKeys(fn (Grupo $g) => $g->estudiantes->mapWithKeys(fn ($e) => [$e->id => $g->id])->all());
        $matriculados = $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter()->sortBy('name')->values();

        return view('livewire.compartido.grupos.index', [
            'grupos' => $grupos,
            'sinGrupo' => $matriculados->reject(fn ($estudiante) => $grupoDe->has($estudiante->id))->values(),
            'maximo' => $this->curso->tamano_maximo_grupo,
        ])->title("Grupos — {$this->curso->nombre}");
    }
}
