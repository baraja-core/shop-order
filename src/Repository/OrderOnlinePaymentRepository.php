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
	public function getByGoPayIdAndHash(string $hash, ?string $gatewayId = null): OrderOnlinePayment
	{
		$return = $this->createQueryBuilder('payment')
			->leftJoin('payment.order', 'o')
			->where('payment.gatewayId = :gatewayId')
			->andWhere('o.hash = :orderHash')
			->setParameter('gatewayId', (string) $gatewayId)
			->setParameter('orderHash', $hash)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof OrderOnlinePayment);

		return $return;
	}


	/**
	 * @return OrderOnlinePayment[]
	 */
	public function getUnresolvedPayments(string $interval = '1 hour'): array
	{
		$findFrom = new \DateTimeImmutable(sprintf('now - %s', $interval));

		/** @var OrderOnlinePayment[] $payments */
		$payments = $this->createQueryBuilder('payment')
			->select('payment, o')
			->leftJoin('payment.order', 'o')
			->where('payment.status IS NULL OR payment.status NOT IN (:successStatuses)')
			->andWhere('payment.insertedDate < :minInsertedDate')
			->setParameter('successStatuses', ['PAID', 'TIMEOUTED', 'CANCELED'])
			->setParameter('minInsertedDate', $findFrom->format('Y-m-d H:i:s'))
			->getQuery()
			->getResult();

		return $payments;
	}
}
