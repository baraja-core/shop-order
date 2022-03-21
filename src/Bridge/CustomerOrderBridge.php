<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Shop\Customer\OrderLoader;
use Baraja\Shop\Order\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class CustomerOrderBridge implements OrderLoader
{
	public function __construct(
		private EntityManagerInterface $entityManager,
	) {
	}


	/**
	 * @return array<int, array{id: int, number: string, price: string, date: \DateTimeImmutable}>
	 */
	public function getOrders(int $customerId): array
	{
		/** @var Order[] $orders */
		$orders = (new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(Order::class),
		))
			->createQueryBuilder('o')
			->where('o.customer = :customerId')
			->setParameter('customerId', $customerId)
			->getQuery()
			->getResult();

		$return = [];
		foreach ($orders as $order) {
			$date = $order->getInsertedDate();
			$return[] = [
				'id' => $order->getId(),
				'number' => $order->getNumber(),
				'price' => $order->getPrice()->render(true),
				'date' => new \DateTimeImmutable($date->format('Y-m-d H:i:s.u'), $date->getTimezone()),
			];
		}

		return $return;
	}
}
