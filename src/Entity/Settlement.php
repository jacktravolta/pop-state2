<?php

namespace App\Entity;

use App\Repository\SettlementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SettlementRepository::class)]
#[UniqueEntity(fields: ['property', 'fechaInicio', 'fechaTermino'], message: 'Ya existe una liquidación para esta propiedad en este período')]
class Settlement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La propiedad es obligatoria')]
    private ?Property $property = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La fecha de inicio es obligatoria')]
    private ?\DateTimeInterface $fechaInicio = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La fecha de término es obligatoria')]
    private ?\DateTimeInterface $fechaTermino = null;

    #[ORM\Column(length: 20)]
    private string $estado = 'PENDIENTE';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observacion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motivoAnulacion = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $totalCargo = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $totalDescuento = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $totalNeto = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $iva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $total = '0.00';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    /** @var Collection<int, SettlementItem> */
    #[ORM\OneToMany(targetEntity: SettlementItem::class, mappedBy: 'settlement', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getProperty(): ?Property { return $this->property; }
    public function setProperty(?Property $property): static { $this->property = $property; return $this; }
    public function getFechaInicio(): ?\DateTimeInterface { return $this->fechaInicio; }
    public function setFechaInicio(\DateTimeInterface $fechaInicio): static { $this->fechaInicio = $fechaInicio; return $this; }
    public function getFechaTermino(): ?\DateTimeInterface { return $this->fechaTermino; }
    public function setFechaTermino(\DateTimeInterface $fechaTermino): static { $this->fechaTermino = $fechaTermino; return $this; }
    public function getEstado(): string { return $this->estado; }
    public function setEstado(string $estado): static { $this->estado = $estado; return $this; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function setObservacion(?string $observacion): static { $this->observacion = $observacion; return $this; }
    public function getMotivoAnulacion(): ?string { return $this->motivoAnulacion; }
    public function setMotivoAnulacion(?string $motivoAnulacion): static { $this->motivoAnulacion = $motivoAnulacion; return $this; }
    public function getTotalCargo(): string { return $this->totalCargo; }
    public function setTotalCargo(string $totalCargo): static { $this->totalCargo = $totalCargo; return $this; }
    public function getTotalDescuento(): string { return $this->totalDescuento; }
    public function setTotalDescuento(string $totalDescuento): static { $this->totalDescuento = $totalDescuento; return $this; }
    public function getTotalNeto(): string { return $this->totalNeto; }
    public function setTotalNeto(string $totalNeto): static { $this->totalNeto = $totalNeto; return $this; }
    public function getIva(): string { return $this->iva; }
    public function setIva(string $iva): static { $this->iva = $iva; return $this; }
    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): static { $this->total = $total; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): static { $this->createdBy = $createdBy; return $this; }
    public function getItems(): Collection { return $this->items; }
    public function addItem(SettlementItem $item): static {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setSettlement($this);
        }
        return $this;
    }
    public function removeItem(SettlementItem $item): static {
        if ($this->items->removeElement($item)) {
            if ($item->getSettlement() === $this) {
                $item->setSettlement(null);
            }
        }
        return $this;
    }
}
