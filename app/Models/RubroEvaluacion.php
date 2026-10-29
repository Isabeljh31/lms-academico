<?php

namespace App\Models;

use App\Enums\ModoCalculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class RubroEvaluacion extends Model
{
    protected $table = 'rubros_evaluacion';

    protected $fillable = [
        'esquema_id',
        'nombre',
        'semana',
        'peso',
        'orden',
        'modo_calculo',
    ];

    protected function casts(): array
    {
        return [
            'modo_calculo' => ModoCalculo::class,
            'peso' => 'decimal:2',
            'semana' => 'integer',
            'orden' => 'integer',
        ];
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    public function esquema(): BelongsTo
    {
        return $this->belongsTo(EsquemaEvaluacion::class, 'esquema_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(NotaRubro::class, 'rubro_id');
    }

    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'rubro_tarea', 'rubro_id', 'tarea_id')->withPivot('peso');
    }

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'rubro_quiz', 'rubro_id', 'quiz_id')->withPivot('peso');
    }

    public function notaDeEstudiante(User $estudiante): ?NotaRubro
    {
        return $this->notas()->where('estudiante_id', $estudiante->id)->first();
    }

    // ==========================================
    // CÁLCULO DE LA NOTA
    // ==========================================

    /** La nota sale de las actividades vinculadas (promedio ponderado o examen). */
    public function esAutomatico(): bool
    {
        return $this->modo_calculo?->esAutomatico() ?? false;
    }

    /**
     * Tareas y quizzes vinculados con su peso dentro de la evaluación.
     *
     * @return Collection<int, array{tipo: string, id: int, titulo: string, peso: float}>
     */
    public function actividadesVinculadas(): Collection
    {
        return $this->tareas->map(fn (Tarea $tarea) => ['tipo' => 'tarea', 'id' => $tarea->id, 'titulo' => $tarea->titulo, 'peso' => (float) $tarea->pivot->peso])
            ->concat($this->quizzes->map(fn (Quiz $quiz) => ['tipo' => 'quiz', 'id' => $quiz->id, 'titulo' => $quiz->tema, 'peso' => (float) $quiz->pivot->peso]))
            ->sortByDesc('peso')->values();
    }

    /**
     * Estado de la configuración: «sin_configurar» (nunca se eligió modo),
     * «incompleta» (automática sin actividades o con pesos que no suman 100)
     * o «lista».
     */
    public function estadoCalculo(): string
    {
        if ($this->modo_calculo === null) {
            return 'sin_configurar';
        }

        if (! $this->esAutomatico()) {
            return 'lista';
        }

        $actividades = $this->actividadesVinculadas();

        return $actividades->isNotEmpty() && abs($actividades->sum('peso') - 100) < 0.01 ? 'lista' : 'incompleta';
    }
}
