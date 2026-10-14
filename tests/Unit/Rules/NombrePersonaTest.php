<?php

namespace Tests\Unit\Rules;

use App\Rules\NombrePersona;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NombrePersonaTest extends TestCase
{
    protected function errorPara(mixed $valor): ?string
    {
        $error = null;

        (new NombrePersona)->validate('nombres', $valor, function (string $mensaje) use (&$error) {
            $error = $mensaje;
        });

        return $error;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nombresValidos(): array
    {
        return [
            'nombre compuesto' => ['María José'],
            'con apóstrofe' => ["O'Brien"],
            'con guion' => ['Pérez-Salinas'],
            'con ñ' => ['Peña Muñoz'],
            'tres palabras' => ['De la Cruz'],
            'espacios de sobra' => ['  Ana    Lucía  '],
            'dos letras' => ['Li'],
        ];
    }

    #[DataProvider('nombresValidos')]
    public function test_acepta_nombres_reales(string $nombre): void
    {
        $this->assertNull($this->errorPara($nombre));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nombresConCaracteresNoPermitidos(): array
    {
        return [
            'solo números' => ['12345'],
            'letras y número' => ['Ana2'],
            'etiqueta html' => ['<b>Ana</b>'],
            'arroba' => ['Ana@'],
            'punto' => ['Dra. María'],
            'guiones seguidos' => ['Ana--Luz'],
            'empieza con guion' => ['-Ana'],
            'vacío' => [''],
            'no es texto' => [['Ana']],
        ];
    }

    #[DataProvider('nombresConCaracteresNoPermitidos')]
    public function test_rechaza_numeros_y_simbolos(mixed $nombre): void
    {
        $this->assertSame(
            "Los :attribute solo pueden tener letras y espacios (también se permite guion y apóstrofe, como en Pérez-Salinas u O'Brien).",
            $this->errorPara($nombre),
        );
    }

    public function test_rechaza_una_sola_letra(): void
    {
        $this->assertSame('Los :attribute deben tener entre 2 y 60 caracteres.', $this->errorPara('A'));
    }

    public function test_rechaza_mas_de_60_caracteres(): void
    {
        $this->assertSame('Los :attribute deben tener entre 2 y 60 caracteres.', $this->errorPara(str_repeat('Ana ', 15).'Luz'));
    }
}
