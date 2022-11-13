<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\Order;


final class OrderResponse
{
	/**
	 * @param OrderItemResponse[] $items
	 * @param OrderMessageResponse[] $messages
	 * @param array<string, mixed> $dataLayerStructure
	 */
	public function __construct(
		public string $hash,
		public string $number,
		public string $variableSymbol,
		public string $locale,
		public string $status,
		public string $statusColor,
		public string $statusCode,
		public ?string $pickupCode,
		public string $price,
		public string $currency,
		public bool $isPaid,
		public bool $paymentAttemptOk,
		public ?string $delivery,
		public ?string $payment,
		public ?string $notice,
		public string $gatewayLink,
		public string $deliveryAddress,
		public string $paymentAddress,
		public \DateTimeInterface $insertedDate,
		public \DateTimeInterface $updatedDate,
		public array $items,
		public array $messages,
		public array $dataLayerStructure,
	) {
	}
}
