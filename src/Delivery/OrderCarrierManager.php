<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Delivery;


use Baraja\Shop\Order\Delivery\Carrier\CarrierAdapter;
use Baraja\Shop\Order\Entity\Order;

final class OrderCarrierManager
{
	/** @var array<int, CarrierAdapter> */
	private array $carrierAdapters = [];


	/**
	 * @param array<int, Order> $orders
	 */
	public function createPackages(array $orders): void
	{
		if ($orders === []) {
			return;
		}
		$relevantOrders = [];
		foreach ($orders as $order) { // ignore orders with valid package
			if (count($order->getPackages()) === 0) {
				$relevantOrders[] = $order;
			}
		}
		$carrier = $this->getSingleCarrier($relevantOrders);
		$adapterService = $this->resolveCarrierAdapterByCarrier($carrier);
		$adapterService->createPackages($relevantOrders);
	}


	public function addAdapter(CarrierAdapter $adapter): void
	{
		$this->carrierAdapters[] = $adapter;
	}


	/**
	 * @return array<int, string>
	 */
	public function getSupportedCarriers(): array
	{
		$return = [];
		foreach ($this->carrierAdapters as $adapter) {
			foreach ($adapter->getCompatibleCarriers() as $carrier) {
				$return[$carrier] = true;
			}
		}

		return array_keys($return);
	}


	private function resolveCarrierAdapterByCarrier(string $carrier): CarrierAdapter
	{
		$adapterService = null;
		foreach ($this->carrierAdapters as $adapter) {
			if (in_array($carrier, $adapter->getCompatibleCarriers(), true)) {
				$adapterService = $adapter;
				break;
			}
		}
		if ($adapterService === null) {
			throw new \InvalidArgumentException('Adapter service does not exist for carrier "' . $carrier . '".');
		}

		return $adapterService;
	}


	/**
	 * @param array<int, Order> $orders
	 */
	private function getSingleCarrier(array $orders): string
	{
		$carrier = null;
		foreach ($orders as $order) {
			$delivery = $order->getDelivery();
			$orderCarrier = $delivery !== null ? $delivery->getCarrier() : null;
			if ($orderCarrier === null) {
				throw new \InvalidArgumentException('Carrier for order "' . $order->getId() . '" does not exist.');
			}
			if ($carrier === null) {
				$carrier = $orderCarrier;
			} elseif ($orderCarrier !== $carrier) {
				throw new \InvalidArgumentException(
					'These orders cannot be sent at the same time. Always select only one carrier.',
				);
			}
		}
		assert($carrier !== null);

		return $carrier;
	}
}
