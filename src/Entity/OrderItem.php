<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\EcommerceStandard\DTO\ManufacturerInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\DTO\OrderItemInterface;
use Baraja\EcommerceStandard\DTO\ProductInterface;
use Baraja\EcommerceStandard\DTO\ProductVariantInterface;
use Baraja\EcommerceStandard\Service\VatResolver;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order_item')]
class OrderItem implements OrderItemInterface
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
	private Order $order;

	#[ORM\ManyToOne(targetEntity: Product::class)]
	private ?Product $product;

	#[ORM\ManyToOne(targetEntity: ProductVariant::class)]
	private ?ProductVariant $variant;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $label = null;

	#[ORM\Column(type: 'integer', options: ['unsigned' => true])]
	private int $count;

	#[ORM\Column(type: 'string', length: 5, nullable: true)]
	private ?string $unit = null;

	#[ORM\Column(type: 'float', options: ['unsigned' => true])]
	private float $price;

	#[ORM\Column(type: 'float', options: ['unsigned' => true])]
	private float $vat;

	#[ORM\Column(type: 'float', options: ['unsigned' => true])]
	private float $sale = 0;


	public function __construct(
		Order $order,
		?Product $product,
		?ProductVariant $variant,
		int $count,
		float $price,
		?string $unit = null,
		?float $vat = null,
	) {
		if ($product === null && $variant !== null) {
			$product = $variant->getProduct();
		}
		$this->order = $order;
		$this->product = $product;
		$this->variant = $variant;
		$this->count = $count;
		$this->price = $price;
		$this->setUnit($unit);
		if ($product !== null) {
			$this->setLabel((string) $product->getName());
			$this->vat = $product->getVat();
		} else {
			$this->vat = (float) ($vat ?? VatResolver::DEFAULT_VAT);
		}
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getType(): string
	{
		return $this->product !== null || $this->variant !== null
			? self::TYPE_PRODUCT
			: self::TYPE_VIRTUAL;
	}


	public function getCode(): string
	{
		$return = null;
		if ($this->variant !== null) {
			$return = $this->variant->getCode();
		}
		if ($return === null && $this->product !== null) {
			$return = $this->product->getCode();
		}

		return $return ?? $this->getType();
	}


	public function getOrder(): OrderInterface
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


	public function isProductBased(): bool
	{
		return $this->product !== null || $this->variant !== null;
	}


	public function getProduct(): ProductInterface
	{
		if ($this->product === null) {
			throw new \LogicException('The product no longer exists.');
		}

		return $this->product;
	}


	public function getVariant(): ?ProductVariantInterface
	{
		return $this->variant;
	}


	public function isRealProduct(): bool
	{
		return $this->product !== null;
	}


	public function getManufacturer(): ?ManufacturerInterface
	{
		return null;
	}


	public function getAmount(): float|int
	{
		return $this->getCount();
	}


	public function getCount(): int
	{
		return $this->count;
	}


	public function setCount(int $count): void
	{
		if ($count < 1) {
			throw new \InvalidArgumentException(sprintf('Minimal count is 1, but "%d" given.', $count));
		}
		$this->count = $count;
	}


	public function getUnit(): string
	{
		$unit = $this->unit;
		if ($unit === null) {
			$unit = 'pc';
			$this->unit = $unit;
		}

		return $unit;
	}


	public function setUnit(?string $unit): void
	{
		$this->unit = $unit;
	}


	public function getLabel(): string
	{
		$return = $this->label ?? $this->getName();
		if ($this->variant !== null) {
			$return .= sprintf(' [%s]', str_replace(';', '; ', $this->variant->getRelationHash()));
		}

		return $return;
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


	public function getVat(): float
	{
		return $this->vat;
	}


	public function setVat(float|int $vat): void
	{
		$this->vat = (float) $vat;
	}


	public function getWeight(): ?int
	{
		if ($this->product !== null) {
			return $this->product->getWeight();
		}

		return null;
	}
}
