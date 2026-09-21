<?php
namespace App\Tests\Service;

use App\Entity\Settlement;
use App\Entity\SettlementItem;
use App\Service\SettlementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SettlementServiceTest extends TestCase
{
    private SettlementService $service;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $this->service = new SettlementService($em, 0.19);
    }

    public function testRecalculateWithExampleFromSDD(): void
    {
        // Ejemplo del SDD: Arriendo 800k, Comisión 80k, Otros 20k
        $settlement = new Settlement();

        $item1 = new SettlementItem();
        $item1->setTipo('CARGO');
        $item1->setMonto('800000.00');
        $settlement->addItem($item1);

        $item2 = new SettlementItem();
        $item2->setTipo('DESCUENTO');
        $item2->setMonto('80000.00');
        $settlement->addItem($item2);

        $item3 = new SettlementItem();
        $item3->setTipo('DESCUENTO');
        $item3->setMonto('20000.00');
        $settlement->addItem($item3);

        $this->service->recalculate($settlement);

        $this->assertEquals('800000.00', $settlement->getTotalCargo());
        $this->assertEquals('100000.00', $settlement->getTotalDescuento());
        $this->assertEquals('700000.00', $settlement->getTotalNeto());
        $this->assertEquals('133000.00', $settlement->getIva());
        $this->assertEquals('833000.00', $settlement->getTotal());
    }

    public function testRecalculateWithOnlyCargos(): void
    {
        $settlement = new Settlement();

        $item = new SettlementItem();
        $item->setTipo('CARGO');
        $item->setMonto('500000.00');
        $settlement->addItem($item);

        $this->service->recalculate($settlement);

        $this->assertEquals('500000.00', $settlement->getTotalCargo());
        $this->assertEquals('0.00', $settlement->getTotalDescuento());
        $this->assertEquals('500000.00', $settlement->getTotalNeto());
        $this->assertEquals('95000.00', $settlement->getIva());
        $this->assertEquals('595000.00', $settlement->getTotal());
    }

    public function testRecalculateWithEmptyItems(): void
    {
        $settlement = new Settlement();
        $this->service->recalculate($settlement);

        $this->assertEquals('0.00', $settlement->getTotalCargo());
        $this->assertEquals('0.00', $settlement->getTotalDescuento());
        $this->assertEquals('0.00', $settlement->getTotalNeto());
        $this->assertEquals('0.00', $settlement->getIva());
        $this->assertEquals('0.00', $settlement->getTotal());
    }

    public function testRecalculateThrowsExceptionWhenNetoIsNegative(): void
    {
        $settlement = new Settlement();

        $cargo = new SettlementItem();
        $cargo->setTipo('CARGO');
        $cargo->setMonto('100.00');
        $settlement->addItem($cargo);

        $descuento = new SettlementItem();
        $descuento->setTipo('DESCUENTO');
        $descuento->setMonto('200.00');
        $settlement->addItem($descuento);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no puede ser negativo');

        $this->service->recalculate($settlement);
    }

    public function testMarkAsPaidChangesState(): void
    {
        $settlement = new Settlement();
        $settlement->setEstado('PENDIENTE');

        $this->service->markAsPaid($settlement);

        $this->assertEquals('PAGADA', $settlement->getEstado());
    }

    public function testMarkAsPaidThrowsExceptionWhenCancelled(): void
    {
        $settlement = new Settlement();
        $settlement->setEstado('ANULADA');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->markAsPaid($settlement);
    }

    public function testCancelChangesState(): void
    {
        $settlement = new Settlement();
        $settlement->setEstado('PENDIENTE');

        $this->service->cancel($settlement);

        $this->assertEquals('ANULADA', $settlement->getEstado());
    }
}