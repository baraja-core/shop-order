<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\Shop\Order\Entity\OrderStatusHistory;
use Doctrine\ORM\EntityRepository;

final class OrderStatusHistoryRepository extends EntityRepository
{
	/**
	 * @return array<int, OrderStatusHistory>
	 */
	public function getHistory(int $orderId): array
	{
		/** @var array<int, OrderStatusHistory> $return */
		$return = $this->createQueryBuilder('sh')
			->select('sh, s')
			->join('sh.status', 's')
			->where('sh.order = :orderId')
			->setParameter('orderId', $orderId)
			->orderBy('sh.insertedDate', 'DESC')
			->getQuery()
			->getResult();

		return $return;
	}
}
