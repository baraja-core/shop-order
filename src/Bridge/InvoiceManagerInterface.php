<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderDocument;

interface InvoiceManagerInterface
{
	public function createInvoice(Order $order): OrderDocument;

	public function isInvoice(Order $order): bool;
}
