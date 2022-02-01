<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\EcommerceStandard\DTO\OrderInterface;

interface OrderPaymentEntity
{
	public function getOrder(): ?OrderInterface;

	public function getGatewayId(): string;

	public function getStatus(): ?string;

	/**
	 * @return numeric-string
	 */
	public function getPrice(): string;

	public function getDate(): \DateTimeInterface;

	public function getLastCheckedDate(): \DateTimeInterface;

	public function setCheckedNow(): void;
}
