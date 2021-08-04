<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Status;


use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;

interface OrderStatusChangedEvent
{
	public function process(Order $order, OrderStatus $oldStatus, OrderStatus $newStatus): void;
}
