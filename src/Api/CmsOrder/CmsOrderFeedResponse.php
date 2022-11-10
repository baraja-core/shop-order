<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\CmsOrder;


use Nette\Utils\Paginator;

final class CmsOrderFeedResponse
{
	/**
	 * @param array<int, CmsOrderFeedList> $items
	 * @param array<string, string> $sum
	 */
	public function __construct(
		public array $items,
		public array $sum,
		public Paginator $paginator,
	) {
	}
}
