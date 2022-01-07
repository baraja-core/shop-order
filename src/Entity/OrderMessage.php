<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Cms\User\Entity\User;
use Baraja\Shop\Customer\Entity\Customer;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order_message')]
class OrderMessage
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Order::class)]
	private Order $order;

	#[ORM\Column(type: 'text')]
	private string $message;

	#[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
	private ?int $userId = null;

	#[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
	private ?int $customerId = null;

	#[ORM\Column(name: 'share', type: 'boolean')]
	private bool $shareWithCustomer;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;


	public function __construct(Order $order, string $message, User|Customer $user, bool $shareWithCustomer = true)
	{
		$this->order = $order;
		$this->message = trim($message);
		$this->shareWithCustomer = $shareWithCustomer;
		$this->insertedDate = new \DateTime;

		if ($user instanceof Customer) {
			$this->customerId = $user->getId();
		} else {
			$this->userId = $user->getId();
		}
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getMessage(): string
	{
		return $this->message;
	}


	public function getUserId(): ?int
	{
		return $this->userId;
	}


	public function getCustomerId(): ?int
	{
		return $this->customerId;
	}


	public function isShareWithCustomer(): bool
	{
		return $this->shareWithCustomer;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}
}
