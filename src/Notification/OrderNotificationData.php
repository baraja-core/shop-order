<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Notification;


use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\SimpleTemplate\TemplateData;

final class OrderNotificationData implements TemplateData
{
	public function __construct(
		private OrderInterface $order,
	) {
	}


	/**
	 * Returns a public order number for communication with the customer.
	 * The order number is unique within the order group.
	 */
	public function getNumber(): string
	{
		return $this->order->getNumber();
	}
}
