<?php
namespace App\Tests\Service\AI;

use App\Service\AI\SqlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SqlGuardTest extends TestCase
{
    private SqlGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new SqlGuard();
    }

    #[DataProvider('safeSqlProvider')]
    public function testValidateAcceptsSafeSelects(string $sql): void
    {
        $this->assertTrue($this->guard->validate($sql), "SQL '$sql' debería ser seguro");
    }

    public static function safeSqlProvider(): array
    {
        return [
            'SELECT simple' => ['SELECT * FROM company'],
            'SELECT con WHERE' => ['SELECT id FROM property WHERE comuna = \'Providencia\''],
            'SELECT con COUNT' => ['SELECT COUNT(*) FROM settlement WHERE estado = \'PAGADA\''],
            'SELECT con SUM' => ['SELECT SUM(total::numeric) FROM invoice'],
            'SELECT con JOIN' => ['SELECT p.direccion FROM property p JOIN company c ON p.company_id = c.id'],
        ];
    }

    #[DataProvider('dangerousSqlProvider')]
    public function testValidateRejectsDangerousSql(string $sql): void
    {
        $this->assertFalse($this->guard->validate($sql), "SQL '$sql' debería ser bloqueado");
    }

    public static function dangerousSqlProvider(): array
    {
        return [
            'DROP TABLE' => ['DROP TABLE company'],
            'DELETE FROM' => ['DELETE FROM settlement'],
            'UPDATE' => ['UPDATE company SET rut = \'x\''],
            'INSERT' => ['INSERT INTO company VALUES (1)'],
            'ALTER' => ['ALTER TABLE company ADD COLUMN x INT'],
            'TRUNCATE' => ['TRUNCATE TABLE settlement'],
            'No empieza con SELECT' => ['SHOW TABLES'],
            'Con punto y coma' => ['SELECT * FROM company; DROP TABLE company'],
            'Con comentario --' => ['SELECT * FROM company -- comentario'],
            'Con comentario /*' => ['SELECT * FROM company /* comentario */'],
        ];
    }

    public function testEnforceLimitAddsLimitWhenMissing(): void
    {
        $sql = 'SELECT * FROM company';
        $this->assertStringContainsString('LIMIT 100', $this->guard->enforceLimit($sql));
    }

    public function testEnforceLimitPreservesExistingLimit(): void
    {
        $sql = 'SELECT * FROM company LIMIT 10';
        $this->assertEquals($sql, $this->guard->enforceLimit($sql));
    }

    public function testValidateRejectsTooLongSql(): void
    {
        $longSql = 'SELECT * FROM company WHERE ' . str_repeat('a', 1000);
        $this->assertFalse($this->guard->validate($longSql));
    }
}