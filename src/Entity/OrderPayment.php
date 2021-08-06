<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order_payment')]
class OrderPayment
{
	use IdentifierUnsigned;

	#[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'payments')]
	private Order $order;

	#[ORM\Column(type: 'string', length: 64)]
	private string $gatewayId;

	#[ORM\Column(type: 'float')]
	private float $price;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private ?string $status = null;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;


	public function __construct(Order $order, string $gatewayId, ?float $price = null)
	{
		$this->order = $order;
		$this->gatewayId = $gatewayId;
		$this->price = $price ?? $order->getPrice();
		$this->insertedDate = new \DateTimeImmutable;
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getGatewayId(): string
	{
		return $this->gatewayId;
	}


	public function getPrice(): float
	{
		return $this->price;
	}


	public function getStatus(): ?string
	{
		return $this->status;
	}


	public function setStatus(?string $status): void
	{
		$this->status = $status;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}
}
