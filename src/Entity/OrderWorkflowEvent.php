<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order_workflow_event')]
class OrderWorkflowEvent
{
	use IdentifierUnsigned;

	#[ORM\ManyToOne(targetEntity: OrderStatus::class)]
	private OrderStatus $status;

	#[ORM\Column(type: 'string', length: 64)]
	private string $label;

	#[ORM\ManyToOne(targetEntity: OrderStatus::class)]
	private ?OrderStatus $newStatus = null;

	#[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
	private ?int $automaticInterval = null;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private ?string $emailTemplate = null;

	#[ORM\Column(type: 'boolean')]
	private bool $active = false;

	#[ORM\Column(type: 'boolean')]
	private bool $ignoreIfPinged = false;

	#[ORM\Column(type: 'boolean')]
	private bool $markAsPinged = false;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $activeFrom;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTimeInterface $activeTo = null;

	#[ORM\Column(type: 'integer', options: ['unsigned' => true])]
	private int $priority = 0;


	public function __construct(OrderStatus $status, string $label)
	{
		$this->status = $status;
		$this->label = $label;
		$this->insertedDate = new \DateTimeImmutable;
		$this->activeFrom = new \DateTimeImmutable;
	}


	public function getStatus(): OrderStatus
	{
		return $this->status;
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	public function setLabel(string $label): void
	{
		$this->label = $label;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}


	public function getNewStatus(): ?OrderStatus
	{
		return $this->newStatus;
	}


	public function setNewStatus(?OrderStatus $newStatus): void
	{
		$this->newStatus = $newStatus;
	}


	public function getAutomaticInterval(): ?int
	{
		return $this->automaticInterval;
	}


	public function setAutomaticInterval(?int $automaticInterval): void
	{
		if ($automaticInterval !== null && $automaticInterval < 1) {
			$automaticInterval = null;
		}
		$this->automaticInterval = $automaticInterval;
	}


	public function getEmailTemplate(): ?string
	{
		return $this->emailTemplate;
	}


	public function setEmailTemplate(?string $emailTemplate): void
	{
		$this->emailTemplate = $emailTemplate;
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active): void
	{
		$this->active = $active;
	}


	public function isIgnoreIfPinged(): bool
	{
		return $this->ignoreIfPinged;
	}


	public function setIgnoreIfPinged(bool $ignoreIfPinged): void
	{
		$this->ignoreIfPinged = $ignoreIfPinged;
	}


	public function isMarkAsPinged(): bool
	{
		return $this->markAsPinged;
	}


	public function setMarkAsPinged(bool $markAsPinged): void
	{
		$this->markAsPinged = $markAsPinged;
	}


	public function getActiveFrom(): \DateTimeInterface
	{
		return $this->activeFrom;
	}


	public function setActiveFrom(\DateTimeInterface $activeFrom): void
	{
		$this->activeFrom = $activeFrom;
	}


	public function getActiveTo(): ?\DateTimeInterface
	{
		return $this->activeTo;
	}


	public function setActiveTo(?\DateTimeInterface $activeTo): void
	{
		$this->activeTo = $activeTo;
	}


	public function getPriority(): int
	{
		return $this->priority;
	}


	public function setPriority(int $priority): void
	{
		if ($priority < 0) {
			$priority = 0;
		}
		$this->priority = $priority;
	}
}
