<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\Shop\Order\Repository\OrderOnlinePaymentRepository;
use Baraja\Shop\Price\Price;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderOnlinePaymentRepository::class)]
#[ORM\Table(name: 'shop__order_payment')]
class OrderOnlinePayment implements OrderPaymentEntity
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'payments')]
	private OrderInterface $order;

	#[ORM\Column(type: 'string', length: 64)]
	private string $gatewayId;

	/** @var numeric-string */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private string $price;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private ?string $status = null;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeInterface $insertedDate;


	/**
	 * @param numeric-string|null $price
	 */
	public function __construct(OrderInterface $order, string $gatewayId, ?string $price = null)
	{
		$this->order = $order;
		$this->gatewayId = $gatewayId;
		$this->price = Price::normalize($price ?? $order->getPrice()->getValue());
		$this->insertedDate = new \DateTimeImmutable;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getOrder(): OrderInterface
	{
		return $this->order;
	}


	public function getGatewayId(): string
	{
		return $this->gatewayId;
	}


	/**
	 * @return numeric-string
	 */
	public function getPrice(): string
	{
		return Price::normalize($this->price);
	}


	public function getStatus(): ?string
	{
		return $this->status;
	}


	public function setStatus(?string $status): void
	{
		$this->status = $status;
	}


	public function getDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}
}
