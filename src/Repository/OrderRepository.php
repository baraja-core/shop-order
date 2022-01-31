<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\Shop\Order\Entity\Order;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class OrderRepository extends EntityRepository
{
	/**
	 * @return array{
	 *     order: array{id: int, number: string, hash: string, locale: string, group: array{id: int, name: string}},
	 *     before: array{id: int, number: string}|null,
	 *     after: array{id: int, number: string}|null
	 * }
	 * @throws \InvalidArgumentException
	 */
	public function getSimplePluginInfo(int $id): array
	{
		/** @var array{0?: array{id: int, number: string, hash: string, locale: string, group: array{id: int, name: string}}} $orderResult */
		$orderResult = $this->createQueryBuilder('o')
			->select('PARTIAL o.{id, number, hash, locale}, PARTIAL group.{id, name}')
			->join('o.group', 'group')
			->where('o.id = :id')
			->setParameter('id', $id)
			->setMaxResults(1)
			->getQuery()
			->getArrayResult();

		$order = $orderResult[0] ?? null;
		if ($order === null) {
			throw new \InvalidArgumentException(sprintf('Order "%d" does not exist.', $id));
		}
		$groupId = $order['group']['id'] ?? null;

		return [
			'order' => $order,
			'before' => $this->getNextOrder($id, $groupId, 'DESC'),
			'after' => $this->getNextOrder($id, $groupId, 'ASC'),
		];
	}


	/**
	 * @return array{id: int, number: string}|null
	 */
	public function getNextOrder(int $id, ?int $groupId = null, string $direction): ?array
	{
		$qb = $this->createQueryBuilder('o')
			->select('PARTIAL o.{id, number}')
			->andWhere(sprintf('o.id %s :id', $direction !== 'ASC' ? '<' : '>'))
			->setParameter('id', $id);

		if ($groupId !== null) {
			$qb
				->andWhere('o.group = :groupId')
				->setParameter('groupId', $groupId);
		}

		/** @var array{0?: array{id: int, number: string}} $result */
		$return = $qb
			->orderBy('o.id', $direction)
			->setMaxResults(1)
			->getQuery()
			->getArrayResult();

		return $return[0] ?? null;
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getByHash(string $hash): Order
	{
		$return = $this->createQueryBuilder('o')
			->where('o.hash = :hash')
			->setParameter('hash', $hash)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof Order);

		return $return;
	}
}
