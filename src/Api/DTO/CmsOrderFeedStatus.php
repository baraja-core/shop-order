<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\DTO;


final class CmsOrderFeedStatus
{
	public function __construct(
		public string $code,
		public string $color,
		public string $label,
	) {
	}
}
