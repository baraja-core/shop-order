<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Delivery;


use App\BalikobotManager;
use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderPackage;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Tracy\Debugger;
use Tracy\ILogger;

final class OrderDeliveryManager
{
	public function __construct(
		private EntityManager $entityManager,
		private OrderCarrierManager $carrierManager,
	) {
	}


	public function sendOrder(Order $order): OrderPackage
	{
		$delivery = $order->getDelivery();
		if ($delivery->getBotShipper() === null || $delivery->getCarrier() === null) {
			throw new \InvalidArgumentException(
				'Order ' . $order->getNumber() . ' is delivered to the store, it cannot be shipped by carrier.',
			);
		}

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



		$this->entityManager->flush();
	}
}
