<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\Shop\Order\Entity\OrderOnlinePayment;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class OrderOnlinePaymentRepository extends EntityRepository
{
	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getByGoPayIdAndHash(string $hash, int $id = null): OrderOnlinePayment
	{
		$return = $this->createQueryBuilder('payment')
			->leftJoin('payment.order', 'o')
			->where('payment.gopayId = :gopayId')
			->andWhere('o.hash = :orderHash')
			->setParameter('gopayId', $id)
			->setParameter('orderHash', $hash)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof OrderOnlinePayment);

		return $return;
	}
}
