<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Address\Entity\Address;
use Baraja\Shop\Cart\Entity\OrderNumber;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Payment\Entity\Payment;
use Baraja\VariableGenerator\Order\OrderEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Nette\Utils\DateTime;
use Nette\Utils\Random;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order')]
#[Index(columns: ['status_id'], name: 'order__status')]
#[Index(columns: ['inserted_date'], name: 'order__inserted_date')]
#[Index(columns: ['inserted_date', 'status_id', 'id'], name: 'order__feed')]
class Order implements OrderEntity, OrderNumber
{
	use IdentifierUnsigned;

	public const FREE_DELIVERY_LIMIT = 1_000;

	#[ORM\ManyToOne(targetEntity: Customer::class)]
	private Customer $customer;

	#[ORM\ManyToOne(targetEntity: Address::class)]
	private Address $deliveryAddress;

	#[ORM\ManyToOne(targetEntity: Address::class)]
	private Address $invoiceAddress;

	#[ORM\Column(type: 'string', length: 24, unique: true)]
	private string $number;

	#[ORM\ManyToOne(targetEntity: OrderStatus::class)]
	private OrderStatus $status;

	#[ORM\Column(type: 'string', length: 32, unique: true)]
	private string $hash;

	#[ORM\Column(type: 'string', length: 2)]
	private string $locale;

	#[ORM\ManyToOne(targetEntity: Delivery::class)]
	private Delivery $delivery;

	#[ORM\ManyToOne(targetEntity: Payment::class)]
	private Payment $payment;

	#[ORM\Column(type: 'float')]
	private float $price;

	#[ORM\Column(type: 'float')]
	private float $priceWithoutVat;

	#[ORM\Column(type: 'float')]
	private float $sale = 0;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $deliveryPrice = null;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $deliveryBranchId = null;

	#[ORM\Column(type: 'datetime')]
	private \DateTime $insertedDate;

	#[ORM\Column(type: 'datetime')]
	private \DateTime $updatedDate;

	#[ORM\Column(type: 'boolean')]
	private bool $sendPingMail = false;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $notice = null;

	#[ORM\Column(type: 'bigint', nullable: true)]
	private ?int $zasilkovnaId = null;

	#[ORM\Column(type: 'string', length: 16, nullable: true)]
	private ?string $zasilkovnaBarcode = null;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $handoverUrl = null;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $deprecatedId = null;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTime $lastPaymentAttempt = null;

