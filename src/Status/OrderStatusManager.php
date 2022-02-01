<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\Entity\OrderStatusCollection;
use Baraja\Shop\Order\Entity\OrderStatusHistory;
use Baraja\Shop\Order\Repository\OrderStatusHistoryRepository;
use Baraja\Shop\Order\Repository\OrderStatusRepository;
use Baraja\Shop\Order\Status\OrderStatusChangedEvent;
use Baraja\Shop\Order\Status\OrderWorkflow;
use Doctrine\ORM\EntityManagerInterface;

final class OrderStatusManager
{
	private OrderStatusRepository $orderStatusRepository;

	private OrderStatusHistoryRepository $orderStatusHistoryRepository;


	/**
	 * @param OrderStatusChangedEvent[] $onChangeEvents
	 */
	public function __construct(
		private EntityManagerInterface $entityManager,
		private OrderWorkflow $workflow,
		private array $onChangeEvents = [],
	) {
		$orderStatusRepository = $entityManager->getRepository(OrderStatus::class);
		$orderStatusHistoryRepository = $entityManager->getRepository(OrderStatusHistory::class);
		assert($orderStatusRepository instanceof OrderStatusRepository);
		assert($orderStatusHistoryRepository instanceof OrderStatusHistoryRepository);
		$this->orderStatusRepository = $orderStatusRepository;
		$this->orderStatusHistoryRepository = $orderStatusHistoryRepository;
	}


	/**
	 * @return OrderStatus[]
	 */
	public function getAllStatuses(): array
	{
		static $cache;
		if ($cache === null) {
			$cache = $this->orderStatusRepository->getAll();
			if ($cache === []) {
				$this->initDefault();

				return $this->getAllStatuses();
			}
		}

		return $cache;
	}


	public function getStatusById(int $id): OrderStatus
	{
		foreach ($this->getAllStatuses() as $status) {
			if ($status->getId() === $id) {
				return $status;
			}
		}

		throw new \InvalidArgumentException(sprintf('Order status "%d" does not exist.', $id));
	}


	public function getStatusByCode(string $code): OrderStatus
	{
		$code = strtolower($code);
		foreach ($this->getAllStatuses() as $status) {
			if ($status->getCode() === $code) {
				return $status;
			}
		}

		throw new \InvalidArgumentException(sprintf('Order status "%s" does not exist.', $code));
	}


	/**
	 * @return array<string, string>
	 */
	public function getKeyValueList(bool $collections = false): array
	{
		$return = [];
		if ($collections === true) {
			foreach ($this->getCollections() as $collectionCode => $collection) {
				$return[$collectionCode] = $collection['label'];
			}
		}
		foreach ($this->getAllStatuses() as $status) {
			$return[$status->getCode()] = $status->getName();
		}

		return $return;
	}


	/**
	 * @return array<string, array{label: string, codes:array<int, string>}>
	 */
	public function getCollections(): array
	{
		static $cache;
		if ($cache === null) {
			/** @var OrderStatusCollection[] $collections */
			$collections = $this->entityManager->getRepository(OrderStatusCollection::class)->findAll();
			$cache = [];
			foreach ($collections as $collection) {
				$cache[$collection->getCode()] = [
					'label' => $collection->getLabel(),
					'codes' => $collection->getCodes(),
				];
			}
		}

		return $cache;
	}


	public function isRegularStatus(string $code): bool
	{
		foreach ($this->getAllStatuses() as $status) {
			if ($status->getCode() === $code) {
				return true;
			}
		}

		return false;
	}


	public function isCollection(string $code): bool
	{
		return isset($this->getCollections()[$code]);
	}


	public function setStatus(Order $order, OrderStatus|string $status, bool $force = false): void
	{
		$oldStatus = $order->getStatus();
		if (is_string($status)) {
			try {
				$status = $this->getStatusByCode($status);
			} catch (\InvalidArgumentException) {
				$status = $this->createStatus($status, $status);
			}
		}
		if ($oldStatus->getId() === $status->getId()) {
			return;
		}

		$this->entityManager->persist(new OrderStatusHistory($order, $status));

		$redirect = $status->getRedirectTo();
		if ($redirect !== null) {
			$this->entityManager->persist(new OrderStatusHistory($order, $redirect));
			$order->setStatus($redirect);
		} else {
			$order->setStatus($status);
		}

		$this->workflow->run($order);
		foreach ($this->onChangeEvents as $changedEvent) {
			$changedEvent->process($order, $oldStatus, $status);
		}

		$this->entityManager->flush();
	}


	public function createStatus(string $name, string $code): OrderStatus
	{
		$status = new OrderStatus($name, $code);
		$this->entityManager->persist($status);
		$this->entityManager->flush();

		return $status;
	}


	/**
	 * @param array<int, string> $statuses
	 */
	public function createCollection(string $code, string $label, array $statuses): OrderStatusCollection
	{
		$statusList = [];
		foreach ($statuses as $statusCode) {
			$statusList[] = $this->getStatusByCode($statusCode)->getCode();
		}
		$collection = new OrderStatusCollection($code, $label, $statusList);
		$this->entityManager->persist($collection);
		$this->entityManager->flush();

		return $collection;
	}


	/**
	 * @return array<int, OrderStatusHistory>
	 */
	public function getHistory(OrderInterface $order): array
	{
		return $this->orderStatusHistoryRepository->getHistory($order->getId());
	}


	public function initDefault(): void
	{
		$position = 1;
		foreach (OrderStatus::COMMON_STATUSES as $code) {
			$status = new OrderStatus($code, str_replace('-', ' ', $code));
			$status->setWorkflowPosition($position);
			$this->entityManager->persist($status);
			$position++;
		}
		$this->entityManager->flush();
	}
}
