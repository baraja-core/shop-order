<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Delivery;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderPackage;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class OrderDeliveryManager
{
	public function __construct(
		private EntityManager $entityManager,
		private OrderCarrierManager $carrierManager,
	) {
	}


	/**
	 * This method accepts an order, for which it establishes a shipment with the carrier
	 * based on the selected shipping method.
	 * If a shipment already exists, we will return the existing shipment and a new one will not be created.
	 * The carrier or shipping service may refuse to establish the shipment,
	 * in which case an exception will be thrown.
	 * In order to establish a shipment, the order must contain a valid transport
	 * that has been paired with a specific carrier.
	 *
	 * @param array<int, Order> $orders
	 */
	public function sendOrders(array $orders): void
	{
		if ($orders === []) {
			return;
		}
		$this->carrierManager->createPackages($orders);
	}


	public function getPackage(Order $order): ?OrderPackage
	{
		try {
			return $this->entityManager->getRepository(OrderPackage::class)
				->createQueryBuilder('package')
				->where('package.order = :orderId')
				->setParameter('orderId', $order->getId())
				->orderBy('package.insertedDate', 'DESC')
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			// Package does not exist.
		}

		return null;
	}
}