	/** @var OrderItem[]|Collection */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class)]
	private $items;

	/** @var OrderPackage[]|Collection */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderPackage::class)]
	private $packages;

	/** @var Transaction[]|Collection */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: Transaction::class)]
	private $transactions;

	/** @var OrderPayment[]|Collection */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderPayment::class)]
	private $payments;

	/** @var OrderMeta[]|Collection */
	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderMeta::class)]
	private $metas;


	public function __construct(
		OrderStatus $status,
		Customer $customer,
		Address $deliveryAddress,
		Address $invoiceAddress,
		string $number,
		string $locale,
		Delivery $delivery,
		Payment $payment,
		float $price,
		float $priceWithoutVat,
	) {
		$this->status = $status;
		$this->customer = $customer;
		$this->deliveryAddress = $deliveryAddress;
		$this->invoiceAddress = $invoiceAddress;
		$this->number = $number;
		$this->locale = $locale;
		$this->delivery = $delivery;
		$this->payment = $payment;
		$this->price = $price;
		$this->priceWithoutVat = $priceWithoutVat;
		$this->deliveryPrice = $price > self::FREE_DELIVERY_LIMIT ? 0 : $delivery->getPrice();
		$this->hash = Random::generate(32);
		$this->insertedDate = DateTime::from('now');
		$this->updatedDate = DateTime::from('now');
		$this->items = new ArrayCollection;
		$this->packages = new ArrayCollection;
		$this->transactions = new ArrayCollection;
		$this->payments = new ArrayCollection;
		$this->metas = new ArrayCollection;
	}


	public function isPaid(): bool
	{
		$sum = 0;
		foreach ($this->payments as $payment) {
			if ($payment->getStatus() === 'PAID') {
				$sum += $payment->getPrice();
			}
		}
		foreach ($this->transactions as $transaction) {
			$sum += $transaction->getPrice();
		}

		return $this->price <= $sum;
	}


	public function getDeliveryPrice(): int
	{
		return $this->deliveryPrice
			?? ($this->deliveryPrice = $this->getPrice() > self::FREE_DELIVERY_LIMIT ? 0 : $this->delivery->getPrice());
	}


	public function setDeliveryPrice(int $deliveryPrice): void
	{
		$this->deliveryPrice = $deliveryPrice;
	}


	public function getColor(): string
	{
		return $this->status->getColor();
	}


	public function getCustomer(): Customer
	{
		return $this->customer;
	}


	public function getDeliveryAddress(): Address
	{
		return $this->deliveryAddress;
	}


	public function getInvoiceAddress(): Address
	{
		return $this->invoiceAddress;
	}


	public function getNumber(): string
	{
		return $this->number;
	}


	public function getLocale(): string
	{
		return $this->locale;
	}


	public function getStatus(): OrderStatus
	{
		return $this->status;
	}


	public function setStatus(OrderStatus $status): void
	{
		if ($status->getCode() !== $this->status->getCode()) {
			$this->setUpdated();
		}
		$this->status = $status;
	}


	public function getStatusHuman(): string
	{
		return $this->status->getLabel();
	}


	public function getHash(): string
	{
		return $this->hash;
	}


	public function getDelivery(): Delivery
	{
		return $this->delivery;
	}


	public function setDelivery(Delivery $delivery): void
	{
		if ($delivery->getId() !== $this->delivery->getId()) {
			$this->setUpdated();
		}
		$this->delivery = $delivery;
	}


	public function getPayment(): Payment
	{
		return $this->payment;
	}


	public function setPayment(Payment $payment): void
	{
		if ($payment->getId() !== $this->payment->getId()) {
			$this->setUpdated();
		}
		$this->payment = $payment;
	}


	public function getBasePrice(): float
	{
		return $this->price;
	}


	public function getPriceWithoutVat(): float
	{
		return $this->getPrice() * .79;
	}


	public function getPrice(): float
	{
		return $this->price - $this->getSale();
	}


	public function setPrice(float $price): void
	{
		if ($price !== $this->price) {
			$this->setUpdated();
			$this->price = $price;
		}
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
		if ($sale !== $this->sale) {
			$this->setUpdated();
			$this->sale = $sale;
		}
	}


	public function recountPrice(): void
	{
		$sum = 0;
		foreach ($this->items as $item) {
			$sum += $item->getFinalPrice() * $item->getCount();
		}
		$sum += $this->getDeliveryPrice();
		$sum += $this->payment->getPrice();

		$this->price = $sum;
	}


	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): \DateTime
	{
		return $this->updatedDate;
	}


	public function isSendPingMail(): bool
	{
		return $this->sendPingMail;
	}


	public function setSendPingMail(bool $sendPingMail): void
	{
		$this->sendPingMail = $sendPingMail;
	}


	/**
	 * @return string[]
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
	 * @return OrderItem[]|Collection
	 */
	public function getItems()
	{
		return $this->items;
	}


	public function addItem(OrderItem $item): self
	{
		$this->items[] = $item;

		return $this;
	}


	public function removeItem(int $id): void
	{
		$items = [];
		foreach ($this->items as $item) {
			if ($item->getId() !== $id) {
				$items[] = $item;
			}
		}
		$this->items = $items;
	}


	/**
	 * @return OrderPackage[]|Collection
	 */
	public function getPackages()
	{
		return $this->packages;
	}


	/**
	 * @return Invoice[]|Collection
	 */
	public function getInvoices()
	{
		return $this->invoices;
	}


	public function isInvoice(): bool
	{
		return \count($this->invoices->toArray()) > 0;
	}


	/**
	 * @return Transaction[]|Collection
	 */
	public function getTransactions()
	{
		return $this->transactions;
	}


	/**
	 * @return OrderPayment[]|Collection
	 */
	public function getPayments()
	{
		return $this->payments;
	}


	public function addPayment(OrderPayment $payment): void
	{
		$this->payments[] = $payment;
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
		$haystack = ($haystack !== '' ? "\n" : '') . $notice;
		$this->setNotice($haystack);
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


	public function legacySetDate(\DateTime $date): void
	{
		$this->insertedDate = $date;
		$this->updatedDate = $date;
	}


	public function isPaymentAttemptOk(): bool
	{
		if ($this->lastPaymentAttempt === null) {
			return true;
		}

		return $this->lastPaymentAttempt->getTimestamp() < (int) strtotime('now - 5 minutes');
	}


	public function getLastPaymentAttempt(): ?\DateTime
	{
		return $this->lastPaymentAttempt;
	}


	public function setLastPaymentAttempt(?\DateTime $lastPaymentAttempt = null): void
	{
		$this->lastPaymentAttempt = $lastPaymentAttempt;
	}


	private function setUpdated(): void
	{
		$this->updatedDate = DateTime::from('now');
	}
}
