<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class OrderRepository extends EntityRepository
{
	public const
		VAT_EXPORT_FILTER_INSERTED_DATE = 'insertedDate',
		VAT_EXPORT_FILTER_INVOICE_DATE = 'invoiceDate';

	public const VAT_EXPORT_FILTERS = [
		self::VAT_EXPORT_FILTER_INSERTED_DATE,
		self::VAT_EXPORT_FILTER_INVOICE_DATE,
	];

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
	public function getNextOrder(int $id, ?int $groupId, string $direction): ?array
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

		/** @var array{0?: array{id: int, number: string}} $return */
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
	public function getById(int $id): Order
	{
		$return = $this->createQueryBuilder('o')
			->where('o.id = :id')
			->setParameter('id', $id)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof Order);

		return $return;
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


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getAllByHash(string $hash): Order
	{
		$return = $this->createQueryBuilder('o')
			->select('o, customer, deliveryAddress, invoiceAddress, delivery, payment')
			->leftJoin('o.customer', 'customer')
			->leftJoin('o.deliveryAddress', 'deliveryAddress')
			->leftJoin('o.paymentAddress', 'invoiceAddress')
			->leftJoin('o.delivery', 'delivery')
			->leftJoin('o.payment', 'payment')
			->where('o.hash = :hash')
			->setParameter('hash', $hash)
			->getQuery()
			->getSingleResult();
		assert($return instanceof Order);

		return $return;
	}


	/**
	 * @return array<int, Order>
	 */
	public function getOrderForCheckPayment(?string $currencyCode = null): array
	{
		$qb = $this->createQueryBuilder('orderEntity')
			->join('orderEntity.status', 'status')
			->where('orderEntity.paid = FALSE')
			->andWhere('status.code = :code')
			->setParameter('code', OrderStatus::STATUS_NEW);

		if ($currencyCode !== null) {
			$qb->join('orderEntity.currency', 'currency')
				->andWhere('currency.code = :currencyCode')
				->setParameter('currencyCode', $currencyCode);
		}

		return $qb->getQuery()->getResult();
	}


	/**
	 * @param array<int, string> $statuses
	 * @return array<int, array<string, mixed>>
	 */
	public function getBasicVatExport(
		\DateTimeInterface $from,
		\DateTimeInterface $to,
		array $statuses,
		?string $filterBy = null,
	): array {
		if ($filterBy !== null && in_array($filterBy, self::VAT_EXPORT_FILTERS, true) === false) {
			throw new \InvalidArgumentException(sprintf('Invalid filter name, because "%s" given.', $filterBy));
		}

		$orderSelection = $this->createQueryBuilder('o')
			->select('o, payment, address, PARTIAL country.{id, isoCode}, PARTIAL status.{id, code}')
			->join('o.payment', 'payment')
			->join('o.paymentAddress', 'address')
			->join('address.country', 'country')
			->join('o.status', 'status')
			->andWhere('status.code IN (:statuses)')
			->setParameter('statuses', $statuses)
			->orderBy('o.insertedDate', 'DESC');

		if ($filterBy === self::VAT_EXPORT_FILTER_INSERTED_DATE) {
			$orderSelection
				->andWhere('o.insertedDate >= :dateFrom')
				->andWhere('o.insertedDate < :dateTo')
				->setParameter('dateFrom', $from->format('Y-m-d 00:00:00'))
				->setParameter('dateTo', $to->format('Y-m-d 00:00:00'));
		}

		/** @var array<int, array{id: int}> $orders */
		$orders = $orderSelection->getQuery()->getArrayResult();

		$invoiceSelection = (new EntityRepository(
			$this->_em,
			$this->_em->getClassMetadata('Baraja\Shop\Invoice\Entity\Invoice'),
		))
			->createQueryBuilder('i')
			->select('i, PARTIAL o.{id}')
			->join('i.order', 'o')
			->where('o.id IN (:ids)')
			->setParameter('ids', array_map(static fn (array $order): int => $order['id'], $orders));

		if ($filterBy === self::VAT_EXPORT_FILTER_INVOICE_DATE) {
			$invoiceSelection->andWhere('i.insertedDate >= :dateFrom')
				->andWhere('i.insertedDate < :dateTo')
				->setParameter('dateFrom', $from->format('Y-m-d 00:00:00'))
				->setParameter('dateTo', $to->format('Y-m-d 00:00:00'));
		}

		/** @var array<int, array{id: int, order: array{id: int}}> $invoiceList */
		$invoiceList = $invoiceSelection->getQuery()->getArrayResult();

		$orderIdToInvoice = [];
		foreach ($invoiceList as $invoice) {
			$orderId = $invoice['order']['id'];
			unset($invoice['order']);
			if (isset($orderIdToInvoice[$orderId]) === false) {
				$orderIdToInvoice[$orderId] = [];
			}
			$orderIdToInvoice[$orderId][] = $invoice;
		}

		$return = [];
		foreach ($orders as $order) {
			$invoices = $orderIdToInvoice[$order['id']] ?? [];
			if ($filterBy === self::VAT_EXPORT_FILTER_INVOICE_DATE && $invoices === []) {
				continue;
			}
			$order['invoices'] = $invoices;
			$return[] = $order;
		}

		return $return;
	}
}
