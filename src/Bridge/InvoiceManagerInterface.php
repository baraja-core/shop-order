<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Shop\Order\Entity\Order;

interface InvoiceManagerInterface
{
	public function createInvoice(Order $order);
}
