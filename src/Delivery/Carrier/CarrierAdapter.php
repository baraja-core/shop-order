<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Delivery\Carrier;


use Baraja\Shop\Order\Entity\Order;

interface CarrierAdapter
{
	/**
	 * @return array<int, string>
	 */
	public function getCompatibleCarriers(): array;

	/**
	 * @param array<int, Order> $orders
	 */
	public function createPackages(array $orders): void;
}
