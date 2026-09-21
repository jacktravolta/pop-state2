<?php
namespace App\Tests\Service;

use App\Service\RutService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RutServiceTest extends TestCase
{
    private RutService $service;

    protected function setUp(): void
    {
        $this->service = new RutService();
    }

    #[DataProvider('validRutsProvider')]
    public function testValidateAcceptsValidRuts(string $rut): void
    {
        $this->assertTrue($this->service->validate($rut), "RUT '$rut' debería ser válido");
    }

    public static function validRutsProvider(): array
    {
        return [
            'RUT con DV K' => ['12.345.670-K'],
            'RUT con DV 1' => ['11.111.111-1'],
            'RUT sin formato' => ['12345670K'],
            'RUT con guion' => ['12345670-K'],
            'RUT conocido válido' => ['12.345.678-5'],
        ];
    }

    #[DataProvider('invalidRutsProvider')]
    public function testValidateRejectsInvalidRuts(string $rut): void
    {
        $this->assertFalse($this->service->validate($rut), "RUT '$rut' debería ser inválido");
    }

    public static function invalidRutsProvider(): array
    {
        return [
            'DV incorrecto' => ['12.345.678-0'],
            'Muy corto' => ['123-4'],
            'Letras en cuerpo' => ['AB123456-7'],
            'Vacío' => [''],
            'Solo guion' => ['-'],
        ];
    }

    public function testFormatStandardizesRut(): void
    {
        $this->assertEquals('12.345.670-K', $this->service->format('12345670K'));
        $this->assertEquals('11.111.111-1', $this->service->format('111111111'));
        $this->assertEquals('12.345.678-5', $this->service->format('123456785'));
    }

    public function testCleanRemovesFormatting(): void
    {
        $this->assertEquals('12345670K', $this->service->clean('12.345.670-K'));
        $this->assertEquals('111111111', $this->service->clean('11.111.111-1'));
    }

    public function testCalculateDvReturnsCorrectDigit(): void
    {
        $this->assertEquals('K', $this->service->calculateDv('12345670'));
        $this->assertEquals('1', $this->service->calculateDv('11111111'));
        $this->assertEquals('5', $this->service->calculateDv('12345678'));
    }
}