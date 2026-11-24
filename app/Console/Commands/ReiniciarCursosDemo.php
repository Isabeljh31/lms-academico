<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Livewire\Profesor\PorCalificar\Index as PorCalificar;
use App\Models\BitacoraAuditoria;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Debate;
use App\Models\Entrega;
use App\Models\EsquemaEvaluacion;
use App\Models\EventoCalendario;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Mensaje;
use App\Models\Modulo;
use App\Models\PropuestaBot;
use App\Models\Quiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Models\User;
use App\Services\ConfiguracionPlataforma;
use App\Services\Evaluacion\AsignadorEvaluacion;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Evaluacion\PracticasEnEvaluaciones;
use App\Services\Matricula\GestorMatriculas;
use App\Services\Reportes\CursosProfesor;
use App\Services\Reportes\PanelProfesor;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;
use Throwable;

/**
 * Borra TODOS los cursos (con todo lo que cuelga de ellos) y siembra cursos
 * nuevos de demostración con fechas relativas a hoy, en UNA transacción.
 *
 * - Solo DELETE de filas acotado por ids de curso (y dependientes) e INSERT.
 *   No toca usuarios, roles/permisos, migraciones, esquema ni triggers.
 * - La bitácora es inmutable (trigger): no se borra; la FK deja curso_id en
 *   NULL. La siembra también escribe bitácora (vía los servicios), que queda.
 * - Se borran las notificaciones cuyo data.url apunta inequívocamente a
 *   /cursos/{id} de un curso borrado, y las propuestas del bot (y su evento
 *   de calendario) cuyo datos_propuesta.curso_id es de un curso borrado.
 * - La siembra usa los usuarios existentes y los servicios de la app
 *   (GestorMatriculas, AsignadorEvaluacion, PracticasEnEvaluaciones,
 *   Curso::generarSesionesDesdeHorario); Mail, Notification y Queue se simulan.
 *
 * Sin --apply simula y hace rollback (incluida una prueba de --restore).
 * Respaldo en storage/app/backups-demo/<fecha-hora>_reinicio/.
 */
class ReiniciarCursosDemo extends Command
{
    protected $signature = 'app:reiniciar-cursos-demo
        {--apply : Escribe de verdad (sin esta opción solo simula)}
        {--dry-run : Solo simula dentro de una transacción con rollback (predeterminado)}
        {--restore= : Carpeta de respaldo a restaurar (ruta completa o nombre dentro de storage/app/backups-demo)}
        {--semilla=2026 : Semilla del contenido aleatorio}';

    protected $description = 'Borra todos los cursos y siembra cursos de demostración con fechas actuales (simula por defecto)';

    /** Tablas cuyo contenido debe quedar idéntico (huella md5 antes y después). */
    protected const TABLAS_INTOCABLES = ['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'migrations', 'configuraciones_plataforma', 'password_reset_tokens', 'failed_jobs', 'jobs', 'job_batches'];

    protected const PROFESORES = ['pepito' => 'Pepito Reyes Diaz', 'jose' => 'José Cuya Camara', 'gerardo' => 'Gerardo Sarmiento'];

    /**
     * codigo, nombre, titular, estado final, semanas, inicio (semanas desde el lunes actual), modalidad, cupo, matriculados, casos.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int, 6: string, 7: int, 8: int, 9: list<string>}>
     */
    protected const CURSOS = [
        ['EST-26-01', 'Estadística I', 'pepito', 'publicado', 16, -10, 'presencial', 40, 36, ['masiva', 'bajas', 'debate', 'anuncio']],
        ['CAL2-26-01', 'Cálculo 2', 'pepito', 'publicado', 16, -5, 'virtual', 35, 32, ['grupal_actual', 'bajas', 'debate', 'anuncio']],
        ['FIS-26-01', 'Física General', 'pepito', 'publicado', 8, -3, 'presencial', 30, 24, ['codocente', 'practicas', 'grupal_anterior', 'debate_borrador']],
        ['QUI-26-01', 'Química General', 'pepito', 'publicado', 5, -2, 'presencial', 20, 20, ['cupo_lleno']],
        ['PRO1-26-01', 'Programación 1', 'pepito', 'publicado', 8, -6, 'virtual', 30, 22, ['siguiente_vacia', 'grupal_anterior', 'tardias']],
        ['RED-26-01', 'Redacción Académica', 'pepito', 'publicado', 16, -1, 'remoto', 30, 18, ['todo_calificado', 'sin_entregas', 'debate']],
        ['BIO-26-01', 'Biología Celular', 'pepito', 'publicado', 5, 1, 'presencial', 25, 15, []],
        ['HIS-26-01', 'Historia del Perú', 'pepito', 'borrador', 16, 3, 'virtual', 30, 12, ['listo']],
        ['ECO-26-01', 'Economía General', 'pepito', 'borrador', 5, 10, 'presencial', 25, 0, []],
        ['ALG-26-01', 'Álgebra Lineal', 'pepito', 'borrador', 8, 2, 'virtual', 30, 8, ['sin_contenido']],
        ['MET-26-01', 'Metodología de la Investigación', 'pepito', 'despublicado', 8, -4, 'virtual', 20, 14, []],
        ['ANA-26-01', 'Análisis Matemático', 'pepito', 'publicado', 5, -5, 'presencial', 30, 16, ['espera_larga']],
        ['MIC-26-01', 'Microeconomía', 'pepito', 'archivado', 5, -8, 'virtual', 30, 18, []],
        ['GEO-26-01', 'Geografía General', 'jose', 'publicado', 16, -8, 'presencial', 30, 20, ['anuncio']],
        ['COM-26-01', 'Comunicación Oral', 'gerardo', 'publicado', 8, -2, 'virtual', 25, 15, []],
    ];

    /** @var array<string, list<string>> */
    protected const TEMAS = [
        'Estadística I' => ['Introducción a la estadística', 'Organización de datos', 'Tablas de frecuencia', 'Gráficos estadísticos', 'Medidas de tendencia central', 'Medidas de dispersión', 'Medidas de posición', 'Repaso para el parcial', 'Probabilidad básica', 'Probabilidad condicional', 'Variables aleatorias', 'Distribución binomial', 'Distribución normal', 'Muestreo', 'Estimación de parámetros', 'Repaso final'],
        'Cálculo 2' => ['Antiderivadas', 'Integral definida', 'Teorema fundamental del cálculo', 'Integración por sustitución', 'Integración por partes', 'Fracciones parciales', 'Integrales impropias', 'Repaso para el parcial', 'Áreas entre curvas', 'Volúmenes de sólidos', 'Longitud de arco', 'Sucesiones', 'Series', 'Series de potencias', 'Series de Taylor', 'Repaso final'],
        'Física General' => ['Magnitudes y unidades', 'Cinemática', 'Leyes de Newton', 'Trabajo y energía', 'Cantidad de movimiento', 'Rotación', 'Oscilaciones', 'Repaso final'],
        'Química General' => ['Materia y medición', 'Estructura atómica', 'Tabla periódica', 'Enlace químico', 'Estequiometría'],
        'Programación 1' => ['Algoritmos y diagramas de flujo', 'Variables y tipos de datos', 'Estructuras condicionales', 'Estructuras repetitivas', 'Funciones', 'Arreglos', 'Cadenas de texto', 'Proyecto final'],
        'Redacción Académica' => ['El texto académico', 'Coherencia y cohesión', 'El párrafo', 'Signos de puntuación', 'Normas APA', 'Citas y referencias', 'El resumen', 'Repaso para el parcial', 'La reseña', 'El ensayo', 'Argumentación', 'El informe', 'Revisión entre pares', 'Exposición oral', 'Corrección de estilo', 'Repaso final'],
        'Biología Celular' => ['La célula', 'Membrana celular', 'Organelos', 'Ciclo celular', 'Genética básica'],
        'Historia del Perú' => ['Poblamiento de América', 'Culturas preíncas', 'El Tahuantinsuyo', 'La conquista', 'El virreinato', 'Rebeliones del siglo XVIII', 'La independencia', 'Repaso para el parcial', 'Primeros años de la república', 'La era del guano', 'Guerra del Pacífico', 'La reconstrucción nacional', 'La república aristocrática', 'El oncenio de Leguía', 'El Perú contemporáneo', 'Repaso final'],
        'Economía General' => ['Conceptos básicos de economía', 'Oferta y demanda', 'Elasticidad', 'Mercados', 'Macroeconomía básica'],
        'Álgebra Lineal' => ['Sistemas de ecuaciones lineales', 'Matrices', 'Determinantes', 'Espacios vectoriales', 'Bases y dimensión', 'Transformaciones lineales', 'Valores propios', 'Repaso final'],
        'Metodología de la Investigación' => ['El problema de investigación', 'Marco teórico', 'Hipótesis y variables', 'Diseño metodológico', 'Población y muestra', 'Instrumentos', 'Análisis de datos', 'Proyecto de investigación'],
        'Análisis Matemático' => ['Números reales', 'Funciones', 'Límites', 'Continuidad', 'Derivadas'],
        'Microeconomía' => ['El consumidor', 'La demanda', 'La empresa', 'Costos de producción', 'Estructuras de mercado'],
        'Geografía General' => ['La Tierra en el universo', 'Cartografía', 'Relieve', 'Clima', 'Hidrografía', 'Biomas', 'Población', 'Repaso para el parcial', 'Ciudades', 'Actividades económicas', 'Recursos naturales', 'Riesgos naturales', 'Geografía del Perú', 'Regiones naturales', 'Ordenamiento territorial', 'Repaso final'],
        'Comunicación Oral' => ['La comunicación', 'La voz y la dicción', 'El lenguaje corporal', 'La exposición', 'El debate', 'La entrevista', 'Hablar en público', 'Exposición final'],
    ];

    protected const COMENTARIOS = ['Buen trabajo, revisa el último ejercicio.', 'Desarrollo claro y ordenado.', 'Falta justificar algunos pasos.', 'Excelente análisis, sigue así.', 'Revisa las unidades en tus respuestas.', 'Bien planteado; cuida la redacción de las conclusiones.'];

    protected Randomizer $azar;

    protected User $supervisor;

    /** @var array<string, User> */
    protected array $docentes = [];

    /** @var array<string, list<int>> filas sembradas que no cuelgan de un curso (para --restore) */
    protected array $otrasCreadas = ['comunicados' => [], 'eventos_calendario' => [], 'propuestas_bot' => []];

    protected string $carpeta = '';

    public function handle(ConfiguracionPlataforma $configuracion): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando no se ejecuta con APP_ENV=production.');

            return self::FAILURE;
        }

        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Usa --apply o --dry-run, no ambos.');

