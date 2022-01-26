<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Notification;


interface OrderNotificationDataFactoryAccessor
{
	public function get(): OrderNotificationDataFactory;
}
