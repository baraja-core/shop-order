<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


interface OrderPaymentEntity
{
	/**
	 * @return numeric-string
	 */
	public function getPrice(): string;

	public function getDate(): \DateTimeInterface;
}
