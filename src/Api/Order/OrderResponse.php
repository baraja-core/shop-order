<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\Order;


final class OrderResponse
{
	/**
	 * @param array<string, mixed> $dataLayerStructure
	 */
	public function __construct(
		public string $hash,
		public bool $isPaid,
		public array $dataLayerStructure,
	) {
	}
}
