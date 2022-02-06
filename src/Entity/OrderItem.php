<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\ManufacturerInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\DTO\OrderItemInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\EcommerceStandard\DTO\ProductInterface;
use Baraja\EcommerceStandard\DTO\ProductVariantInterface;
use Baraja\EcommerceStandard\Service\VatResolver;
use Baraja\Shop\Price\Price;
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
	private ?ProductInterface $product;

	#[ORM\ManyToOne(targetEntity: ProductVariant::class)]
	private ?ProductVariantInterface $variant;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $label = null;

	#[ORM\Column(type: 'integer', options: ['unsigned' => true])]
	private int $count;

	#[ORM\Column(type: 'string', length: 5, nullable: true)]
	private ?string $unit = null;

	/** @var numeric-string */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private string $price;

	/** @var numeric-string */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private string $vat;

	/** @var numeric-string */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private string $sale = '0';


	/**
	 * @param numeric-string $price
	 * @param numeric-string|null $vat
	 */
	public function __construct(
		Order $order,
		?ProductInterface $product,
		?ProductVariantInterface $variant,
		int $count,
		string $price,
		?string $unit = null,
		?string $vat = null,
	) {
		if ($product === null && $variant !== null) {
			$product = $variant->getProduct();
		}
		$this->order = $order;
		$this->product = $product;
		$this->variant = $variant;
		$this->count = $count;
		$this->price = Price::normalize($price);
		$this->setUnit($unit);
		if ($product !== null) {
			$this->setLabel($product->getLabel());
			$this->vat = $product->getVat();
		} else {
			$this->vat = $vat ?? (string) VatResolver::DEFAULT_VAT;
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
		$return = $this->variant?->getCode();
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

		return $this->product->getLabel();
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


	public function getAmount(): float
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
			assert($this->variant instanceof ProductVariant);
			$return .= sprintf(' [%s]', str_replace(';', '; ', $this->variant->getRelationHash()));
		}

		return $return;
	}


	public function setLabel(?string $label): void
	{
		$this->label = trim($label ?? '') ?: null;
	}


	public function getPrice(): PriceInterface
	{
		return new Price($this->price, $this->getCurrency());
	}


	public function dangerouslySetPrice(PriceInterface $price): void
	{
		$this->price = $price->getValue();
	}


	public function getFinalPrice(): PriceInterface
	{
		return $this->getPrice()->minus($this->getSale());
	}


	public function getSale(): PriceInterface
	{
		return new Price($this->sale, $this->getCurrency());
	}


	public function setSale(PriceInterface $sale): void
	{
		if ($sale->isSmallerThan('0')) {
			return;
		}
		$this->sale = $sale->getValue();
	}


	public function getVat(): PriceInterface
	{
		return new Price($this->vat, $this->getCurrency());
	}


	public function setVat(PriceInterface $vat): void
	{
		$this->vat = $vat->getValue();
	}


	public function getWeight(): ?int
	{
		if ($this->product !== null) {
			return $this->product->getWeight();
		}

		return null;
	}


	private function getCurrency(): CurrencyInterface
	{
		return $this->getOrder()->getCurrency();
	}
}
