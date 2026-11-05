<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EsquemaEvaluacion;
use App\Models\Grupo;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Models\User;
use App\Services\ConfiguracionPlataforma;
use App\Services\Evaluacion\AsignadorEvaluacion;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Matricula\GestorMatriculas;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Datos de prueba realistas y permanentes para el lado Profesor: 12 cursos de
 * un profesor existente, con semanas, lecciones, tareas, quizzes, evaluaciones
 * del Supervisor, matrículas, grupos, entregas y resultados.
 *
 * Usa la misma vía que la aplicación: GestorCuentas (alta de estudiantes, con
 * contraseña aleatoria que no se guarda en ninguna parte), GestorMatriculas
 * (con el control de cupo de la pantalla), AsignadorEvaluacion (pesos de las
 * actividades al 100 %), las reglas de código de curso y de pesos del esquema,
 * y la bitácora de auditoría.
 *
 * Seguridad: no corre en producción; durante la ejecución el correo va a
 * "log" y además se simulan Mail, Notification y Queue, así que no sale ningún
 * correo, notificación ni trabajo en cola. Todo va en una transacción.
 *
 * Idempotente: un curso cuyo código ya existe no se vuelve a crear, y un
 * correo existente solo se reutiliza si es de un estudiante creado por este
 * seeder (está en el manifiesto). Los IDs creados se guardan en
 * storage/app/pruebas-profesor-manifiesto.json (ignorado por git).
 *
 * php artisan db:seed --class=Database\\Seeders\\PruebasProfesorSeeder
 */
class PruebasProfesorSeeder extends Seeder
{
    /** Profesor titular de todos los cursos (debe existir, no se crea ni se modifica). */
    public const PROFESOR = 'Pepito Reyes Diaz';

    public const DOMINIO = 'lms.com';

    public static string $rutaManifiesto = '';

    /**
     * codigo, nombre, estado final, semanas, inicio (semanas desde el lunes
     * actual; negativo = ya empezó), modalidad, cupo, matriculados, semana
     * actual vacía.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: int, 4: int, 5: string, 6: int, 7: int, 8: bool}>
     */
    public const CURSOS = [
        ['EST-26-01', 'Estadística I', 'publicado', 16, -11, 'presencial', 30, 24, false],
        ['EST-26-02', 'Estadística I', 'publicado', 16, -11, 'virtual', 25, 18, true],
        ['FIS-26-01', 'Física General', 'publicado', 16, -8, 'presencial', 35, 28, false],
        ['QUI-26-01', 'Química General', 'publicado', 5, -2, 'presencial', 20, 16, true],
        ['CAL2-26-01', 'Cálculo 2', 'publicado', 16, -4, 'virtual', 40, 30, false],
        ['PRO1-26-01', 'Programación 1', 'publicado', 5, -1, 'virtual', 30, 22, false],
        ['RED-26-01', 'Redacción Académica', 'publicado', 16, -9, 'presencial', 30, 12, false],
        ['BIO-26-01', 'Biología Celular', 'publicado', 5, 0, 'presencial', 25, 9, true],
        ['MET-26-01', 'Metodología de la Investigación', 'publicado', 5, -7, 'virtual', 30, 14, false],
        ['HIS-26-01', 'Historia del Perú', 'borrador', 16, 2, 'virtual', 30, 6, false],
        ['ECO-26-01', 'Economía General', 'borrador', 5, 11, 'presencial', 25, 5, false],
        ['ALG-26-01', 'Álgebra Lineal', 'archivado', 5, -30, 'virtual', 30, 20, false],
    ];

    /**
     * Cursos cuyo titular es OTRO profesor existente (no se crea ni se
     * modifica): sirven para comprobar que Pepito no los ve. Mismo formato que CURSOS.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: int, 4: int, 5: string, 6: int, 7: int, 8: bool}>
     */
    public const CURSOS_OTRO_PROFESOR = [
        ['GEO-26-01', 'Geografía General', 'publicado', 16, -6, 'presencial', 30, 10, false],
        ['FIL-26-01', 'Filosofía', 'publicado', 5, -1, 'virtual', 25, 8, false],
    ];

    /** Curso de Pepito compartido con el otro profesor como co-docente (curso_profesor). */
    public const CURSO_COMPARTIDO = 'RED-26-01';

