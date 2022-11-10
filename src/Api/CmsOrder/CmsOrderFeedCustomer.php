<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\CmsOrder;


final class CmsOrderFeedCustomer
{
	public function __construct(
		public int $id,
		public ?string $email,
		public string $firstName,
		public string $lastName,
		public ?string $phone,
		public bool $premium,
		public bool $ban,
	) {
	}
}
