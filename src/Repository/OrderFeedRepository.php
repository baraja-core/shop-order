<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\Doctrine\EntityManager;
use Baraja\EcommerceStandard\DTO\InvoiceInterface;
use Baraja\Search\Search;
use Baraja\Shop\Invoice\Entity\Invoice;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\OrderGroupManager;
use Baraja\Shop\Order\OrderStatusManager;

final class OrderFeedRepository
{
	private OrderInvoiceRepository $invoiceRepository;


	public function __construct(
		private EntityManager $entityManager,
		private OrderStatusManager $statusManager,
		private OrderGroupManager $orderGroupManager,
		private Search $search,
	) {
		if (class_exists(Invoice::class) === false) {
			throw new \LogicException('Package "baraja-core/shop-invoice" has not been installed.');
		}
		/** @phpstan-ignore-next-line */
		$invoiceRepository = $entityManager->getRepository(Invoice::class);
		assert($invoiceRepository instanceof OrderInvoiceRepository);
		$this->invoiceRepository = $invoiceRepository;
	}


	/**
	 * @return array{
	 *     orders: array<int, Order>,
	 *     invoices: array<int, InvoiceInterface>,
	 *     count: int
	 * }
	 */
	public function getFeed(
		?string $query = null,
		?string $status = null,
		?int $delivery = null,
		?int $payment = null,
		?string $orderBy = null,
		?string $dateFrom = null,
		?string $dateTo = null,
		?string $currency = null,
		?string $group = null,
		int $limit = 128,
		int $page = 1,
	): array {
		$orderCandidateSelection = $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->select('PARTIAL o.{id}')
			->join('o.status', 'status')
			->join('o.group', 'orderGroup');

		if ($orderBy !== null) {
			if ($orderBy === 'old') {
				$orderCandidateSelection->orderBy('o.updatedDate', 'ASC');
			} elseif ($orderBy === 'number') {
				$orderCandidateSelection->orderBy('o.number', 'ASC');
			} elseif ($orderBy === 'number-desc') {
				$orderCandidateSelection->orderBy('o.number', 'DESC');
			}
		} else {
			$orderCandidateSelection->orderBy('o.number', 'DESC');
		}
		if ($query !== null) {
			$query = trim($query);
			if (preg_match('/^\d+$/', $query) === 1) {
				$orderCandidateSelection->andWhere('o.number LIKE :queryLeft OR o.number LIKE :queryRight')
					->setParameter('queryLeft', $query . '%')
					->setParameter('queryRight', '%' . $query);
			} else {
				$orderCandidateSelection->andWhere('o.id IN (:searchIds)')
					->setParameter(
						'searchIds',
						$this->search->selectorBuilder($query)
							->addEntity(Order::class)
							->addColumn('number')
							->addColumn('notice')
							->addColumn('customer.email')
							->addColumn('customer.firstName')
							->addColumn('customer.lastName')
							->search(useAnalytics: false)
							->getIds(),
					);
			}
		}
		if ($status === null) { // find all
			$orderCandidateSelection->andWhere('status.code != :statusDone')
				->andWhere('status.code != :statusStorno')
				->andWhere('status.code != :statusTest')
				->setParameter('statusDone', OrderStatus::STATUS_DONE)
				->setParameter('statusStorno', OrderStatus::STATUS_STORNO)
				->setParameter('statusTest', OrderStatus::STATUS_TEST);
		} elseif ($this->statusManager->isRegularStatus($status)) {
			$orderCandidateSelection->andWhere('status.code = :status')
				->setParameter('status', $status);
		} elseif ($this->statusManager->isCollection($status)) {
			$collections = $this->statusManager->getCollections();
			assert(isset($collections[$status]['codes']));
			$orderCandidateSelection
				->andWhere('status.code IN (:statusCollectionCodes)')
				->setParameter('statusCollectionCodes', $collections[$status]['codes']);
		} else {
			throw new \InvalidArgumentException(
				sprintf('Status "%s" is not valid regular status code or collection.', $status),
			);
		}
		if ($dateFrom !== null) {
			$orderCandidateSelection->andWhere('o.insertedDate >= :dateFrom')
				->setParameter('dateFrom', $dateFrom . ' 00:00:00');
		}
		if ($dateTo !== null) {
			$orderCandidateSelection->andWhere('o.insertedDate <= :dateTo')
				->setParameter('dateTo', $dateTo . ' 23:59:59');
		}
		if ($delivery !== null) {
			$orderCandidateSelection->andWhere('o.delivery = :delivery')
				->setParameter('delivery', $delivery);
		}
		if ($payment !== null) {
			$orderCandidateSelection->andWhere('o.payment = :payment')
				->setParameter('payment', $payment);
		}
		if ($currency !== null) {
			$orderCandidateSelection->join('o.currency', 'currency')
				->andWhere('currency.code = :currencyCode')
				->setParameter('currencyCode', $currency);
		}
		$orderCandidateSelection->andWhere('orderGroup.code = :groupCode')
			->setParameter(
				'groupCode',
				$group ?? $this->orderGroupManager->getDefaultGroup()->getCode(),
			);

		$count = (int) (clone $orderCandidateSelection)
			->orderBy('o.id', 'DESC')
			->select('COUNT(o.id)')
			->getQuery()
			->getSingleScalarResult();

		$orderCandidates = $orderCandidateSelection
			->setMaxResults($limit)
			->setFirstResult($limit * ($page - 1))
			->getQuery()
			->getArrayResult();

		$candidateIds = array_map(static fn(array $order): int => (int) $order['id'], $orderCandidates);

		/** @var Order[] $orders */
		$orders = $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->select('PARTIAL o.{id, hash, number, paid, pinged, price, currency, sale, insertedDate, updatedDate, notice, deliveryPrice, paymentPrice}')
			->addSelect('PARTIAL status.{id, code, label, workflowPosition, color}')
			->addSelect('PARTIAL customer.{id, email, firstName, lastName, phone, premium, ban}')
			->addSelect('PARTIAL item.{id, count, price, sale, label}')
			->addSelect('PARTIAL currency.{id, code, symbol}')
			->addSelect('PARTIAL product.{id, name}')
			->addSelect('PARTIAL delivery.{id, name, price, color}')
			->addSelect('PARTIAL payment.{id, name, price, color}')
			->addSelect('productVariant')
			->addSelect('PARTIAL paymentReal.{id}')
			->addSelect('PARTIAL package.{id}')
			->join('o.status', 'status')
			->join('o.customer', 'customer')
			->leftJoin('o.items', 'item')
			->join('o.currency', 'currency')
			->leftJoin('o.delivery', 'delivery')
			->leftJoin('o.payment', 'payment')
			->leftJoin('item.product', 'product')
			->leftJoin('item.variant', 'productVariant')
			->leftJoin('o.payments', 'paymentReal')
			->leftJoin('o.packages', 'package')
			->where('o.id IN (:ids)')
			->setParameter('ids', $candidateIds)
			->orderBy('o.number', 'DESC')
			->getQuery()
			->getResult();

		return [
			'orders' => $orders,
			'invoices' => $this->invoiceRepository->getInvoicesByOrderIds($candidateIds),
			'count' => $count,
		];
	}
}
