<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Notification;


use Baraja\EcommerceStandard\DTO\AddressInterface;
use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\CustomerInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\DTO\OrderItemInterface;
use Baraja\EcommerceStandard\DTO\OrderStatusInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;

final class SampleOrderEntity implements OrderInterface
{
	public function getId(): int
	{
	}


	public function getNumber(): string
	{
		return date('y') . '0102034';
	}


	public function getStatus(): OrderStatusInterface
	{
	}


	public function getLocale(): string
	{
	}


	public function getInvoiceNumber(): ?string
	{
	}


	public function getHash(): string
	{
	}


	public function isPaid(): bool
	{
	}


	public function setPaid(bool $paid): void
	{
	}


	public function getCustomer(): ?CustomerInterface
	{
	}


	public function getItems(): array
	{
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
	}


	public function setCurrency(CurrencyInterface $currency): void
	{
	}


	public function getCurrencyCode(): string
	{
	}


	public function getDeliveryAddress(): ?AddressInterface
	{
	}


	public function getPaymentAddress(): ?AddressInterface
	{
	}


	public function getPackageNumber(): ?string
	{
	}


	public function getInsertedDate(): \DateTimeInterface
	{
	}


	public function getUpdatedDate(): \DateTimeInterface
	{
	}
}