            return self::FAILURE;
        }

        $configuracion->aplicarZonaHoraria();
        $aplicar = (bool) $this->option('apply');

        if ($this->option('restore')) {
            return $this->restaurar((string) $this->option('restore'), $aplicar);
        }

        // Nada sale de la aplicación durante la siembra.
        config(['mail.default' => 'log']);
        Mail::fake();
        Notification::fake();
        Queue::fake();

        $this->prepararUsuarios();
        $cursos = DB::table('cursos')->orderBy('id')->pluck('id')->all();
        $plan = $this->plan($cursos);
        $this->carpeta = $this->respaldar($plan, $cursos, $aplicar);

        DB::beginTransaction();

        try {
            $antes = $this->foto();
            $bitacoraAntes = DB::table('bitacoras_auditoria')->count();
            $borradas = $this->borrar($plan);
            $this->exigir(DB::table('cursos')->count() === 0, 'quedaron cursos después del borrado');

            $sembrados = $this->sembrar();
            $insertadas = $this->contarSembrado($sembrados) + ['bitacoras_auditoria (permanentes)' => DB::table('bitacoras_auditoria')->count() - $bitacoraAntes];
            $errores = $this->verificar($plan, $borradas, $antes, $sembrados);
            $this->table(['Tabla', 'Borradas', 'Insertadas'], collect(array_keys($borradas + $insertadas))->unique()->map(fn (string $tabla) => [$tabla, $borradas[$tabla] ?? 0, $insertadas[$tabla] ?? 0])->values()->all());

            if ($errores !== []) {
                DB::rollBack();
                $this->error('Verificación fallida; no se escribió nada:');
                foreach ($errores as $error) {
                    $this->line("  - {$error}");
                }

                return self::FAILURE;
            }

            $this->info('Verificación: sin huérfanos, esquema/índices/constraints/triggers idénticos, usuarios/roles/permisos/migraciones idénticos.');
            $this->mostrarPaneles();

            if (! $aplicar) {
                $this->probarRestauracion($sembrados, $antes);
                DB::rollBack();
                $this->info("SIMULACIÓN: se hizo rollback, la base no cambió. Respaldo de prueba en {$this->carpeta}");

                return self::SUCCESS;
            }

            $this->escribirJson("{$this->carpeta}/creadas.json", ['cursos' => $sembrados, ...$this->otrasCreadas]);
            $this->actualizarManifiesto($this->carpeta);
            DB::commit();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        $this->info("APLICADO. Para revertir: php artisan app:reiniciar-cursos-demo --restore=\"{$this->carpeta}\" --apply");

        return self::SUCCESS;
    }

    // ==========================================
    // BORRADO
    // ==========================================

    /**
     * Orden seguro (hijos antes que padres). Los ids intermedios se fijan antes de borrar.
     *
     * @param  list<int>  $cursos
     * @return array<string, callable(): Builder>
     */
    protected function plan(array $cursos): array
    {
        $ids = fn (string $tabla, string $columna, array $valores) => DB::table($tabla)->whereIn($columna, $valores)->pluck('id')->all();
        $modulos = $ids('modulos', 'curso_id', $cursos);
        $tareas = $ids('tareas', 'curso_id', $cursos);
        $quizzes = $ids('quizzes', 'curso_id', $cursos);
        $grupos = $ids('grupos', 'curso_id', $cursos);
        $sesiones = $ids('sesiones_clase', 'curso_id', $cursos);
        $esquemas = $ids('esquemas_evaluacion', 'curso_id', $cursos);
        $rubros = $ids('rubros_evaluacion', 'esquema_id', $esquemas);
        $debates = $ids('debates', 'curso_id', $cursos);
        $lecciones = $ids('lecciones', 'modulo_id', $modulos);
        $porCurso = fn (string $tabla) => fn () => DB::table($tabla)->whereIn('curso_id', $cursos);
        $notificaciones = $this->notificacionesDe($cursos);
        $propuestas = DB::table('propuestas_bot')->get(['id', 'datos_propuesta'])
            ->filter(fn ($fila) => in_array((int) (json_decode((string) $fila->datos_propuesta, true)['curso_id'] ?? 0), $cursos, true));
        $eventos = $propuestas->map(fn ($fila) => (int) (json_decode((string) $fila->datos_propuesta, true)['evento_calendario_id'] ?? 0))->filter()->values()->all();

        return [
            'notifications' => fn () => DB::table('notifications')->whereIn('id', $notificaciones),
            'propuestas_bot' => fn () => DB::table('propuestas_bot')->whereIn('id', $propuestas->pluck('id')->all()),
            'eventos_calendario' => fn () => DB::table('eventos_calendario')->whereIn('id', $eventos),
            'lecciones_completadas' => fn () => DB::table('lecciones_completadas')->whereIn('leccion_id', $lecciones),
            'debate_comentarios' => fn () => DB::table('debate_comentarios')->whereIn('debate_id', $debates),
            'notas_rubro' => fn () => DB::table('notas_rubro')->whereIn('rubro_id', $rubros),
            'rubro_tarea' => fn () => DB::table('rubro_tarea')->where(fn ($q) => $q->whereIn('rubro_id', $rubros)->orWhereIn('tarea_id', $tareas)),
            'rubro_quiz' => fn () => DB::table('rubro_quiz')->where(fn ($q) => $q->whereIn('rubro_id', $rubros)->orWhereIn('quiz_id', $quizzes)),
            'resultados_quiz' => fn () => DB::table('resultados_quiz')->whereIn('quiz_id', $quizzes),
            'asistencias' => fn () => DB::table('asistencias')->whereIn('sesion_id', $sesiones),
            'entregas' => fn () => DB::table('entregas')->whereIn('tarea_id', $tareas),
            'grupo_estudiante' => fn () => DB::table('grupo_estudiante')->whereIn('grupo_id', $grupos),
            'tareas' => $porCurso('tareas'),
            'grupos' => $porCurso('grupos'),
            'quizzes' => $porCurso('quizzes'),
            'lecciones' => fn () => DB::table('lecciones')->whereIn('modulo_id', $modulos),
            'modulos' => $porCurso('modulos'),
            'rubros_evaluacion' => fn () => DB::table('rubros_evaluacion')->whereIn('esquema_id', $esquemas),
            'esquemas_evaluacion' => $porCurso('esquemas_evaluacion'),
            'sesiones_clase' => $porCurso('sesiones_clase'),
            'debates' => $porCurso('debates'),
            'inscripciones' => $porCurso('inscripciones'),
            'curso_profesor' => $porCurso('curso_profesor'),
            'banco_preguntas' => $porCurso('banco_preguntas'),
            'carpetas_curso' => $porCurso('carpetas_curso'),
            'comunicados' => $porCurso('comunicados'),
            'mensajes' => $porCurso('mensajes'),
            'conversaciones_bot' => $porCurso('conversaciones_bot'),
            // bitacoras_auditoria NO: trigger de inmutabilidad; la FK deja curso_id en NULL.
            'cursos' => fn () => DB::table('cursos')->whereIn('id', $cursos),
        ];
    }

    /**
     * Notificaciones cuyo data.url apunta a /cursos/{id} de uno de estos cursos (referencia inequívoca).
     *
     * @param  list<int>  $cursos
     * @return list<string>
     */
    protected function notificacionesDe(array $cursos): array
    {
        return DB::table('notifications')->get(['id', 'data'])
            ->filter(fn ($fila) => preg_match('~/cursos/(\d+)(?:/|$|\?)~', (string) (json_decode((string) $fila->data, true)['url'] ?? ''), $m) === 1 && in_array((int) $m[1], $cursos, true))
            ->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
    }

    /**
     * @param  array<string, callable(): Builder>  $plan
     * @return array<string, int>
     */
    protected function borrar(array $plan): array
    {
        return collect($plan)->map(fn (callable $consulta) => $consulta()->delete())->all();
    }

    // ==========================================
    // RESPALDO
    // ==========================================

    /**
     * @param  array<string, callable(): Builder>  $plan
     * @param  list<int>  $cursos
     */
    protected function respaldar(array $plan, array $cursos, bool $aplicar): string
    {
        $carpeta = storage_path('app/backups-demo/'.now()->format('Y-m-d_His').'_reinicio'.($aplicar ? '' : '_simulacion'));
        File::ensureDirectoryExists("{$carpeta}/filas");
        $conteos = [];

        foreach ($plan as $tabla => $consulta) {
            $filas = $consulta()->get()->map(fn ($fila) => (array) $fila)->all();
            $conteos[$tabla] = count($filas);
            $this->escribirJson("{$carpeta}/filas/{$tabla}.json", $filas);
        }

        $this->escribirJson("{$carpeta}/bitacora_desvinculada.json", DB::table('bitacoras_auditoria')->whereIn('curso_id', $cursos)->orderBy('id')->get(['id', 'curso_id'])->map(fn ($fila) => (array) $fila)->all());
        $this->escribirJson("{$carpeta}/archivos_ligados.json", $this->archivosLigados($carpeta));
        $this->escribirJson("{$carpeta}/creadas.json", []);
        $this->escribirJson("{$carpeta}/manifest.json", [
            'modo' => $aplicar ? 'apply' : 'dry-run',
            'fecha' => now()->toIso8601String(),
            'cursos_borrados' => $cursos,
            'orden_borrado' => array_keys($plan),
            'conteos' => $conteos,
            'semilla' => (int) $this->option('semilla'),
        ]);
        $this->volcarConPgDump($carpeta, array_keys($plan));
        $this->actualizarManifiesto($carpeta);
        $this->line("Respaldo: {$carpeta}");

        return $carpeta;
    }

    /**
     * Rutas de archivos que referencian las filas respaldadas (no se borran ni se copian: solo se listan).
     *
     * @return array{en_disco: list<string>, enlaces_externos: list<string>, no_encontrados: list<string>}
     */
    protected function archivosLigados(string $carpeta): array
    {
        $columnas = ['cursos' => ['imagen_portada_url'], 'entregas' => ['archivo_url'], 'lecciones' => ['material_url', 'video_url'], 'mensajes' => ['archivo_url']];
        $rutas = collect($columnas)->flatMap(fn (array $campos, string $tabla) => collect($this->leerJson("{$carpeta}/filas/{$tabla}.json"))
            ->flatMap(fn (array $fila) => array_map(fn ($campo) => $fila[$campo] ?? null, $campos)))
            ->merge(collect([...$this->leerJson("{$carpeta}/filas/modulos.json"), ...$this->leerJson("{$carpeta}/filas/carpetas_curso.json")])
                ->flatMap(fn (array $fila) => collect(json_decode((string) ($fila['materiales'] ?? $fila['recursos'] ?? ''), true) ?: [])->flatten()))
            ->filter(fn ($valor) => is_string($valor) && $valor !== '' && str_contains($valor, '/'))
            ->unique()->values();
        $disco = Storage::disk(config('archivos.disco'));

        return [
            'en_disco' => $rutas->reject(fn ($ruta) => preg_match('~^https?://~i', $ruta))->filter(fn ($ruta) => $disco->exists($ruta))->values()->all(),
            'enlaces_externos' => $rutas->filter(fn ($ruta) => preg_match('~^https?://~i', $ruta))->values()->all(),
            'no_encontrados' => $rutas->reject(fn ($ruta) => preg_match('~^https?://~i', $ruta))->reject(fn ($ruta) => $disco->exists($ruta))->values()->all(),
        ];
    }

    /** @param  list<string>  $tablas */
    protected function volcarConPgDump(string $carpeta, array $tablas): void
    {
        $conexion = config('database.connections.'.config('database.default'));

        if (($conexion['driver'] ?? null) !== 'pgsql') {
            return;
        }

        $resultado = Process::timeout(600)
            ->env(['PGPASSWORD' => (string) $conexion['password'], 'PGSSLMODE' => (string) ($conexion['sslmode'] ?? 'prefer')])
            ->run(['pg_dump', '--data-only', '--no-owner', ...array_map(fn ($tabla) => "--table=public.{$tabla}", $tablas),
                '--host='.$conexion['host'], '--port='.$conexion['port'], '--username='.$conexion['username'],
                '--dbname='.$conexion['database'], '--file='.$carpeta.'/pg_dump_tablas_afectadas.sql']);

        $resultado->successful()
            ? $this->line('pg_dump: volcado de datos de las tablas afectadas guardado.')
            : $this->warn('pg_dump no disponible o falló (el respaldo por filas sigue siendo válido).');
    }

    // ==========================================
    // SIEMBRA
    // ==========================================

    protected function prepararUsuarios(): void
    {
        $this->supervisor = User::role(Rol::Supervisor->value)->orderBy('id')->firstOrFail();

        foreach (self::PROFESORES as $clave => $nombre) {
            $encontrados = User::role(Rol::Profesor->value)->where('name', $nombre)->get();
            $this->exigir($encontrados->count() === 1, "se esperaba exactamente un profesor llamado \"{$nombre}\"; hay {$encontrados->count()}");
            $this->docentes[$clave] = $encontrados->first();
        }

        $estudiantes = User::role(Rol::Estudiante->value)->where('activo', true)->count();
        $this->exigir($estudiantes >= 40, "hay {$estudiantes} estudiantes activos; se necesitan al menos 40 (no se crean usuarios)");
    }

    /** @return list<int> ids de los cursos sembrados */
    protected function sembrar(): array
    {
        $this->azar = new Randomizer(new Mt19937((int) $this->option('semilla')));
        $estudiantes = User::role(Rol::Estudiante->value)->where('activo', true)->orderBy('id')->get();
        $cursos = [];

        foreach (self::CURSOS as $indice => $datos) {
            $cursos[] = $this->crearCurso($datos, $indice, $estudiantes)->id;
        }

        $this->sembrarActividadGeneral();

        return $cursos;
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int, 6: string, 7: int, 8: int, 9: list<string>}  $datos
     * @param  Collection<int, User>  $estudiantes
     */
    protected function crearCurso(array $datos, int $indice, Collection $estudiantes): Curso
    {
        [$codigo, $nombre, $titularClave, $estadoFinal, $semanas, $desfase, $modalidad, $cupo, $matriculados, $casos] = $datos;
        $titular = $this->docentes[$titularClave];
        $inicio = now()->startOfWeek()->addWeeks($desfase);
        $this->exigir(preg_match(Curso::FORMATO_CODIGO, $codigo) === 1 && ! Curso::prefijoPerteneceAOtraMateria($codigo, $nombre), "código inválido {$codigo}");
        $conHorario = in_array($modalidad, Curso::MODALIDADES_CON_HORARIO, true);

        $curso = Curso::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'descripcion' => "Curso de {$nombre} para estudiantes de los primeros ciclos.",
            'profesor_id' => $titular->id,
            'estado' => 'borrador',
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addWeeks($semanas)->toDateString(),
            'semanas_duracion' => $semanas,
            'cupo_maximo' => $cupo,
            'modalidad' => $modalidad,
            'horario_bloques' => $conHorario
                ? [['dia' => 1 + $indice % 3, 'hora_inicio' => sprintf('%02d:00', 8 + $indice % 4), 'hora_fin' => sprintf('%02d:00', 10 + $indice % 4), 'aula' => $modalidad === 'presencial' ? 'A-'.(201 + $indice) : null],
                    ['dia' => 4 + $indice % 2, 'hora_inicio' => '16:00', 'hora_fin' => '18:00', 'aula' => $modalidad === 'presencial' ? 'B-'.(101 + $indice) : null]]
                : null,
            'usa_asistencia' => $conHorario,
            'tamano_maximo_grupo' => 4,
            'tiene_practicas_campo' => in_array('practicas', $casos, true),
        ]);
        $curso->generarSesionesDesdeHorario();
        $this->bitacora($curso, 'Curso creado: ');

        if (in_array('codocente', $casos, true)) {
            $curso->profesoresAdicionales()->sync([$this->docentes['jose']->id]);
        }

        $actual = 1 - $desfase;
        $rubros = $this->esquema($curso, $semanas);
        $this->semanas($curso, $nombre, $semanas, $actual, $estadoFinal, $casos);
        $inscritos = $this->matricular($curso, $estudiantes, $matriculados, $indice, in_array('bajas', $casos, true));

        if ($estadoFinal !== 'borrador') {
            $this->exigir($curso->modulos()->exists() && $curso->inscripcionesActivas()->exists(), "{$codigo} no cumple la regla para publicar");
            $curso->update(['estado' => 'publicado']);
            $this->bitacora($curso, 'Curso editado: ');

            $cerradas = $this->actividades($curso, $nombre, $semanas, $actual, $inscritos, $titular, $casos, $estadoFinal);
            $this->asignarEvaluaciones($curso, $rubros, $cerradas, $actual > $semanas, $titular);
            $this->asistencia($curso, $inscritos, $titular);
            $this->interaccion($curso, $nombre, $inscritos, $titular, $casos);

            if (in_array($estadoFinal, ['despublicado', 'archivado'], true)) {
                $curso->update(['estado' => $estadoFinal]);
                $this->bitacora($curso, 'Curso editado: ');
            }
        } elseif (in_array('listo', $casos, true)) {
            Tarea::create([
                'curso_id' => $curso->id, 'modulo_id' => $this->moduloDeSemana($curso, 2)->id,
                'titulo' => 'Práctica 1: '.self::TEMAS[$nombre][1], 'descripcion' => 'Primera práctica del curso (borrador).',
                'fecha_inicio' => $curso->rangoSemana(2)[0]->copy()->setTime(8, 0), 'fecha_entrega' => $curso->rangoSemana(2)[0]->copy()->addDays(4)->setTime(23, 59),
                'peso_nota' => 1, 'intentos_permitidos' => 1, 'tipo_entrega' => 'individual', 'visible' => true, 'estado_contenido' => 'borrador',
            ]);
        }

        return $curso;
    }

    protected function bitacora(Curso $curso, string $prefijo): void
    {
        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $this->supervisor,
            tipoEvento: 'modificacion_curso',
            descripcion: $prefijo."\"{$curso->nombre}\" (estado {$curso->estado}).",
            curso: $curso,
        );
    }

    /**
     * Esquema del Supervisor con pesos al 100 %; con prácticas de campo, 30 % para ellas.
     *
     * @return Collection<int, RubroEvaluacion>
     */
    protected function esquema(Curso $curso, int $semanas): Collection
    {
        $rubros = match (true) {
            $semanas >= 16 => [['Examen parcial', 8, 30], ['Trabajo de investigación', 12, 30], ['Examen final', 16, 40]],
            $semanas >= 8 => [['Evaluación 1', 4, 40], ['Evaluación 2', 8, 60]],
            default => [['Evaluación 1', 2, 40], ['Evaluación 2', 5, 60]],
        };
        $this->exigir(app(CalculadoraNotas::class)->pesosSumanCien(array_column($rubros, 2)), "pesos inválidos en {$curso->codigo}");

        $esquema = EsquemaEvaluacion::create(['curso_id' => $curso->id, 'peso_practicas_campo' => $curso->tiene_practicas_campo ? 30 : null]);

        foreach ($rubros as $orden => [$nombre, $semana, $peso]) {
            $esquema->rubros()->create(['nombre' => $nombre, 'semana' => $semana, 'peso' => $peso, 'orden' => $orden + 1]);
        }

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $this->supervisor, tipoEvento: 'modificacion_esquema',
            descripcion: "Esquema de evaluación guardado para \"{$curso->nombre}\" (".count($rubros).' rubros, 100 %).',
            curso: $curso,
        );

        return $esquema->rubros()->get();
    }

    /**
     * Semanas: pasadas publicadas (una vacía en cursos largos), la actual publicada,
     * la siguiente programada (salvo el caso "siguiente_vacia"); borradores en borrador.
     *
     * @param  list<string>  $casos
     */
    protected function semanas(Curso $curso, string $materia, int $total, int $actual, string $estadoFinal, array $casos): void
    {
        if (in_array('sin_contenido', $casos, true)) {
            $this->moduloDeSemana($curso, 1, 'borrador');

            return;
        }

        $temas = self::TEMAS[$materia];
        $borrador = $estadoFinal === 'borrador';
        $pasadaVacia = $total >= 16 && $actual > 4 ? $actual - 3 : null;

        for ($numero = 1; $numero <= $total; $numero++) {
            $conContenido = match (true) {
                $borrador => $numero <= (in_array('listo', $casos, true) ? 3 : 2),
                $numero < $actual => $numero !== $pasadaVacia,
                $numero === $actual => true,
                $numero === $actual + 1 => ! in_array('siguiente_vacia', $casos, true),
                $actual < 1 => $numero <= 2,
                default => false,
            };

            if (! $conContenido) {
                continue;
            }

            [$lunes] = $curso->rangoSemana($numero);
            $yaEmpezo = $lunes->lte(now());
            $estado = $borrador ? 'borrador' : ($yaEmpezo ? 'publicado' : 'programado');
            $tema = $temas[($numero - 1) % count($temas)];
            $modulo = Modulo::create([
                'curso_id' => $curso->id, 'semana' => $numero, 'titulo' => "Semana {$numero}: {$tema}", 'materiales' => [],
                'estado_contenido' => $estado, 'publicar_en' => $estado === 'programado' ? $lunes->copy()->setTime(7, 0) : null,
            ]);

            $lecciones = [
                ['mensaje_inicio', 'Bienvenida a la semana', "Esta semana trabajamos {$tema}. Revisa los materiales antes de la clase."],
                ['recursos', "Presentación: {$tema}", "Diapositivas de la clase sobre {$tema}."],
            ];
            $this->azar->getInt(0, 1) === 1 && $lecciones[] = ['recursos', "Lectura: {$tema}", 'Capítulo del texto base del curso.'];
            $this->azar->getInt(0, 2) > 0 && $lecciones[] = ['actividades', "Ejercicios de {$tema}", 'Ejercicios de práctica sin nota, con solucionario.'];
            $numero < $actual && $lecciones[] = ['grabacion', "Grabación de la clase {$numero}", "Grabación de la sesión sobre {$tema}."];

            foreach ($lecciones as $orden => [$seccion, $titulo, $descripcion]) {
                $modulo->lecciones()->create([
                    'titulo' => $titulo, 'descripcion' => $descripcion, 'estado' => $estado,
                    'fecha_publicacion' => $estado === 'programado' ? $lunes->copy()->setTime(7, 0) : null,
                    'orden' => $orden + 1, 'tipo_recurso' => $seccion,
                ]);
            }
        }
    }

    protected function moduloDeSemana(Curso $curso, int $semana, ?string $estado = null): Modulo
    {
        [$lunes] = $curso->rangoSemana($semana);
        $estado ??= $lunes->lte(now()) ? 'publicado' : 'programado';

        return Modulo::firstOrCreate(
            ['curso_id' => $curso->id, 'semana' => $semana],
            ['titulo' => 'Semana '.$semana, 'materiales' => [], 'estado_contenido' => $estado, 'publicar_en' => $estado === 'programado' ? $lunes->copy()->setTime(7, 0) : null],
        );
    }

    /**
     * Matrícula manual con GestorMatriculas y el control de cupo; con "bajas", dos bajas lógicas.
     *
     * @param  Collection<int, User>  $estudiantes
     * @return Collection<int, User> matriculados activos
     */
    protected function matricular(Curso $curso, Collection $estudiantes, int $cantidad, int $indice, bool $conBajas): Collection
    {
        if ($cantidad === 0) {
            return collect();
        }

        $gestor = app(GestorMatriculas::class);
        $elegidos = $estudiantes->values()->slice(($indice * 11) % $estudiantes->count())->concat($estudiantes->values())->unique('id')->take($cantidad);
        $inscritos = collect();

        foreach ($elegidos as $estudiante) {
            if ($curso->cupoLleno()) {
                break;
            }

            $gestor->matricularManual($curso, $estudiante, $this->supervisor);
            $inscritos->push($estudiante);
        }

        if ($conBajas) {
            foreach ($inscritos->splice(-2) as $baja) {
                $gestor->darDeBaja(Inscripcion::where('curso_id', $curso->id)->where('estudiante_id', $baja->id)->firstOrFail(), $this->supervisor, 'Retiro voluntario del curso.');
            }
        }

        return $inscritos->values();
    }

    /**
     * Tareas, prácticas de campo y quizzes con entregas y resultados.
     *
     * @param  Collection<int, User>  $inscritos
     * @param  list<string>  $casos
     * @return list<Tarea|Quiz> actividades cerradas (para las evaluaciones)
     */
    protected function actividades(Curso $curso, string $materia, int $total, int $actual, Collection $inscritos, User $titular, array $casos, string $estadoFinal): array
    {
        $temas = self::TEMAS[$materia];
        $tema = fn (int $semana) => $temas[($semana - 1) % count($temas)];
        $terminado = $actual > $total;
        $grupos = $this->grupos($curso, $inscritos, $casos);
        $hoy = now()->startOfDay();
        $cerradas = [];
        $n = 0;
        $tarea = function (int $semana, string $titulo, Carbon $cierre, string $tipo = 'individual') use ($curso, &$n) {
            [$lunes] = $curso->rangoSemana($semana);
            $n++;

            return Tarea::create([
                'curso_id' => $curso->id, 'modulo_id' => $this->moduloDeSemana($curso, $semana)->id,
                'titulo' => $titulo, 'descripcion' => 'Resuelve lo propuesto y sube tu desarrollo en PDF.',
                'instrucciones' => 'Entrega un solo archivo con tu nombre completo en la primera página.',
                'fecha_inicio' => $lunes->copy()->setTime(8, 0), 'fecha_entrega' => $cierre,
                'peso_nota' => 1, 'intentos_permitidos' => 1, 'tipo_entrega' => $tipo, 'visible' => true, 'estado_contenido' => 'publicado',
            ]);
        };

        if ($terminado) {
            foreach ([2, 4] as $i => $semana) {
                $t = $tarea($semana, 'Práctica '.($i + 1).": {$tema($semana)}", $curso->rangoSemana($semana)[0]->copy()->addDays(4)->setTime(23, 59));
                $largas = $estadoFinal !== 'archivado' && in_array('espera_larga', $casos, true) && $semana === 4 ? 3 : 0;
                $this->entregas($t, $inscritos, $grupos, calificadas: 100, largas: $largas);
                $cerradas[] = $t;
            }
        } else {
            // Semana actual - 2: casi todo calificado. Semana actual - 1: pendientes de 7-10 días.
            if ($actual >= 3) {
                $t = $tarea($actual - 2, "Práctica 1: {$tema($actual - 2)}", $curso->rangoSemana($actual - 2)[0]->copy()->addDays(4)->setTime(23, 59));
                $this->entregas($t, $inscritos, $grupos, calificadas: 92, minimo: $hoy->copy()->subDays(10));
                $cerradas[] = $t;
            }

            if ($actual >= 2) {
                $grupal = in_array('grupal_anterior', $casos, true);
                $t = $tarea($actual - 1, ($grupal ? 'Trabajo grupal: ' : 'Práctica 2: ').$tema($actual - 1), $curso->rangoSemana($actual - 1)[0]->copy()->addDays(4)->setTime(23, 59), $grupal ? 'grupal' : 'individual');
                $todo = in_array('todo_calificado', $casos, true);
                $this->entregas($t, $inscritos, $grupos, calificadas: $todo ? 100 : 55, minimo: $hoy->copy()->subDays(10), participacion: $todo ? 100 : 85, tardias: in_array('tardias', $casos, true));
                $cerradas[] = $t;
            }

            // Semana actual: una que venció ayer (o hoy si la semana recién empieza) y otra que vence hoy o mañana.
            if ($actual >= 1) {
                [$lunes, $domingo] = $curso->rangoSemana($actual);
                $ayer = $hoy->copy()->subDay()->setTime(23, 59)->max($lunes->copy()->setTime(23, 59));
                $t = $tarea($actual, "Práctica 3: {$tema($actual)}", $ayer);
                $this->entregas($t, $inscritos, $grupos, calificadas: 10, participacion: 80);
                $t->fecha_entrega->lt(now()) && $cerradas[] = $t;

                $vence = $hoy->copy()->addDays(count($cerradas) % 2)->setTime(23, 59)->min($domingo->copy()->setTime(23, 59));
                $grupal = in_array('grupal_actual', $casos, true);
                $t = $tarea($actual, ($grupal ? 'Trabajo grupal: ' : 'Informe: ').$tema($actual), $vence, $grupal ? 'grupal' : 'individual');
                in_array('sin_entregas', $casos, true) || $this->entregas($t, $inscritos, $grupos, calificadas: 0, participacion: in_array('masiva', $casos, true) ? 95 : 70);
            }

            // Semana siguiente: abre el lunes, vence en 3-7 días (sin entregas todavía).
            if ($actual + 1 <= $total && ! in_array('siguiente_vacia', $casos, true) && $actual >= 0) {
                $tarea($actual + 1, "Práctica 4: {$tema($actual + 1)}", $curso->rangoSemana($actual + 1)[0]->copy()->addDays(2 + $actual % 3)->setTime(23, 59));
            }
        }

        if ($curso->tiene_practicas_campo) {
            $this->practicasDeCampo($curso, $inscritos, $actual, $titular);
        }

        // Quizzes autocalificados: uno cerrado con resultados y otro abierto esta semana.
        foreach (array_filter([$terminado ? 3 : ($actual >= 2 ? $actual - 1 : null), ! $terminado && $actual >= 1 ? $actual : null]) as $semana) {
            [$lunes] = $curso->rangoSemana($semana);
            $quiz = Quiz::create([
                'curso_id' => $curso->id, 'modulo_id' => $this->moduloDeSemana($curso, $semana)->id, 'tema' => "Quiz: {$tema($semana)}",
                'preguntas' => [
                    ['enunciado' => "¿Cuál es la idea central de {$tema($semana)}?", 'opciones' => ['La definición vista en clase', 'Un caso particular', 'Ninguna de las anteriores'], 'respuesta_correcta' => 0],
                    ['enunciado' => "¿En qué situación aplicarías {$tema($semana)}?", 'opciones' => ['En ningún caso', 'En el problema resuelto en clase', 'Solo en el examen'], 'respuesta_correcta' => 1],
                    ['enunciado' => '¿Cuál de estas afirmaciones es correcta?', 'opciones' => ['La primera', 'La segunda', 'La tercera'], 'respuesta_correcta' => 2],
                ],
                'configuracion' => ['intentos' => 1, 'tiempo_limite_min' => 20],
                'fecha_inicio' => $lunes->copy()->setTime(8, 0), 'fecha_limite' => $lunes->copy()->addDays(6)->setTime(23, 59),
                'visible' => true, 'estado_contenido' => 'publicado',
            ]);

            if ($quiz->fecha_limite->lt(now())) {
                $this->resultados($quiz, $inscritos);
                $cerradas[] = $quiz;
            } else {
                $this->resultados($quiz, $inscritos->take(intdiv($inscritos->count(), 3)));
            }
        }

        return $cerradas;
    }

    /**
     * Grupos de 2 a 4 integrantes para los cursos con tareas grupales.
     *
     * @param  Collection<int, User>  $inscritos
     * @param  list<string>  $casos
     * @return Collection<int, array{grupo: Grupo, miembros: Collection<int, User>}>
     */
    protected function grupos(Curso $curso, Collection $inscritos, array $casos): Collection
    {
        if (! array_intersect(['grupal_actual', 'grupal_anterior'], $casos)) {
            return collect();
        }

        $tamanos = [];
        $restantes = $inscritos->count();
        while ($restantes > 0) {
            $tamano = $restantes <= 4 ? $restantes : ($restantes % 4 === 1 ? 3 : 4);
            $tamanos[] = max(2, $tamano);
            $restantes -= $tamano;
        }

        $grupos = collect();
        $desde = 0;
        foreach (array_slice($tamanos, 0, 8) as $n => $tamano) {
            $miembros = $inscritos->slice($desde, $tamano)->values();
            $desde += $tamano;
            $grupo = Grupo::create(['curso_id' => $curso->id, 'nombre' => 'Grupo '.($n + 1)]);
            $grupo->estudiantes()->attach($miembros->pluck('id')->all());
            $grupos->push(['grupo' => $grupo, 'miembros' => $miembros]);
        }

        return $grupos;
    }

    /**
     * Entregas con fechas reales: nunca antes de la apertura ni en el futuro; las
     * pendientes no esperan más de 10 días (salvo $largas, que esperan 12-15).
     *
     * @param  Collection<int, User>  $inscritos
     * @param  Collection<int, array{grupo: Grupo, miembros: Collection<int, User>}>  $grupos
     */
    protected function entregas(Tarea $tarea, Collection $inscritos, Collection $grupos, int $calificadas, ?Carbon $minimo = null, int $participacion = 85, bool $tardias = false, int $largas = 0): void
    {
        if ($tarea->fecha_inicio->gt(now())) {
            return;
        }

        $tope = $tardias ? $tarea->fecha_entrega->copy()->addDays(2) : $tarea->fecha_entrega->copy();
        $momento = function (bool $pendiente) use ($tarea, $minimo, $tope): Carbon {
            $desde = $tarea->fecha_inicio->copy()->addHour();
            $desde = $pendiente && $minimo ? $desde->max($minimo) : $desde;
            $hasta = $tope->copy()->min(now()->subMinutes(30));
            $hasta = $hasta->lt($desde) ? $desde->copy() : $hasta;

            return $desde->copy()->addSeconds($this->azar->getInt(0, max(0, (int) $desde->diffInSeconds($hasta))))->startOfMinute();
        };
        $unidades = $tarea->tipo_entrega === 'grupal'
            ? $grupos->map(fn (array $g) => ['grupo' => $g['grupo']->id, 'miembros' => $g['miembros']])
            : $inscritos->map(fn (User $u) => ['grupo' => null, 'miembros' => collect([$u])]);
        $filas = [];

        foreach ($unidades->values() as $i => $unidad) {
            if ($this->azar->getInt(1, 100) > $participacion) {
                continue;
            }

            $larga = $i < $largas;
            $calificada = ! $larga && $this->azar->getInt(1, 100) <= $calificadas;
            $envio = $larga ? now()->startOfDay()->subDays(12 + $i)->setTime(19, 30)->max($tarea->fecha_inicio->copy()->addHour()) : $momento(! $calificada);
            $revision = $calificada ? $envio->copy()->addHours($this->azar->getInt(20, 72))->min(now()->subMinutes(10)) : $envio;
            $nota = $calificada ? $this->azar->getInt(18, 40) / 2 : null;
            $comentario = $calificada ? self::COMENTARIOS[$this->azar->getInt(0, count(self::COMENTARIOS) - 1)] : null;

            foreach ($unidad['miembros'] as $miembro) {
                $filas[] = [
                    'tarea_id' => $tarea->id, 'estudiante_id' => $miembro->id, 'grupo_id' => $unidad['grupo'],
                    'respuesta_texto' => $unidad['grupo'] ? 'Informe del grupo adjunto en el enlace.' : 'Adjunto mi desarrollo de los ejercicios.',
                    'enlace' => $unidad['grupo'] ? 'https://docs.google.com/document/d/informe-grupo-'.$unidad['grupo'] : null,
                    'estado' => $calificada ? 'calificado' : 'entregado', 'nota' => $nota, 'feedback' => $comentario, 'intentos_usados' => 1,
                    'created_at' => $envio->format('Y-m-d H:i:s'), 'updated_at' => $revision->format('Y-m-d H:i:s'),
                ];
            }
        }

        foreach (array_chunk($filas, 200) as $tanda) {
            DB::table('entregas')->insert($tanda);
        }
    }

    /**
     * Prácticas de campo (una por semana): tareas Práctica 01..N de la sección de
     * prácticas hasta la semana actual, y su reparto entre evaluaciones (30 %).
     *
     * @param  Collection<int, User>  $inscritos
     */
    protected function practicasDeCampo(Curso $curso, Collection $inscritos, int $actual, User $titular): void
    {
        $seccion = $curso->moduloPracticas();

        foreach (range(1, min($actual, $curso->totalPracticasCampo())) as $numero) {
            [$lunes] = $curso->rangoSemana($numero);
            $practica = Tarea::create([
                'curso_id' => $curso->id, 'modulo_id' => $seccion->id, 'titulo' => Tarea::etiquetaPractica($numero),
                'descripcion' => 'Informe de la salida de campo de la semana.', 'fecha_inicio' => $lunes->copy()->setTime(8, 0),
                'fecha_entrega' => $lunes->copy()->addDays(5)->setTime(23, 59), 'peso_nota' => 1, 'intentos_permitidos' => 1,
                'tipo_entrega' => 'individual', 'visible' => true, 'estado_contenido' => 'publicado', 'practica' => $numero,
            ]);
            $this->entregas($practica, $inscritos, collect(), calificadas: $numero < $actual ? 80 : 0, minimo: now()->startOfDay()->subDays(10), participacion: 80);
        }

        $servicio = app(PracticasEnEvaluaciones::class);
        $rubros = $curso->esquemaEvaluacion->rubros()->get();
        $servicio->guardar($curso, $servicio->repartoSugerido($rubros, $curso->totalPracticasCampo(), 30.0), $titular);
    }

    /** @param  Collection<int, User>  $inscritos */
    protected function resultados(Quiz $quiz, Collection $inscritos): void
    {
        $filas = [];

        foreach ($inscritos as $estudiante) {
            if ($this->azar->getInt(1, 100) > 80) {
                continue;
            }

            $respuestas = array_map(fn () => $this->azar->getInt(0, 2), $quiz->preguntas);
            $momento = $quiz->fecha_inicio->copy()->addMinutes($this->azar->getInt(60, max(61, (int) $quiz->fecha_inicio->diffInMinutes(now()->min($quiz->fecha_limite)))));
            $filas[] = ['quiz_id' => $quiz->id, 'estudiante_id' => $estudiante->id, 'intento' => 1, 'respuestas' => json_encode($respuestas),
                'puntaje' => $quiz->calcularPuntaje($respuestas), 'tiempo_usado_seg' => $this->azar->getInt(240, 1150),
                'created_at' => $momento->format('Y-m-d H:i:s'), 'updated_at' => $momento->format('Y-m-d H:i:s')];
        }

        $filas === [] || DB::table('resultados_quiz')->insert($filas);
    }

    /**
     * Actividades cerradas a las evaluaciones con AsignadorEvaluacion: en un curso en
     * marcha solo la primera evaluación recibe actividades (las demás quedan sin ninguna).
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @param  list<Tarea|Quiz>  $actividades
     */
    protected function asignarEvaluaciones(Curso $curso, Collection $rubros, array $actividades, bool $terminado, User $titular): void
    {
        $asignador = app(AsignadorEvaluacion::class);
        $destinos = $terminado ? $rubros->values() : $rubros->take(1)->values();

        foreach (array_values(array_filter($actividades, fn ($actividad) => ! ($actividad instanceof Tarea && $actividad->esPracticaCampo()))) as $n => $actividad) {
            $asignador->asignar($curso, $destinos[$n % $destinos->count()]->fresh(), $actividad, false, $titular);
        }
    }

    /**
     * Asistencia de las sesiones ya dictadas (las de hoy y futuras quedan sin registrar).
     *
     * @param  Collection<int, User>  $inscritos
     */
    protected function asistencia(Curso $curso, Collection $inscritos, User $titular): void
    {
        $filas = [];

        foreach ($curso->sesionesClase()->where('fecha', '<', now()->startOfDay())->get() as $sesion) {
            foreach ($inscritos as $estudiante) {
                $tiro = $this->azar->getInt(1, 100);
                $filas[] = ['sesion_id' => $sesion->id, 'estudiante_id' => $estudiante->id, 'registrado_por' => $titular->id,
                    'estado' => match (true) {
                        $tiro <= 78 => 'presente', $tiro <= 88 => 'tardanza', $tiro <= 96 => 'ausente', default => 'justificado'
                    },
                    'observacion' => null, 'created_at' => $sesion->fecha, 'updated_at' => $sesion->fecha];
            }
        }

        foreach (array_chunk($filas, 300) as $tanda) {
            DB::table('asistencias')->insert($tanda);
        }
    }

    /**
     * Debates con comentarios, anuncios del curso y mensajes privados.
     *
     * @param  Collection<int, User>  $inscritos
     * @param  list<string>  $casos
     */
    protected function interaccion(Curso $curso, string $materia, Collection $inscritos, User $titular, array $casos): void
    {
        if (in_array('debate', $casos, true) && $inscritos->count() >= 4) {
            $debate = Debate::create(['curso_id' => $curso->id, 'autor_id' => $titular->id, 'titulo' => '¿Para qué nos sirve '.mb_strtolower(self::TEMAS[$materia][0]).'?',
                'consigna' => 'Comparte un ejemplo de tu vida diaria y comenta el de un compañero.', 'estado' => 'publicado', 'publicado_en' => now()->subDays(5)]);
            $padre = null;
            foreach ($inscritos->take(4)->values() as $i => $estudiante) {
                $comentario = $debate->comentarios()->create(['user_id' => $estudiante->id, 'parent_id' => $i === 2 ? $padre?->id : null,
                    'contenido' => ['En mi trabajo lo uso para revisar las ventas de la semana.', 'Me sirvió para entender las encuestas del barrio.', 'Coincido, y además ayuda a decidir con datos.', 'Lo aplico al organizar mis gastos del mes.'][$i]]);
                $padre ??= $comentario;
            }
            $debate->comentarios()->create(['user_id' => $titular->id, 'parent_id' => $padre->id, 'contenido' => 'Muy buen ejemplo; tráelo a la próxima clase.']);
        }

        if (in_array('debate_borrador', $casos, true)) {
            Debate::create(['curso_id' => $curso->id, 'autor_id' => $titular->id, 'titulo' => 'Debate de la próxima semana', 'consigna' => 'Se publicará el lunes.', 'estado' => 'borrador']);
        }

        if (in_array('anuncio', $casos, true)) {
            $curso->comunicados()->create(['user_id' => $titular->id, 'titulo' => 'Cambio de aula para la práctica', 'contenido' => 'La práctica de esta semana será en el laboratorio 3.', 'prioridad' => 'importante', 'destinatario' => 'estudiantes', 'activo' => true, 'notificado' => true]);
            $curso->comunicados()->create(['user_id' => $titular->id, 'titulo' => 'Recordatorio de la evaluación', 'contenido' => 'La evaluación ya pasó; este anuncio quedó inactivo.', 'prioridad' => 'informativo', 'destinatario' => 'estudiantes', 'activo' => false, 'notificado' => true]);
        }

        foreach ($inscritos->take(2)->values() as $i => $estudiante) {
            $consulta = Mensaje::create(['remitente_id' => $estudiante->id, 'destinatario_id' => $titular->id, 'curso_id' => $curso->id,
                'asunto' => 'Consulta sobre la práctica', 'cuerpo' => '¿Se puede entregar el informe en un solo PDF con las fotos del cuaderno?', 'leido_at' => $i === 0 ? now()->subDay() : null]);
            $i === 0 && Mensaje::create(['remitente_id' => $titular->id, 'destinatario_id' => $estudiante->id, 'curso_id' => $curso->id,
                'asunto' => 'Re: '.$consulta->asunto, 'cuerpo' => 'Sí, un solo PDF está bien. Revisa que se lean las fotos.']);
        }
    }

    /** Comunicados institucionales, eventos de calendario y propuestas del bot (sin curso). */
    protected function sembrarActividadGeneral(): void
    {
        $pepito = $this->docentes['pepito'];
        foreach ([
            ['Inicio de matrículas del ciclo 2027-I', 'La matrícula del próximo ciclo empieza el lunes.', 'informativo', 'todos', true, null],
            ['Mantenimiento de la plataforma', 'El sábado pasado hubo mantenimiento (comunicado vencido).', 'urgente', 'profesores', false, null],
            ['Encuesta docente', 'La encuesta de satisfacción se abrirá en unos días.', 'informativo', 'estudiantes', true, now()->addDays(3)->setTime(8, 0)],
        ] as [$titulo, $contenido, $prioridad, $destinatario, $activo, $publicarEn]) {
            $this->otrasCreadas['comunicados'][] = Comunicado::create(['user_id' => $this->supervisor->id, 'curso_id' => null, 'titulo' => $titulo, 'contenido' => $contenido,
                'prioridad' => $prioridad, 'destinatario' => $destinatario, 'activo' => $activo, 'publicar_en' => $publicarEn, 'notificado' => true])->id;
        }

        foreach ([[$pepito, 'Reunión de coordinación académica', now()->addDays(3)->setTime(15, 0), 'evento'], [$pepito, 'Preparar el examen parcial de Cálculo 2', now()->addDay()->setTime(9, 0), 'bloque_estudio']] as [$usuario, $titulo, $fecha, $tipo]) {
            $this->otrasCreadas['eventos_calendario'][] = EventoCalendario::create(['usuario_id' => $usuario->id, 'titulo' => $titulo, 'fecha' => $fecha, 'tipo' => $tipo])->id;
        }

        // Propuestas del bot de la última semana para estudiantes de Pepito con una tarea vencida sin entrega.
        $vencidas = Tarea::query()->whereHas('curso', fn ($q) => $q->where('profesor_id', $pepito->id)->where('estado', 'publicado'))
            ->visiblesParaEstudiante()->whereNull('practica')->where('fecha_entrega', '<', now())->with('curso')->orderByDesc('fecha_entrega')->get();

        foreach (['pendiente', 'aprobado', 'rechazado'] as $i => $estado) {
            $tarea = $vencidas->get($i);
            $estudianteId = $tarea ? Inscripcion::where('curso_id', $tarea->curso_id)->where('estado', 'activo')
                ->whereNotIn('estudiante_id', Entrega::where('tarea_id', $tarea->id)->select('estudiante_id'))->value('estudiante_id') : null;

            if (! $estudianteId) {
                continue;
            }

            $datos = ['tarea_id' => $tarea->id, 'tarea_titulo' => $tarea->titulo, 'curso_id' => $tarea->curso_id, 'curso_nombre' => $tarea->curso->nombre,
                'motivo' => 'La tarea venció el '.$tarea->fecha_entrega->format('d/m/Y').' sin que se registrara una entrega.',
                'nueva_fecha_recordatorio' => now()->addDays(1 + $i)->setTime(18, 0)->format('Y-m-d\TH:i')];

            if ($estado === 'aprobado') {
                $evento = EventoCalendario::create(['usuario_id' => $estudianteId, 'titulo' => 'Recordatorio: '.$tarea->titulo, 'fecha' => $datos['nueva_fecha_recordatorio'], 'tipo' => 'recordatorio']);
                $this->otrasCreadas['eventos_calendario'][] = $evento->id;
                $datos['evento_calendario_id'] = $evento->id;
            }

            $this->otrasCreadas['propuestas_bot'][] = PropuestaBot::create(['usuario_id' => $estudianteId, 'tipo' => 'redistribucion_plan', 'datos_propuesta' => $datos, 'estado' => $estado,
                'aprobado_por' => $estado === 'pendiente' ? null : $estudianteId, 'fecha_respuesta' => $estado === 'pendiente' ? null : now()->subDay()])->id;
        }

        // Progreso: algunas lecciones ya vistas por estudiantes del curso más avanzado.
        $curso = Curso::where('codigo', 'EST-26-01')->first();
        $lecciones = DB::table('lecciones')->whereIn('modulo_id', Modulo::where('curso_id', $curso->id)->where('estado_contenido', 'publicado')->select('id'))->limit(10)->pluck('id');
        $filas = $curso->inscripcionesActivas()->limit(12)->pluck('estudiante_id')
            ->flatMap(fn ($estudianteId) => $lecciones->take($this->azar->getInt(3, 10))->map(fn ($leccionId) => ['leccion_id' => $leccionId, 'estudiante_id' => $estudianteId, 'completada_en' => now()->subDays($this->azar->getInt(1, 20))->format('Y-m-d H:i:s')]))
            ->values()->all();
        $filas === [] || DB::table('lecciones_completadas')->insert($filas);
    }

    // ==========================================
    // VERIFICACIÓN Y ESTADO
    // ==========================================

    /**
     * @param  list<int>  $sembrados
     * @return array<string, int>
     */
    protected function contarSembrado(array $sembrados): array
    {
        return collect($this->plan($sembrados))->map(fn (callable $consulta) => $consulta()->count())
            ->merge(collect($this->otrasCreadas)->map(fn (array $ids) => count($ids)))->all();
    }

    /**
     * Huella del esquema (columnas, constraints, índices, triggers) y del contenido de las tablas intocables.
     *
     * @return array{esquema: string, intocables: array<string, string>}
     */
    protected function foto(): array
    {
        if (DB::getDriverName() === 'pgsql') {
            $esquema = md5(json_encode([
                DB::select("select table_name, column_name, data_type, is_nullable, column_default from information_schema.columns where table_schema = 'public' order by 1, 2"),
                DB::select("select table_name, constraint_name, constraint_type from information_schema.table_constraints where table_schema = 'public' order by 1, 2"),
                DB::select("select tablename, indexname, indexdef from pg_indexes where schemaname = 'public' order by 1, 2"),
                DB::select("select c.relname, t.tgname, pg_get_triggerdef(t.oid) as definicion from pg_trigger t join pg_class c on c.oid = t.tgrelid join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'public' and not t.tgisinternal order by 1, 2"),
            ]));
            $huella = fn (string $tabla) => (string) DB::selectOne("select md5(coalesce(string_agg(x::text, '|' order by x::text), '')) as h from {$tabla} x")->h;
        } else {
            $esquema = md5(json_encode(collect(Schema::getTables())->map(fn (array $tabla) => [$tabla['name'], Schema::getColumns($tabla['name'])])->all()));
            $huella = fn (string $tabla) => md5(json_encode(DB::table($tabla)->get()));
        }

        return [
            'esquema' => $esquema,
            'intocables' => collect(self::TABLAS_INTOCABLES)->filter(fn ($tabla) => Schema::hasTable($tabla))->mapWithKeys(fn ($tabla) => [$tabla => $huella($tabla)])->all(),
        ];
    }

    /**
     * @param  array<string, callable(): Builder>  $plan
     * @param  array<string, int>  $borradas
     * @param  array<string, mixed>  $antes
     * @param  list<int>  $sembrados
     * @return list<string>
     */
    protected function verificar(array $plan, array $borradas, array $antes, array $sembrados): array
    {
        $errores = [];
        $manifiesto = json_decode(File::get("{$this->carpeta}/manifest.json"), true);

        foreach ($borradas as $tabla => $filas) {
            $filas === (int) $manifiesto['conteos'][$tabla] || $errores[] = "{$tabla}: se borraron {$filas} filas y el respaldo tiene {$manifiesto['conteos'][$tabla]}";
        }

        $despues = $this->foto();
        $despues['esquema'] === $antes['esquema'] || $errores[] = 'cambió el esquema (columnas, constraints, índices o triggers)';
        foreach ($antes['intocables'] as $tabla => $huella) {
            ($despues['intocables'][$tabla] ?? null) === $huella || $errores[] = "cambió el contenido de {$tabla}";
        }

        $desvinculadas = collect($this->leerJson("{$this->carpeta}/bitacora_desvinculada.json"))->pluck('id');
        DB::table('bitacoras_auditoria')->whereIn('id', $desvinculadas)->whereNull('curso_id')->count() === $desvinculadas->count()
            || $errores[] = 'la bitácora de los cursos borrados no quedó conservada con curso_id NULL';

        $tareas = DB::table('tareas')->whereIn('curso_id', $sembrados)->pluck('id');
        $incoherentes = DB::table('entregas as e')->join('tareas as t', 't.id', '=', 'e.tarea_id')->whereIn('t.id', $tareas)
            ->where(fn ($q) => $q->where('e.created_at', '>', now())->orWhereColumn('e.created_at', '<', 't.fecha_inicio')->orWhereColumn('e.updated_at', '<', 'e.created_at'))->count();
        $incoherentes === 0 || $errores[] = "{$incoherentes} entregas antes de la apertura de su tarea o en el futuro";
        $gruposDispares = DB::table('entregas')->whereIn('tarea_id', $tareas)->whereNotNull('grupo_id')->groupBy('tarea_id', 'grupo_id')->havingRaw('count(distinct created_at) > 1')->select('tarea_id')->get()->count();
        $gruposDispares === 0 || $errores[] = "{$gruposDispares} grupos con fechas distintas entre integrantes";
        $sinMatricula = DB::table('entregas as e')->join('tareas as t', 't.id', '=', 'e.tarea_id')->whereIn('t.id', $tareas)
            ->whereNotExists(fn ($q) => $q->from('inscripciones as i')->whereColumn('i.curso_id', 't.curso_id')->whereColumn('i.estudiante_id', 'e.estudiante_id'))->count();
        $sinMatricula === 0 || $errores[] = "{$sinMatricula} entregas de estudiantes sin matrícula en el curso";
        $publicadosSinRegla = Curso::whereIn('id', $sembrados)->whereIn('estado', ['publicado', 'despublicado', 'archivado'])
            ->where(fn ($q) => $q->whereDoesntHave('modulos')->orWhereDoesntHave('inscripciones'))->count();
        $publicadosSinRegla === 0 || $errores[] = "{$publicadosSinRegla} cursos publicados sin módulo o sin matrícula";

        foreach (EsquemaEvaluacion::whereIn('curso_id', $sembrados)->with('rubros')->get() as $esquema) {
            abs($esquema->rubros->sum('peso') - 100) < 0.01 || $errores[] = "el esquema del curso {$esquema->curso_id} no suma 100 %";
        }

        foreach ($this->violacionesDeFk() as $violacion) {
            $errores[] = $violacion;
        }

        $pepito = $this->docentes['pepito'];
        $dock = app(PanelProfesor::class)->totalPorCalificar($pepito);
        $servicio = app(CursosProfesor::class);
        $cursos = $servicio->cursos($pepito);
        $hero = (int) collect($servicio->resumen($cursos, $cursos))->sum('pendientes');
        $dock === $hero || $errores[] = "Por calificar: dock {$dock} ≠ hero {$hero}";

        return $errores;
    }

    /** @return list<string> */
    protected function violacionesDeFk(): array
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select('PRAGMA foreign_key_check'))->map(fn ($fila) => "huérfano en {$fila->table} hacia {$fila->parent}")->all();
        }

        $relaciones = DB::select("select tc.table_name as hija, kcu.column_name as columna, ccu.table_name as padre, ccu.column_name as columna_padre
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu on tc.constraint_name = kcu.constraint_name and tc.table_schema = kcu.table_schema
            join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name and ccu.table_schema = tc.table_schema
            where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = 'public'");

        return collect($relaciones)->map(function ($fk) {
            $huerfanas = DB::table("{$fk->hija} as h")->whereNotNull("h.{$fk->columna}")
                ->whereNotExists(fn ($q) => $q->from("{$fk->padre} as p")->whereColumn("p.{$fk->columna_padre}", "h.{$fk->columna}"))->count();

            return $huerfanas > 0 ? "{$huerfanas} huérfanos en {$fk->hija}.{$fk->columna} → {$fk->padre}" : null;
        })->filter()->values()->all();
    }

    /** Lo que verán Pepito, el Supervisor y un estudiante. */
    protected function mostrarPaneles(): void
    {
        $pepito = $this->docentes['pepito'];
        $this->table(['Curso', 'Titular', 'Estado', 'Semana', 'Matrícula activa', 'Inicio – fin'], Curso::with('profesor')->withCount('inscripcionesActivas')->orderBy('id')->get()->map(function (Curso $curso) {
            $total = $curso->totalSemanas();
            $actual = CursosProfesor::semanaActual($curso, $total);

            return ["{$curso->codigo} {$curso->nombre}", $curso->profesor->name, $curso->estado, $actual ? "{$actual} de {$total}" : ($curso->fecha_inicio->isFuture() ? 'por empezar' : 'terminado'),
                $curso->inscripciones_activas_count.' / '.$curso->cupo_maximo, $curso->fecha_inicio->format('d/m/Y').' – '.$curso->fecha_fin->format('d/m/Y')];
        })->all());

        $servicio = app(CursosProfesor::class);
        $cursos = $servicio->cursos($pepito);
        $pendientes = Entrega::query()->whereHas('tarea.curso', fn ($q) => $q->dictadosPor($pepito->id)->where('estado', '!=', 'archivado'))
            ->where('estado', 'entregado')->with(['estudiante:id,name', 'tarea.curso', 'tarea.modulo', 'grupo'])->get();
        $filas = PorCalificar::filasPendientes($pendientes);
        $seleccionada = PorCalificar::tareaSeleccionada(PorCalificar::agruparPorTarea($filas), null);
        $estudiante = User::role(Rol::Estudiante->value)->withCount(['inscripciones as activas_count' => fn ($q) => $q->where('estado', 'activo')])->orderByDesc('activas_count')->first();
        $visibles = Curso::visiblesParaEstudiantes()->whereHas('inscripcionesActivas', fn ($q) => $q->where('estudiante_id', $estudiante->id))->get();

        $this->table(['Panel', 'Dato', 'Valor'], [
            ["Profesor {$pepito->name}", 'Cursos a cargo / "N cursos activos"', PanelProfesor::consultaCursosActivos($pepito)->count()],
            ['', 'Archivados', $cursos->count() - PanelProfesor::consultaCursosActivos($pepito)->count()],
            ['', 'Insignia del dock / hero', app(PanelProfesor::class)->totalPorCalificar($pepito).' / '.collect($servicio->resumen($cursos, $cursos))->sum('pendientes')],
            ['', 'Filas de Por calificar', $filas->count()],
            ['', 'Esperas 0-6 / 7-10 / 11 / 12+ días', $filas->where('dias', '<=', 6)->count().' / '.$filas->whereBetween('dias', [7, 10])->count().' / '.$filas->where('dias', 11)->count().' / '.$filas->where('dias', '>=', 12)->count()],
            ['', 'Tarea seleccionada por defecto', $seleccionada ? $seleccionada['tarea']->titulo.' ('.$seleccionada['tarea']->curso->codigo.', '.$seleccionada['total'].' pendientes)' : '—'],
            ["Supervisor {$this->supervisor->name}", 'Cursos por estado', Curso::selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado')->map(fn ($n, $e) => "{$e} {$n}")->join(', ')],
            ['', 'Matrículas activas / bajas', Inscripcion::where('estado', 'activo')->count().' / '.Inscripcion::where('estado', '!=', 'activo')->count()],
            ['', 'Comunicados institucionales activos', Comunicado::institucionales()->activos()->count()],
            ["Estudiante {$estudiante->name}", 'Cursos visibles', $visibles->pluck('codigo')->join(', ')],
            ['', 'Tareas abiertas sin entregar', Tarea::whereIn('curso_id', $visibles->modelKeys())->visiblesParaEstudiante()->where('fecha_entrega', '>=', now())->whereDoesntHave('entregas', fn ($q) => $q->where('estudiante_id', $estudiante->id))->count()],
        ]);
    }

    // ==========================================
    // RESTAURACIÓN
    // ==========================================

    protected function restaurar(string $carpeta, bool $aplicar): int
    {
        $carpeta = is_dir($carpeta) ? $carpeta : storage_path('app/backups-demo/'.$carpeta);
        $this->exigir(is_file("{$carpeta}/manifest.json"), "no hay manifest.json en {$carpeta}");
        $manifiesto = json_decode(File::get("{$carpeta}/manifest.json"), true);

        foreach ($manifiesto['sha256'] ?? [] as $archivo => $suma) {
            $this->exigir(hash_file('sha256', "{$carpeta}/{$archivo}") === $suma, "el archivo {$archivo} no coincide con su SHA-256");
        }

        $creadas = $this->leerJson("{$carpeta}/creadas.json");
        if (DB::table('cursos')->whereIn('id', $manifiesto['cursos_borrados'])->exists()) {
            $this->error('Los cursos del respaldo ya existen en la base: no hay nada que restaurar (¿es un respaldo de simulación?).');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $conteos = $this->revertir($carpeta, $creadas);
            $this->table(['Paso', 'Filas'], collect($conteos)->map(fn (int $n, string $paso) => [$paso, $n])->values()->all());
            $huerfanos = $this->violacionesDeFk();

            if ($huerfanos !== []) {
                DB::rollBack();
                $this->error('La restauración dejaría huérfanos; no se escribió nada: '.implode('; ', $huerfanos));

                return self::FAILURE;
            }

            $aplicar ? DB::commit() : DB::rollBack();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        $this->warn('La bitácora no se puede revertir (trigger de inmutabilidad): las filas de los cursos borrados siguen con curso_id NULL y las que escribió la siembra se conservan.');
        $this->info($aplicar ? 'RESTAURADO.' : 'SIMULACIÓN de restauración: rollback, la base no cambió. Usa --apply para restaurar.');

        return self::SUCCESS;
    }

    /**
     * Borra lo sembrado (cursos sembrados y filas sueltas registradas) y reinserta el respaldo
     * con sus ids, padres antes que hijos; re-sincroniza las secuencias sin bajarlas.
     *
     * @param  array<string, list<int>>  $creadas
     * @return array<string, int>
     */
    protected function revertir(string $carpeta, array $creadas): array
    {
        $conteos = [];
        $sembrados = DB::table('cursos')->whereIn('id', $creadas['cursos'] ?? [])->pluck('id')->all();
        $conteos['borradas (siembra)'] = array_sum($this->borrar($this->plan($sembrados)));

        foreach (['propuestas_bot', 'eventos_calendario', 'comunicados'] as $tabla) {
            $conteos['borradas (siembra)'] += DB::table($tabla)->whereIn('id', $creadas[$tabla] ?? [])->delete();
        }

        $manifiesto = json_decode(File::get("{$carpeta}/manifest.json"), true);

        foreach (array_reverse($manifiesto['orden_borrado']) as $tabla) {
            $filas = collect($this->leerJson("{$carpeta}/filas/{$tabla}.json"));
            $filas = $tabla === 'debate_comentarios' ? $filas->sortBy('id')->values() : $filas;
            $this->exigir($filas->count() === (int) $manifiesto['conteos'][$tabla], "{$tabla}: el archivo no coincide con el manifiesto");

            foreach ($filas->chunk(200) as $tanda) {
                DB::table($tabla)->insert($tanda->values()->all());
            }

            $conteos["reinsertadas {$tabla}"] = $filas->count();

            if (DB::getDriverName() === 'pgsql' && $filas->isNotEmpty() && is_int($filas->first()['id'] ?? null)) {
                $secuencia = DB::selectOne("select pg_get_serial_sequence('{$tabla}', 'id') as s")->s;
                $secuencia && DB::statement("select setval('{$secuencia}', greatest((select max(id) from {$tabla}), (select last_value from {$secuencia})))");
            }
        }

        return $conteos;
    }

    /**
     * Dentro de la simulación: revierte la siembra y el borrado con el respaldo recién
     * escrito y comprueba que cada fila vuelve idéntica.
     *
     * @param  list<int>  $sembrados
     * @param  array<string, mixed>  $antes
     */
    protected function probarRestauracion(array $sembrados, array $antes): void
    {
        $conteos = $this->revertir($this->carpeta, ['cursos' => $sembrados, ...$this->otrasCreadas]);
        $manifiesto = json_decode(File::get("{$this->carpeta}/manifest.json"), true);
        $distintas = [];

        foreach ($manifiesto['orden_borrado'] as $tabla) {
            $respaldo = collect($this->leerJson("{$this->carpeta}/filas/{$tabla}.json"));

            if ($respaldo->isEmpty()) {
                continue;
            }

            $clave = array_key_exists('id', $respaldo->first()) ? ['id'] : array_keys($respaldo->first());
            $faltan = $respaldo->reject(fn (array $fila) => DB::table($tabla)->where(collect($fila)->only($clave)->all())->get()->contains(fn ($actual) => (array) $actual == $fila))->count();
            $faltan === 0 || $distintas[] = "{$tabla}: {$faltan} filas no volvieron idénticas";
        }

        $sobran = DB::table('cursos')->count() - count($manifiesto['cursos_borrados']);
        $sobran === 0 || $distintas[] = "quedaron {$sobran} cursos de más";
        $this->foto()['intocables'] === $antes['intocables'] || $distintas[] = 'cambiaron usuarios/roles';
        $this->exigir($distintas === [] && $this->violacionesDeFk() === [], 'la prueba de restauración falló: '.implode('; ', $distintas));
        $this->info('Prueba de --restore (dentro de la simulación): OK — '.$conteos['borradas (siembra)'].' filas sembradas borradas y '.array_sum(array_diff_key($conteos, ['borradas (siembra)' => 0])).' filas reinsertadas idénticas al respaldo, sin huérfanos.');
    }

    // ==========================================
    // AUXILIARES
    // ==========================================

    protected function actualizarManifiesto(string $carpeta): void
    {
        $ruta = "{$carpeta}/manifest.json";
        $manifiesto = json_decode(File::get($ruta), true);
        $manifiesto['sha256'] = collect(File::allFiles($carpeta))
            ->reject(fn ($archivo) => $archivo->getFilename() === 'manifest.json' && $archivo->getRelativePath() === '')
            ->mapWithKeys(fn ($archivo) => [str_replace('\\', '/', $archivo->getRelativePathname()) => hash_file('sha256', $archivo->getPathname())])
            ->sortKeys()->all();
        $this->escribirJson($ruta, $manifiesto);
    }

    protected function exigir(bool $condicion, string $mensaje): void
    {
        if (! $condicion) {
            throw new RuntimeException($mensaje);
        }
    }

    protected function escribirJson(string $ruta, mixed $datos): void
    {
        File::put($ruta, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<int|string, mixed> */
    protected function leerJson(string $ruta): array
    {
        return is_file($ruta) ? json_decode(File::get($ruta), true) : [];
    }
}
