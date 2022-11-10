<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\Order;


final class OrderMessageResponse
{
	public function __construct(
		public string $message,
		public \DateTimeInterface $insertedDate,
	) {
	}
}