    /** @var array<string, list<string>> temas por materia (se repiten si el curso tiene más semanas) */
    protected const TEMAS = [
        'Estadística I' => ['Introducción a la estadística', 'Organización de datos', 'Tablas de frecuencia', 'Gráficos estadísticos', 'Medidas de tendencia central', 'Medidas de dispersión', 'Medidas de posición', 'Repaso para el parcial', 'Probabilidad básica', 'Probabilidad condicional', 'Variables aleatorias', 'Distribución binomial', 'Distribución normal', 'Muestreo', 'Estimación de parámetros', 'Repaso final'],
        'Física General' => ['Magnitudes y unidades', 'Vectores', 'Cinemática en una dimensión', 'Movimiento en el plano', 'Leyes de Newton', 'Aplicaciones de las leyes de Newton', 'Trabajo y energía', 'Repaso para el parcial', 'Cantidad de movimiento', 'Rotación', 'Equilibrio', 'Gravitación', 'Fluidos', 'Oscilaciones', 'Ondas', 'Repaso final'],
        'Química General' => ['Materia y medición', 'Estructura atómica', 'Tabla periódica', 'Enlace químico', 'Estequiometría'],
        'Cálculo 2' => ['Antiderivadas', 'Integral definida', 'Teorema fundamental del cálculo', 'Integración por sustitución', 'Integración por partes', 'Fracciones parciales', 'Integrales impropias', 'Repaso para el parcial', 'Áreas entre curvas', 'Volúmenes de sólidos', 'Longitud de arco', 'Sucesiones', 'Series', 'Series de potencias', 'Series de Taylor', 'Repaso final'],
        'Programación 1' => ['Algoritmos y diagramas de flujo', 'Variables y tipos de datos', 'Estructuras condicionales', 'Estructuras repetitivas', 'Funciones'],
        'Redacción Académica' => ['El texto académico', 'Coherencia y cohesión', 'El párrafo', 'Signos de puntuación', 'Normas APA', 'Citas y referencias', 'El resumen', 'Repaso para el parcial', 'La reseña', 'El ensayo', 'Argumentación', 'El informe', 'Revisión entre pares', 'Exposición oral', 'Corrección de estilo', 'Repaso final'],
        'Biología Celular' => ['La célula', 'Membrana celular', 'Organelos', 'Ciclo celular', 'Genética básica'],
        'Metodología de la Investigación' => ['El problema de investigación', 'Marco teórico', 'Hipótesis y variables', 'Diseño metodológico', 'Proyecto de investigación'],
        'Historia del Perú' => ['Poblamiento de América', 'Culturas preíncas', 'El Tahuantinsuyo', 'La conquista', 'El virreinato', 'Rebeliones del siglo XVIII', 'La independencia', 'Repaso para el parcial', 'Primeros años de la república', 'La era del guano', 'Guerra del Pacífico', 'La reconstrucción nacional', 'La república aristocrática', 'El oncenio de Leguía', 'El Perú contemporáneo', 'Repaso final'],
        'Economía General' => ['Conceptos básicos de economía', 'Oferta y demanda', 'Elasticidad', 'Mercados', 'Macroeconomía básica'],
        'Álgebra Lineal' => ['Sistemas de ecuaciones lineales', 'Matrices', 'Determinantes', 'Espacios vectoriales', 'Transformaciones lineales'],
        'Geografía General' => ['La Tierra en el universo', 'Cartografía', 'Relieve', 'Clima', 'Hidrografía', 'Biomas', 'Población', 'Repaso para el parcial', 'Ciudades', 'Actividades económicas', 'Recursos naturales', 'Riesgos naturales', 'Geografía del Perú', 'Regiones naturales', 'Ordenamiento territorial', 'Repaso final'],
        'Filosofía' => ['¿Qué es la filosofía?', 'Filosofía antigua', 'Ética', 'Teoría del conocimiento', 'Filosofía política'],
    ];

    protected const NOMBRES = ['Lucía', 'Mateo', 'Valeria', 'Sebastián', 'Camila', 'Diego', 'Daniela', 'Joaquín', 'Fernanda', 'Gabriel', 'Ximena', 'Adrián', 'Mariana', 'Rodrigo', 'Alejandra', 'Nicolás', 'Andrea', 'Santiago', 'Paola', 'Emilio', 'Renata', 'Bruno', 'Carolina', 'Gonzalo', 'Milagros', 'Fabián', 'Rosa', 'Martín', 'Isabel', 'Álvaro'];

    protected const APELLIDOS = ['Quispe', 'Flores', 'Huamán', 'Rojas', 'Mendoza', 'Vargas', 'Castillo', 'Chávez', 'Ramírez', 'Torres', 'Salazar', 'Gutiérrez', 'Paredes', 'Cárdenas', 'Medina', 'Espinoza', 'Ríos', 'Navarro', 'Campos', 'Vega', 'Aguilar', 'Palomino', 'Ccori', 'Mamani', 'Huanca', 'Benites', 'Zapata', 'Lozano', 'Valdivia', 'Arce'];

