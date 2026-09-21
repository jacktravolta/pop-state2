<?php

namespace App\Service;

use App\Entity\Settlement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SettlementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private float $ivaRate = 0.19,
    ) {
    }

    public function recalculate(Settlement $s): void
    {
        $cargo = 0.0;
        $desc = 0.0;
        foreach ($s->getItems() as $it) {
            $m = abs((float) $it->getMonto());
            if ($it->getTipo() === 'DESCUENTO') {
                $desc += $m;
            } else {
                $cargo += $m;
            }
        }
        $neto = $cargo - $desc;
        if ($neto < 0) {
            throw new \InvalidArgumentException('El neto no puede ser negativo: cargos insuficientes para los descuentos');
        }
        $iva = round($neto * $this->ivaRate, 2);
        $total = $neto + $iva;
        $fmt = static fn (float $v): string => number_format($v, 2, '.', '');
        $s->setTotalCargo($fmt($cargo));
        $s->setTotalDescuento($fmt($desc));
        $s->setTotalNeto($fmt($neto));
        $s->setIva($fmt($iva));
        $s->setTotal($fmt($total));
    }

    /** Alias histórico usado por SettlementController. */
    public function recalcular(Settlement $s): void
    {
        $this->recalculate($s);
    }

    public function save(Settlement $s): void
    {
        if (!$s->getProperty() || !$s->getFechaInicio() || !$s->getFechaTermino()) {
            throw new \InvalidArgumentException('Propiedad y fechas son obligatorias');
        }
        $dup = $this->em->getRepository(Settlement::class)->findDuplicate(
            $s->getProperty(),
            $s->getFechaInicio(),
            $s->getFechaTermino(),
            $s->getId(),
        );
        if ($dup) {
            throw new \LogicException('Ya existe una liquidación para esta propiedad en este período (#'.$dup->getId().')');
        }
        if (!$s->getEstado()) {
            $s->setEstado('PENDIENTE');
        }
        $this->recalculate($s);
        $this->em->persist($s);
        $this->em->flush();
    }

    public function markAsPaid(Settlement $s): void
    {
        if ($s->getEstado() === 'ANULADA') {
            throw new \InvalidArgumentException('Una liquidación ANULADA no se puede pagar');
        }
        if ($s->getEstado() === 'PAGADA') {
            throw new \LogicException('La liquidación ya está PAGADA');
        }
        $s->setEstado('PAGADA');
        $this->em->flush();
    }

    public function cancel(Settlement $s, ?string $motivo = null, ?User $user = null): void
    {
        if ($this->isInvoiced($s)) {
            throw new \LogicException('Liquidación facturada no se puede anular');
        }
        $s->setEstado('ANULADA');
        if ($motivo !== null) {
            $s->setMotivoAnulacion($this->buildAudit($motivo, $user));
            $s->setObservacion($s->getMotivoAnulacion());
        }
        $this->em->flush();
    }

    /** Alias histórico. */
    public function pagar(Settlement $s): void
    {
        $this->markAsPaid($s);
    }

    /** Alias histórico. */
    public function anular(Settlement $s, ?string $motivo = null, ?User $user = null): void
    {
        $this->cancel($s, $motivo, $user);
    }

    public function buildAudit(string $motivo, ?User $user = null): string
    {
        $motivo = trim($motivo);
        if (strlen($motivo) < 10) {
            throw new \InvalidArgumentException('El motivo de anulación debe tener al menos 10 caracteres');
        }
        $who = $user?->getEmail() ?? 'sistema';
        $when = (new \DateTimeImmutable())->format('d/m/Y H:i');

        return sprintf('%s | por %s el %s', $motivo, $who, $when);
    }

    private function isInvoiced(Settlement $s): bool
    {
        if ($s->getId() === null) {
            return false;
        }

        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM invoice_settlement WHERE settlement_id = :id',
            ['id' => $s->getId()],
        ) > 0;
    }
}
