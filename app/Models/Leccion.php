<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ERS RF-35: Lección como sub-entidad dentro del Módulo.
 * RF-36: Puede tener video_url y material_url.
 * RF-37: Estado borrador | publicado | programado.
 */
class Leccion extends Model
{
    protected $table = 'lecciones';

    /**
     * ERS RF-67: condición que define el Profesor para dar la lección por completada.
     *
     * @var array<string, string>
     */
    public const CONDICIONES = [
        'marcar' => 'El estudiante la marca como vista',
        'abrir_recurso' => 'Al abrir su video o su material',
    ];

    /**
     * Secciones fijas del contenido de cada semana. La sección se guarda en
     * `tipo_recurso` y define el formulario del Profesor, la etiqueta y el
     * ícono que ve el estudiante.
     *
     * @var array<string, array{etiqueta: string, elemento: string, icono: string, descripcion: string}>
     */
    public const SECCIONES = [
        'mensaje_inicio' => [
            'etiqueta' => 'Mensaje de inicio',
            'elemento' => 'Mensaje de inicio',
            'icono' => 'megafono',
            'descripcion' => 'Bienvenida o pregunta de reflexión para empezar la semana.',
        ],
        'recursos' => [
            'etiqueta' => 'Recursos de aprendizaje',
            'elemento' => 'Recurso de aprendizaje',
            'icono' => 'libro',
            'descripcion' => 'Materiales de estudio: documentos, presentaciones, imágenes o enlaces.',
        ],
        'actividades' => [
            'etiqueta' => 'Actividades complementarias',
            'elemento' => 'Actividad complementaria',
            'icono' => 'lista',
            'descripcion' => 'Ejercicios, laboratorios, casos de estudio y lecturas sin nota.',
        ],
        'grabacion' => [
            'etiqueta' => 'Grabación de la clase',
            'elemento' => 'Grabación de la clase',
            'icono' => 'grabacion',
            'descripcion' => 'Enlace a la grabación de la sesión.',
        ],
    ];

    /** Sección antigua que ya no se ofrece, pero cuyas lecciones se siguen mostrando y editando. */
    public const SECCION_HEREDADA = 'foro';

    /**
     * Equivalencia de los tipos antiguos con las secciones. Solo se aplica al
     * leer: la base de datos no se modifica y el valor nuevo se escribe cuando
     * el Profesor guarda esa lección.
     *
     * @var array<string, string>
     */
    public const EQUIVALENCIAS_ANTIGUAS = [
        'lectura' => 'recursos',
        'presentacion' => 'recursos',
        'video' => 'recursos',
        'enlace' => 'recursos',
        'recurso_externo' => 'recursos',
        'documento' => 'recursos',
        'actividad' => 'actividades',
        'actividad_complementaria' => 'actividades',
        'grabacion_sesion' => 'grabacion',
        'foro_preguntas' => 'foro',
    ];

    /**
     * Etiqueta e ícono de los tipos antiguos, para que las lecciones creadas
     * antes se sigan mostrando igual hasta que el Profesor las guarde.
     *
     * @var array<string, array{etiqueta: string, icono: string}>
     */
    public const TIPOS_ANTIGUOS = [
        'lectura' => ['etiqueta' => 'Lectura', 'icono' => 'libro'],
        'video' => ['etiqueta' => 'Video', 'icono' => 'video'],
        'presentacion' => ['etiqueta' => 'Presentación', 'icono' => 'presentacion'],
        'foro' => ['etiqueta' => 'Foro de preguntas', 'icono' => 'chat'],
        'actividad' => ['etiqueta' => 'Actividad complementaria', 'icono' => 'lista'],
        'enlace' => ['etiqueta' => 'Recurso externo', 'icono' => 'enlace'],
        'documento' => ['etiqueta' => 'Documento', 'icono' => 'documento'],
    ];

