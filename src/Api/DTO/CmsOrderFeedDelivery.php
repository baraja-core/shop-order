<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\DTO;


final class CmsOrderFeedDelivery
{
	public function __construct(
		public int $id,
		public string $name,
		public string $price,
		public ?string $color,
	) {
	}
}
