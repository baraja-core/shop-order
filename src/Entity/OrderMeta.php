<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order_meta')]
#[UniqueConstraint(name: 'shop__order_meta_order_key', columns: ['order_id', 'key'])]
class OrderMeta
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'metas')]
	private Order $order;

	#[ORM\Column(name: '`key`', type: 'string', length: 64)]
	private string $key;

	#[ORM\Column(name: '`value`', type: 'text', nullable: true)]
	private ?string $value;


	public function __construct(Order $order, string $key, ?string $value)
	{
		$value = trim($value ?? '');
		$this->order = $order;
		$this->key = $key;
		$this->value = $value !== '' ? $value : null;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getKey(): string
	{
		return $this->key;
	}


	public function getValue(): ?string
	{
		return $this->value;
	}


	public function setValue(?string $value): void
	{
		$this->value = $value;
	}
}
