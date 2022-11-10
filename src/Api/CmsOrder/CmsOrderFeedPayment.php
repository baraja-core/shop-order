<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\CmsOrder;


final class CmsOrderFeedPayment
{
	public function __construct(
		public int $id,
		public string $name,
		public string $price,
		public ?string $color,
	) {
	}
}
