<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\EcommerceStandard\DTO\OrderStatusInterface;
use Baraja\Localization\Localization;
use Baraja\Shop\Order\Entity\OrderNotification;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class OrderNotificationRepository extends EntityRepository
{
	public function findByStatusAndType(
		OrderStatusInterface $status,
		string $locale,
		string $type,
		bool $activeOnly = false,
	): ?OrderNotification {
		try {
			$qb = $this->createQueryBuilder('n')
				->where('n.status = :status')
				->andWhere('n.locale = :locale')
				->andWhere('n.type = :type')
				->setParameter('status', $status->getId())
				->setParameter('locale', Localization::normalize($locale))
				->setParameter('type', $type);

			if ($activeOnly === true) {
				$qb->andWhere('n.active = TRUE');
			}

			$return = $qb
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
			assert($return instanceof OrderNotification);

			return $return;
		} catch (NoResultException | NonUniqueResultException) {
			// Silence is golden.
		}

		return null;
	}


	/**
	 * @param array<int, string> $types
	 * @return array<string, OrderNotification>
	 */
	public function getAllReadyToSend(string $locale, array $types = []): array
	{
		$qb = $this->createQueryBuilder('n')
			->andWhere('n.locale = :locale')
			->setParameter('locale', Localization::normalize($locale))
			->andWhere('n.active = TRUE');

		if ($types !== []) {
			$qb->andWhere('n.type IN (:types)')
				->setParameter('types', $types);
		}

		/** @var array<int, OrderNotification> $result */
		$result = $qb->getQuery()->getResult();

		$return = [];
		foreach ($result as $notification) {
			$key = sprintf(
				'%s-%s-%s',
				$notification->getLocale(),
				$notification->getStatus()->getCode(),
				$notification->getType(),
			);
			$return[$key] = $notification;
		}

		return $return;
	}
}
