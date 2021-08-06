<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order_item')]
class OrderItem
{
	use IdentifierUnsigned;

	#[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
	private Order $order;

	#[ORM\ManyToOne(targetEntity: Product::class)]
	private ?Product $product;

	#[ORM\ManyToOne(targetEntity: ProductVariant::class)]
	private ?ProductVariant $variant;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $label = null;

	#[ORM\Column(type: 'integer')]
	private int $count;

	#[ORM\Column(type: 'float')]
	private float $price;

	#[ORM\Column(type: 'float')]
	private float $sale = 0;


	public function __construct(
		Order $order,
		?Product $product,
		?ProductVariant $variant,
		int $count,
		float $price,
	) {
		$this->order = $order;
		$this->product = $product;
		$this->variant = $variant;
		$this->count = $count;
		$this->price = $price;
		if ($product !== null) {
			$this->setLabel((string) $product->getName());
		}
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getEan(): ?string
	{
		if ($this->variant !== null) {
			return $this->variant->getEan();
		}
		if ($this->product !== null) {
			return $this->product->getEan();
		}

		return null;
	}


	public function getName(): string
	{
		if ($this->variant !== null) {
			return $this->variant->getName();
		}
		if ($this->product === null) {
			return '[!!!] Unknown product';
		}

		return (string) $this->product->getName();
	}


	public function getProduct(): Product
	{
		if ($this->product === null) {
			throw new \LogicException('The product no longer exists.');
		}

		return $this->product;
	}


	public function getVariant(): ?ProductVariant
	{
		return $this->variant;
	}


	public function getCount(): int
	{
		return $this->count;
	}


	public function setCount(int $count): void
	{
		if ($count < 1) {
			throw new \InvalidArgumentException('Minimal count is 1, but "' . $count . '" given.');
		}
		$this->count = $count;
	}


	public function getLabel(): ?string
	{
		return ($this->label ?? $this->getName())
			. ($this->variant ? ' [' . str_replace(';', '; ', $this->variant->getRelationHash()) . ']' : '');
	}


	public function setLabel(?string $label): void
	{
		$this->label = trim($label ?? '') ?: null;
	}


	public function getPrice(): float
	{
		return $this->price;
	}


	public function getFinalPrice(): float
	{
		return $this->getPrice() - $this->getSale();
	}


	public function getSale(): float
	{
		return $this->sale;
	}


	public function setSale(float $sale): void
	{
		if ($sale < 0) {
			return;
		}
		$this->sale = $sale;
	}
}
