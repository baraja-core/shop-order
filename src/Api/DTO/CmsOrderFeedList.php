<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\DTO;


use Baraja\EcommerceStandard\DTO\PriceInterface;

final class CmsOrderFeedList
{
	/**
	 * @param array<int, CmsOrderFeedItem> $items
	 * @param array<int, CmsOrderFeedDocument> $documents
	 * @param array<int, array{id: int}> $payments
	 */
	public function __construct(
		public int $id,
		public bool $checked,
		public string $number,
		public CmsOrderFeedStatus $status,
		public bool $paid,
		public bool $pinged,
		public PriceInterface $price,
		public PriceInterface $sale,
		public string $finalPrice,
		public string $currency,
		public ?string $notice,
		public string $insertedDate,
		public string $updatedDate,
		public int $package,
		public CmsOrderFeedCustomer $customer,
		public ?CmsOrderFeedDelivery $delivery,
		public ?CmsOrderFeedPayment $payment,
		public array $items,
		public array $documents,
		public array $payments,
	) {
	}
}
