<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\CmsOrder;


final class CmsOrderFeedDocument
{
	public function __construct(
		public string $url,
		public string $label,
	) {
	}
}
