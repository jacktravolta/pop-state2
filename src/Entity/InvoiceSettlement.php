<?php
namespace App\Entity;

use App\Repository\InvoiceSettlementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceSettlementRepository::class)]
#[ORM\Table(name: 'invoice_settlement')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_settlement', columns: ['invoice_id', 'settlement_id'])]
class InvoiceSettlement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'invoiceSettlements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Settlement $settlement = null;

    public function getId(): ?int { return $this->id; }

    public function getInvoice(): ?Invoice { return $this->invoice; }
    public function setInvoice(?Invoice $invoice): static { $this->invoice = $invoice; return $this; }

    public function getSettlement(): ?Settlement { return $this->settlement; }
    public function setSettlement(?Settlement $settlement): static { $this->settlement = $settlement; return $this; }
}
