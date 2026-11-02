<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Compartido\Cursos\GestionMatricula;
use App\Livewire\Compartido\Cursos\Index;
use App\Livewire\Compartido\Cursos\Show;
use App\Livewire\Supervisor\Cursos\Formulario;
use App\Models\Curso;
use App\Models\EsquemaEvaluacion;
use App\Models\RubroEvaluacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CursosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    /**
     * ERS RF-18 y matriz de permisos: solo el Supervisor crea cursos. Esta
     * prueba reemplaza a "profesor_puede_crear_un_curso", que contradecía el ERS.
     */
    public function test_supervisor_crea_un_curso_asignando_un_profesor(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Matemática Básica')
            ->set('descripcion', 'Curso introductorio')
            ->set('profesor_id', $profesor->id)
            ->set('modalidad', 'virtual')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', [
            'nombre' => 'Matemática Básica',
            'profesor_id' => $profesor->id,
            'estado' => 'borrador',
        ]);
    }

    public function test_el_aula_es_obligatoria_en_modalidad_presencial(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso presencial sin aula')
            ->set('profesor_id', $profesor->id)
            ->set('modalidad', 'presencial')
            ->set('horarioBloques', [['dia' => '1', 'hora_inicio' => '08:00', 'hora_fin' => '09:30', 'aula' => '']])
            ->call('guardar')
            ->assertHasErrors(['horarioBloques.0.aula' => 'required']);

        $this->assertDatabaseMissing('cursos', ['nombre' => 'Curso presencial sin aula']);
    }

    public function test_virtual_no_pide_aula_ni_horario(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso autoestudio')
            ->set('profesor_id', $profesor->id)
            ->set('modalidad', 'virtual')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', ['nombre' => 'Curso autoestudio', 'modalidad' => 'virtual', 'horario_bloques' => null]);
    }

    public function test_genera_las_sesiones_de_clase_automaticamente_segun_el_horario(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        // 2026-10-22 es jueves; +2 semanas = 2026-11-05. Con horario lunes/miércoles,
        // dentro de ese rango (inclusive) caen: 26 y 28 oct, 2 y 4 nov. Lunes y
        // miércoles llevan horas y aulas distintas a propósito.
        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso con horario')
            ->set('profesor_id', $profesor->id)
            ->set('fecha_inicio', '2026-10-22')
            ->set('semanas_duracion', '2')
            ->set('horarioBloques', [
                ['dia' => '1', 'hora_inicio' => '08:00', 'hora_fin' => '09:30', 'aula' => 'Aula 101'],
                ['dia' => '3', 'hora_inicio' => '14:00', 'hora_fin' => '16:00', 'aula' => 'Aula 305'],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $curso = Curso::where('nombre', 'Curso con horario')->firstOrFail();

        $this->assertSame(4, $curso->sesionesClase()->count());
        $this->assertDatabaseHas('sesiones_clase', ['curso_id' => $curso->id, 'fecha' => '2026-10-26 08:00:00', 'duracion_minutos' => 90, 'descripcion' => 'Aula: Aula 101', 'numero_sesion' => 1]);
        $this->assertDatabaseHas('sesiones_clase', ['curso_id' => $curso->id, 'fecha' => '2026-10-28 14:00:00', 'duracion_minutos' => 120, 'descripcion' => 'Aula: Aula 305', 'numero_sesion' => 2]);
        $this->assertDatabaseHas('sesiones_clase', ['curso_id' => $curso->id, 'fecha' => '2026-11-02 08:00:00', 'duracion_minutos' => 90, 'numero_sesion' => 3]);
        $this->assertDatabaseHas('sesiones_clase', ['curso_id' => $curso->id, 'fecha' => '2026-11-04 14:00:00', 'duracion_minutos' => 120, 'numero_sesion' => 4]);
    }

    public function test_no_genera_sesiones_si_el_curso_no_usa_asistencia(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso sin asistencia')
            ->set('profesor_id', $profesor->id)
            ->set('fecha_inicio', '2026-10-22')
            ->set('semanas_duracion', '2')
            ->set('horarioBloques', [['dia' => '1', 'hora_inicio' => '08:00', 'hora_fin' => '09:30', 'aula' => 'Aula 101']])
            ->set('usa_asistencia', false)
            ->call('guardar')
            ->assertHasNoErrors();

        $curso = Curso::where('nombre', 'Curso sin asistencia')->firstOrFail();

        $this->assertSame(0, $curso->sesionesClase()->count());
    }

    public function test_el_cupo_maximo_se_guarda_al_crear_un_curso(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso con cupo')
            ->set('profesor_id', $profesor->id)
            ->set('cupo_maximo', '30')
            ->set('modalidad', 'virtual')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', ['nombre' => 'Curso con cupo', 'cupo_maximo' => 30]);
    }

    public function test_no_se_puede_matricular_mas_alla_del_cupo_maximo(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->create(['cupo_maximo' => 1]);
        $yaMatriculado = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $yaMatriculado->id, 'fecha_inscripcion' => now()]);
        $segundoEstudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->assertTrue($curso->cupoLleno());

        Livewire::actingAs($supervisor)
            ->test(GestionMatricula::class, ['curso' => $curso])
            ->assertSet('puedeMatricular', false)
            ->set('estudianteSeleccionadoId', $segundoEstudiante->id)
            ->call('matricularEstudiante')
            ->assertForbidden();

        $this->assertDatabaseMissing('inscripciones', ['curso_id' => $curso->id, 'estudiante_id' => $segundoEstudiante->id]);
    }

    public function test_la_fecha_de_fin_se_calcula_sola_desde_el_inicio_y_la_duracion(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('fecha_inicio', '2026-10-22')
            ->set('semanas_duracion', '2')
            ->assertSet('fecha_fin', '2026-11-05');
    }

    public function test_no_se_puede_publicar_un_curso_sin_estudiantes_matriculados(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->borrador()->create(['modalidad' => 'virtual']);
        $curso->modulos()->create(['titulo' => 'Semana 1', 'semana' => 1, 'materiales' => []]);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class, ['curso' => $curso])
            ->set('estado', 'publicado')
            ->call('guardar')
            ->assertHasErrors('estado');

        $this->assertSame('borrador', $curso->fresh()->estado);
    }

    public function test_se_puede_publicar_un_curso_con_modulos_y_matriculados(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->borrador()->create(['modalidad' => 'virtual']);
        $curso->modulos()->create(['titulo' => 'Semana 1', 'semana' => 1, 'materiales' => []]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class, ['curso' => $curso])
            ->set('estado', 'publicado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('publicado', $curso->fresh()->estado);
    }

    public function test_un_curso_nuevo_siempre_nace_en_borrador_sin_pedir_estado(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->assertDontSee('Estado del curso')
            ->set('nombre', 'Curso recién creado')
            ->set('profesor_id', $profesor->id)
            ->set('modalidad', 'virtual')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', ['nombre' => 'Curso recién creado', 'estado' => 'borrador']);
    }

    public function test_la_hora_de_fin_del_horario_debe_ser_despues_de_la_de_inicio(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso con horario invertido')
            ->set('profesor_id', $profesor->id)
            ->set('horarioBloques', [['dia' => '1', 'hora_inicio' => '09:00', 'hora_fin' => '08:00', 'aula' => 'Aula 101']])
            ->call('guardar')
            ->assertHasErrors(['horarioBloques.0.hora_fin' => 'after']);

        $this->assertDatabaseMissing('cursos', ['nombre' => 'Curso con horario invertido']);
    }

    public function test_bloques_de_horario_no_pueden_repetir_el_mismo_dia(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso con día repetido')
            ->set('profesor_id', $profesor->id)
            ->set('horarioBloques', [
                ['dia' => '1', 'hora_inicio' => '08:00', 'hora_fin' => '09:30', 'aula' => 'Aula 101'],
                ['dia' => '1', 'hora_inicio' => '14:00', 'hora_fin' => '16:00', 'aula' => 'Aula 305'],
            ])
            ->call('guardar')
            ->assertHasErrors('horarioBloques');

        $this->assertDatabaseMissing('cursos', ['nombre' => 'Curso con día repetido']);
    }

    public function test_la_fecha_de_fin_se_recalcula_siempre_al_cambiar_la_duracion(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('fecha_inicio', '2026-10-22')
            ->set('semanas_duracion', '2')
            ->assertSet('fecha_fin', '2026-11-05')
            ->set('semanas_duracion', '4')
            ->assertSet('fecha_fin', '2026-11-19');
    }

    public function test_asignar_profesor_despues_deja_el_curso_a_nombre_del_supervisor(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso sin titular todavía')
            ->set('asignarProfesorDespues', true)
            ->set('modalidad', 'virtual')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', [
            'nombre' => 'Curso sin titular todavía',
            'profesor_id' => $supervisor->id,
        ]);
    }

    public function test_asignar_profesor_despues_no_esta_disponible_al_editar(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class, ['curso' => $curso])
            ->set('asignarProfesorDespues', true)
            ->assertSet('asignarProfesorDespues', false);

        $this->assertSame($profesor->id, $curso->fresh()->profesor_id);
    }

    public function test_busca_y_selecciona_el_profesor_titular(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create(['name' => 'Ana Rivas'])->assignRole(Rol::Profesor->value);
        User::factory()->create(['name' => 'Otro Profesor'])->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('busquedaProfesorTitular', 'Ana')
            ->assertViewHas('profesoresBusquedaTitular', fn ($resultados) => $resultados->pluck('id')->all() === [$profesor->id])
            ->call('seleccionarProfesorTitular', $profesor->id)
            ->assertSet('profesor_id', $profesor->id)
            ->assertSet('busquedaProfesorTitular', 'Ana Rivas');
    }

    public function test_busca_y_agrega_un_profesor_adicional(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $titular = User::factory()->create()->assignRole(Rol::Profesor->value);
        $adicional = User::factory()->create(['name' => 'Beto Soto'])->assignRole(Rol::Profesor->value);

        $componente = Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('profesor_id', $titular->id)
            ->set('busquedaProfesorAdicional', 'Beto')
            ->assertViewHas('profesoresBusquedaAdicional', fn ($resultados) => $resultados->pluck('id')->all() === [$adicional->id])
            ->call('agregarProfesorAdicional', $adicional->id)
            ->assertSet('profesoresAdicionales', [(string) $adicional->id]);

        $componente->call('quitarProfesorAdicional', $adicional->id)->assertSet('profesoresAdicionales', []);
    }

    public function test_sugiere_codigo_automatico_para_un_nombre_nuevo(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $anio = now()->format('y');

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Matemática 1')
            ->assertSet('codigo', "MAT1-{$anio}-01");
    }

    public function test_el_codigo_hereda_el_prefijo_de_un_curso_con_el_mismo_nombre(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        Curso::factory()->create(['nombre' => 'Matemática 1', 'profesor_id' => $profesor->id, 'codigo' => 'MAT1-24-01']);

        $anio = now()->format('y');

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Matemática 1')
            ->assertSet('codigo', "MAT1-{$anio}-01");
    }

    public function test_la_secuencia_del_codigo_aumenta_para_el_mismo_prefijo_y_anio(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $anio = now()->format('y');
        Curso::factory()->create(['nombre' => 'Matemática 1', 'profesor_id' => $profesor->id, 'codigo' => "MAT1-{$anio}-01"]);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Matemática 1')
            ->assertSet('codigo', "MAT1-{$anio}-02");
    }

    public function test_avisa_en_vivo_si_el_codigo_editado_a_mano_ya_existe(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        Curso::factory()->create(['profesor_id' => $profesor->id, 'codigo' => 'MAT-201']);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Otro curso')
            ->set('codigo', 'mat-201')
            ->assertHasErrors(['codigo' => 'Ya existe un curso con este código.']);
    }

    public function test_avisa_en_vivo_si_el_prefijo_ya_pertenece_a_otra_materia(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        Curso::factory()->create(['nombre' => 'Matemática 1', 'profesor_id' => $profesor->id, 'codigo' => 'MAT1-24-01']);

        $componente = Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Materiales de Construcción')
            ->set('codigo', 'MAT1-26-05');

        $this->assertStringContainsString('Matemática 1', (string) $componente->errors()->first('codigo'));
    }

    public function test_sugiere_un_nombre_parecido_en_vez_de_crear_una_materia_duplicada(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        Curso::factory()->create(['nombre' => 'Matemática 1', 'profesor_id' => $profesor->id]);

        $componente = Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Matemática I')
            ->assertSet('sugerenciaNombre', 'Matemática 1');

        $componente->call('usarNombreSugerido')->assertSet('nombre', 'Matemática 1')->assertSet('sugerenciaNombre', '');
    }

    public function test_el_nombre_normalizado_no_crea_una_materia_duplicada_por_mayusculas_o_espacios(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $anio = now()->format('y');
        Curso::factory()->create(['nombre' => 'Matemática 1', 'profesor_id' => $profesor->id, 'codigo' => "MAT1-{$anio}-01"]);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', '  matemática  1  ')
            ->assertSet('codigo', "MAT1-{$anio}-02")
            ->assertSet('sugerenciaNombre', '');
    }

    public function test_editar_el_codigo_a_mano_evita_que_se_vuelva_a_autocompletar(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Matemática 1')
            ->set('codigo', 'CODIGO-PROPIO')
            ->set('nombre', 'Matemática 1 avanzada')
            ->assertSet('codigo', 'CODIGO-PROPIO');
    }

    public function test_el_codigo_escrito_en_minusculas_se_guarda_en_mayusculas(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Cálculo Diferencial')
            ->set('codigo', ' mat-201 ')
            ->set('profesor_id', $profesor->id)
            ->set('modalidad', 'virtual')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', ['nombre' => 'Cálculo Diferencial', 'codigo' => 'MAT-201']);
    }

    public function test_rechaza_un_codigo_que_ya_existe_aunque_se_escriba_en_minusculas(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        Curso::factory()->create(['profesor_id' => $profesor->id, 'codigo' => 'MAT-201']);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Otro curso')
            ->set('codigo', 'mat-201')
            ->set('profesor_id', $profesor->id)
            ->call('guardar')
            ->assertHasErrors(['codigo' => 'Ya existe un curso con este código.']);

        $this->assertDatabaseMissing('cursos', ['nombre' => 'Otro curso']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function codigosConFormatoInvalido(): array
    {
        return [
            'con espacio' => ['MAT 201'],
            'con tilde' => ['FÍS-150'],
            'con guion bajo' => ['MAT_201'],
            'guion al final' => ['MAT-'],
            'guiones seguidos' => ['MAT--201'],
        ];
    }

    #[DataProvider('codigosConFormatoInvalido')]
    public function test_rechaza_un_codigo_con_caracteres_no_permitidos(string $codigo): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        $componente = Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso con código inválido')
            ->set('codigo', $codigo)
            ->set('profesor_id', $profesor->id)
            ->call('guardar');

        $this->assertSame(
            'El código solo puede tener letras (A-Z), números y guiones, sin espacios ni tildes. Ejemplo: MAT-201.',
            $componente->errors()->first('codigo'),
        );
        $this->assertDatabaseMissing('cursos', ['nombre' => 'Curso con código inválido']);
    }

    public function test_clonar_rechaza_un_codigo_que_ya_existe_aunque_se_escriba_en_minusculas(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'codigo' => 'MAT-201']);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->call('abrirClonar')
            ->set('clonCodigo', 'mat-201')
            ->call('clonarCurso')
            ->assertHasErrors(['clonCodigo' => 'Ya existe un curso con este código.']);

        $this->assertSame(1, Curso::count());
    }

    public function test_clonar_no_permite_asignar_a_un_estudiante_como_profesor(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->call('abrirClonar')
            ->set('clonProfesorId', $estudiante->id)
            ->call('clonarCurso')
            ->assertHasErrors(['clonProfesorId' => 'El usuario seleccionado no tiene rol de profesor.']);

        $this->assertSame(1, Curso::count());
    }

    public function test_no_permite_un_estudiante_como_profesor_adicional(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso con co-docente')
            ->set('profesor_id', $profesor->id)
            ->set('profesoresAdicionales', [(string) $estudiante->id])
            ->call('guardar')
            ->assertHasErrors(['profesoresAdicionales' => 'Todos los usuarios seleccionados deben tener rol de profesor.']);

        $this->assertDatabaseMissing('cursos', ['nombre' => 'Curso con co-docente']);
    }

    public function test_profesor_no_puede_crear_cursos(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        $this->actingAs($profesor)->get(route('cursos.crear'))->assertForbidden();
    }

    /**
     * ERS RF-25: un curso sin módulos no puede publicarse.
     */
    public function test_no_se_puede_publicar_un_curso_sin_modulos(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->borrador()->create(['modalidad' => 'virtual']);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class, ['curso' => $curso])
            ->set('estado', 'publicado')
            ->call('guardar')
            ->assertHasErrors('estado');

        $this->assertSame('borrador', $curso->fresh()->estado);
    }

    public function test_estudiante_no_puede_crear_un_curso(): void
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)
            ->get(route('cursos.crear'))
            ->assertForbidden();
    }

    public function test_profesor_no_puede_editar_el_curso_de_otro_profesor(): void
    {
        $curso = Curso::factory()->create();
        $otroProfesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        $this->actingAs($otroProfesor)
            ->get(route('cursos.editar', $curso))
            ->assertForbidden();
    }

    public function test_administrador_puede_editar_cualquier_curso(): void
    {
        $curso = Curso::factory()->create();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $this->actingAs($admin)
            ->get(route('cursos.editar', $curso))
            ->assertOk();
    }

    /**
     * ERS 2.4 y RF-26: no hay autoinscripción. Reemplaza a
     * "estudiante_puede_inscribirse_en_un_curso", que contradecía el ERS.
     */
    public function test_estudiante_no_puede_autoinscribirse_ni_ver_cursos_ajenos(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)->get(route('cursos.show', $curso))->assertForbidden();
        $this->assertDatabaseMissing('inscripciones', ['curso_id' => $curso->id, 'estudiante_id' => $estudiante->id]);
    }

    public function test_estudiante_matriculado_no_puede_invocar_la_autoinscripcion(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->call('inscribirme')
            ->assertForbidden();
    }

    public function test_profesor_prepara_una_semana_de_su_curso_con_su_tema(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'semanas_duracion' => 16]);

        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->call('prepararSeccion', 3)
            ->assertSet('mostrarModalModulo', true)
            ->set('moduloTitulo', 'La derivada')
            ->call('guardarModulo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('modulos', [
            'curso_id' => $curso->id,
            'semana' => 3,
            'tipo_seccion' => 'semana',
            'titulo' => 'La derivada',
        ]);
    }

    public function test_estudiante_no_puede_agregar_unidades(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->call('prepararSeccion', 1)
            ->assertForbidden();

        $this->assertDatabaseMissing('modulos', ['curso_id' => $curso->id]);
    }

    public function test_catalogo_de_profesor_solo_muestra_sus_propios_cursos(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $otroProfesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $suyo = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $ajeno = Curso::factory()->create(['profesor_id' => $otroProfesor->id]);

        Livewire::actingAs($profesor)
            ->test(Index::class)
            ->assertSee($suyo->nombre)
            ->assertDontSee($ajeno->nombre);
    }

    public function test_el_supervisor_ve_el_resumen_administrativo_del_curso_con_su_esquema(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
            'codigo' => 'MAT-101',
            'modalidad' => 'virtual',
            'cupo_maximo' => 30,
        ]);

        $esquema = EsquemaEvaluacion::create(['curso_id' => $curso->id]);
        RubroEvaluacion::create(['esquema_id' => $esquema->id, 'nombre' => 'Tareas', 'peso' => 40, 'orden' => 1]);
        RubroEvaluacion::create(['esquema_id' => $esquema->id, 'nombre' => 'Examen final', 'peso' => 60, 'orden' => 2]);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Resumen administrativo')
            ->assertSee('MAT-101')
            ->assertSee('Tareas 40%', false)
            ->assertSee('Examen final 60%', false);
    }

    public function test_el_resumen_administrativo_indica_cuando_no_hay_esquema_definido(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->create(['profesor_id' => $supervisor->id]);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Aún no se definió');
    }

    public function test_el_profesor_no_ve_el_resumen_administrativo_del_curso(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->assertDontSee('Resumen administrativo');
    }

    public function test_el_supervisor_no_ve_anuncios_ni_herramientas_de_autoria_de_contenido(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $curso->comunicados()->create([
            'user_id' => $profesor->id,
            'titulo' => 'Aviso del profesor',
            'contenido' => 'Recuerden revisar la práctica 1',
            'prioridad' => 'normal',
            'destinatario' => 'estudiantes',
            'activo' => true,
        ]);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->assertDontSee('Anuncios del curso')
            ->assertDontSee('Aviso del profesor')
            ->assertDontSee('Grupos');

        // El profesor sí lo sigue viendo todo, sin cambios.
        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Anuncios del curso')
            ->assertSee('Aviso del profesor')
            ->assertSee('Grupos');
    }

    public function test_el_supervisor_crea_una_carpeta_y_agrega_un_enlace(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->create(['profesor_id' => $supervisor->id]);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->call('abrirCrearCarpeta')
            ->set('nuevaCarpetaNombre', 'Sílabo')
            ->call('crearCarpeta')
            ->assertHasNoErrors();

        $carpeta = $curso->carpetas()->firstOrFail();
        $this->assertSame('Sílabo', $carpeta->nombre);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->call('abrirAgregarRecurso', $carpeta->id)
            ->set('nuevoRecursoTipo', 'enlace')
            ->set('nuevoRecursoNombre', 'Formato oficial')
            ->set('nuevoRecursoUrl', 'https://ejemplo.edu.pe/silabo.pdf')
            ->call('agregarRecurso')
            ->assertHasNoErrors()
            ->assertSee('Formato oficial');

        $this->assertCount(1, $carpeta->fresh()->recursos);
        $this->assertSame('https://ejemplo.edu.pe/silabo.pdf', $carpeta->fresh()->recursos[0]['url']);
    }

    public function test_el_supervisor_sube_un_archivo_a_una_carpeta_y_lo_elimina(): void
    {
        Storage::fake('local', ['serve' => true]);
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->create(['profesor_id' => $supervisor->id]);
        $carpeta = $curso->carpetas()->create(['nombre' => 'Actas', 'orden' => 1, 'recursos' => []]);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->call('abrirAgregarRecurso', $carpeta->id)
            ->set('nuevoRecursoTipo', 'archivo')
            ->set('nuevoRecursoNombre', 'Acta de reunión')
            ->set('nuevoRecursoArchivo', UploadedFile::fake()->create('acta.pdf', 20, 'application/pdf'))
            ->call('agregarRecurso')
            ->assertHasNoErrors();

        $ruta = $carpeta->fresh()->recursos[0]['url'];
        Storage::disk('local')->assertExists($ruta);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->call('eliminarRecurso', $carpeta->id, 0)
            ->assertHasNoErrors();

        $this->assertCount(0, $carpeta->fresh()->recursos);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_el_supervisor_elimina_una_carpeta(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->create(['profesor_id' => $supervisor->id]);
        $carpeta = $curso->carpetas()->create(['nombre' => 'Temporal', 'orden' => 1, 'recursos' => []]);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->call('eliminarCarpeta', $carpeta->id);

        $this->assertModelMissing($carpeta);
    }

    public function test_el_profesor_ve_las_carpetas_de_solo_lectura_sin_poder_administrarlas(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $curso->carpetas()->create([
            'nombre' => 'Sílabo',
            'orden' => 1,
            'recursos' => [['nombre' => 'Formato oficial', 'url' => 'https://ejemplo.edu.pe/silabo.pdf']],
        ]);

        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Información del curso')
            ->assertSee('Sílabo')
            ->assertSee('Formato oficial')
            ->assertDontSee('Nueva carpeta')
            ->assertDontSee('Agregar archivo o enlace');

        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->call('abrirCrearCarpeta')
            ->assertForbidden();
    }

    public function test_el_supervisor_matricula_un_estudiante_desde_el_resumen_administrativo(): void
    {
        Notification::fake();
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $estudiante = User::factory()->create(['name' => 'Ana Quispe'])->assignRole(Rol::Estudiante->value);
        $curso = Curso::factory()->create(['profesor_id' => $supervisor->id, 'estado' => 'publicado']);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->set('busquedaEstudianteRapida', 'Ana')
            ->call('seleccionarEstudianteRapido', $estudiante->id)
            ->call('matricularRapido')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('inscripciones', [
            'curso_id' => $curso->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'activo',
        ]);
    }

    public function test_el_contenido_por_semanas_aparece_colapsado_solo_para_el_supervisor(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Ver detalle semana por semana')
            ->assertSee('con contenido');

        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->assertDontSee('Ver detalle semana por semana')
            ->assertSee('Semana 1');
    }
}
