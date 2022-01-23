<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Localization\Localization;
use Baraja\Shop\Order\Repository\OrderNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderNotificationRepository::class)]
#[ORM\Table(name: 'shop__order_notification')]
#[ORM\UniqueConstraint(name: 'shop__order_notification_key', columns: ['status_id', 'locale', 'type'])]
class OrderNotification
{
	public const
		TYPE_EMAIL = 'email',
		TYPE_SMS = 'sms';

	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: OrderStatus::class)]
	private OrderStatus $status;

	#[ORM\Column(type: 'string', length: 2)]
	private string $locale;

	#[ORM\Column(type: 'string', length: 10)]
	private string $type;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $subject = null;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $content = null;

	#[ORM\Column(type: 'boolean')]
	private bool $active = false;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $insertedDate;


	public function __construct(OrderStatus $status, string $locale, string $type)
	{
		$this->status = $status;
		$this->locale = Localization::normalize($locale);
		$this->type = strtolower($type);
		$this->insertedDate = new \DateTimeImmutable;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getStatus(): OrderStatus
	{
		return $this->status;
	}


	public function getLocale(): string
	{
		return $this->locale;
	}


	public function getType(): string
	{
		return $this->type;
	}


	public function getSubject(): ?string
	{
		return $this->subject;
	}


	public function setSubject(?string $subject): void
	{
		$subject = trim($subject ?? '');
		if ($subject === '') {
			$subject = null;
		}
		$this->subject = $subject;
	}


	public function getContent(): ?string
	{
		return $this->content;
	}


	public function setContent(?string $content): void
	{
		$content = trim($content ?? '');
		if ($content === '') {
			$content = null;
		}
		$this->content = $content;
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active): void
	{
		$this->active = $active;
	}


	public function getInsertedDate(): \DateTimeImmutable
	{
		return $this->insertedDate;
	}
}
