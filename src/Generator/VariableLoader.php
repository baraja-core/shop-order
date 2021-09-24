<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderGroup;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class VariableLoader implements \Baraja\VariableGenerator\VariableLoader
{
	public function __construct(
		private EntityManagerInterface $entityManager,
		private OrderGroup $group,
	) {
	}


	public function getCurrent(?\DateTime $findFromDate = null): ?string
	{
		$selector = (new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(Order::class),
		))
			->createQueryBuilder('o')
			->select('o.number')
			->andWhere('o.insertedDate > :preferenceInsertedDateFrom')
			->andWhere('o.group = :groupId')
			->setParameter(
				'preferenceInsertedDateFrom',
				$findFromDate === null
					? (date('Y') - 1) . '-' . date('m-d')
					: $findFromDate->format('Y-m-d'),
			)
			->setParameter('groupId', $this->group->getId())
			->orderBy('o.number', 'DESC')
			->setMaxResults(1);

		try {
			return (string) $selector->getQuery()->getSingleScalarResult();
		} catch (\Throwable) {
			// Silence is golden.
		}

		return null;
	}
}
