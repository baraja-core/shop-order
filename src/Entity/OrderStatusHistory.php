<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Shop\Order\Repository\OrderStatusHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderStatusHistoryRepository::class)]
#[ORM\Table(name: 'shop__order_status_history')]
class OrderStatusHistory
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Order::class)]
	private Order $order;

	#[ORM\ManyToOne(targetEntity: OrderStatus::class)]
	private OrderStatus $status;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $insertedDate;


	public function __construct(Order $order, OrderStatus $status)
	{
		$this->order = $order;
		$this->status = $status;
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


	public function getStatus(): OrderStatus
	{
		return $this->status;
	}


	public function getInsertedDate(): \DateTimeImmutable
	{
		return $this->insertedDate;
	}
}
