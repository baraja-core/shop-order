<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\DTO;


use Baraja\EcommerceStandard\DTO\PriceInterface;

final class CmsOrderFeedItem
{
	public function __construct(
		public int $id,
		public string $name,
		public int $count,
		public PriceInterface $price,
		public ?PriceInterface $sale,
		public PriceInterface $finalPrice,
	) {
	}
}
