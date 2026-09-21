<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $folio = null;

    #[ORM\Column(length: 255)]
    private ?string $emisor = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $receptor = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $periodo = null;

    #[ORM\Column]
    private ?float $total = null;

    #[ORM\Column(length: 20)]
    private ?string $estado = 'PENDIENTE';

    // --- NUEVO REQ #2 y #5: auditoría para ANULADA (no rompe down -v) ---
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacion = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?\App\Entity\User $createdBy = null;

    /** @var Collection<int, InvoiceSettlement> */
    #[ORM\OneToMany(targetEntity: InvoiceSettlement::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $invoiceSettlements;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->invoiceSettlements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getFolio(): ?string { return $this->folio; }
    public function setFolio(string $folio): static { $this->folio = $folio; return $this; }
    public function getEmisor(): ?string { return $this->emisor; }
    public function setEmisor(string $emisor): static { $this->emisor = $emisor; return $this; }
    public function getReceptor(): ?string { return $this->receptor; }
    public function setReceptor(?string $receptor): static { $this->receptor = $receptor; return $this; }
    public function getPeriodo(): ?string { return $this->periodo; }
    public function setPeriodo(?string $periodo): static { $this->periodo = $periodo; return $this; }
    public function getTotal(): ?float { return $this->total; }
    public function setTotal(float $total): static { $this->total = $total; return $this; }
    public function getEstado(): ?string { return $this->estado; }
    public function setEstado(string $estado): static { $this->estado = strtoupper($estado); return $this; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function setObservacion(?string $obs): static { $this->observacion = $obs; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $dt): static { $this->createdAt = $dt; return $this; }
    public function getCreatedBy(): ?\App\Entity\User { return $this->createdBy; }
    public function setCreatedBy(?\App\Entity\User $u): static { $this->createdBy = $u; return $this; }

    /** @return Collection<int, InvoiceSettlement> */
    public function getInvoiceSettlements(): Collection { return $this->invoiceSettlements; }

    public function addInvoiceSettlement(InvoiceSettlement $is): static
    {
        if (!$this->invoiceSettlements->contains($is)) {
            $this->invoiceSettlements->add($is);
            $is->setInvoice($this);
        }

        return $this;
    }
}
