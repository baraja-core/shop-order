<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\EcommerceStandard\DTO\OrderStatusInterface;
use Baraja\Localization\Localization;
use Baraja\Shop\Order\Entity\OrderNotification;
use Baraja\Shop\Order\Entity\OrderNotificationType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class OrderNotificationRepository extends EntityRepository
{
	public function findByStatusAndType(
		OrderStatusInterface $status,
		string $locale,
		OrderNotificationType $type,
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


	public function getById(int $id): OrderNotification
	{
		$return = $this->createQueryBuilder('n')
			->select('n, status')
			->join('n.status', 'status')
			->where('n.id = :id')
			->setParameter('id', $id)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof OrderNotification);

		return $return;
	}


	/**
	 * @param array<int, OrderNotificationType> $types
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
				$notification->getType()->value,
			);
			$return[$key] = $notification;
		}

		return $return;
	}


	/**
	 * @return array<int, array{id: int, type: OrderNotificationType, status: string, label: string}>
	 */
	public function getActiveStatusTypes(string $locale): array
	{
		/** @var array<int, OrderNotification> $result */
		$result = $this->createQueryBuilder('n')
			->select('n, status')
			->join('n.status', 'status')
			->andWhere('n.active = TRUE')
			->andWhere('n.locale = :locale')
			->setParameter('locale', $locale)
			->orderBy('status.workflowPosition', 'ASC')
			->getQuery()
			->getResult();

		$return = [];
		foreach ($result as $notification) {
			$return[] = [
				'id' => $notification->getId(),
				'type' => $notification->getType(),
				'status' => $notification->getStatus()->getCode(),
				'label' => $notification->getStatus()->getLabel(),
			];
		}

		return $return;
	}
}
