<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\EcommerceStandard\DTO\InvoiceInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\Service\InvoiceManagerInterface;
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

	/** @var array<int, OrderStatus> */
	private array $statusList = [];


	/**
	 * @param OrderStatusChangedEvent[] $onChangeEvents
	 */
	public function __construct(
		private EntityManagerInterface $entityManager,
		private OrderWorkflow $workflow,
		private ?InvoiceManagerInterface $invoiceManager = null,
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
		if ($this->statusList === []) {
			$this->statusList = $this->orderStatusRepository->getAll();
			if ($this->statusList === []) {
				$this->initDefault();

				return $this->getAllStatuses();
			}
		}

		return $this->statusList;
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


	public function setStatus(OrderInterface $order, OrderStatus|string $status, bool $force = false): void
	{
		assert($order instanceof Order);
		$oldStatus = $order->getStatus();
		if (is_string($status)) {
			try {
				$status = $this->getStatusByCode($status);
			} catch (\InvalidArgumentException $e) {
				if ($force) {
					$status = $this->createStatus($status, $status);
				} else {
					throw $e;
				}
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
		if ($status->isMarkAsPaid()) {
			$order->setPaid(true);
		}
		$attachments = [];
		if (
			$status->isCreateInvoice()
			|| $status->getCode() === OrderStatus::STATUS_PAID
			|| (
				$status->getCode() === OrderStatus::STATUS_DONE
				&& PHP_SAPI !== 'cli'
				&& $this->invoiceManager->isInvoice($order) === false
			)
		) {
			$attachments[] = $this->invoiceManager->getInvoicePath($this->createInvoice($order));
		}

		$this->workflow->run($order, $attachments);
		foreach ($this->onChangeEvents as $changedEvent) {
			$changedEvent->process($order, $oldStatus, $status);
		}

		$this->entityManager->flush();
	}


	public function createStatus(string $name, string $code): OrderStatus
	{
		$status = new OrderStatus($name, $code);

		$topPosition = 1;
		foreach ($this->getAllStatuses() as $statusItem) {
			$statusPosition = $statusItem->getWorkflowPosition();
			if ($statusPosition > $topPosition) {
				$topPosition = $statusPosition;
			}
		}

		$status->setWorkflowPosition($topPosition + 1);
		$this->entityManager->persist($status);
		$this->entityManager->flush();

		// update cache
		$this->statusList = [];
		$this->getAllStatuses();

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


	private function createInvoice(Order $order): InvoiceInterface
	{
		if ($this->invoiceManager === null) {
			throw new \LogicException('Invoice manager does not exist, but it is mandatory.');
		}
		if ($this->invoiceManager->isInvoice($order) === false) {
			return $this->invoiceManager->createInvoice($order);
		}

		return $this->invoiceManager->getByOrder($order);
	}
}
