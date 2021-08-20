<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


interface OrderPaymentEntity
{
	public function getPrice(): float;

	public function getDate(): \DateTimeInterface;
}