    /**
     * Formatos de archivo de Recursos y Actividades: PDF, Word, Excel,
     * PowerPoint e imágenes.
     *
     * @var array<int, string>
     */
    public const EXTENSIONES_ARCHIVO = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'gif', 'webp'];

    /**
     * Sección con que se muestra y se edita un tipo guardado. Un valor
     * desconocido o vacío (la columna usa "lectura" por defecto) va a Recursos.
     */
    public static function seccionDe(?string $tipo): string
    {
        $seccion = self::EQUIVALENCIAS_ANTIGUAS[$tipo] ?? $tipo;

        if ($seccion === self::SECCION_HEREDADA || array_key_exists((string) $seccion, self::SECCIONES)) {
            return $seccion;
        }

        return 'recursos';
    }

    public function seccion(): string
    {
        return self::seccionDe($this->tipo_recurso);
    }

    protected $attributes = [
        'condicion_completitud' => 'marcar',
        'tipo_recurso' => 'lectura',
    ];

    protected $fillable = [
        'modulo_id', 'titulo', 'descripcion', 'video_url',
        'material_url', 'material_nombre', 'estado', 'fecha_publicacion', 'orden', 'condicion_completitud',
        'tipo_recurso',
    ];

    /**
     * Ícono y etiqueta de la lección para el contenido semanal (Profesor y
     * Estudiante). Los tipos antiguos conservan los suyos; en Recursos de
     * aprendizaje el ícono sale de la extensión del archivo o de la URL.
     *
     * @return array{etiqueta: string, icono: string}
     */
    public function tipoRecurso(): array
    {
        if (isset(self::TIPOS_ANTIGUOS[$this->tipo_recurso])) {
            return self::TIPOS_ANTIGUOS[$this->tipo_recurso];
        }

        $seccion = $this->seccion();

        if ($seccion === self::SECCION_HEREDADA) {
            return self::TIPOS_ANTIGUOS['foro'];
        }

        return [
            'etiqueta' => self::SECCIONES[$seccion]['elemento'],
            'icono' => $seccion === 'recursos' ? $this->iconoDelMaterial() : self::SECCIONES[$seccion]['icono'],
        ];
    }

    /** Ícono según la extensión del archivo o el tipo de enlace del material. */
    public function iconoDelMaterial(): string
    {
        $referencia = (string) ($this->material_nombre ?: $this->material_url ?: $this->video_url);

        if (preg_match('#^https?://#i', $referencia)) {
            return preg_match('#(youtube\.com|youtu\.be|vimeo\.com)#i', $referencia) ? 'video' : 'enlace';
        }

        return match (strtolower(pathinfo(parse_url($referencia, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION))) {
            'ppt', 'pptx' => 'presentacion',
            'xls', 'xlsx', 'csv' => 'grafico',
            'zip', 'rar' => 'archivo',
            '' => 'libro',
            default => 'documento',
        };
    }

    protected $casts = [
        'fecha_publicacion' => 'datetime',
        'orden' => 'integer',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * ERS RF-38: Visible para estudiante solo si está publicada
     * (o programada con fecha pasada).
     */
    public function esVisibleParaEstudiante(): bool
    {
        if ($this->estado === 'publicado') {
            return true;
        }
        if ($this->estado === 'programado' && $this->fecha_publicacion?->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * ERS RF-38: publicadas, o programadas con fecha ya alcanzada. Las dos
     * condiciones van agrupadas para no anular otros filtros de la consulta.
     */
    public function scopePublicadas($query)
    {
        return $query->where(fn ($q) => $q
            ->where('estado', 'publicado')
            ->orWhere(fn ($q) => $q
                ->where('estado', 'programado')
                ->where('fecha_publicacion', '<=', now())
            )
        );
    }

    /** La lección se completa sola al abrir su recurso (y tiene un recurso que abrir). */
    public function seCompletaAlAbrir(): bool
    {
        return $this->condicion_completitud === 'abrir_recurso' && ($this->video_url || $this->material_url);
    }

    public function completadaPor(User $estudiante): bool
    {
        return $this->completadas()->where('estudiante_id', $estudiante->id)->exists();
    }

    /** ERS RF-66/RF-67: estudiantes que marcaron la lección como completada. */
    public function completadas(): HasMany
    {
        return $this->hasMany(LeccionCompletada::class, 'leccion_id');
    }
}
