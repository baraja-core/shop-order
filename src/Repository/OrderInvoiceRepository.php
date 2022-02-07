<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\EcommerceStandard\DTO\InvoiceInterface;

interface OrderInvoiceRepository
{
	/**
	 * @param array<int, int> $ids
	 * @return array<int, InvoiceInterface>
	 */
	public function getInvoicesByOrderIds(array $ids): array;
}
