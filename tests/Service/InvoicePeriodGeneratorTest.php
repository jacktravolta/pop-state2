<?php

namespace App\Tests\Service;

use App\Entity\Property;
use App\Entity\Settlement;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Repository\SettlementRepository;
use App\Service\InvoicePeriodGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class InvoicePeriodGeneratorTest extends TestCase
{
    private function qbWithResult(mixed $result, string $method = 'getResult'): QueryBuilder
    {
        $query = $this->createStub(Query::class);
        $query->method($method)->willReturn($result);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('leftJoin')->willReturn($qb);
        $qb->method('addSelect')->willReturn($qb);
        $qb->method('andWhere')->willReturn($qb);
        $qb->method('where')->willReturn($qb);
        $qb->method('setParameter')->willReturn($qb);
        $qb->method('orderBy')->willReturn($qb);
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    private function settlement(float $total, int $propertyId, string $direccion): Settlement
    {
        $prop = new Property();
        // Property::getId() es null sin persistir; usamos reflexión solo para agrupar.
        $ref = new \ReflectionProperty(Property::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($prop, $propertyId);
        $prop->setDireccion($direccion);

        $s = new Settlement();
        $s->setProperty($prop);
        $s->setFechaInicio(new \DateTime('2025-12-01'));
        $s->setFechaTermino(new \DateTime('2025-12-31'));
        $s->setEstado('PAGADA');
        $s->setTotal((string) $total);

        return $s;
    }

    public function testPreviewSumsTotalsAndBreakdown(): void
    {
        $s1 = $this->settlement(100000, 1, 'Dir A');
        $s2 = $this->settlement(200000, 2, 'Dir B');

        $settlementRepo = $this->createStub(SettlementRepository::class);
        $settlementRepo->method('createQueryBuilder')->willReturn($this->qbWithResult([$s1, $s2]));

        $gen = new InvoicePeriodGenerator(
            $settlementRepo,
            $this->createStub(InvoiceRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        $prev = $gen->preview(new \DateTime('2025-12-01'), new \DateTime('2025-12-31'));

        $this->assertSame(2, $prev['count']);
        $this->assertEquals(300000, $prev['total']);
        $this->assertEquals(['Dir A' => 100000.0, 'Dir B' => 200000.0], $prev['breakdown']);
    }

    public function testGenerateThrowsWhenEmpty(): void
    {
        $settlementRepo = $this->createStub(SettlementRepository::class);
        $settlementRepo->method('createQueryBuilder')->willReturn($this->qbWithResult([]));

        $gen = new InvoicePeriodGenerator(
            $settlementRepo,
            $this->createStub(InvoiceRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $gen->generate(new \DateTime('2025-12-01'), new \DateTime('2025-12-31'), null, 'Emisor', null, new User());
    }

    public function testGenerateGroupsOneInvoicePerProperty(): void
    {
        $s1 = $this->settlement(100000, 1, 'Dir A');
        $s2 = $this->settlement(50000, 1, 'Dir A');
        $s3 = $this->settlement(200000, 2, 'Dir B');

        $settlementRepo = $this->createStub(SettlementRepository::class);
        $settlementRepo->method('createQueryBuilder')->willReturn($this->qbWithResult([$s1, $s2, $s3]));

        $invoiceRepo = $this->createStub(InvoiceRepository::class);
        $invoiceRepo->method('createQueryBuilder')->willReturn($this->qbWithResult(null, 'getOneOrNullResult'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $gen = new InvoicePeriodGenerator($settlementRepo, $invoiceRepo, $em);
        $invoices = $gen->generate(new \DateTime('2025-12-01'), new \DateTime('2025-12-31'), null, 'Pop Estate', null, new User());

        $this->assertCount(2, $invoices);
        $totals = array_map(fn ($i) => $i->getTotal(), $invoices);
        sort($totals);
        $this->assertEquals([150000.0, 200000.0], $totals);
    }
}
