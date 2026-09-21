<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceSettlement;
use App\Entity\Settlement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fachada de facturación sobre el esquema real de `invoice`.
 *
 * La tabla `invoice` solo tiene folio/emisor/receptor/periodo/total/estado:
 * neto e IVA se calculan en memoria y no se persisten (Spec 8).
 * La trazabilidad con liquidaciones usa el pivote `invoice_settlement` (Spec 9).
 */
class BillingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InvoicePeriodGenerator $generator,
        private float $ivaRate = 0.19,
    ) {
    }

    /**
     * @return array{periodo:string, liquidaciones_count:int, liquidaciones:Settlement[], items:array, neto:float, iva:float, total:float, ya_facturado:bool}
     */
    public function preview(string $periodo, ?int $companyId = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new \InvalidArgumentException('Período debe ser YYYY-MM');
        }
        [$year, $month] = explode('-', $periodo);
        $inicio = new \DateTimeImmutable(sprintf('%04d-%02d-01', (int) $year, (int) $month));
        $fin = $inicio->modify('last day of this month');

        $qb = $this->em->createQueryBuilder()
            ->select('s', 'p', 'o', 'c')
            ->from(Settlement::class, 's')
            ->join('s.property', 'p')
            ->join('p.owner', 'o')
            ->join('p.company', 'c')
            ->leftJoin(InvoiceSettlement::class, 'invSet', 'WITH', 'invSet.settlement = s')
            ->leftJoin('invSet.invoice', 'i')
            ->where('s.estado = :estado')
            ->andWhere('s.fechaInicio >= :inicio AND s.fechaTermino <= :fin')
            ->andWhere('i.id IS NULL')
            ->setParameter('estado', 'PAGADA')
            ->setParameter('inicio', $inicio->format('Y-m-d'))
            ->setParameter('fin', $fin->format('Y-m-d'));
        if ($companyId) {
            $qb->andWhere('c.id = :cid')->setParameter('cid', $companyId);
        }
        /** @var Settlement[] $settlements */
        $settlements = $qb->getQuery()->getResult();

        $neto = 0.0;
        $items = [];
        foreach ($settlements as $s) {
            $neto += (float) $s->getTotalNeto();
            foreach ($s->getItems() as $it) {
                $items[] = [
                    'settlement_id' => $s->getId(),
                    'direccion' => $s->getProperty()->getDireccion(),
                    'descripcion' => $it->getDescripcion(),
                    'tipo' => $it->getTipo(),
                    'monto' => (float) $it->getMonto(),
                ];
            }
        }
        $iva = round($neto * $this->ivaRate, 2);

        return [
            'periodo' => $periodo,
            'liquidaciones_count' => \count($settlements),
            'liquidaciones' => $settlements,
            'items' => $items,
            'neto' => $neto,
            'iva' => $iva,
            'total' => $neto + $iva,
            'ya_facturado' => $this->isPeriodoFacturado($periodo),
        ];
    }

    public function isPeriodoFacturado(string $periodo): bool
    {
        return (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM invoice WHERE periodo = :p AND estado IN ('EMITIDA','PAGADA')",
            ['p' => $periodo],
        ) > 0;
    }

    /**
     * @return array{invoices:Invoice[], archivo_plano:string, preview:array}
     */
    public function createInvoice(string $periodo, string $emisor, ?string $receptor, User $user, ?int $companyId = null, ?int $propertyId = null): array
    {
        $prev = $this->preview($periodo, $companyId);
        if ($prev['ya_facturado']) {
            throw new \LogicException("Período $periodo ya facturado");
        }
        if ($prev['liquidaciones_count'] === 0) {
            throw new \LogicException("No hay liquidaciones PAGADAS sin facturar para $periodo");
        }

        [$year, $month] = explode('-', $periodo);
        $inicio = new \DateTime(sprintf('%04d-%02d-01', (int) $year, (int) $month));
        $fin = (clone $inicio)->modify('last day of this month');

        $invoices = $this->generator->generate($inicio, $fin, $propertyId, $emisor, $receptor, $user);

        foreach ($invoices as $invoice) {
            $invoice->setPeriodo($periodo);
            if ($invoice->getEstado() === 'PENDIENTE') {
                $invoice->setEstado('EMITIDA');
            }
        }

        // Vincula el pivote invoice_settlement para las liquidaciones del preview.
        $byProperty = [];
        foreach ($prev['liquidaciones'] as $s) {
            $byProperty[$s->getProperty()->getId()][] = $s;
        }
        foreach ($invoices as $invoice) {
            $setts = $prev['liquidaciones'];
            if (\count($invoices) > 1) {
                $setts = $byProperty[$this->guessPropertyId($invoice, $byProperty)] ?? [];
            }
            foreach ($setts as $s) {
                $pivot = new InvoiceSettlement();
                $pivot->setSettlement($s);
                $invoice->addInvoiceSettlement($pivot);
            }
        }
        $this->em->flush();

        return [
            'invoices' => $invoices,
            'archivo_plano' => $this->buildArchivoPlano($invoices[0], $prev),
            'preview' => $prev,
        ];
    }

    public function buildArchivoPlano(Invoice $inv, array $prev): string
    {
        if (empty($prev['liquidaciones'])) {
            return '';
        }
        $owner = $prev['liquidaciones'][0]->getProperty()->getOwner();
        $san = static fn (?string $t): string => str_replace([';', "\n", "\r"], [' ', ' ', ' '], trim((string) $t));
        $lines = [];
        $lines[] = sprintf(
            '->Encabezado<- 33;%s;%s;0;0;%s;%s;%s;%s;%s;%s;%s;',
            $san((string) $inv->getFolio()),
            $inv->getCreatedAt()?->format('Y-m-d') ?? date('Y-m-d'),
            $san($owner->getRut()),
            $san($owner->getNombre()),
            $san($owner->getGiro() ?? 'Arriendo'),
            $san($owner->getDireccion() ?? ''),
            $san($owner->getComuna() ?? ''),
            $san($owner->getCiudad() ?? ''),
            $owner->getEmail() ?? '',
        );
        $lines[] = sprintf(
            '->Totales<- 0;0;0;0;%d;0;%d;%d;%d;',
            (int) $prev['neto'],
            (int) ($this->ivaRate * 100),
            (int) $prev['iva'],
            (int) $prev['total'],
        );
        $n = 1;
        foreach ($prev['items'] as $it) {
            $v = $it['tipo'] === 'DESCUENTO' ? -abs($it['monto']) : abs($it['monto']);
            $d = sprintf('%s - %s [%s]', $it['direccion'], $it['descripcion'], $it['tipo']);
            $lines[] = sprintf(
                '->Detalle<- %d;%s;%s;1;%d;0;0;0;0;0;%d;INT1;UN;;',
                $n,
                'LIQ-'.$it['settlement_id'],
                $san($d),
                (int) abs($v),
                (int) $v,
            );
            if (++$n > 60) {
                break;
            }
        }

        return implode("\n", $lines)."\n";
    }

    /** @param array<int, Settlement[]> $byProperty */
    private function guessPropertyId(Invoice $invoice, array $byProperty): int
    {
        foreach ($byProperty as $pid => $setts) {
            foreach ($setts as $s) {
                if ($s->getProperty()->getDireccion() === $invoice->getReceptor()) {
                    return $pid;
                }
            }
        }

        return (int) array_key_first($byProperty);
    }
}