    /** Estudiantes del grupo de prueba (se reparten y reutilizan entre cursos). */
    protected const ESTUDIANTES = 60;

    protected User $profesor;

    protected User $supervisor;

    /** Otro profesor existente (el activo de menor id que no es Pepito); null si no hay. */
    protected ?User $otroProfesor = null;

    /** @var array<string, list<int|array<string, int>>> tabla => ids creados (o filas de pivote) */
    protected array $creados = [];

    /** @var array<string, mixed> */
    protected array $manifiesto = [];

    /** @var list<string> */
    public array $avisos = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('PruebasProfesorSeeder no se ejecuta en producción.');
        }

        // Nada sale de la aplicación: correo a "log" y, además, Mail/Notification/Queue simulados.
        config(['mail.default' => 'log']);
        Mail::fake();
        Notification::fake();
        Queue::fake();

        app(ConfiguracionPlataforma::class)->aplicarZonaHoraria();

        $this->profesor = $this->profesorTitular();
        $this->supervisor = User::role(Rol::Supervisor->value)->orderBy('id')->firstOrFail();
        $this->manifiesto = $this->leerManifiesto();

        // Registra cada fila creada por Eloquent durante la ejecución (para el manifiesto).
        Event::listen('eloquent.created: *', function (string $evento, array $modelos) {
            foreach ($modelos as $modelo) {
                if ($modelo instanceof Model) {
                    $this->creados[$modelo->getTable()][] = $modelo->getKey();
                }
            }
        });

        // Solo se usa un profesor que ya existe; no se crea ni se modifica.
        $this->otroProfesor = User::role(Rol::Profesor->value)->where('activo', true)
            ->whereKeyNot($this->profesor->id)->orderBy('id')->first();
        $todos = array_merge(self::CURSOS, $this->otroProfesor ? self::CURSOS_OTRO_PROFESOR : []);

        // Ya se ejecutó: todos los cursos existen. No se revisa ni se crea nada.
        if (Curso::whereIn('codigo', array_column($todos, 0))->count() === count($todos)) {
            return;
        }

        if (! $this->otroProfesor) {
            $this->avisos[] = 'No hay otro profesor activo: se omiten los cursos de otro titular y el curso compartido.';
        }

        DB::transaction(function () {
            $estudiantes = $this->estudiantes();

            foreach (self::CURSOS as $indice => $datos) {
                $codocente = $datos[0] === self::CURSO_COMPARTIDO ? $this->otroProfesor : null;
                $this->crearCurso($datos, $indice, $estudiantes, $this->profesor, $codocente);
            }

            if ($this->otroProfesor) {
                foreach (self::CURSOS_OTRO_PROFESOR as $n => $datos) {
                    $this->crearCurso($datos, count(self::CURSOS) + $n, $estudiantes, $this->otroProfesor);
                }
            }
        });

        $this->guardarManifiesto();
    }

    protected function profesorTitular(): User
    {
        $profesores = User::role(Rol::Profesor->value)->where('name', self::PROFESOR)->get();

        if ($profesores->count() !== 1) {
            throw new RuntimeException('Se esperaba exactamente un profesor llamado "'.self::PROFESOR.'"; hay '.$profesores->count().'.');
        }

        return $profesores->first();
    }

    // ==========================================
    // ESTUDIANTES
    // ==========================================

    /**
     * Estudiantes de prueba con correo nombreapellido@lms.com (la convención
     * de las cuentas de estudiante actuales). Un correo que ya existe y no es
     * de este seeder no se toca: se prueba con el segundo apellido y, si
     * también existe, se omite ese estudiante.
     *
     * @return Collection<int, User>
     */
    protected function estudiantes(): Collection
    {
        $propios = collect($this->manifiesto['tablas']['users'] ?? []);
        $cuentas = app(GestorCuentas::class);
        $azar = new Randomizer(new Mt19937(2026));
        $estudiantes = collect();

        for ($i = 0; $i < self::ESTUDIANTES; $i++) {
            $nombre = self::NOMBRES[$i % count(self::NOMBRES)];
            $apellido1 = self::APELLIDOS[($i * 7 + intdiv($i, count(self::NOMBRES))) % count(self::APELLIDOS)];
            $apellido2 = self::APELLIDOS[($i * 11 + 3) % count(self::APELLIDOS)];
            $dni = (string) $azar->getInt(70000000, 79999999);

            $usuario = null;

            foreach ([$nombre.$apellido1, $nombre.$apellido1.$apellido2] as $base) {
                $email = Str::of($base)->ascii()->lower()->replaceMatches('/[^a-z]/', '').'@'.self::DOMINIO;
                $existente = User::where('email', $email)->first();

                if ($existente && $propios->contains($existente->id)) {
                    $usuario = $existente;
                    break;
                }

                if (! $existente) {
                    // Contraseña aleatoria (GestorCuentas sin DNI): no se guarda ni se muestra.
                    $usuario = $cuentas->crear($nombre, "{$apellido1} {$apellido2}", $email, Rol::Estudiante->value, $this->supervisor)['usuario'];

                    if (! User::where('dni', $dni)->exists()) {
                        $usuario->update(['dni' => $dni]);
                    }
                    break;
                }
            }

            if ($usuario) {
                $estudiantes->push($usuario);
            } else {
                $this->avisos[] = "Se omitió a {$nombre} {$apellido1}: sus correos ya existen y no son de este seeder.";
            }
        }

        return $estudiantes;
    }

    // ==========================================
    // CURSOS
    // ==========================================

    /**
     * @param  array{0: string, 1: string, 2: string, 3: int, 4: int, 5: string, 6: int, 7: int, 8: bool}  $datos
     * @param  Collection<int, User>  $estudiantes
     * @param  User  $titular  profesor existente
     * @param  User|null  $codocente  profesor existente que se agrega como profesor adicional (curso_profesor)
     */
    protected function crearCurso(array $datos, int $indice, Collection $estudiantes, User $titular, ?User $codocente = null): void
    {
        [$codigo, $nombre, $estadoFinal, $semanas, $desfase, $modalidad, $cupo, $matriculados, $actualVacia] = $datos;

        if (Curso::where('codigo', $codigo)->exists()) {
            return;
        }

        // Mismas reglas del formulario del Supervisor: formato, unicidad y un prefijo por materia.
        if (! preg_match(Curso::FORMATO_CODIGO, $codigo) || Curso::prefijoPerteneceAOtraMateria($codigo, $nombre)) {
            $this->avisos[] = "Se omitió {$codigo}: su prefijo ya pertenece a otra materia.";

            return;
        }

        $azar = new Randomizer(new Mt19937(crc32($codigo)));
        $inicio = now()->startOfWeek()->addWeeks($desfase);

        $curso = Curso::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'descripcion' => "Curso de {$nombre} para estudiantes de los primeros ciclos.",
            'profesor_id' => $titular->id,
            'estado' => 'borrador',
            'fecha_inicio' => $inicio->toDateString(),
            // Igual que el formulario: la fecha de fin es el inicio más la duración.
            'fecha_fin' => $inicio->copy()->addWeeks($semanas)->toDateString(),
            'semanas_duracion' => $semanas,
            'cupo_maximo' => $cupo,
            'modalidad' => $modalidad,
            'horario_bloques' => $modalidad === 'presencial'
                ? [['dia' => 1 + $indice % 3, 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'aula' => 'A-'.(201 + $indice)],
                    ['dia' => 4, 'hora_inicio' => '10:00', 'hora_fin' => '12:00', 'aula' => 'A-'.(201 + $indice)]]
                : null,
            'usa_asistencia' => true,
        ]);
        $curso->generarSesionesDesdeHorario();

        // Igual que el formulario: los profesores adicionales se sincronizan al guardar el curso.
        if ($codocente && $codocente->id !== $titular->id) {
            $curso->profesoresAdicionales()->sync([$codocente->id]);
            $this->creados['curso_profesor'][] = ['curso_id' => $curso->id, 'profesor_id' => $codocente->id];
        }

        $this->bitacoraCurso($curso, 'Curso creado: ');

        $rubros = $this->esquema($curso, $semanas);
        $actual = 1 - $desfase;
        $this->semanas($curso, $nombre, $semanas, $actual, $actualVacia, $estadoFinal, $azar);

        $inscritos = $this->matricular($curso, $estudiantes, $matriculados, $indice);
        $actividades = $estadoFinal === 'borrador' ? [] : $this->actividades($curso, $nombre, $semanas, $actual, $actualVacia, $estadoFinal, $inscritos, $azar);
        $this->asignarEvaluaciones($curso, $rubros, $actividades, $estadoFinal, $actual, $titular);

        if ($estadoFinal !== 'borrador') {
            // RF-25: se publica con contenido y matriculados; el archivado viene después del periodo.
            $curso->update(['estado' => 'publicado']);
            $this->bitacoraCurso($curso, 'Curso editado: ');

            if ($estadoFinal === 'archivado') {
                $curso->update(['estado' => 'archivado']);
                $this->bitacoraCurso($curso, 'Curso editado: ');
            }
        }
    }

    protected function bitacoraCurso(Curso $curso, string $prefijo): void
    {
        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $this->supervisor,
            tipoEvento: 'modificacion_curso',
            descripcion: $prefijo."\"{$curso->nombre}\" (estado {$curso->estado}).",
            curso: $curso,
        );
    }

    /**
     * Esquema del Supervisor (RF-54..56): semanas dentro de la duración y
     * pesos que suman exactamente 100 %.
     *
     * @return Collection<int, RubroEvaluacion>
     */
    protected function esquema(Curso $curso, int $semanas): Collection
    {
        $rubros = $semanas >= 16
            ? [['Examen parcial', 8, 30], ['Trabajo de investigación', 12, 30], ['Examen final', 16, 40]]
            : [['Evaluación 1', 2, 40], ['Evaluación 2', 5, 60]];

        if (! app(CalculadoraNotas::class)->pesosSumanCien(array_column($rubros, 2)) || max(array_column($rubros, 1)) > $semanas) {
            throw new RuntimeException("Esquema inválido para {$curso->codigo}.");
        }

        $esquema = EsquemaEvaluacion::firstOrCreate(['curso_id' => $curso->id]);

        foreach ($rubros as $orden => [$nombre, $semana, $peso]) {
            $esquema->rubros()->create(['nombre' => $nombre, 'semana' => $semana, 'peso' => $peso, 'orden' => $orden + 1]);
        }

        $esquema->load('rubros');

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $this->supervisor,
            tipoEvento: 'modificacion_esquema',
            descripcion: "Esquema de evaluación guardado para \"{$curso->nombre}\" ({$esquema->rubros->count()} rubros, 100 %).",
            curso: $curso,
            valorNuevo: $esquema->rubros->map->only(['nombre', 'semana', 'peso'])->values()->all(),
        );

        return $esquema->rubros;
    }

    /**
     * Semanas con contenido variado: las pasadas casi todas con elementos en
     * varias secciones (alguna queda vacía), la actual a veces vacía, la
     * siguiente programada en algunos cursos y las demás sin contenido.
     */
    protected function semanas(Curso $curso, string $materia, int $total, int $actual, bool $actualVacia, string $estadoFinal, Randomizer $azar): void
    {
        $temas = self::TEMAS[$materia];
        $pasadaVacia = $actual > 4 ? $azar->getInt(2, $actual - 2) : null;
        $prepararSiguiente = $azar->getInt(0, 1) === 1 || $actual < 1;

        for ($numero = 1; $numero <= $total; $numero++) {
            $tema = $temas[($numero - 1) % count($temas)];
            $inicioSemana = $curso->rangoSemana($numero)[0];

            $conContenido = match (true) {
                $estadoFinal === 'borrador' => $numero <= 2,
                $numero < $actual => $numero !== $pasadaVacia,
                $numero === $actual => ! $actualVacia,
                $numero === $actual + 1 => $prepararSiguiente || $actualVacia,
                default => false,
            };

            if (! $conContenido) {
                continue;
            }

            $yaEmpezo = $inicioSemana->lte(now());
            $modulo = Modulo::create([
                'curso_id' => $curso->id,
                'semana' => $numero,
                'titulo' => "Semana {$numero}: {$tema}",
                'materiales' => [],
                'estado_contenido' => $estadoFinal === 'borrador' ? 'borrador' : ($yaEmpezo ? 'publicado' : 'programado'),
                'publicar_en' => $estadoFinal !== 'borrador' && ! $yaEmpezo ? $inicioSemana->copy()->setTime(7, 0) : null,
            ]);

            $estadoLeccion = $estadoFinal === 'borrador' ? 'borrador' : ($yaEmpezo ? 'publicado' : 'programado');
            $fechaLeccion = $estadoLeccion === 'programado' ? $inicioSemana->copy()->setTime(7, 0) : null;
            $orden = 1;

            $lecciones = [
                ['mensaje_inicio', 'Bienvenida a la semana', "Esta semana trabajamos {$tema}. Revisa los materiales antes de la clase y trae tus dudas."],
                ['recursos', "Presentación: {$tema}", "Diapositivas de la clase sobre {$tema}."],
            ];

            if ($azar->getInt(0, 1) === 1) {
                $lecciones[] = ['recursos', "Lectura: {$tema}", 'Capítulo del texto base del curso.'];
            }

            if ($azar->getInt(0, 2) > 0) {
                $lecciones[] = ['actividades', "Ejercicios de {$tema}", 'Ejercicios de práctica sin nota, con solucionario al final de la semana.'];
            }

            if ($yaEmpezo && $numero < $actual) {
                $lecciones[] = ['grabacion', "Grabación de la clase {$numero}", "Grabación de la sesión sobre {$tema}."];
            }

            foreach ($lecciones as [$seccion, $titulo, $descripcion]) {
                $modulo->lecciones()->create([
                    'titulo' => $titulo,
                    'descripcion' => $descripcion,
                    'estado' => $estadoLeccion,
                    'fecha_publicacion' => $fechaLeccion,
                    'orden' => $orden++,
                    'tipo_recurso' => $seccion,
                ]);
            }
        }
    }

    /**
     * Matrícula manual con el control de cupo de la pantalla (cupoLleno) y
     * GestorMatriculas (estudiante activo, rol estudiante, curso no archivado).
     *
     * @param  Collection<int, User>  $estudiantes
     * @return Collection<int, User>
     */
    protected function matricular(Curso $curso, Collection $estudiantes, int $cantidad, int $indice): Collection
    {
        $gestor = app(GestorMatriculas::class);
        // Cada curso toma un tramo distinto del grupo: así un mismo estudiante aparece en varios cursos.
        $elegidos = $estudiantes->values()->slice(($indice * 13) % max(1, $estudiantes->count()))
            ->concat($estudiantes->values())
            ->unique('id')
            ->take(min($cantidad, (int) $curso->cupo_maximo));
        $inscritos = collect();

        foreach ($elegidos as $estudiante) {
            if ($curso->cupoLleno()) {
                break;
            }

            $gestor->matricularManual($curso, $estudiante, $this->supervisor);
            $inscritos->push($estudiante);
        }

        return $inscritos;
    }

    /**
     * Tareas y quizzes del curso con fechas pasadas y futuras, grupos para la
     * tarea grupal, entregas (pendientes y calificadas de 0 a 20) y
     * resultados de quiz.
     *
     * @param  Collection<int, User>  $inscritos
     * @return list<Tarea|Quiz> actividades ya cerradas, en orden (para asignarlas a evaluaciones)
     */
    protected function actividades(Curso $curso, string $materia, int $total, int $actual, bool $actualVacia, string $estadoFinal, Collection $inscritos, Randomizer $azar): array
    {
        $temas = self::TEMAS[$materia];
        $terminado = $actual > $total;
        $ultima = min($actual, $total);
        $cerradas = [];

        // Semanas de las tareas: dos ya cerradas y una por venir (o todas cerradas si el curso terminó).
        $planTareas = $terminado
            ? [[2, false], [4, true]]
            : array_values(array_filter([
                $actual >= 3 ? [$actual - 2, false] : null,
                $actual >= 2 ? [$actual - 1, in_array($curso->codigo, ['EST-26-01', 'FIS-26-01', 'CAL2-26-01'], true)] : null,
                [min($actual + 1, $total), false],
            ]));

        foreach ($planTareas as $n => [$semana, $grupal]) {
            $tema = $temas[($semana - 1) % count($temas)];
            [$lunes] = $curso->rangoSemana($semana);
            $cierre = $lunes->copy()->addDays(4)->setTime(23, 59);
            $tarea = Tarea::create([
                'curso_id' => $curso->id,
                'modulo_id' => $this->moduloDeSemana($curso, $semana)->id,
                'titulo' => ($grupal ? 'Trabajo grupal: ' : 'Práctica '.($n + 1).': ').$tema,
                'descripcion' => "Resuelve los ejercicios propuestos sobre {$tema} y sube tu desarrollo en PDF.",
                'instrucciones' => 'Entrega un solo archivo con tu nombre completo en la primera página.',
                'fecha_inicio' => $lunes->copy()->setTime(8, 0),
                'fecha_entrega' => $cierre,
                'peso_nota' => 1,
                'intentos_permitidos' => 1,
                'tipo_entrega' => $grupal ? 'grupal' : 'individual',
                'visible' => true,
                'estado_contenido' => 'publicado',
            ]);

            $this->entregas($tarea, $inscritos, $estadoFinal, $terminado, $azar, $n === 0);

            if ($cierre->lt(now())) {
                $cerradas[] = $tarea;
            }
        }

        // Quizzes: uno ya cerrado con resultados y otro abierto (si el curso no terminó).
        // Con la semana actual vacía, el quiz abierto va en la siguiente para que la actual siga sin contenido.
        $abierto = $actualVacia ? ($actual + 1 <= $total ? $actual + 1 : null) : $ultima;
        $planQuizzes = $terminado ? [3] : array_values(array_filter([$actual >= 2 ? $actual - 1 : null, $abierto]));

        foreach ($planQuizzes as $semana) {
            $tema = $temas[($semana - 1) % count($temas)];
            [$lunes] = $curso->rangoSemana($semana);
            $quiz = Quiz::create([
                'curso_id' => $curso->id,
                'modulo_id' => $this->moduloDeSemana($curso, $semana)->id,
                'tema' => "Quiz: {$tema}",
                'preguntas' => [
                    ['enunciado' => "¿Cuál es la idea central de {$tema}?", 'opciones' => ['La definición vista en clase', 'Un caso particular', 'Ninguna de las anteriores'], 'respuesta_correcta' => 0],
                    ['enunciado' => "¿En qué situación aplicarías {$tema}?", 'opciones' => ['En ningún caso', 'En el problema resuelto en clase', 'Solo en el examen'], 'respuesta_correcta' => 1],
                    ['enunciado' => '¿Cuál de estas afirmaciones es correcta?', 'opciones' => ['La primera', 'La segunda', 'La tercera'], 'respuesta_correcta' => 2],
                ],
                'configuracion' => ['intentos' => 1, 'tiempo_limite_min' => 20],
                'fecha_inicio' => $lunes->copy()->setTime(8, 0),
                'fecha_limite' => $lunes->copy()->addDays(4)->setTime(23, 59),
                'visible' => true,
                'estado_contenido' => 'publicado',
            ]);

            if ($quiz->fecha_limite->lt(now())) {
                $this->resultados($quiz, $inscritos, $azar);
                $cerradas[] = $quiz;
            }
        }

        return $cerradas;
    }

    /** Módulo de la semana (lo crea como la acción "Editar semana" si aún no existe). */
    protected function moduloDeSemana(Curso $curso, int $semana): Modulo
    {
        return Modulo::firstOrCreate(
            ['curso_id' => $curso->id, 'semana' => $semana],
            ['titulo' => 'Semana '.$semana, 'materiales' => [], 'estado_contenido' => 'publicado'],
        );
    }

    /**
     * @param  Collection<int, User>  $inscritos
     */
    protected function entregas(Tarea $tarea, Collection $inscritos, string $estadoFinal, bool $terminado, Randomizer $azar, bool $esLaMasAntigua): void
    {
        // Nadie entrega antes de que la tarea se abra.
        if ($tarea->fecha_inicio->gt(now())) {
            return;
        }

        $cerrada = $tarea->fecha_entrega->lt(now());
        $comentarios = ['Buen trabajo, revisa el último ejercicio.', 'Desarrollo claro y ordenado.', 'Falta justificar algunos pasos.', 'Excelente análisis.', 'Revisa las unidades en tus respuestas.'];
        $momento = fn () => $tarea->fecha_inicio->copy()->addMinutes($azar->getInt(60, max(61, (int) $tarea->fecha_inicio->diffInMinutes(min($tarea->fecha_entrega, now())))));

        if ($tarea->tipo_entrega === 'grupal') {
            foreach ($inscritos->values()->chunk(4) as $n => $miembros) {
                $grupo = Grupo::create(['curso_id' => $tarea->curso_id, 'nombre' => 'Grupo '.($n + 1)]);
                $grupo->estudiantes()->attach($miembros->pluck('id')->all());

                foreach ($miembros as $miembro) {
                    $this->creados['grupo_estudiante'][] = ['grupo_id' => $grupo->id, 'estudiante_id' => $miembro->id];
                }

                if ($azar->getInt(1, 10) > 8) {
                    continue;
                }

                $fecha = $momento();

                // Una entrega grupal crea una fila por integrante, igual que Tareas\Detalle.
                foreach ($miembros as $miembro) {
                    $entrega = Entrega::create(['tarea_id' => $tarea->id, 'estudiante_id' => $miembro->id, 'grupo_id' => $grupo->id,
                        'respuesta_texto' => 'Informe del grupo adjunto en el enlace.', 'enlace' => 'https://docs.google.com/document/d/informe-grupo-'.($n + 1),
                        'estado' => 'entregado', 'intentos_usados' => 1]);
                    $entrega->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->saveQuietly();
                }
            }

            return;
        }

        foreach ($inscritos as $estudiante) {
            $probabilidad = $cerrada ? 85 : 15;

            if ($azar->getInt(1, 100) > $probabilidad) {
                continue;
            }

            $calificar = $cerrada && ($estadoFinal === 'archivado' || $terminado || $azar->getInt(1, 100) <= ($esLaMasAntigua ? 75 : 25));
            $fecha = $momento();
            $entrega = Entrega::create([
                'tarea_id' => $tarea->id,
                'estudiante_id' => $estudiante->id,
                'respuesta_texto' => 'Adjunto mi desarrollo de los ejercicios.',
                'estado' => $calificar ? 'calificado' : 'entregado',
                'intentos_usados' => 1,
                'nota' => $calificar ? $azar->getInt(16, 40) / 2 : null,
                'feedback' => $calificar ? $comentarios[$azar->getInt(0, count($comentarios) - 1)] : null,
            ]);
            $entrega->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->saveQuietly();
        }
    }

    /**
     * @param  Collection<int, User>  $inscritos
     */
    protected function resultados(Quiz $quiz, Collection $inscritos, Randomizer $azar): void
    {
        foreach ($inscritos as $estudiante) {
            if ($azar->getInt(1, 100) > 75) {
                continue;
            }

            $respuestas = array_map(fn () => $azar->getInt(0, 2), $quiz->preguntas);
            $quiz->resultados()->create([
                'estudiante_id' => $estudiante->id,
                'intento' => 1,
                'respuestas' => $respuestas,
                'puntaje' => $quiz->calcularPuntaje($respuestas),
                'tiempo_usado_seg' => $azar->getInt(240, 1150),
            ]);
        }
    }

    /**
     * Vincula actividades ya cerradas a las evaluaciones con AsignadorEvaluacion
     * (pesos al 100 %). En un curso en marcha solo la primera evaluación recibe
     * actividades: las siguientes quedan SIN actividades asignadas. En uno
     * terminado o archivado se reparten entre todas. EST-26-02 queda sin
     * ninguna asignación.
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @param  list<Tarea|Quiz>  $actividades
     */
    protected function asignarEvaluaciones(Curso $curso, Collection $rubros, array $actividades, string $estadoFinal, int $actual, User $titular): void
    {
        if ($actividades === [] || $curso->codigo === 'EST-26-02') {
            return;
        }

        $asignador = app(AsignadorEvaluacion::class);
        $completo = $estadoFinal === 'archivado' || $actual > (int) $curso->semanas_duracion;
        $destinos = $completo ? $rubros->values() : $rubros->take(1)->values();

        foreach ($actividades as $n => $actividad) {
            $rubro = $destinos[$n % $destinos->count()];
            $asignador->asignar($curso, $rubro->fresh(), $actividad, false, $titular);
            $tabla = $actividad instanceof Tarea ? 'rubro_tarea' : 'rubro_quiz';
            $this->creados[$tabla][] = ['rubro_id' => $rubro->id, ($actividad instanceof Tarea ? 'tarea_id' : 'quiz_id') => $actividad->id];
        }
    }

    // ==========================================
    // MANIFIESTO
    // ==========================================

    public static function rutaManifiesto(): string
    {
        return self::$rutaManifiesto !== '' ? self::$rutaManifiesto : storage_path('app/pruebas-profesor-manifiesto.json');
    }

    /** @return array<string, mixed> */
    protected function leerManifiesto(): array
    {
        $ruta = self::rutaManifiesto();

        return File::exists($ruta) ? (json_decode(File::get($ruta), true) ?: []) : [];
    }

    protected function guardarManifiesto(): void
    {
        // Roles asignados a los estudiantes nuevos (pivote model_has_roles).
        foreach ($this->creados['users'] ?? [] as $id) {
            $this->creados['model_has_roles'][] = ['model_id' => $id, 'role' => Rol::Estudiante->value];
        }

        $tablas = $this->manifiesto['tablas'] ?? [];

        foreach ($this->creados as $tabla => $filas) {
            $tablas[$tabla] = array_values(array_unique(array_merge($tablas[$tabla] ?? [], $filas), SORT_REGULAR));
        }

        ksort($tablas);

        File::put(self::rutaManifiesto(), json_encode([
            'descripcion' => 'Filas creadas por PruebasProfesorSeeder (por tabla), para poder borrarlas con precisión. No contiene contraseñas.',
            'profesor_id' => $this->profesor->id,
            'otro_profesor_id' => $this->otroProfesor?->id,
            'actualizado' => now()->toIso8601String(),
            'tablas' => $tablas,
            'avisos' => $this->avisos,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
