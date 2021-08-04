<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\DateTime;

/**
 * @ORM\Entity()
 * @ORM\Table(name="shop__order_payment")
 */
class OrderPayment
{
	use IdentifierUnsigned;

	/** @ORM\ManyToOne(targetEntity="Order", inversedBy="payments") */
	private Order $order;

	/** @ORM\Column(type="string") */
	private string $gopayId;

	/** @ORM\Column(type="float") */
	private float $price;

	/** @ORM\Column(type="string", nullable=true) */
	private ?string $status = null;

	/** @ORM\Column(type="datetime") */
	private \DateTime $insertedDate;


	public function __construct(Order $order, string $gopayId, ?float $price = null)
	{
		$this->order = $order;
		$this->gopayId = $gopayId;
		$this->price = $price ?? $order->getPrice();
		$this->insertedDate = DateTime::from('now');
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getGopayId(): string
	{
		return $this->gopayId;
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


	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}
}
