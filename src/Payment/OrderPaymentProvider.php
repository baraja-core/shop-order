<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Payment;


use Baraja\Shop\Order\Entity\Order;

interface OrderPaymentProvider
{
	public function getPaymentMethodCode(): string;

	public function getPaymentStatus(Order $order): string;
}
