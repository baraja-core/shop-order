<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Notification;


use Baraja\EcommerceStandard\DTO\AddressInterface;
use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\CustomerInterface;
use Baraja\EcommerceStandard\DTO\DeliveryInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\DTO\OrderItemInterface;
use Baraja\EcommerceStandard\DTO\OrderStatusInterface;
use Baraja\EcommerceStandard\DTO\PaymentInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\Shop\Entity\Currency\Currency;
use Baraja\Shop\Order\Entity\OrderStatus;

final class SampleOrderEntity implements OrderInterface
{
	public function getId(): int
	{
		return 123;
	}


	public function getNumber(): string
	{
		return date('y') . '0102034';
	}


	public function getStatus(): OrderStatusInterface
	{
		return new OrderStatus('new', 'New');
	}


	public function getLocale(): string
	{
		return 'en';
	}


	public function getInvoiceNumber(): ?string
	{
		return date('y') . '0123456';
	}


	public function getHash(): string
	{
		return md5($this->getNumber());
	}


	public function isPaid(): bool
	{
		return false;
	}


	public function setPaid(bool $paid): void
	{
	}


	public function getCustomer(): ?CustomerInterface
	{
	}


	public function getItems(): array
	{
		return [];
	}


	public function addItem(OrderItemInterface $item): void
	{
	}


	public function getPrice(): PriceInterface
	{
	}


	public function getBasePrice(): PriceInterface
	{
	}


	public function getVatValue(): PriceInterface
	{
	}


	public function getPriceWithoutVat(): PriceInterface
	{
	}


	public function setBasePrice(PriceInterface $price): void
	{
	}


	public function getSale(): PriceInterface
	{
	}


	public function setSale(PriceInterface $sale): void
	{
	}


	public function getCurrency(): CurrencyInterface
	{
		return new Currency('USD', '$');
	}


	public function setCurrency(CurrencyInterface $currency): void
	{
	}


	public function getCurrencyCode(): string
	{
		return $this->getCurrency()->getCode();
	}


	public function getDeliveryAddress(): ?AddressInterface
	{
	}


	public function getPaymentAddress(): ?AddressInterface
	{
	}


	public function getPackageNumber(): ?string
	{
		return 'ABC123456789';
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return new \DateTimeImmutable('now - 30 minutes');
	}


	public function getUpdatedDate(): \DateTimeInterface
	{
		return new \DateTimeImmutable;
	}


	public function getDelivery(): ?DeliveryInterface
	{
		return null;
	}


	public function getPayment(): ?PaymentInterface
	{
		return null;
	}


	public function getNotice(): ?string
	{
		return 'My notice.';
	}


	public function getDeliveryPrice(): PriceInterface
	{
		// TODO: Implement getDeliveryPrice() method.
	}


	public function getPaymentPrice(): PriceInterface
	{
		// TODO: Implement getPaymentPrice() method.
	}
}
