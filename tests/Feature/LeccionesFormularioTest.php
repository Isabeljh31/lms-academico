<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Compartido\Cursos\Show;
use App\Models\Leccion;
use App\Models\Modulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Contenido de cada semana gestionado en la pantalla del curso (Profesor):
 * acordeón por semana con secciones fijas (Mensaje de inicio, Recursos de
 * aprendizaje, Actividades complementarias y Grabación de la clase). Cada
 * sección define su formulario y su validación; las lecciones antiguas se
 * muestran en la sección equivalente y se actualizan solo al guardarlas.
 */
class LeccionesFormularioTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('archivos.disco'));
    }

    public function test_cada_semana_muestra_las_cuatro_secciones_aunque_este_vacia(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $escenario['modulo']->lecciones()->create(['titulo' => 'Guía', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'recursos', 'material_url' => 'https://example.com']);

        $this->componente($escenario)
            ->assertSeeInOrder(['Semana 1', 'Mensaje de inicio', 'Recursos de aprendizaje', 'Guía', 'Actividades complementarias', 'Grabación de la clase',
                'Semana 2', 'Mensaje de inicio', 'Recursos de aprendizaje', 'Actividades complementarias', 'Grabación de la clase'])
            ->assertSee('aria-label="1 elemento"', false)
            ->assertDontSee('Preparar semana')
            ->assertDontSee(route('lecciones.index', $escenario['modulo']), false);
    }

    public function test_la_ruta_antigua_lleva_a_la_pantalla_del_curso_con_la_semana_abierta(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $escenario['modulo']->update(['semana' => 4]);

        $this->actingAs($escenario['profesor'])->get(route('lecciones.index', $escenario['modulo']))
            ->assertRedirect(route('cursos.show', [$escenario['curso'], 'semana' => 4]));

        $html = $this->actingAs($escenario['profesor'])->get(route('cursos.show', [$escenario['curso'], 'semana' => 4]))->assertOk()->getContent();
        $this->assertSame([4], $this->semanasDesplegadas($html));
    }

    public function test_la_semana_inicial_abierta_es_la_actual_o_la_primera_con_contenido_o_la_primera(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $escenario['tarea']->delete();
        $escenario['quiz']->delete();
        $escenario['modulo']->delete();

        $curso->update(['fecha_inicio' => null, 'semanas_duracion' => 6]);
        $this->componente($escenario)->assertSet('semanasAbiertas', [1]);

        Modulo::factory()->create(['curso_id' => $curso->id, 'semana' => 4])->lecciones()->create(['titulo' => 'x', 'estado' => 'publicado', 'orden' => 1]);
        $this->componente($escenario)->assertSet('semanasAbiertas', [4]);

        $curso->update(['fecha_inicio' => now()->startOfWeek()->subWeeks(2)]);
        $this->componente($escenario)->assertSet('semanasAbiertas', [3]);
    }

    public function test_tras_guardar_la_semana_queda_abierta_y_las_demas_conservan_su_estado(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $componente = $this->componente($escenario)->assertSet('semanasAbiertas', [1])
            // El profesor también despliega la semana 2 (Alpine lo cambia sin pedir al servidor).
            ->set('semanasAbiertas', [1, 2])
            ->call('agregarLeccion', 'grabacion', 5)
            ->set('titulo', 'Clase 5')
            ->set('video_url', 'https://zoom.us/rec/5')
            ->call('guardarLeccion')
            ->assertHasNoErrors()
            ->assertSet('mostrarFormularioLeccion', false);

        $this->assertSame([1, 2, 5], $this->semanasDesplegadas($componente->html()));
        $this->assertStringContainsString('Clase 5', $componente->html());

        // Con un error de validación el formulario sigue abierto con su mensaje.
        $componente->call('agregarLeccion', 'recursos', 2)->call('guardarLeccion')
            ->assertHasErrors(['titulo', 'archivo'])
            ->assertSet('mostrarFormularioLeccion', true)
            ->assertSee('Sube un archivo o elige');
        $this->assertSame([1, 2, 5], $this->semanasDesplegadas($componente->html()));
    }

    public function test_las_secciones_salen_cerradas_y_solo_se_muestra_la_semana_actual(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $escenario['curso']->update(['semanas_duracion' => 6, 'fecha_inicio' => now()->startOfWeek()->subWeek()]);
        $escenario['modulo']->lecciones()->create(['titulo' => 'Guía', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'recursos', 'material_url' => 'https://example.com']);

        $html = $this->componente($escenario)
            ->assertSet('semanasAbiertas', [2])
            ->assertSet('seccionesAbiertas', [])
            ->html();

        $this->assertSame([2], $this->semanasDesplegadas($html));
        $this->assertSame([], $this->seccionesDesplegadas($html));
        $this->assertStringContainsString('id="semana-1-recursos-boton" x-on:click="alternar(\'recursos\', $el)"', $html);
    }

    public function test_solo_una_seccion_abierta_por_semana_y_el_estado_sobrevive_a_un_re_render(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $componente = $this->componente($escenario)
            // Alpine cambia el estado sin pedir al servidor; al abrir otra sección de la misma semana reemplaza a la anterior.
            ->set('seccionesAbiertas', [1 => 'recursos', 2 => 'grabacion'])
            ->set('seccionesAbiertas', [1 => 'actividades', 2 => 'grabacion']);
        $this->assertSame(['1:actividades', '2:grabacion'], $this->seccionesDesplegadas($componente->html()));

        // Un re-render cualquiera no cierra nada.
        $componente->call('$refresh');
        $this->assertSame(['1:actividades', '2:grabacion'], $this->seccionesDesplegadas($componente->html()));

        // Lo que no es "semana => sección válida" se descarta.
        $componente->set('seccionesAbiertas', [1 => 'inexistente', 3 => 'recursos'])->assertSet('seccionesAbiertas', [3 => 'recursos']);
    }

    public function test_agregar_no_alterna_la_seccion_y_guardar_abre_la_del_elemento(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $componente = $this->componente($escenario)
            ->set('seccionesAbiertas', [1 => 'recursos', 2 => 'actividades'])
            ->call('agregarLeccion', 'grabacion', 1)
            ->assertSet('mostrarFormularioLeccion', true)
            ->assertSet('seccionesAbiertas', [1 => 'recursos', 2 => 'actividades'])
            ->set('titulo', 'Clase 1')
            ->set('video_url', 'https://zoom.us/rec/1')
            ->call('guardarLeccion')
            ->assertHasNoErrors()
            ->assertSet('seccionesAbiertas', [1 => 'grabacion', 2 => 'actividades']);

        $this->assertSame(['1:grabacion', '2:actividades'], $this->seccionesDesplegadas($componente->html()));
    }

    public function test_el_mensaje_de_inicio_se_edita_solo_desde_su_fila(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $escenario['modulo']->lecciones()->create(['titulo' => 'Bienvenida', 'descripcion' => 'Hola', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'mensaje_inicio']);

        $this->componente($escenario)
            ->assertSee('aria-label="Editar mensaje de inicio"', false)
            ->assertDontSee('aria-label="Editar Bienvenida"', false)
            ->assertSee('aria-label="Eliminar Bienvenida"', false);
    }

    public function test_una_semana_vacia_se_crea_al_guardar_su_primer_elemento_y_no_al_abrir_el_formulario(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $componente = $this->componente($escenario)->call('agregarLeccion', 'actividades', 3);
        $this->assertNull($escenario['curso']->modulos()->where('semana', 3)->first());

        $componente->set('titulo', 'Laboratorio')->set('descripcion', 'Sigue la guía.')->call('guardarLeccion')->assertHasNoErrors();

        $modulo = $escenario['curso']->modulos()->where('semana', 3)->sole();
        $this->assertSame(['Laboratorio'], $modulo->lecciones()->pluck('titulo')->all());

        // Una semana fuera de la duración del curso no existe.
        $this->componente($escenario)->call('agregarLeccion', 'recursos', 99)->assertNotFound();
    }

    public function test_mensaje_de_inicio_exige_el_mensaje_y_es_unico_por_semana(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $componente = $this->componente($escenario)
            ->call('agregarLeccion', 'mensaje_inicio', 1)
            ->assertSet('titulo', 'Mensaje de inicio')
            ->call('guardarLeccion')
            ->assertHasErrors('descripcion')
            ->set('descripcion', '¿Qué esperas aprender esta semana?')
            ->call('guardarLeccion')
            ->assertHasNoErrors();

        $mensaje = Leccion::sole();
        $this->assertSame(['mensaje_inicio', 'marcar', null], [$mensaje->tipo_recurso, $mensaje->condicion_completitud, $mensaje->material_url]);

        // Con un mensaje ya creado, el botón dice "Editar" y abre el existente.
        $this->assertMatchesRegularExpression('/Mensaje de inicio.*Editar.*Recursos de aprendizaje.*Agregar/s', $componente->html());
        $componente->call('agregarLeccion', 'mensaje_inicio', 1)->assertSet('leccionEditId', $mensaje->id);

        // Si otro mensaje aparece mientras el formulario está abierto, no se crea un segundo.
        $mensaje->delete();
        $otro = $this->componente($escenario)->call('agregarLeccion', 'mensaje_inicio', 1)->set('descripcion', 'Hola');
        $escenario['modulo']->lecciones()->create(['titulo' => 'Mensaje de inicio', 'descripcion' => 'Otro', 'estado' => 'borrador', 'orden' => 1, 'tipo_recurso' => 'mensaje_inicio']);
        $otro->call('guardarLeccion')->assertHasErrors('seccionLeccion');
        $this->assertSame(1, Leccion::where('tipo_recurso', 'mensaje_inicio')->count());
    }

    public function test_recursos_exige_archivo_o_enlace_y_valida_formato_tamano_y_url(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $componente = $this->componente($escenario)->call('agregarLeccion', 'recursos', 1)->assertSet('fuente', 'archivo')->set('titulo', 'Material');

        $componente->call('guardarLeccion')->assertHasErrors('archivo');
        $componente->set('archivo', UploadedFile::fake()->create('carpeta.zip', 100))->call('guardarLeccion')->assertHasErrors('archivo');
        $componente->set('archivo', UploadedFile::fake()->create('apuntes.pdf', 10241))->call('guardarLeccion')->assertHasErrors('archivo');
        $componente->set('fuente', 'enlace')->call('guardarLeccion')->assertHasErrors('material_url');
        $componente->set('material_url', 'javascript:alert(1)')->call('guardarLeccion')->assertHasErrors('material_url');

        $componente->set('fuente', 'archivo')->set('archivo', UploadedFile::fake()->create('diapositivas.pptx', 200))->call('guardarLeccion')->assertHasNoErrors();
        $this->componente($escenario)->call('agregarLeccion', 'recursos', 1)->set('titulo', 'Simulador')
            ->set('fuente', 'enlace')->set('material_url', 'https://phet.colorado.edu')->call('guardarLeccion')->assertHasNoErrors();

        $diapositivas = Leccion::where('titulo', 'Material')->sole();
        $simulador = Leccion::where('titulo', 'Simulador')->sole();
        $this->assertSame(['recursos', 'diapositivas.pptx', 'abrir_recurso', 'presentacion'],
            [$diapositivas->tipo_recurso, $diapositivas->material_nombre, $diapositivas->condicion_completitud, $diapositivas->tipoRecurso()['icono']]);
        $this->assertSame(['https://phet.colorado.edu', null, 'enlace'],
            [$simulador->material_url, $simulador->material_nombre, $simulador->tipoRecurso()['icono']]);
    }

    public function test_actividades_exige_instrucciones_y_el_adjunto_es_opcional(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $this->componente($escenario)->call('agregarLeccion', 'actividades', 1)
            ->assertSet('fuente', 'ninguno')
            ->assertSee('Si tiene nota, créala en Tareas o Quizzes.')
            ->set('titulo', 'Caso de estudio')
            ->call('guardarLeccion')->assertHasErrors('descripcion')
            ->set('descripcion', 'Lee el caso y responde en clase.')
            ->call('guardarLeccion')->assertHasNoErrors();

        $this->componente($escenario)->call('agregarLeccion', 'actividades', 1)->set('titulo', 'Laboratorio')->set('descripcion', 'Sigue la guía.')
            ->set('fuente', 'archivo')->set('archivo', UploadedFile::fake()->create('guia.docx', 100))->call('guardarLeccion')->assertHasNoErrors();

        $this->assertSame(['marcar', 'abrir_recurso'], Leccion::where('tipo_recurso', 'actividades')->orderBy('id')->pluck('condicion_completitud')->all());
    }

    public function test_grabacion_exige_un_enlace_valido(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $this->componente($escenario)->call('agregarLeccion', 'grabacion', 1)->set('titulo', 'Clase 3')
            ->call('guardarLeccion')->assertHasErrors('video_url')
            ->set('video_url', 'no-es-un-enlace')->call('guardarLeccion')->assertHasErrors('video_url')
            ->set('video_url', 'https://teams.microsoft.com/grabacion')->call('guardarLeccion')->assertHasNoErrors();

        $grabacion = Leccion::sole();
        $this->assertSame(['grabacion', 'https://teams.microsoft.com/grabacion', null, 'abrir_recurso'],
            [$grabacion->tipo_recurso, $grabacion->video_url, $grabacion->material_url, $grabacion->condicion_completitud]);
    }

    public function test_al_cambiar_de_fuente_o_de_seccion_no_quedan_datos_de_la_anterior(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $this->componente($escenario)->call('agregarLeccion', 'recursos', 1)
            ->set('titulo', 'Material')->set('descripcion', 'Texto')
            ->set('fuente', 'enlace')->set('material_url', 'https://example.com')
            ->set('fuente', 'archivo')->assertSet('material_url', '')
            ->set('archivo', UploadedFile::fake()->create('guia.pdf', 100))
            ->set('fuente', 'enlace')->assertSet('archivo', null)
            ->call('cerrarFormularioLeccion')
            ->call('agregarLeccion', 'grabacion', 2)
            ->assertSet('seccionLeccion', 'grabacion')
            ->assertSet('semanaLeccion', 2)
            ->assertSet('titulo', '')
            ->assertSet('descripcion', '')
            ->assertSet('material_url', '')
            ->assertSet('fuente', 'ninguno')
            ->assertSet('condicion_completitud', 'marcar');
    }

    public function test_la_condicion_por_defecto_sigue_al_recurso_hasta_que_el_profesor_elige(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $componente = $this->componente($escenario)->call('agregarLeccion', 'recursos', 1)
            ->assertSet('condicion_completitud', 'marcar')
            ->set('fuente', 'enlace')->set('material_url', 'https://example.com')
            ->assertSet('condicion_completitud', 'abrir_recurso')
            ->set('condicion_completitud', 'marcar')
            ->set('material_url', 'https://example.com/otra')
            ->assertSet('condicion_completitud', 'marcar');

        $componente->set('condicion_completitud', 'abrir_recurso')->set('material_url', '')->assertSet('condicion_completitud', 'marcar');
        $this->assertMatchesRegularExpression('/value="abrir_recurso"[^>]*disabled/', $componente->html());

        $this->componente($escenario)->call('agregarLeccion', 'mensaje_inicio', 1)->set('descripcion', 'Hola')
            ->set('condicion_completitud', 'abrir_recurso')->call('guardarLeccion')->assertHasErrors('condicion_completitud');
    }

    public function test_ningun_valor_visible_queda_vacio(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $componente = $this->componente($escenario)->call('agregarLeccion', 'recursos', 1);
        $this->assertSame(['estado' => 'borrador', 'fuente' => 'archivo', 'condicion_completitud' => 'marcar'], $this->valoresVisibles($componente));

        $componente->call('guardarLeccion')->assertHasErrors('titulo');
        $this->assertSame(['estado' => 'borrador', 'fuente' => 'archivo', 'condicion_completitud' => 'marcar'], $this->valoresVisibles($componente));

        $leccion = $escenario['modulo']->lecciones()->create([
            'titulo' => 'Clase grabada', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'grabacion',
            'video_url' => 'https://youtube.com/watch?v=3', 'condicion_completitud' => 'abrir_recurso',
        ]);
        $componente->call('editarLeccion', $leccion->id);
        $this->assertSame(['estado' => 'publicado', 'condicion_completitud' => 'abrir_recurso'], $this->valoresVisibles($componente));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, string>}>
     */
    public static function tiposAntiguos(): array
    {
        return [
            'lectura' => ['lectura', 'recursos', ['descripcion' => 'Capítulo 1', 'material_url' => 'https://drive.google.com/x']],
            'presentación' => ['presentacion', 'recursos', ['material_url' => 'cursos/lecciones/diapositivas.pptx', 'material_nombre' => 'diapositivas.pptx']],
            'recurso externo' => ['enlace', 'recursos', ['material_url' => 'https://es.wikipedia.org']],
            'recurso externo (nombre largo)' => ['recurso_externo', 'recursos', ['material_url' => 'https://es.wikipedia.org']],
            'documento' => ['documento', 'recursos', ['material_url' => 'cursos/lecciones/guia.pdf', 'material_nombre' => 'guia.pdf']],
            'actividad' => ['actividad', 'actividades', ['descripcion' => 'Resuelve la guía']],
            'actividad (nombre largo)' => ['actividad_complementaria', 'actividades', ['descripcion' => 'Resuelve la guía']],
            'grabación (nombre largo)' => ['grabacion_sesion', 'grabacion', ['video_url' => 'https://zoom.us/rec/1']],
        ];
    }

    /**
     * @param  array<string, string>  $datos
     */
    #[DataProvider('tiposAntiguos')]
    public function test_una_leccion_antigua_se_muestra_en_su_seccion_y_se_actualiza_solo_al_guardarla(string $tipoAntiguo, string $seccion, array $datos): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $escenario['modulo']->lecciones()->create(['titulo' => 'Antigua', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => $tipoAntiguo, ...$datos]);
        $otra = $escenario['modulo']->lecciones()->create(['titulo' => 'Otra antigua', 'descripcion' => 'x', 'estado' => 'publicado', 'orden' => 2, 'tipo_recurso' => $tipoAntiguo, ...$datos]);

        $this->componente($escenario)->call('editarLeccion', $leccion->id)
            ->assertSet('seccionLeccion', $seccion)
            ->set('titulo', 'Antigua (revisada)')
            ->call('guardarLeccion')
            ->assertHasNoErrors();

        $leccion->refresh();
        $this->assertSame([$seccion, 'Antigua (revisada)'], [$leccion->tipo_recurso, $leccion->titulo]);
        foreach ($datos as $campo => $valor) {
            $this->assertSame($valor, $leccion->{$campo}, "Se perdió {$campo} al guardar.");
        }
        // Solo cambia la lección guardada: nada de actualizaciones masivas.
        $this->assertSame($tipoAntiguo, $otra->fresh()->tipo_recurso);
    }

    public function test_una_leccion_antigua_de_video_pasa_su_enlace_a_recursos(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $escenario['modulo']->lecciones()->create(['titulo' => 'Video', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'video', 'video_url' => 'https://youtube.com/watch?v=9']);

        $this->componente($escenario)->call('editarLeccion', $leccion->id)
            ->assertSet('seccionLeccion', 'recursos')
            ->assertSet('fuente', 'enlace')
            ->assertSet('material_url', 'https://youtube.com/watch?v=9')
            ->call('guardarLeccion')->assertHasNoErrors();

        $leccion->refresh();
        $this->assertSame(['recursos', 'https://youtube.com/watch?v=9', null, 'video'],
            [$leccion->tipo_recurso, $leccion->material_url, $leccion->video_url, $leccion->tipoRecurso()['icono']]);
    }

    public function test_una_leccion_antigua_conserva_lo_que_su_seccion_ya_no_muestra(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $escenario['modulo']->lecciones()->create([
            'titulo' => 'Capítulo 1', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'lectura',
            'material_url' => 'cursos/lecciones/capitulo.pdf', 'material_nombre' => 'capitulo.pdf', 'video_url' => 'https://youtube.com/watch?v=2',
        ]);

        $this->componente($escenario)->call('editarLeccion', $leccion->id)
            ->assertSet('archivoActual', 'capitulo.pdf')
            ->assertSee('conserva un video o un material del formulario anterior')
            ->call('guardarLeccion')->assertHasNoErrors();

        $leccion->refresh();
        $this->assertSame(['recursos', 'cursos/lecciones/capitulo.pdf', 'https://youtube.com/watch?v=2'],
            [$leccion->tipo_recurso, $leccion->material_url, $leccion->video_url]);
    }

    public function test_el_foro_antiguo_se_sigue_editando_con_su_aviso(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $escenario['modulo']->lecciones()->create([
            'titulo' => 'Dudas de la semana', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'foro',
            'video_url' => 'https://youtube.com/watch?v=1', 'material_url' => 'https://drive.google.com/x',
        ]);

        $componente = $this->componente($escenario)->assertSee('Foro de preguntas (formato anterior)')
            ->call('editarLeccion', $leccion->id)
            ->assertSet('seccionLeccion', 'foro')
            ->assertSee('Este formato ya no se ofrece para contenido nuevo');
        $componente->set('titulo', 'Dudas (actualizado)')->call('guardarLeccion')->assertHasNoErrors();

        $leccion->refresh();
        $this->assertSame(['foro', 'https://youtube.com/watch?v=1', 'https://drive.google.com/x'], [$leccion->tipo_recurso, $leccion->video_url, $leccion->material_url]);

        $this->componente($escenario)->call('agregarLeccion', 'foro', 1)->assertNotFound();
    }

    public function test_el_orden_se_cambia_dentro_de_la_seccion(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $modulo = $escenario['modulo'];
        $a = $modulo->lecciones()->create(['titulo' => 'A', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'recursos']);
        $modulo->lecciones()->create(['titulo' => 'Grabación', 'estado' => 'publicado', 'orden' => 2, 'tipo_recurso' => 'grabacion']);
        $modulo->lecciones()->create(['titulo' => 'B', 'estado' => 'publicado', 'orden' => 3, 'tipo_recurso' => 'recursos']);

        $this->componente($escenario)->call('moverLeccion', $a->id, 1);

        $this->assertSame(['B', 'Grabación', 'A'], $modulo->lecciones()->pluck('titulo')->all());
    }

    public function test_eliminar_un_elemento_pide_confirmacion_indicando_que_se_borra(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $escenario['modulo']->lecciones()->create(['titulo' => 'Guía 1', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'recursos']);

        $componente = $this->componente($escenario)->call('confirmarEliminarLeccion', $leccion->id)
            ->assertSet('mostrarConfirmacion', true)
            ->assertSee('Se eliminará "Guía 1" de Semana 1');
        $this->assertNotNull($leccion->fresh());

        $componente->call('cerrarConfirmacion')->assertSet('mostrarConfirmacion', false);
        $this->assertNotNull($leccion->fresh());

        $componente->call('confirmarEliminarLeccion', $leccion->id)->call('ejecutarConfirmacion');
        $this->assertNull($leccion->fresh());
        $this->assertSame([1], $this->semanasDesplegadas($componente->html()));
    }

    public function test_vaciar_una_semana_o_quitar_las_practicas_pide_confirmacion_con_su_contenido(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $escenario['modulo']->lecciones()->create(['titulo' => 'Guía', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'recursos']);
        $practicas = $escenario['curso']->modulos()->create(['titulo' => 'Prácticas de campo', 'tipo_seccion' => Modulo::TIPO_PRACTICAS, 'materiales' => []]);

        $componente = $this->componente($escenario)
            ->assertDontSee('wire:confirm', false)
            ->call('confirmarVaciarModulo', $escenario['modulo']->id)
            ->assertSet('confirmacionTitulo', 'Vaciar Semana 1')
            ->assertSee('se eliminan 1 elemento de contenido, 1 tarea y 1 quiz');
        $this->assertNotNull($escenario['modulo']->fresh());

        $componente->call('ejecutarConfirmacion');
        $this->assertNull($escenario['modulo']->fresh());

        $componente->call('confirmarVaciarModulo', $practicas->id)
            ->assertSet('confirmacionTitulo', 'Quitar prácticas de campo')
            ->assertSet('confirmacionTextoBoton', 'Quitar');
        $this->assertNotNull($practicas->fresh());
        $componente->call('ejecutarConfirmacion');
        $this->assertNull($practicas->fresh());
    }

    public function test_solo_el_profesor_del_curso_gestiona_el_contenido(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $escenario['modulo']->lecciones()->create(['titulo' => 'Guía', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'recursos']);
        $otroProfesor = $this->usuarioConRol(Rol::Profesor->value);

        $this->componente($escenario)->call('agregarLeccion', 'recursos', 1)->assertSet('mostrarFormularioLeccion', true);

        Livewire::actingAs($otroProfesor)->test(Show::class, ['curso' => $escenario['curso']])->assertForbidden();
        $this->actingAs($otroProfesor)->get(route('lecciones.index', $escenario['modulo']))->assertForbidden();

        foreach ([$escenario['estudiante'], $escenario['supervisor']] as $usuario) {
            $abrir = fn () => Livewire::actingAs($usuario)->test(Show::class, ['curso' => $escenario['curso']]);
            $abrir()->assertDontSee('agregarLeccion', false);
            $abrir()->call('agregarLeccion', 'recursos', 1)->assertForbidden();
            $abrir()->call('confirmarEliminarLeccion', $leccion->id)->assertForbidden();
            $abrir()->call('confirmarVaciarModulo', $escenario['modulo']->id)->assertForbidden();
            $abrir()->call('eliminarLeccion', $leccion->id)->assertForbidden();
        }

        $this->assertNotNull($leccion->fresh());
    }

    public function test_el_contenido_de_todas_las_semanas_se_carga_sin_consultas_repetidas(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $escenario['curso']->update(['semanas_duracion' => 8]);

        $consultasCon = function (int $semanas) use ($escenario): int {
            foreach (range(1, $semanas) as $semana) {
                $modulo = $escenario['curso']->modulos()->firstOrCreate(['semana' => $semana], ['titulo' => 'Semana '.$semana, 'materiales' => []]);
                $modulo->lecciones()->create(['titulo' => 'L'.$semana, 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'recursos']);
            }

            // Primero una carga para calentar cachés (permisos, roles); luego se mide con el curso recién leído.
            $medir = fn () => Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']->fresh()]);
            $medir();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $medir();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->assertSame($consultasCon(2), $consultasCon(8));
    }

    public function test_el_estudiante_ve_iconos_y_etiquetas_de_valores_nuevos_y_antiguos(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $modulo = $escenario['modulo'];
        foreach ([['mensaje_inicio', 'Bienvenida'], ['recursos', 'Guía nueva'], ['actividades', 'Ejercicios'], ['grabacion', 'Clase 1'], ['lectura', 'Lectura antigua'], ['foro', 'Dudas']] as $orden => [$tipo, $titulo]) {
            $modulo->lecciones()->create(['titulo' => $titulo, 'estado' => 'publicado', 'orden' => $orden + 1, 'tipo_recurso' => $tipo]);
        }

        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSeeInOrder(['Mensaje de inicio', 'Bienvenida', 'Recurso de aprendizaje', 'Guía nueva', 'Actividad complementaria', 'Ejercicios',
                'Grabación de la clase', 'Clase 1', 'Lectura', 'Lectura antigua', 'Foro de preguntas', 'Dudas'])
            ->assertDontSee('agregarLeccion', false);

        $esperados = [
            'mensaje_inicio' => ['Mensaje de inicio', 'megafono'],
            'actividades' => ['Actividad complementaria', 'lista'],
            'grabacion' => ['Grabación de la clase', 'grabacion'],
            'lectura' => ['Lectura', 'libro'],
            'presentacion' => ['Presentación', 'presentacion'],
            'video' => ['Video', 'video'],
            'enlace' => ['Recurso externo', 'enlace'],
            'grabacion_sesion' => ['Grabación de la clase', 'grabacion'],
            'recurso_externo' => ['Recurso de aprendizaje', 'libro'],
            'foro_preguntas' => ['Foro de preguntas', 'chat'],
        ];
        foreach ($esperados as $tipo => [$etiqueta, $icono]) {
            $this->assertSame(['etiqueta' => $etiqueta, 'icono' => $icono], (new Leccion(['tipo_recurso' => $tipo]))->tipoRecurso(), $tipo);
        }
    }

    /**
     * Escenario con el módulo de prueba en la Semana 1 (el contenido se gestiona por semana).
     *
     * @return array<string, mixed>
     */
    private function escenarioEnSemana1(): array
    {
        $escenario = $this->escenario();
        $escenario['modulo']->update(['semana' => 1, 'orden' => 1]);
        $escenario['curso']->update(['fecha_inicio' => null]);

        return $escenario;
    }

    /**
     * @param  array<string, mixed>  $escenario
     */
    private function componente(array $escenario): Testable
    {
        return Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']]);
    }

    /**
     * Semanas cuyo contenido sale desplegado en el HTML del servidor (sin display: none).
     *
     * @return array<int, int>
     */
    private function semanasDesplegadas(string $html): array
    {
        preg_match_all('/<div id="semana-(\d+)-contenido"[^>]*>/', $html, $regiones, PREG_SET_ORDER);

        return collect($regiones)
            ->reject(fn (array $region) => str_contains($region[0], 'display: none'))
            ->map(fn (array $region) => (int) $region[1])
            ->values()
            ->all();
    }

    /**
     * Secciones desplegadas en el HTML del servidor, como "semana:sección".
     *
     * @return array<int, string>
     */
    private function seccionesDesplegadas(string $html): array
    {
        preg_match_all('/<div id="semana-(\d+)-([a-z_]+)-contenido"[^>]*>/', $html, $regiones, PREG_SET_ORDER);

        return collect($regiones)
            ->reject(fn (array $region) => str_contains($region[0], 'display: none'))
            ->map(fn (array $region) => $region[1].':'.$region[2])
            ->values()
            ->all();
    }

    /**
     * Opción marcada (atributo checked renderizado en el servidor) de cada
     * grupo del formulario abierto, y comprobación de que el resumen de
     * "Avance del estudiante" muestra la condición.
     *
     * @return array<string, string>
     */
    private function valoresVisibles(Testable $componente): array
    {
        $html = $componente->html();
        $valores = [];

        foreach (['estado', 'fuente', 'condicion_completitud'] as $grupo) {
            preg_match_all('/<input type="radio" name="leccion_'.$grupo.'" value="([^"]+)"[^>]*>/', $html, $radios, PREG_SET_ORDER);

            if ($radios === []) {
                continue;
            }

            $marcados = array_values(array_filter($radios, fn (array $radio) => preg_match('/\schecked(\s|=|>)/', $radio[0])));
            $this->assertCount(1, $marcados, "El grupo {$grupo} debe tener exactamente una opción marcada.");
            $valores[$grupo] = $marcados[0][1];
        }

        $this->assertMatchesRegularExpression('/id="resumen-completitud"[^>]*>Se considera completado: \S/', $html);

        return $valores;
    }
}
