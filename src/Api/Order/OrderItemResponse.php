<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\Order;


final class OrderItemResponse
{
	public function __construct(
		public int $id,
		public ?string $slug,
		public ?string $mainImageUrl,
		public string $label,
		public int $count,
		public ?string $price,
		public ?string $ean,
		public bool $sale,
	) {
	}
}
