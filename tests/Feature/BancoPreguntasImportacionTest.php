<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Profesor\BancoPreguntas;
use App\Livewire\Quizzes\Formulario as FormularioQuiz;
use App\Models\BancoPregunta;
use App\Models\Curso;
use App\Services\Evaluacion\ImportadorBancoPreguntas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Banco de preguntas del Profesor: filtros, "usada en N quizzes" (el quiz
 * guarda una copia), eliminación en lote y carga masiva desde Excel/CSV con
 * vista previa, validación fila por fila, duplicados y reporte.
 */
class BancoPreguntasImportacionTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private const ENCABEZADO = 'unidad,tema,tipo,enunciado,opcion_1,opcion_2,opcion_3,opcion_4,opcion_5,opcion_6,correcta,puntaje,retroalimentacion';

    public function test_la_plantilla_se_descarga_y_sus_ejemplos_son_validos(): void
    {
        $escenario = $this->escenario();

        $this->componente($escenario)->call('descargarPlantilla')->assertFileDownloaded('plantilla-banco-preguntas.xlsx');

        $plantilla = UploadedFile::fake()->createWithContent('plantilla.xlsx', app(ImportadorBancoPreguntas::class)->plantilla()->contenido());
        $analisis = app(ImportadorBancoPreguntas::class)->analizar($plantilla->getRealPath(), 'xlsx', $escenario['curso']);

        $this->assertCount(3, $analisis['validas']);
        $this->assertSame(['unidad', 'tema'], array_slice(ImportadorBancoPreguntas::COLUMNAS, 0, 2));
        $this->assertSame(['T1', 'T1', 'T2'], array_column($analisis['validas'], 'unidad'));
        $this->assertSame([], $analisis['errores']);
        $this->assertSame(['opcion_unica', 'opcion_multiple', 'verdadero_falso'], array_column($analisis['validas'], 'tipo'));
        $this->assertSame([[1], [0, 2], [1]], array_column($analisis['validas'], 'respuestas_correctas'));
    }

    public function test_vista_previa_y_luego_solo_se_guardan_las_filas_validas(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $curso->bancoPreguntasCurso()->create(['tema' => 'Álgebra', 'pregunta' => '¿Cuánto es 1 + 1?', 'tipo' => 'opcion_unica', 'opciones' => ['1', '2'], 'respuestas_correctas' => [1]]);

        $csv = $this->csv([
            'T1;Álgebra;unica;¿Cuánto es 2 + 2?;3;4;;;;;B;2;Suma',
            ' t1 ;Álgebra;multiple;¿Cuáles son pares?;2;3;4;;;;A,C;;',
            't 2;Lógica;vf;El cielo es verde;;;;;;;F;;',
            'T2;Lógica;unica;Sin respuesta válida;Sí;No;;;;;D;;',
            'T2;Lógica;unica;Dos correctas;Sí;No;;;;;A,B;;',
            'T2;Lógica;abierta;Tipo raro;Sí;No;;;;;A;;',
            'T2;Lógica;unica;Una sola opción;Sí;;;;;;A;;',
            'T9;;unica;Sin tema;Sí;No;;;;;A;0;',
            'T1;álgebra;unica;¿cuánto es 1 + 1?;1;2;;;;;B;;',
            'T1;Álgebra;unica;¿Cuánto es 2 + 2?;3;4;;;;;B;;',
        ], ';');

        $componente = $this->componente($escenario)
            ->call('abrirImportacion')
            ->set('archivoImportacion', $csv)
            ->assertHasNoErrors()
            ->assertSet('analisis.validas', 3)
            ->assertSee('Vista previa (aún no se guardó nada)');

        $this->assertSame(1, $curso->bancoPreguntasCurso()->count());
        $errores = collect($componente->get('analisis.errores'))->keyBy('fila');
        $this->assertSame([5, 6, 7, 8, 9], $errores->keys()->all());
        $this->assertStringContainsString('"D" no corresponde a ninguna opción', $errores[5]['motivo']);
        $this->assertStringContainsString('solo puede haber una respuesta correcta', $errores[6]['motivo']);
        $this->assertStringContainsString('Tipo "abierta" no válido', $errores[7]['motivo']);
        $this->assertStringContainsString('al menos 2 opciones', $errores[8]['motivo']);
        $this->assertStringContainsString('Unidad "T9" no válida. Usa T1, T2, T3 o T4.', $errores[9]['motivo']);
        $this->assertStringContainsString('Falta el tema.', $errores[9]['motivo']);
        $this->assertStringContainsString('puntaje debe ser un número', $errores[9]['motivo']);
        $this->assertSame([10, 11], array_column($componente->get('analisis.duplicadas'), 'fila'));

        $componente->call('importar')
            ->assertSet('importado', true)
            ->assertSet('importadas', 3)
            ->assertSee('Resultado de la importación')
            ->call('descargarReporte')
            ->assertFileDownloaded('reporte-importacion-banco.xlsx');

        $this->assertSame(4, $curso->bancoPreguntasCurso()->count());
        $multiple = $curso->bancoPreguntasCurso()->where('pregunta', '¿Cuáles son pares?')->sole();
        $this->assertSame(['2', '3', '4'], $multiple->opciones);
        $this->assertSame([0, 2], $multiple->respuestas_correctas);
        $this->assertNull($multiple->puntaje);
        $this->assertSame(1.0, $multiple->aPreguntaDeQuiz()['puntaje']);
        $this->assertSame(2.0, $curso->bancoPreguntasCurso()->where('pregunta', '¿Cuánto es 2 + 2?')->sole()->aPreguntaDeQuiz()['puntaje']);
        // La unidad se guarda normalizada (" t1 " => T1, "t 2" => T2).
        $this->assertSame('T1', $multiple->unidad);
        $this->assertSame('T2', $curso->bancoPreguntasCurso()->where('tipo', 'verdadero_falso')->sole()->unidad);
        $this->assertSame(['Verdadero', 'Falso'], $curso->bancoPreguntasCurso()->where('tipo', 'verdadero_falso')->sole()->opciones);
    }

    public function test_archivos_mal_formados_o_fuera_de_limite_se_rechazan_completos(): void
    {
        $escenario = $this->escenario();
        $componente = $this->componente($escenario)->call('abrirImportacion');

        $componente->set('archivoImportacion', UploadedFile::fake()->createWithContent('roto.xlsx', 'esto no es un zip'))
            ->assertHasErrors('archivoImportacion')
            ->assertSee('no es un libro de Excel');

        $componente->set('archivoImportacion', UploadedFile::fake()->createWithContent('preguntas.csv', "unidad,tema,enunciado\nT1,A,B\n"))
            ->assertHasErrors('archivoImportacion')
            ->assertSee('le faltan las columnas: tipo, correcta');

        // Un archivo hecho con la plantilla anterior (sin la columna unidad) pide usar la nueva.
        $componente->set('archivoImportacion', UploadedFile::fake()->createWithContent('anterior.csv', "tema,tipo,enunciado,opcion_1,opcion_2,correcta\nA,unica,B,x,y,A\n"))
            ->assertHasErrors('archivoImportacion')
            ->assertSee('Al archivo le falta la columna &quot;unidad&quot;', false)
            ->assertSee('usa la plantilla nueva');

        $componente->set('archivoImportacion', UploadedFile::fake()->createWithContent('vacio.csv', self::ENCABEZADO."\n"))
            ->assertHasErrors('archivoImportacion')
            ->assertSee('no tiene preguntas');

        $componente->set('archivoImportacion', UploadedFile::fake()->create('preguntas.pdf', 10))
            ->assertHasErrors(['archivoImportacion' => 'extensions']);

        $componente->set('archivoImportacion', UploadedFile::fake()->create('grande.csv', 2100))
            ->assertHasErrors(['archivoImportacion' => 'max']);

        $filas = array_map(fn ($i) => "T1,Tema,unica,Pregunta {$i},A,B,,,,,A,,", range(1, 501));
        $componente->set('archivoImportacion', $this->csv($filas, ','))
            ->assertHasErrors('archivoImportacion')
            ->assertSee('tiene 501 filas con datos; el máximo es 500');

        $this->assertSame(0, $escenario['curso']->bancoPreguntasCurso()->count());
    }

    public function test_el_contenido_importado_se_guarda_como_texto_y_se_muestra_escapado(): void
    {
        $escenario = $this->escenario();

        $this->componente($escenario)
            ->call('abrirImportacion')
            ->set('archivoImportacion', $this->csv(['T1,Seguridad,unica,<script>alert(1)</script>,<b>Sí</b>,No,,,,,A,,<img src=x onerror=alert(2)>'], ','))
            ->call('importar')
            ->assertSet('importadas', 1)
            ->call('cerrarImportacion')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<b>Sí</b>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $pregunta = $escenario['curso']->bancoPreguntasCurso()->sole();
        $this->assertSame('<script>alert(1)</script>', $pregunta->pregunta);
        $this->assertSame('<img src=x onerror=alert(2)>', $pregunta->explicacion);
    }

    public function test_un_csv_en_windows_1252_se_lee_sin_perder_tildes(): void
    {
        $escenario = $this->escenario();
        $contenido = mb_convert_encoding(self::ENCABEZADO."\nT1,Álgebra,única,¿Qué es un número?,Uno,Dos,,,,,A,,", 'Windows-1252', 'UTF-8');

        $this->componente($escenario)->call('abrirImportacion')
            ->set('archivoImportacion', UploadedFile::fake()->createWithContent('ansi.csv', $contenido))
            ->call('importar')
            ->assertSet('importadas', 1);

        $this->assertSame('¿Qué es un número?', $escenario['curso']->bancoPreguntasCurso()->sole()->pregunta);
    }

    public function test_filtros_contador_y_usos_en_quizzes(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $usada = $curso->bancoPreguntasCurso()->create(['unidad' => 'T1', 'tema' => 'Álgebra', 'pregunta' => '¿Cuánto es 3 + 3?', 'tipo' => 'opcion_unica', 'opciones' => ['5', '6'], 'respuestas_correctas' => [1]]);
        $curso->bancoPreguntasCurso()->create(['unidad' => 'T1', 'tema' => 'Lógica', 'pregunta' => 'Proposición falsa', 'tipo' => 'verdadero_falso', 'opciones' => ['Verdadero', 'Falso'], 'respuestas_correctas' => [1]]);

        // Agregarla a un quiz la COPIA dentro del quiz.
        Livewire::actingAs($escenario['profesor'])->test(FormularioQuiz::class, ['curso' => $curso, 'quiz' => $escenario['quiz']])
            ->set('seleccionBanco', [$usada->id])
            ->call('agregarDesdeBanco')
            ->call('guardar')
            ->assertHasNoErrors();
        $this->assertContains('¿Cuánto es 3 + 3?', array_column($escenario['quiz']->fresh()->preguntas, 'enunciado'));

        $componente = $this->componente($escenario)
            ->assertSeeText('2 preguntas en la unidad actual')
            ->assertSee('Usada en 1 quiz')
            ->assertSee('Sin usar en quizzes')
            ->set('filtroTipo', 'verdadero_falso')
            ->assertSee('Proposición falsa')
            ->assertDontSee('¿Cuánto es 3 + 3?')
            ->assertSee('de 2 preguntas coinciden')
            ->set('filtroTipo', '')
            ->set('filtroTema', 'Álgebra')
            ->assertSee('¿Cuánto es 3 + 3?')
            ->assertDontSee('Proposición falsa');

        // Editar avisa que los quizzes conservan su copia y no cambian.
        $componente->call('editar', $usada->id)
            ->assertSee('Esta pregunta se usa en 1 quiz')
            ->set('pregunta', '¿Cuánto es 3 + 4?')
            ->set('correctas', [0])
            ->call('guardar')
            ->assertHasNoErrors();
        $this->assertContains('¿Cuánto es 3 + 3?', array_column($escenario['quiz']->fresh()->preguntas, 'enunciado'));
    }

    public function test_muestra_la_unidad_actual_y_las_archivadas_agrupadas_a_pedido(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $this->pregunta($curso, 'Pregunta de T1', 'T1', 'Conjuntos');
        $this->pregunta($curso, 'Pregunta de T2', 'T2', 'Lógica');
        $this->pregunta($curso, 'Pregunta de T3', 'T3', 'Funciones');
        $this->pregunta($curso, 'Pregunta antigua', null, 'Repaso');

        $componente = $this->componente($escenario)
            // Sin chips; por defecto la primera unidad del curso y las sin unidad, con aviso para reasignarlas.
            ->assertSet('unidadActual', 'T1')
            ->assertDontSee('Filtrar por unidad')
            ->assertSee('Pregunta de T1')
            ->assertSee('Pregunta antigua')
            ->assertSee('Hay 1 pregunta sin unidad')
            ->assertDontSee('Pregunta de T2')
            ->set('unidadActual', 'T3')
            ->assertSee('Pregunta de T3')
            ->assertDontSee('Pregunta de T1')
            ->assertDontSee('Pregunta de T2')
            ->assertViewHas('temas', fn ($temas) => $temas->all() === ['Funciones', 'Repaso'])
            // "Ver unidades archivadas": T2 y T1 agrupadas (la más reciente primero), sin unidad al final.
            ->set('verArchivadas', true)
            ->assertSeeInOrder(['Unidad T3', 'Pregunta de T3', 'Unidad T2', 'Pregunta de T2', 'Unidad T1', 'Pregunta de T1', 'Pregunta antigua'])
            // Una unidad que no es del curso no se acepta.
            ->set('unidadActual', 'T9')
            ->assertSet('unidadActual', 'T1');

        $this->assertSame(4, $curso->bancoPreguntasCurso()->count());
        $this->assertStringContainsString('lms.banco-preguntas.unidad-actual.'.$curso->id, $componente->html());
    }

    public function test_la_unidad_es_obligatoria_y_solo_admite_t1_a_t4(): void
    {
        $escenario = $this->escenario();

        $componente = $this->componente($escenario)
            ->call('nueva')
            // Se propone la unidad actual.
            ->assertSet('unidad', 'T1')
            ->set('unidad', '')
            ->set('tema', 'Conjuntos')
            ->set('pregunta', '¿Cuántos elementos tiene {a, b}?')
            ->set('opciones', ['1', '2'])
            ->set('correctas', [1])
            ->call('guardar')
            ->assertHasErrors(['unidad' => 'required'])
            ->set('unidad', 'T5')
            ->call('guardar')
            ->assertHasErrors(['unidad' => 'in'])
            ->set('unidad', 'T1')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('T1', $escenario['curso']->bancoPreguntasCurso()->sole()->unidad);
    }

    public function test_eliminar_en_lote_pide_confirmacion_y_solo_afecta_al_curso(): void
    {
        $escenario = $this->escenario();
        $otro = $this->escenario();
        $propias = collect(range(1, 3))->map(fn ($i) => $this->pregunta($escenario['curso'], "Propia {$i}"));
        $ajena = $this->pregunta($otro['curso'], 'Ajena');

        $this->componente($escenario)
            ->set('seleccionadas', [$propias[0]->id, $propias[1]->id, $ajena->id])
            ->call('confirmarEliminarSeleccionadas')
            ->assertSet('mostrarConfirmacionLote', true)
            // La ajena no cuenta: solo las del curso que están a la vista.
            ->assertSee('Se eliminarán 2 preguntas')
            ->call('eliminarSeleccionadas')
            ->assertSet('seleccionadas', []);

        $this->assertSame([$propias[2]->id], $escenario['curso']->bancoPreguntasCurso()->pluck('id')->all());
        $this->assertNotNull($ajena->fresh());
    }

    public function test_solo_el_profesor_del_curso_usa_el_banco(): void
    {
        $escenario = $this->escenario();
        $otroProfesor = $this->usuarioConRol(Rol::Profesor->value);

        foreach ([$otroProfesor, $escenario['estudiante'], $escenario['supervisor']] as $usuario) {
            Livewire::actingAs($usuario)->test(BancoPreguntas::class, ['curso' => $escenario['curso']])->assertForbidden();
            $this->actingAs($usuario)->get(route('cursos.banco-preguntas', $escenario['curso']))->assertForbidden();
        }

        $this->actingAs($escenario['profesor'])->get(route('cursos.banco-preguntas', $escenario['curso']))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $escenario
     */
    private function componente(array $escenario): Testable
    {
        return Livewire::actingAs($escenario['profesor'])->test(BancoPreguntas::class, ['curso' => $escenario['curso']]);
    }

    /**
     * @param  list<string>  $filas
     */
    private function csv(array $filas, string $separador): UploadedFile
    {
        $encabezado = str_replace(',', $separador, self::ENCABEZADO);

        return UploadedFile::fake()->createWithContent('preguntas.csv', "\xEF\xBB\xBF".$encabezado."\n".implode("\n", $filas)."\n");
    }

    private function pregunta(Curso $curso, string $texto, ?string $unidad = 'T1', string $tema = 'Tema'): BancoPregunta
    {
        return $curso->bancoPreguntasCurso()->create(['unidad' => $unidad, 'tema' => $tema, 'pregunta' => $texto, 'tipo' => 'opcion_unica', 'opciones' => ['A', 'B'], 'respuestas_correctas' => [0]]);
    }
}
