<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order_workflow_event')]
class OrderWorkflowEvent
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: OrderStatus::class)]
	private OrderStatus $status;

	#[ORM\Column(type: 'string', length: 64)]
	private string $label;

	#[ORM\ManyToOne(targetEntity: OrderStatus::class)]
	private ?OrderStatus $newStatus = null;

	#[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
	private ?int $automaticInterval = null;

	#[ORM\Column(type: 'boolean')]
	private bool $active = false;

	#[ORM\Column(type: 'boolean')]
	private bool $ignoreIfPinged = false;

	#[ORM\Column(type: 'boolean')]
	private bool $markAsPinged = false;

	#[ORM\Column(name: 'stop_if_match', type: 'boolean')]
	private bool $stopWorkflowIfMatch = false;

	#[ORM\Column(name: 'send_notification', type: 'boolean')]
	private bool $sendNotification = false;

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


	public function getId(): int
	{
		return $this->id;
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


	public function isStopWorkflowIfMatch(): bool
	{
		return $this->stopWorkflowIfMatch;
	}


	public function setStopWorkflowIfMatch(bool $stopWorkflowIfMatch): void
	{
		$this->stopWorkflowIfMatch = $stopWorkflowIfMatch;
	}


	public function isSendNotification(): bool
	{
		return $this->sendNotification;
	}


	public function setSendNotification(bool $sendNotification): void
	{
		$this->sendNotification = $sendNotification;
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
