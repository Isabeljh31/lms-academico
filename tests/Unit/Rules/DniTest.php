<?php

namespace Tests\Unit\Rules;

use App\Rules\Dni;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DniTest extends TestCase
{
    protected function errorPara(mixed $valor): ?string
    {
        $error = null;

        (new Dni)->validate('dni', $valor, function (string $mensaje) use (&$error) {
            $error = $mensaje;
        });

        return $error;
    }

    #[TestWith(['45678123'])]
    #[TestWith(['01234567'])]
    public function test_acepta_exactamente_8_digitos(string $dni): void
    {
        $this->assertNull($this->errorPara($dni));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function dnisInvalidos(): array
    {
        return [
            '7 dígitos' => ['1234567'],
            '9 dígitos' => ['123456789'],
            'con letra' => ['1234567A'],
            'con espacio intermedio' => ['1234 5678'],
            'con guion' => ['1234-5678'],
            'vacío' => [''],
            'no es texto' => [['45678123']],
        ];
    }

    #[DataProvider('dnisInvalidos')]
    public function test_rechaza_lo_que_no_son_8_digitos(mixed $dni): void
    {
        $this->assertSame('El DNI debe tener exactamente 8 dígitos (solo números).', $this->errorPara($dni));
    }
}
