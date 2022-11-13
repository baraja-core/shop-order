<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\EcommerceStandard\DTO\AddressInterface;
use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\CustomerInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\DTO\OrderItemInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\Localization\Localization;
use Baraja\Shop\Address\Entity\Address;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Entity\Currency\Currency;
use Baraja\Shop\Order\Repository\OrderRepository;
use Baraja\Shop\Payment\Entity\Payment;
use Baraja\Shop\Price\Price;
use Baraja\VariableGenerator\Order\OrderEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\Random;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'shop__order')]
#[ORM\UniqueConstraint(name: 'order__number_group', columns: ['number', 'group_id'])]
#[ORM\UniqueConstraint(name: 'order__id_group', columns: ['id', 'group_id'])]
#[ORM\Index(columns: ['number'], name: 'order__number')]
#[ORM\Index(columns: ['status_id'], name: 'order__status')]
#[ORM\Index(columns: ['inserted_date'], name: 'order__inserted_date')]
#[ORM\Index(columns: ['inserted_date', 'status_id', 'id'], name: 'order__feed')]
class Order implements OrderInterface, OrderEntity
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: OrderGroup::class)]
	private OrderGroup $group;

	#[ORM\ManyToOne(targetEntity: Customer::class)]
	private Customer $customer;

	#[ORM\ManyToOne(targetEntity: Address::class)]
	private Address $deliveryAddress;

	#[ORM\ManyToOne(targetEntity: Address::class)]
	private Address $paymentAddress;

	#[ORM\Column(type: 'string', length: 24)]
	private string $number;

	#[ORM\ManyToOne(targetEntity: OrderStatus::class)]
	private ?OrderStatus $status;

	#[ORM\Column(type: 'boolean')]
	private bool $paid = false;

	#[ORM\Column(type: 'string', length: 32, unique: true)]
	private string $hash;

	#[ORM\Column(type: 'string', length: 2)]
	private string $locale;

	#[ORM\ManyToOne(targetEntity: Delivery::class)]
	private ?Delivery $delivery;

	#[ORM\ManyToOne(targetEntity: Payment::class)]
	private ?Payment $payment;

	/** @var numeric-string */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private string $price;

	#[ORM\ManyToOne(targetEntity: Currency::class)]
	private CurrencyInterface $currency;

	/** @var numeric-string */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private string $priceWithoutVat;

	/** @var numeric-string */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private string $sale = '0';

	/** @var numeric-string|null */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private ?string $deliveryPrice = null;

	/** @var numeric-string|null */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private ?string $paymentPrice = '0';

	#[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
	private ?int $deliveryBranchId = null;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $updatedDate;

	#[ORM\Column(type: 'boolean')]
	private bool $pinged = false;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $notice = null;

	#[ORM\Column(type: 'string', length: 8, nullable: true)]
	private ?string $pickupCode;

	#[ORM\Column(type: 'bigint', nullable: true)]
	private ?int $zasilkovnaId = null;

	#[ORM\Column(type: 'string', length: 16, nullable: true)]
	private ?string $zasilkovnaBarcode = null;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $handoverUrl = null;

	#[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
	private ?int $deprecatedId = null;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTimeInterface $lastPaymentAttempt = null;

	/** @var Collection<OrderItem> */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class)]
	private Collection $items;

	/** @var Collection<OrderPackage> */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderPackage::class)]
	private Collection $packages;

	/** @var Collection<OrderBankPayment> */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderBankPayment::class)]
	private Collection $transactions;

	/** @var Collection<OrderOnlinePayment> */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderOnlinePayment::class)]
	private Collection $payments;

	/** @var Collection<OrderMeta> */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderMeta::class)]
	private Collection $metas;

	/** @var Collection<OrderFile> */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderFile::class)]
	private Collection $files;

	/** @var Collection<OrderMessage> */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderMessage::class)]
	private Collection $messages;


	/**
	 * @param numeric-string $price
	 * @param numeric-string $priceWithoutVat
	 */
	public function __construct(
		OrderGroup $group,
		OrderStatus $status,
		Customer $customer,
		Address $deliveryAddress,
		?Address $invoiceAddress,
		string $number,
		string $locale,
		?Delivery $delivery,
		?Payment $payment,
		string $price,
		string $priceWithoutVat,
		CurrencyInterface $currency,
	) {
		if ($customer->isBan()) {
			throw new \InvalidArgumentException(sprintf(
				'Can not create new order, because customer "%s" (id %d) has been banned.',
				$customer->getName(),
				$customer->getId(),
			));
		}
		$this->group = $group;
		$this->status = $status;
		$this->customer = $customer;
		$this->deliveryAddress = $deliveryAddress;
		$this->paymentAddress = $invoiceAddress ?? $deliveryAddress;
		$this->number = $number;
		$this->locale = Localization::normalize($locale);
		$this->delivery = $delivery;
		$this->payment = $payment;
		$this->price = $price;
		$this->priceWithoutVat = $priceWithoutVat;
		$this->deliveryPrice = $delivery !== null ? $delivery->getPrice() : '0';
		$this->setCurrency($currency);
		$this->hash = Random::generate(32);
		$this->pickupCode = Random::generate(8);
		$this->insertedDate = new \DateTimeImmutable;
		$this->updatedDate = new \DateTime;
		$this->items = new ArrayCollection;
		$this->packages = new ArrayCollection;
		$this->transactions = new ArrayCollection;
		$this->payments = new ArrayCollection;
		$this->metas = new ArrayCollection;
		$this->files = new ArrayCollection;
		$this->messages = new ArrayCollection;
	}


	public function getId(): int
	{
		assert($this->id > 0);

		return $this->id;
	}


	public function isPaid(): bool
	{
		return $this->paid;
	}


	public function setPaid(bool $paid): void
	{
		$this->paid = $paid;
	}


	public function getPackageNumber(): ?string
	{
		// TODO: Implement getPackageNumber() method.
		return null;
	}


	public function getCurrency(): CurrencyInterface
	{
		return $this->currency;
	}


	public function setCurrency(CurrencyInterface $currency): void
	{
		$this->currency = $currency;
	}


	public function getDeliveryPrice(): Price
	{
		if ($this->deliveryPrice === null) {
			if ($this->delivery !== null) {
				$return = $this->delivery->getPrice();
			} else {
				$return = '0';
			}
			$this->deliveryPrice = $return;
		} else {
			$return = $this->deliveryPrice;
		}

		return new Price($return, $this->currency);
	}


	/**
	 * @param numeric-string $deliveryPrice
	 */
	public function setDeliveryPrice(string $deliveryPrice): void
	{
		$this->deliveryPrice = Price::normalize($deliveryPrice);
	}


	public function getPaymentPrice(): Price
	{
		if ($this->paymentPrice === null) {
			if ($this->payment !== null) {
				$return = $this->payment->getPrice();
			} else {
				$return = '0';
			}
			$this->paymentPrice = $return;
		} else {
			$return = $this->paymentPrice;
		}

		return new Price($return, $this->currency);
	}


	/**
	 * @param numeric-string $paymentPrice
	 */
	public function setPaymentPrice(string $paymentPrice): void
	{
		$this->paymentPrice = Price::normalize($paymentPrice);
	}


	public function getGroup(): OrderGroup
	{
		return $this->group;
	}


	public function getCustomer(): CustomerInterface
	{
		return $this->customer;
	}


	public function getDeliveryAddress(): AddressInterface
	{
		return $this->deliveryAddress;
	}


	public function getPaymentAddress(): AddressInterface
	{
		return $this->paymentAddress;
	}


	public function getNumber(): string
	{
		assert($this->number !== '');

		return $this->number;
	}


	public function getLocale(): string
	{
		return $this->locale;
	}


	public function getStatus(): OrderStatus
	{
		if ($this->status === null) {
			throw new \LogicException('Order status does not exist.');
		}

		return $this->status;
	}


	public function setStatus(OrderStatus $status): void
	{
		if ($status->getCode() !== $this->getStatus()->getCode()) {
			$this->setUpdated();
		}
		$this->status = $status;
	}


	public function getHash(): string
	{
		assert($this->hash !== '');

		return $this->hash;
	}


	public function getDelivery(): ?Delivery
	{
		return $this->delivery;
	}


	public function setDelivery(Delivery $delivery): void
	{
		if (
			$this->delivery !== null
			&& $delivery->getId() !== $this->delivery->getId()
		) {
			$this->setUpdated();
		}
		$this->delivery = $delivery;
	}


	public function getPayment(): ?Payment
	{
		return $this->payment;
	}


	public function setPayment(Payment $payment): void
	{
		if (
			$this->payment !== null
			&& $payment->getId() !== $this->payment->getId()
		) {
			$this->setUpdated();
		}
		$this->payment = $payment;
	}


	public function getBasePrice(): PriceInterface
	{
		return new Price($this->price, $this->currency);
	}


	public function getVatValue(): PriceInterface
	{
		$vat = '0';
		foreach ($this->getItems() as $item) {
			$itemPrice = $item->getFinalPrice()->getValue();
			$itemVat = $item->getVat()->getValue();
			$itemValue = bcsub($itemPrice, bcmul($itemPrice, bcdiv($itemVat, '100', 2), 2), 2);
			$vat = bcadd($vat, $itemValue, 2);
		}

		return new Price($vat, $this->currency);
	}


	public function getPriceWithoutVat(): PriceInterface
	{
		return $this->getPrice()->minus($this->getVatValue());
	}


	/**
	 * @return numeric-string
	 */
	public function getPriceWithoutVatRaw(): string
	{
		return $this->priceWithoutVat;
	}


	public function getPrice(): PriceInterface
	{
		$price = new Price($this->price, $this->currency);
		$return = $price->minus($this->getSale());
		if ($return->isSmallerThan('0')) {
			$return = '0';
		}

		return new Price($return, $this->currency);
	}


	public function setBasePrice(PriceInterface $price): void
	{
		$value = Price::normalize($price->getValue());
		if ($value !== Price::normalize($this->price)) {
			$this->setUpdated();
			$this->price = $value;
		}
	}


	public function getCurrencyCode(): string
	{
		return $this->currency->getCode();
	}


	public function getSale(): PriceInterface
	{
		return new Price($this->sale, $this->currency);
	}


	public function setSale(PriceInterface $sale): void
	{
		$value = Price::normalize($sale->getValue());
		if ($sale->isSmallerThan('0')) {
			$value = '0';
		}
		if ($value !== Price::normalize($this->sale)) {
			$this->setUpdated();
			$this->sale = $value;
		}
	}


	public function recountPrice(): void
	{
		// TODO: Move this method to OrderPriceCalculator with native support for side effects.
		$sum = '0';
		foreach ($this->items as $item) {
			$sum = bcadd($sum, bcmul($item->getFinalPrice()->getValue(), (string) $item->getCount(), 2), 2);
		}
		$sum = bcadd($sum, $this->getDeliveryPrice()->getValue(), 2);
		$sum = bcadd($sum, $this->getPaymentPrice()->getValue(), 2);

		$this->price = Price::normalize($sum);
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): \DateTimeInterface
	{
		return $this->updatedDate;
	}


	public function isPinged(): bool
	{
		return $this->pinged;
	}


	public function setPinged(bool $pinged = true): void
	{
		$this->pinged = $pinged;
	}


	/**
	 * @return array<string, string>
	 */
	public function getMetaData(): array
	{
		$return = [];
		foreach ($this->metas as $meta) {
			$value = $meta->getValue();
			if ($value !== null) {
				$return[$meta->getKey()] = $value;
			}
		}

		return $return;
	}


	public function getMetaKey(string $key): ?string
	{
		foreach ($this->metas as $meta) {
			if ($meta->getKey() === $key) {
				return $meta->getValue();
			}
		}

		return null;
	}


	/**
	 * @return array<int, OrderItemInterface>
	 */
	public function getItems(): array
	{
		return $this->items->toArray();
	}


	public function addItem(OrderItemInterface $item): void
	{
		$this->items[] = $item;
	}


	public function removeItem(int $id): void
	{
		$items = [];
		foreach ($this->items as $item) {
			if ($item->getId() !== $id) {
				$items[] = $item;
			}
		}
		$this->items = new ArrayCollection($items);
	}


	/**
	 * @return Collection<OrderPackage>
	 */
	public function getPackages(): Collection
	{
		return $this->packages;
	}


	/**
	 * @return Collection<OrderBankPayment>
	 */
	public function getTransactions(): Collection
	{
		return $this->transactions;
	}


	/**
	 * @return Collection<OrderOnlinePayment>
	 */
	public function getPayments(): Collection
	{
		return $this->payments;
	}


	public function addPayment(OrderOnlinePayment $payment): void
	{
		$this->payments[] = $payment;
	}


	/**
	 * @return Collection<OrderMeta>
	 */
	public function getMetas(): Collection
	{
		return $this->metas;
	}


	/**
	 * @return Collection<OrderFile>
	 */
	public function getFiles(): Collection
	{
		return $this->files;
	}


	/**
	 * @return Collection<OrderMessage>
	 */
	public function getMessages(): Collection
	{
		return $this->messages;
	}


	public function getNotice(): ?string
	{
		return $this->notice;
	}


	public function setNotice(?string $notice): void
	{
		$haystack = trim($notice ?? '');
		$this->notice = $haystack === '' ? null : $haystack;
	}


	public function addNotice(string $notice): void
	{
		$haystack = trim((string) $this->notice);
		$haystack = ($haystack !== '' ? $haystack . "\n" : '') . $notice;
		$this->setNotice($haystack);
	}


	public function getPickupCode(): ?string
	{
		return $this->pickupCode;
	}


	public function setPickupCode(?string $pickupCode): void
	{
		$this->pickupCode = $pickupCode;
	}


	public function getDeliveryBranchId(): ?int
	{
		return $this->deliveryBranchId;
	}


	public function setDeliveryBranchId(?int $deliveryBranchId): void
	{
		if ($deliveryBranchId !== null && $deliveryBranchId < 1) {
			throw new \InvalidArgumentException('Delivery branch ID must be positive number.');
		}
		$this->deliveryBranchId = $deliveryBranchId;
	}


	public function getZasilkovnaId(): ?int
	{
		return $this->zasilkovnaId;
	}


	public function setZasilkovnaId(?int $zasilkovnaId): void
	{
		$this->zasilkovnaId = $zasilkovnaId;
	}


	public function getZasilkovnaBarcode(): ?string
	{
		return $this->zasilkovnaBarcode;
	}


	public function setZasilkovnaBarcode(?string $zasilkovnaBarcode): void
	{
		$this->zasilkovnaBarcode = $zasilkovnaBarcode;
	}


	public function getHandoverUrl(): ?string
	{
		return $this->handoverUrl;
	}


	public function setHandoverUrl(?string $handoverUrl): void
	{
		$this->handoverUrl = $handoverUrl;
	}


	public function getDeprecatedId(): ?int
	{
		return $this->deprecatedId;
	}


	public function setDeprecatedId(?int $deprecatedId): void
	{
		$this->deprecatedId = $deprecatedId;
	}


	public function legacySetDate(\DateTimeInterface $date): void
	{
		$this->insertedDate = $date;
		$this->updatedDate = $date;
	}


	public function isPaymentAttemptOk(): bool
	{
		if ($this->lastPaymentAttempt === null) {
			return true;
		}

		return $this->lastPaymentAttempt->getTimestamp() < strtotime('now - 5 minutes');
	}


	public function getLastPaymentAttempt(): ?\DateTimeInterface
	{
		return $this->lastPaymentAttempt;
	}


	public function setLastPaymentAttempt(?\DateTimeInterface $lastPaymentAttempt = null): void
	{
		$this->lastPaymentAttempt = $lastPaymentAttempt;
	}


	private function setUpdated(): void
	{
		$this->updatedDate = new \DateTime;
	}
}
