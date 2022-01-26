<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Shop\Order\Repository\OrderNotificationHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderNotificationHistoryRepository::class)]
#[ORM\Table(name: 'shop__order_notification_history')]
class OrderNotificationHistory
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Order::class)]
	private Order $order;

	#[ORM\ManyToOne(targetEntity: OrderNotification::class)]
	private OrderNotification $notification;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $subject = null;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $content = null;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $insertedDate;


	public function __construct(Order $order, OrderNotification $notification)
	{
		$this->order = $order;
		$this->notification = $notification;
		$this->insertedDate = new \DateTimeImmutable;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getNotification(): OrderNotification
	{
		return $this->notification;
	}


	public function getSubject(): ?string
	{
		return $this->subject;
	}


	public function setSubject(?string $subject): void
	{
		$this->subject = $subject;
	}


	public function getContent(): ?string
	{
		return $this->content;
	}


	public function setContent(?string $content): void
	{
		$this->content = $content;
	}


	public function getPlaintextContent(): ?string
	{
		if ($this->content === null) {
			return null;
		}

		$content = strip_tags($this->content);
		$content = str_replace('&nbsp;', ' ', $content);
		$content = (string) preg_replace('/\n\s+/', "\n", $content);

		return $content;
	}


	public function getInsertedDate(): \DateTimeImmutable
	{
		return $this->insertedDate;
	}
}
