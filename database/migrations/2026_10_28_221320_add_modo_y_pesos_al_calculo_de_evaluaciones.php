<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasa del cálculo automático sí/no a tres modos (manual, promedio, examen)
 * con peso por actividad. Conserva lo ya configurado: las evaluaciones
 * automáticas quedan en «promedio» con sus actividades repartidas en partes
 * iguales (el mismo resultado que el promedio simple anterior).
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PIVOTES = ['rubro_tarea' => 'tarea_id', 'rubro_quiz' => 'quiz_id'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rubros_evaluacion', function (Blueprint $table) {
            // null = el profesor todavía no lo configuró (se comporta como nota manual).
            $table->string('modo_calculo', 20)->nullable();
        });
        DB::table('rubros_evaluacion')->where('calculo_automatico', true)->update(['modo_calculo' => 'promedio']);
        Schema::table('rubros_evaluacion', function (Blueprint $table) {
            $table->dropColumn('calculo_automatico');
        });

        foreach (self::PIVOTES as $tabla => $columna) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->decimal('peso', 5, 2)->default(100);
            });

            // Una actividad cuenta para una sola evaluación: si estaba en varias, se queda en la primera.
            DB::table($tabla)->orderBy('rubro_id')->get()->groupBy($columna)
                ->each(fn ($vinculos) => $vinculos->skip(1)->each(fn ($vinculo) => DB::table($tabla)
                    ->where('rubro_id', $vinculo->rubro_id)->where($columna, $vinculo->{$columna})->delete()));
        }

        $this->repartirPesosEnPartesIguales();

        foreach (self::PIVOTES as $tabla => $columna) {
            Schema::table($tabla, function (Blueprint $table) use ($columna) {
                $table->unique($columna);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::PIVOTES as $tabla => $columna) {
            Schema::table($tabla, function (Blueprint $table) use ($columna) {
                $table->dropUnique([$columna]);
                $table->dropColumn('peso');
            });
        }

        Schema::table('rubros_evaluacion', function (Blueprint $table) {
            $table->boolean('calculo_automatico')->default(false);
        });
        DB::table('rubros_evaluacion')->whereIn('modo_calculo', ['promedio', 'examen'])->update(['calculo_automatico' => true]);
        Schema::table('rubros_evaluacion', function (Blueprint $table) {
            $table->dropColumn('modo_calculo');
        });
    }

    private function repartirPesosEnPartesIguales(): void
    {
        $vinculos = collect(self::PIVOTES)->flatMap(fn (string $columna, string $tabla) => DB::table($tabla)->get()
            ->map(fn ($vinculo) => ['tabla' => $tabla, 'columna' => $columna, 'rubro_id' => $vinculo->rubro_id, 'actividad_id' => $vinculo->{$columna}]));

        foreach ($vinculos->groupBy('rubro_id') as $delRubro) {
            $base = floor(10000 / $delRubro->count()) / 100;
            foreach ($delRubro->values() as $indice => $vinculo) {
                $peso = $indice === $delRubro->count() - 1 ? round(100 - $base * ($delRubro->count() - 1), 2) : $base;
                DB::table($vinculo['tabla'])->where('rubro_id', $vinculo['rubro_id'])
                    ->where($vinculo['columna'], $vinculo['actividad_id'])->update(['peso' => $peso]);
            }
        }
    }
};
