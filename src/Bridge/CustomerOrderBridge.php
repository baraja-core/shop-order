<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Customer\OrderLoader;
use Baraja\Shop\Order\Entity\Order;
use Nette\Utils\DateTime;

final class CustomerOrderBridge implements OrderLoader
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	/**
	 * @return array<int, array{id: int, number: string, price: float, date: \DateTime}>
	 */
	public function getOrders(int $customerId): array
	{
		/** @var Order[] $orders */
		$orders = $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->where('o.customer = :customerId')
			->setParameter('customerId', $customerId)
			->getQuery()
			->getResult();

		$return = [];
		foreach ($orders as $order) {
			$return[] = [
				'id' => (int) $order->getId(),
				'number' => $order->getNumber(),
				'price' => $order->getPrice(),
				'date' => DateTime::from($order->getInsertedDate()),
			];
		}

		return $return;
	}
}
