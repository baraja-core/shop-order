<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\EcommerceStandard\DTO\OrderStatusInterface;
use Baraja\Shop\Order\Entity\OrderNotification;
use Doctrine\ORM\EntityRepository;

final class OrderNotificationRepository extends EntityRepository
{
	public function findByStatusAndType(OrderStatusInterface $status, string $type): ?OrderNotification
	{
		$return = $this->createQueryBuilder('n')
			->where('n.status = :status')
			->andWhere('n.type = :type')
			->setParameter('status', $status->getId())
			->setParameter('type', $type)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof OrderNotification || $return === null);

		return $return;
	}
}
