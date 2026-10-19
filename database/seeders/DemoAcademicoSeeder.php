<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Asistencia;
use App\Models\BitacoraAuditoria;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EventoCalendario;
use App\Models\Grupo;
use App\Models\IntegracionExterna;
use App\Models\Mensaje;
use App\Models\Modulo;
use App\Models\NotaRubro;
use App\Models\Quiz;
use App\Models\ResultadoQuiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Models\User;
use App\Notifications\NotaRubroRegistrada;
use App\Notifications\NuevaTareaAsignada;
use App\Notifications\TareaCalificada;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Datos de ejemplo coherentes para revisar visualmente cada requisito del ERS
 * sin una base compartida: profesores, estudiantes, cursos en distintos
 * estados (publicados, borrador y un periodo archivado con historial),
 * esquemas al 100 %, notas por rubro (algunas bajo la nota mínima), tareas en
 * todos sus estados, quizzes con intentos, sesiones con asistencia, grupos,
 * comunicados, mensajes, eventos de calendario, una matrícula por API, una
 * baja y registros de auditoría.
 *
 * Es idempotente: si el curso MAT-201 ya existe, no hace nada. Las fechas son
 * relativas a hoy (el periodo actual va en su semana 9 de 16). Todas las
 * cuentas usan la contraseña "password123".
 */
class DemoAcademicoSeeder extends Seeder
{
    protected const PASSWORD = 'password123';

    protected User $supervisor;

    /** @var array<string, User> */
    protected array $profesores = [];

    /** @var Collection<int, User> */
    protected Collection $estudiantes;

    protected Carbon $inicioPeriodo;

    public function run(): void
    {
        if (Curso::where('codigo', 'MAT-201')->exists()) {
            $this->command?->info('Datos de ejemplo ya cargados (curso MAT-201). No se hace nada.');

            return;
        }

        mt_srand(2026);
        $this->inicioPeriodo = now()->startOfWeek()->subWeeks(8)->setTime(8, 0);

        $this->crearUsuarios();

        foreach ($this->catalogoCursos() as $datos) {
            $this->crearCurso($datos);
        }

        $this->crearComunicadosInstitucionales();
        $this->crearIntegracion();
        $this->crearEventosPersonales();
    }

    // ==========================================
    // USUARIOS
    // ==========================================

