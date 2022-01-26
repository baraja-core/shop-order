<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\Shop\Order\Entity\OrderNotificationHistory;
use Doctrine\ORM\EntityRepository;

final class OrderNotificationHistoryRepository extends EntityRepository
{
	/**
	 * @return array<int, OrderNotificationHistory>
	 */
	public function getHistory(int $orderId): array
	{
		/** @var array<int, OrderNotificationHistory> $return */
		$return = $this->createQueryBuilder('nh')
			->select('nh, n, s')
			->join('nh.notification', 'n')
			->join('n.status', 's')
			->where('nh.order = :orderId')
			->setParameter('orderId', $orderId)
			->orderBy('nh.insertedDate', 'DESC')
			->getQuery()
			->getResult();

		return $return;
	}
}