    protected function crearUsuarios(): void
    {
        $this->supervisor = $this->usuario('admin@lms.edu.pe', 'Supervisor General', Rol::Supervisor);

        $this->profesores = [
            'perez' => $this->usuario('profesor@lms.edu.pe', 'Juan Pérez Salinas', Rol::Profesor),
            'castillo' => $this->usuario('maria.castillo@lms.edu.pe', 'María Castillo Ramos', Rol::Profesor),
            'quispe' => $this->usuario('jorge.quispe@lms.edu.pe', 'Jorge Quispe Huamán', Rol::Profesor),
            'flores' => $this->usuario('lucia.flores@lms.edu.pe', 'Lucía Flores Paredes', Rol::Profesor),
        ];

        $nombres = [
            'Ana Torres Vega', 'Luis Mendoza Rojas', 'Camila Salazar Díaz', 'Diego Chávez León', 'Valeria Gutiérrez Soto',
            'Mateo Vargas Ríos', 'Sofía Ramírez Cruz', 'Sebastián Herrera Luna', 'Isabella Castro Núñez', 'Nicolás Morales Paz',
            'Lucía Ortiz Campos', 'Gabriel Silva Medina', 'Daniela Rojas Aguilar', 'Andrés Navarro Pinto', 'Fernanda Cárdenas Ruiz',
            'Joaquín Espinoza Vera', 'Mariana Delgado Ponce', 'Rodrigo Fuentes Arce', 'Paula Villanueva Lara', 'Adrián Palacios Reyes',
            'Renata Cabrera Mejía', 'Tomás Benítez Olivares', 'Alejandra Zapata Ibáñez', 'Martín Guerrero Salas',
        ];

        $this->estudiantes = collect([$this->usuario('estudiante@lms.edu.pe', 'Estudiante Demo', Rol::Estudiante)]);

        foreach ($nombres as $indice => $nombre) {
            $partes = explode(' ', Str::ascii(Str::lower($nombre)));
            $correo = "{$partes[0]}.{$partes[1]}@estudiantes.lms.edu.pe";

            $estudiante = $this->usuario($correo, $nombre, Rol::Estudiante, [
                // RF-05: dos cuentas nuevas que aún deben cambiar su contraseña temporal.
                'primer_login' => in_array($indice, [20, 21], true),
                // RF-15: dos cuentas desactivadas que conservan su historial.
                'activo' => ! in_array($indice, [22, 23], true),
            ]);
            $estudiante->forceFill(['created_at' => now()->subDays(mt_rand(3, 120))])->saveQuietly();
            $this->estudiantes->push($estudiante);
        }

        foreach ($this->estudiantes->take(4) as $estudiante) {
            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: $this->supervisor,
                tipoEvento: 'creacion_cuenta',
                descripcion: "Cuenta de estudiante creada para {$estudiante->name} (importación masiva).",
                usuarioAfectado: $estudiante,
            );
        }
    }

    /** DNI de prueba (8 dígitos) que todavía no usa ninguna cuenta. */
    protected function siguienteDni(): string
    {
        do {
            $dni = (string) mt_rand(40000000, 79999999);
        } while (User::where('dni', $dni)->exists());

        return $dni;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function usuario(string $correo, string $nombre, Rol $rol, array $extra = []): User
    {
        $usuario = User::firstOrNew(['email' => $correo]);

        if (! $usuario->exists) {
            $usuario->fill(['name' => $nombre, 'dni' => $this->siguienteDni(), 'password' => Hash::make(self::PASSWORD)]);
            $usuario->email_verified_at = now();
        }

        $usuario->fill(['activo' => $extra['activo'] ?? true, 'primer_login' => $extra['primer_login'] ?? false]);
        $usuario->save();
        $usuario->syncRoles([$rol->value]);

        return $usuario;
    }

    // ==========================================
    // CURSOS
    // ==========================================

    /**
     * @return list<array<string, mixed>>
     */
    protected function catalogoCursos(): array
    {
        return [
            [
                'codigo' => 'MAT-201', 'nombre' => 'Cálculo Diferencial', 'profesor' => 'perez', 'adicionales' => ['flores'],
                'descripcion' => 'Límites, continuidad, derivadas y sus aplicaciones en problemas de optimización y razón de cambio.',
                'estado' => 'publicado', 'asistencia' => true, 'estudiantes' => 15, 'grupos' => true,
                'modulos' => [
                    ['Límites y continuidad', ['Idea intuitiva de límite', 'Límites laterales y al infinito', 'Continuidad de funciones']],
                    ['La derivada', ['Definición de derivada', 'Reglas de derivación', 'Regla de la cadena']],
                    ['Aplicaciones de la derivada', ['Máximos y mínimos', 'Optimización', 'Razón de cambio']],
                    ['Derivadas de orden superior', ['Segunda derivada y concavidad', 'Trazado de curvas'], 'programado'],
                ],
                'tareas' => ['Práctica de límites', 'Taller de reglas de derivación', 'Proyecto grupal de optimización', 'Informe de trazado de curvas'],
                'quizzes' => ['Autoevaluación de límites', 'Quiz de derivadas'],
            ],
            [
                'codigo' => 'PRG-110', 'nombre' => 'Programación Web con Laravel', 'profesor' => 'perez', 'adicionales' => [],
                'descripcion' => 'Desarrollo de aplicaciones web con PHP y Laravel: rutas, controladores, Eloquent, Blade y Livewire.',
                'estado' => 'publicado', 'asistencia' => true, 'estudiantes' => 12, 'api' => true, 'baja' => true,
                'modulos' => [
                    ['Fundamentos de PHP', ['Sintaxis y tipos', 'Funciones y arreglos']],
                    ['Rutas y controladores', ['Enrutamiento en Laravel', 'Controladores y validación']],
                    ['Base de datos con Eloquent', ['Migraciones', 'Modelos y relaciones', 'Consultas eficientes']],
                    ['Interfaces con Livewire', ['Componentes', 'Formularios reactivos']],
                ],
                'tareas' => ['Ejercicios de PHP', 'CRUD con controladores', 'Modelo de datos del proyecto', 'Proyecto final con Livewire'],
                'quizzes' => ['Quiz de fundamentos', 'Quiz de Eloquent'],
            ],
            [
                'codigo' => 'COM-105', 'nombre' => 'Comunicación Efectiva', 'profesor' => 'castillo', 'adicionales' => [],
                'descripcion' => 'Comunicación oral y escrita en contextos académicos y profesionales.',
                'estado' => 'publicado', 'asistencia' => false, 'estudiantes' => 10,
                'modulos' => [
                    ['Bases de la comunicación', ['Elementos del proceso comunicativo', 'Barreras de la comunicación']],
                    ['Redacción académica', ['El párrafo', 'Citas y referencias']],
                    ['Exposición oral', ['Estructura de una presentación', 'Manejo del escenario']],
                ],
                'tareas' => ['Ensayo breve', 'Reseña con citas APA', 'Presentación grabada'],
                'quizzes' => ['Quiz de redacción'],
            ],
            [
                'codigo' => 'EST-220', 'nombre' => 'Estadística Aplicada', 'profesor' => 'quispe', 'adicionales' => [],
                'descripcion' => 'Estadística descriptiva, probabilidad e inferencia con casos reales.',
                'estado' => 'publicado', 'asistencia' => true, 'estudiantes' => 12,
                'modulos' => [
                    ['Estadística descriptiva', ['Medidas de tendencia central', 'Medidas de dispersión']],
                    ['Probabilidad', ['Espacio muestral', 'Distribución normal']],
                    ['Inferencia', ['Intervalos de confianza', 'Pruebas de hipótesis']],
                ],
                'practicas' => [
                    ['Guía de la salida de campo', 'Protocolo para levantar una encuesta en el campus: muestreo, consentimiento y registro.', 'lectura'],
                    ['Levantamiento de datos en el campus', 'Aplica la encuesta en grupos de tres y sube la base de datos al terminar.', 'actividad'],
                    ['Foro de la práctica de campo', 'Comparte incidencias y dudas durante el levantamiento.', 'foro'],
                ],
                'tareas' => ['Análisis descriptivo de un caso', 'Ejercicios de probabilidad', 'Informe de inferencia'],
                'quizzes' => ['Quiz de estadística descriptiva', 'Quiz de probabilidad'],
            ],
            [
                'codigo' => 'FIS-150', 'nombre' => 'Física I', 'profesor' => 'quispe', 'adicionales' => [],
                'descripcion' => 'Cinemática y dinámica de la partícula. Curso en preparación para el próximo periodo.',
                'estado' => 'borrador', 'asistencia' => true, 'estudiantes' => 0,
                'modulos' => [
                    ['Cinemática', ['Movimiento rectilíneo'], 'borrador'],
                ],
                'tareas' => [],
                'quizzes' => [],
            ],
            [
                'codigo' => 'MAT-101-2026I', 'nombre' => 'Matemática Básica (2026-I)', 'profesor' => 'perez', 'adicionales' => [],
                'descripcion' => 'Periodo anterior, finalizado. Se conserva con su historial de notas (regla de negocio 8).',
                'estado' => 'archivado', 'asistencia' => true, 'estudiantes' => 12, 'archivado' => true,
                'modulos' => [
                    ['Números reales', ['Operaciones y propiedades']],
                    ['Ecuaciones', ['Ecuaciones lineales', 'Ecuaciones cuadráticas']],
                    ['Funciones', ['Dominio y rango']],
                ],
                'tareas' => ['Práctica de ecuaciones', 'Trabajo de funciones'],
                'quizzes' => ['Quiz de números reales'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function crearCurso(array $datos): void
    {
        $archivado = $datos['archivado'] ?? false;
        $inicio = $archivado ? $this->inicioPeriodo->copy()->subWeeks(24) : $this->inicioPeriodo->copy();
        $profesor = $this->profesores[$datos['profesor']];

        $curso = Curso::create([
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'profesor_id' => $profesor->id,
            'estado' => 'borrador',
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addWeeks(16)->toDateString(),
            'semanas_duracion' => 16,
            'sesiones_clase_count' => 32,
            'usa_asistencia' => $datos['asistencia'],
        ]);

        $curso->profesoresAdicionales()->sync(collect($datos['adicionales'])->map(fn ($clave) => $this->profesores[$clave]->id)->all());

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $this->supervisor,
            tipoEvento: 'modificacion_curso',
            descripcion: "Curso creado: \"{$curso->nombre}\".",
            curso: $curso,
        );

        $modulos = $this->crearModulos($curso, $datos['modulos'], $inicio);
        $this->crearPracticasDeCampo($curso, $datos['practicas'] ?? []);
        $rubros = $this->crearEsquema($curso);

        // El estado final se asigna al terminar de cargar su contenido (RF-25: publicar exige módulos).
        $curso->update(['estado' => $datos['estado']]);

        if ($datos['estudiantes'] === 0) {
            return;
        }

        $inscritos = $this->matricular($curso, $datos, $inicio);
        $grupos = ($datos['grupos'] ?? false) ? $this->crearGrupos($curso, $inscritos) : collect();
        $tareas = $this->crearTareas($curso, $modulos, $datos['tareas'], $inicio, $archivado, $grupos);
        $this->crearEntregas($tareas, $inscritos, $profesor, $archivado);
        $this->crearQuizzes($curso, $modulos, $datos['quizzes'], $inicio, $inscritos, $archivado);
        $this->registrarNotas($curso, $rubros, $inscritos, $profesor, $inicio, $archivado);

        if ($curso->usa_asistencia) {
            $this->crearSesionesYAsistencia($curso, $inscritos, $profesor, $inicio, $archivado);
        }

        if (! $archivado) {
            $this->crearAnunciosYMensajes($curso, $profesor, $inscritos);
        }
    }

    /**
     * @param  list<array{0: string, 1: list<string>, 2?: string}>  $definicion
     * @return Collection<int, Modulo>
     */
    protected function crearModulos(Curso $curso, array $definicion, Carbon $inicio): Collection
    {
        $modulos = collect();

        foreach ($definicion as $orden => $datosModulo) {
            [$titulo, $lecciones] = $datosModulo;
            $estado = $datosModulo[2] ?? 'publicado';

            // Contenido por semanas: cada tema empieza en su tramo del ciclo (4 temas en 16 semanas: 1, 5, 9 y 13).
            $modulo = $curso->modulos()->create([
                'titulo' => $titulo,
                'semana' => intdiv($orden * $curso->semanas_duracion, count($definicion)) + 1,
                'materiales' => [],
                'estado_contenido' => $estado,
                'publicar_en' => $estado === 'programado' ? now()->addDays(10)->setTime(8, 0) : null,
                // RF-67: variedad de condiciones de completitud.
                'condicion_completitud' => ['actividades', 'lecciones', 'aprobacion'][$orden % 3],
            ]);

            foreach ($lecciones as $indice => $tituloLeccion) {
                $slug = Str::slug($tituloLeccion);
                $modulo->lecciones()->create([
                    'titulo' => $tituloLeccion,
                    'descripcion' => "Material de la semana \"{$titulo}\": {$tituloLeccion}.",
                    'video_url' => $indice % 2 === 0 ? "https://www.youtube.com/results?search_query={$slug}" : null,
                    'material_url' => "https://es.wikipedia.org/wiki/Especial:Buscar?search={$slug}",
                    'material_nombre' => "{$tituloLeccion}.pdf",
                    'estado' => $estado === 'publicado' ? 'publicado' : $estado,
                    'fecha_publicacion' => $estado === 'programado' ? now()->addDays(10)->setTime(8, 0) : null,
                    'orden' => $indice + 1,
                    'condicion_completitud' => $indice === 0 ? 'abrir_recurso' : 'marcar',
                    'tipo_recurso' => match (true) {
                        $indice % 2 === 0 => 'video',
                        $indice === 1 => 'lectura',
                        default => 'presentacion',
                    },
                ]);
            }

            // Recursos complementarios de la semana: grabación de la clase y foro de preguntas.
            if ($orden === 0) {
                $modulo->lecciones()->create([
                    'titulo' => 'Grabación de la primera clase',
                    'descripcion' => "Clase grabada de la semana: {$titulo}.",
                    'video_url' => 'https://www.youtube.com/results?search_query='.Str::slug($titulo),
                    'estado' => $estado,
                    'fecha_publicacion' => $estado === 'programado' ? now()->addDays(10)->setTime(8, 0) : null,
                    'orden' => count($lecciones) + 1,
                    'tipo_recurso' => 'grabacion',
                ]);
            }

            $modulo->lecciones()->create([
                'titulo' => "Foro de preguntas: {$titulo}",
                'descripcion' => 'Deja tus dudas de la semana; el profesor responde en la siguiente clase.',
                'estado' => $estado,
                'fecha_publicacion' => $estado === 'programado' ? now()->addDays(10)->setTime(8, 0) : null,
                'orden' => count($lecciones) + 2,
                'tipo_recurso' => 'foro',
            ]);

            $modulos->push($modulo);
        }

        return $modulos;
    }

    /**
     * Sección "Prácticas de campo": actividades que no siguen la numeración semanal.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $practicas  [título, descripción, tipo de recurso]
     */
    protected function crearPracticasDeCampo(Curso $curso, array $practicas): void
    {
        if ($practicas === []) {
            return;
        }

        $seccion = $curso->modulos()->create([
            'titulo' => 'Prácticas de campo',
            'tipo_seccion' => Modulo::TIPO_PRACTICAS,
            'materiales' => [],
            'estado_contenido' => 'publicado',
            'condicion_completitud' => 'lecciones',
        ]);

        foreach ($practicas as $indice => [$titulo, $descripcion, $tipo]) {
            $seccion->lecciones()->create([
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'material_url' => $tipo === 'lectura' ? 'https://es.wikipedia.org/wiki/Especial:Buscar?search='.Str::slug($titulo) : null,
                'material_nombre' => $tipo === 'lectura' ? "{$titulo}.pdf" : null,
                'estado' => 'publicado',
                'orden' => $indice + 1,
                'tipo_recurso' => $tipo,
            ]);
        }
    }

    /**
     * ERS RF-54 a RF-56: cuatro rubros que suman exactamente 100 %.
     *
     * @return Collection<int, RubroEvaluacion>
     */
    protected function crearEsquema(Curso $curso): Collection
    {
        $esquema = $curso->esquemaEvaluacion()->create(['bloqueado' => false]);

        foreach ([['Evaluación continua 1', 4, 20], ['Examen parcial', 8, 30], ['Evaluación continua 2', 12, 20], ['Examen final', 16, 30]] as $orden => [$nombre, $semana, $peso]) {
            $esquema->rubros()->create(['nombre' => $nombre, 'semana' => $semana, 'peso' => $peso, 'orden' => $orden + 1]);
        }

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $this->supervisor,
            tipoEvento: 'modificacion_esquema',
            descripcion: "Esquema de evaluación definido para \"{$curso->nombre}\" (4 rubros, 100 %).",
            curso: $curso,
        );

        return $esquema->rubros()->orderBy('orden')->get();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return Collection<int, User>
     */
    protected function matricular(Curso $curso, array $datos, Carbon $inicio): Collection
    {
        // El Estudiante Demo está en todos los cursos con alumnos; el resto rota para variar la composición.
        $desplazamiento = crc32($curso->codigo) % 10;
        $otros = $this->estudiantes->slice(1)->values();
        $inscritos = collect([$this->estudiantes->first()]);

        for ($i = 0; $i < $datos['estudiantes'] - 1; $i++) {
            $inscritos->push($otros[($i + $desplazamiento) % $otros->count()]);
        }

        $inscritos = $inscritos->unique('id')->values();

        foreach ($inscritos as $indice => $estudiante) {
            $porApi = ($datos['api'] ?? false) && $indice === $inscritos->count() - 1;

            $curso->inscripciones()->create([
                'estudiante_id' => $estudiante->id,
                'fecha_inscripcion' => $inicio->copy()->subDays(5),
                'origen' => $porApi ? 'api' : 'manual',
                'estado' => 'activo',
                'activo_local' => true,
            ]);

            if (! $porApi && $indice < 3) {
                BitacoraAuditoria::registrarAccionAdmin(
                    ejecutor: $this->supervisor,
                    tipoEvento: 'matricula_manual',
                    descripcion: "Matrícula manual de {$estudiante->name} en \"{$curso->nombre}\".",
                    usuarioAfectado: $estudiante,
                    curso: $curso,
                );
            }
        }

        // RF-29/RF-30: una baja lógica que conserva el historial.
        if ($datos['baja'] ?? false) {
            $retirado = $inscritos[$inscritos->count() - 2];
            $inscripcion = $curso->inscripciones()->where('estudiante_id', $retirado->id)->first();
            $inscripcion->darDeBaja($this->supervisor);

            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: $this->supervisor,
                tipoEvento: 'baja_estudiante',
                descripcion: "Baja manual de {$retirado->name} en \"{$curso->nombre}\". El historial se conserva.",
                usuarioAfectado: $retirado,
                curso: $curso,
                motivo: 'Retiro voluntario por cruce de horarios.',
            );

            $inscritos = $inscritos->reject(fn (User $e) => $e->id === $retirado->id)->values();
        }

        return $inscritos;
    }

    /**
     * @param  Collection<int, User>  $inscritos
     * @return Collection<int, Grupo>
     */
    protected function crearGrupos(Curso $curso, Collection $inscritos): Collection
    {
        $grupos = collect();

        foreach ($inscritos->chunk(5)->values() as $indice => $integrantes) {
            $grupo = $curso->grupos()->create(['nombre' => 'Grupo '.chr(65 + $indice)]);
            $grupo->estudiantes()->attach($integrantes->pluck('id'));
            $grupos->push($grupo);
        }

        return $grupos;
    }

    /**
     * Tareas repartidas en el periodo: vencidas, por vencer y futuras.
     *
     * @param  Collection<int, Modulo>  $modulos
     * @param  list<string>  $titulos
     * @param  Collection<int, Grupo>  $grupos
     * @return Collection<int, Tarea>
     */
    protected function crearTareas(Curso $curso, Collection $modulos, array $titulos, Carbon $inicio, bool $archivado, Collection $grupos): Collection
    {
        $semanasEntrega = [3, 7, 10, 13];
        $tareas = collect();

        foreach ($titulos as $indice => $titulo) {
            $modulo = $modulos[min($indice, $modulos->count() - 1)];
            $entrega = $inicio->copy()->addWeeks($semanasEntrega[$indice] ?? 14)->addDays(4)->setTime(23, 59);
            $esGrupal = $grupos->isNotEmpty() && $indice === 2;

            $tareas->push($curso->tareas()->create([
                'modulo_id' => $modulo->id,
                'titulo' => $titulo,
                'descripcion' => "Actividad de práctica de la {$modulo->nombreCompleto()}.",
                'instrucciones' => "1. Revisa las lecciones de la semana.\n2. Desarrolla la actividad en un documento PDF o comparte el enlace.\n3. Entrega antes de la fecha límite.",
                'fecha_inicio' => $entrega->copy()->subWeeks(2)->setTime(8, 0),
                'fecha_entrega' => $entrega,
                'peso_nota' => 5,
                'intentos_permitidos' => 2,
                'formatos_permitidos' => ['pdf', 'docx', 'zip'],
                'tipo_entrega' => $esGrupal ? 'grupal' : 'individual',
                'visible' => true,
                'estado_contenido' => $modulo->estado_contenido === 'publicado' ? 'publicado' : $modulo->estado_contenido,
                'publicar_en' => $modulo->publicar_en,
            ]));
        }

        return $tareas;
    }

    /**
     * RF-43: entregas en todos los estados (calificado, entregado, fuera de
     * plazo, devuelto y no entregado).
     *
     * @param  Collection<int, Tarea>  $tareas
     * @param  Collection<int, User>  $inscritos
     */
    protected function crearEntregas(Collection $tareas, Collection $inscritos, User $profesor, bool $archivado): void
    {
        foreach ($tareas as $tarea) {
            if ($tarea->fecha_entrega->gt(now()->addDays(7)) || ! $tarea->esVisibleParaEstudiante()) {
                continue;
            }

            $vencida = $tarea->fecha_entrega->isPast();

            foreach ($inscritos as $indice => $estudiante) {
                $azar = mt_rand(1, 100);

                if ($azar <= ($vencida ? 10 : 45)) {
                    continue; // No entregado (vencida) o todavía pendiente.
                }

                $tarde = $vencida && $azar <= 18;
                $momento = $tarde ? $tarea->fecha_entrega->copy()->addDays(2) : $tarea->fecha_entrega->copy()->subDays(mt_rand(1, 5));
                $calificada = $vencida && ($archivado || $azar > 35);
                $devuelta = ! $archivado && $vencida && $azar > 90;
                $nota = $calificada && ! $devuelta ? $this->notaAleatoria($estudiante) : null;

                $entrega = new Entrega([
                    'tarea_id' => $tarea->id,
                    'estudiante_id' => $estudiante->id,
                    'respuesta_texto' => 'Desarrollo de la actividad adjunto.',
                    'enlace' => $indice % 3 === 0 ? 'https://docs.google.com/document/d/ejemplo-'.$tarea->id.'-'.$estudiante->id : null,
                    'archivo_url' => $indice % 3 === 0 ? null : 'https://res.cloudinary.com/demo/raw/upload/entrega-'.$tarea->id.'-'.$estudiante->id.'.pdf',
                    'estado' => $devuelta ? 'devuelto' : ($nota !== null ? 'calificado' : 'entregado'),
                    'intentos_usados' => 1,
                    'nota' => $nota,
                    'feedback' => $devuelta
                        ? 'Falta el desarrollo de la segunda parte. Corrige y vuelve a entregar.'
                        : ($nota !== null ? ($nota >= 14 ? 'Buen trabajo, bien fundamentado.' : 'Revisa los procedimientos; hay errores de cálculo.') : null),
                ]);
                $entrega->created_at = $momento;
                $entrega->updated_at = $momento;
                $entrega->save();

                if ($estudiante->email === 'estudiante@lms.edu.pe' && $nota !== null && ! $archivado) {
                    $estudiante->notify(new TareaCalificada($entrega));
                }
            }
        }
    }

    /**
     * RF-44 a RF-46: un quiz cerrado con intentos y otro abierto.
     *
     * @param  Collection<int, Modulo>  $modulos
     * @param  list<string>  $temas
     * @param  Collection<int, User>  $inscritos
     */
    protected function crearQuizzes(Curso $curso, Collection $modulos, array $temas, Carbon $inicio, Collection $inscritos, bool $archivado): void
    {
        foreach ($temas as $indice => $tema) {
            $cerrado = $archivado || $indice === 0;
            $quiz = $curso->quizzes()->create([
                'modulo_id' => $modulos[min($indice, $modulos->count() - 1)]->id,
                'tema' => $tema,
                'preguntas' => $this->preguntasDe($tema),
                'configuracion' => ['intentos' => 2, 'tiempo_limite_min' => 20, 'aleatorizar' => true, 'mostrar_retroalimentacion' => true],
                'fecha_inicio' => $inicio->copy()->addWeeks($cerrado ? 2 : 8),
                'fecha_limite' => $cerrado ? $inicio->copy()->addWeeks(5)->setTime(23, 59) : now()->addDays(6)->setTime(23, 59),
                'visible' => true,
                'estado_contenido' => 'publicado',
            ]);

            foreach ($inscritos as $estudiante) {
                if (mt_rand(1, 100) > ($cerrado ? 85 : 35)) {
                    continue;
                }

                $intentos = mt_rand(1, 2);
                for ($intento = 1; $intento <= $intentos; $intento++) {
                    $respuestas = collect($quiz->preguntas)->map(fn (array $pregunta) => mt_rand(1, 100) <= 70
                        ? ($pregunta['tipo'] === 'opcion_multiple' ? $pregunta['respuestas_correctas'] : $pregunta['respuesta_correcta'])
                        : ($pregunta['tipo'] === 'opcion_multiple' ? [0] : (($pregunta['respuesta_correcta'] + 1) % count($pregunta['opciones']))))->all();

                    ResultadoQuiz::create([
                        'quiz_id' => $quiz->id,
                        'estudiante_id' => $estudiante->id,
                        'intento' => $intento,
                        'respuestas' => $respuestas,
                        'puntaje' => $quiz->calcularPuntaje($respuestas),
                        'tiempo_usado_seg' => mt_rand(240, 1100),
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function preguntasDe(string $tema): array
    {
        return [
            ['enunciado' => "¿Cuál es el objetivo principal de \"{$tema}\"?", 'tipo' => 'opcion_unica', 'opciones' => ['Aplicar los conceptos de la semana', 'Memorizar definiciones', 'Ninguna de las anteriores'], 'respuesta_correcta' => 0, 'puntaje' => 2, 'retroalimentacion' => 'La práctica busca aplicar lo aprendido.'],
            ['enunciado' => 'Los ejercicios de práctica no forman parte de la nota final ponderada.', 'tipo' => 'verdadero_falso', 'opciones' => ['Verdadero', 'Falso'], 'respuesta_correcta' => 0, 'puntaje' => 1],
            ['enunciado' => 'Selecciona las buenas prácticas de estudio.', 'tipo' => 'opcion_multiple', 'opciones' => ['Repasar con frecuencia', 'Estudiar solo la noche anterior', 'Resolver ejercicios', 'Pedir retroalimentación'], 'respuestas_correctas' => [0, 2, 3], 'puntaje' => 2],
            ['enunciado' => '¿Qué recurso conviene revisar antes de rendir la evaluación?', 'tipo' => 'opcion_unica', 'opciones' => ['Las lecciones de la semana', 'Redes sociales', 'Ninguno'], 'respuesta_correcta' => 0, 'puntaje' => 1],
        ];
    }

    /**
     * RF-57: notas de los rubros cuya semana ya pasó (todas si el curso está
     * archivado), con estudiantes bajo la nota mínima para ver el resaltado en rojo.
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @param  Collection<int, User>  $inscritos
     */
    protected function registrarNotas(Curso $curso, Collection $rubros, Collection $inscritos, User $profesor, Carbon $inicio, bool $archivado): void
    {
        foreach ($rubros as $rubro) {
            if (! $archivado && $inicio->copy()->addWeeks($rubro->semana)->isFuture()) {
                continue;
            }

            foreach ($inscritos as $estudiante) {
                $nota = NotaRubro::create([
                    'rubro_id' => $rubro->id,
                    'estudiante_id' => $estudiante->id,
                    'nota' => $this->notaAleatoria($estudiante),
                    'registrado_por' => $profesor->id,
                ]);

                if ($estudiante->email === 'estudiante@lms.edu.pe' && ! $archivado) {
                    $estudiante->notify(new NotaRubroRegistrada($curso, $rubro));
                }

                // RF-65: un ajuste de nota con motivo, visible en la auditoría.
                if ($rubro->orden === 1 && $estudiante->is($inscritos[1] ?? null)) {
                    $anterior = (float) $nota->nota;
                    $nota->update(['nota' => min(20, $anterior + 2)]);

                    BitacoraAuditoria::registrarCambioNota(
                        ejecutor: $profesor,
                        curso: $curso,
                        estudiante: $estudiante,
                        entidad: 'nota_rubro',
                        entidadId: $nota->id,
                        valorAnterior: $anterior,
                        valorNuevo: (float) $nota->nota,
                        motivo: 'Revisión de examen solicitada por el estudiante.',
                    );
                }
            }
        }
    }

    /**
     * RF-70: dos sesiones por semana; las pasadas tienen asistencia registrada.
     *
     * @param  Collection<int, User>  $inscritos
     */
    protected function crearSesionesYAsistencia(Curso $curso, Collection $inscritos, User $profesor, Carbon $inicio, bool $archivado): void
    {
        $enRiesgo = $inscritos->last();

        for ($numero = 1; $numero <= 32; $numero++) {
            $semana = intdiv($numero - 1, 2);
            $fecha = $inicio->copy()->addWeeks($semana)->addDays($numero % 2 === 1 ? 0 : 2)->setTime(18, 0);

            $sesion = $curso->sesionesClase()->create([
                'titulo' => 'Sesión '.$numero.($numero % 2 === 1 ? ': teoría' : ': práctica dirigida'),
                'fecha' => $fecha,
                'duracion_minutos' => 90,
                'numero_sesion' => $numero,
            ]);

            if ($fecha->isFuture()) {
                continue;
            }

            foreach ($inscritos as $estudiante) {
                $azar = mt_rand(1, 100);
                // Un estudiante con muchas faltas, para el reporte de riesgo de abandono.
                $estado = $estudiante->is($enRiesgo)
                    ? ($azar <= 55 ? 'ausente' : 'presente')
                    : match (true) {
                        $azar <= 82 => 'presente',
                        $azar <= 90 => 'tardanza',
                        $azar <= 96 => 'ausente',
                        default => 'justificado',
                    };

                Asistencia::create([
                    'sesion_id' => $sesion->id,
                    'estudiante_id' => $estudiante->id,
                    'estado' => $estado,
                    'observacion' => $estado === 'justificado' ? 'Certificado médico presentado.' : null,
                    'registrado_por' => $profesor->id,
                ]);
            }
        }
    }

    /**
     * RF-80 y RF-81: anuncios del curso y una conversación privada.
     *
     * @param  Collection<int, User>  $inscritos
     */
    protected function crearAnunciosYMensajes(Curso $curso, User $profesor, Collection $inscritos): void
    {
        Comunicado::create([
            'user_id' => $profesor->id,
            'curso_id' => $curso->id,
            'titulo' => 'Bienvenidos al curso',
            'contenido' => 'Revisen el esquema de evaluación y el calendario. Las consultas se atienden por mensajería.',
            'prioridad' => 'normal',
            'destinatario' => 'estudiantes',
            'activo' => true,
            'notificado' => true,
        ]);

        Comunicado::create([
            'user_id' => $profesor->id,
            'curso_id' => $curso->id,
            'titulo' => 'Examen parcial: temario',
            'contenido' => 'El examen parcial comprende las semanas 1 a 8. Traer calculadora y documento de identidad.',
            'prioridad' => 'importante',
            'destinatario' => 'estudiantes',
            'activo' => true,
            'notificado' => true,
        ]);

        $demo = $this->estudiantes->first();
        $conversacion = [
            [$demo, $profesor, 'Consulta sobre la práctica', 'Profesor, ¿la práctica se puede entregar en formato Word?', 3],
            [$profesor, $demo, 'Re: Consulta sobre la práctica', 'Sí, se aceptan PDF, DOCX y ZIP. Revisa las instrucciones de la tarea.', 2],
            [$inscritos[1] ?? $demo, $demo, 'Trabajo en grupo', 'Hola, ¿nos reunimos el jueves para avanzar el proyecto?', 0],
        ];

        foreach ($conversacion as [$de, $para, $asunto, $cuerpo, $haceDias]) {
            if ($de->is($para)) {
                continue;
            }

            $mensaje = Mensaje::create([
                'remitente_id' => $de->id,
                'destinatario_id' => $para->id,
                'curso_id' => $curso->id,
                'asunto' => "[{$curso->codigo}] {$asunto}",
                'cuerpo' => $cuerpo,
                'leido_at' => $haceDias > 0 ? now()->subDays($haceDias - 1) : null,
            ]);
            $mensaje->forceFill(['created_at' => now()->subDays($haceDias)->setTime(10, 30)])->saveQuietly();
        }

        foreach ($curso->tareas()->visiblesParaEstudiante()->where('fecha_entrega', '>', now())->limit(1)->get() as $tarea) {
            $demo->notify(new NuevaTareaAsignada($tarea));
        }
    }

    // ==========================================
    // DATOS GLOBALES
    // ==========================================

    /** RF-87: comunicados institucionales publicados y uno programado. */
    protected function crearComunicadosInstitucionales(): void
    {
        $comunicados = [
            ['Inicio del periodo académico 2026-II', 'Les damos la bienvenida al nuevo periodo. Revisen sus cursos matriculados y su calendario.', 'normal', 'todos', null],
            ['Capacitación docente: evaluación por rubros', 'Sesión virtual para profesores sobre el registro de notas por rubro. Jueves 6:00 p. m.', 'importante', 'profesores', null],
            ['Semana de exámenes parciales', 'Los exámenes parciales se rinden en la semana 8 según el esquema de cada curso.', 'urgente', 'estudiantes', null],
            ['Mantenimiento programado de la plataforma', 'La plataforma no estará disponible el domingo de 00:00 a 04:00.', 'normal', 'todos', now()->addDays(2)->setTime(9, 0)],
        ];

        foreach ($comunicados as [$titulo, $contenido, $prioridad, $destinatario, $publicarEn]) {
            Comunicado::create([
                'user_id' => $this->supervisor->id,
                'curso_id' => null,
                'titulo' => $titulo,
                'contenido' => $contenido,
                'prioridad' => $prioridad,
                'destinatario' => $destinatario,
                'activo' => true,
                'publicar_en' => $publicarEn,
                'notificado' => $publicarEn === null,
            ]);
        }
    }

    /** RF-101: una integración externa activa. La clave real no se guarda ni se muestra. */
    protected function crearIntegracion(): void
    {
        [$integracion] = IntegracionExterna::generarApiKey(
            'Sistema académico institucional (SIGA)',
            'Sincroniza matrículas desde el sistema de admisión. Para usarla, regenera la clave desde Integraciones.',
        );
        $integracion->update(['ultimo_uso' => now()->subDay()]);
    }

    /** RF-79: eventos personales en el calendario del Estudiante Demo y del Profesor. */
    protected function crearEventosPersonales(): void
    {
        $demo = $this->estudiantes->first();

        foreach ([
            [$demo, 'Repaso de derivadas', now()->addDay()->setTime(16, 0), 'bloque_estudio'],
            [$demo, 'Reunión de grupo del proyecto', now()->addDays(3)->setTime(19, 0), 'evento'],
            [$demo, 'Preparar presentación oral', now()->addDays(5)->setTime(15, 0), 'recordatorio'],
            [$demo, 'Repaso para el examen parcial', now()->subDays(6)->setTime(17, 0), 'bloque_estudio'],
            [$this->profesores['perez'], 'Revisar entregas de PRG-110', now()->addDays(2)->setTime(10, 0), 'recordatorio'],
            [$this->profesores['perez'], 'Reunión de coordinación académica', now()->addDays(4)->setTime(12, 0), 'evento'],
        ] as [$usuario, $titulo, $fecha, $tipo]) {
            EventoCalendario::create(['usuario_id' => $usuario->id, 'titulo' => $titulo, 'fecha' => $fecha, 'tipo' => $tipo]);
        }
    }

    /**
     * Nota 0-20 en múltiplos de 0.5 alrededor de un nivel propio de cada
     * estudiante, para que haya aprobados y desaprobados consistentes.
     */
    protected function notaAleatoria(User $estudiante): float
    {
        $nivel = 8 + (crc32($estudiante->email) % 11);

        return max(0, min(20, round(($nivel + mt_rand(-30, 30) / 10) * 2) / 2));
    }
}
