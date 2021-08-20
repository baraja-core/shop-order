<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\Status\OrderStatusChangedEvent;
use Baraja\Shop\Order\Status\OrderWorkflow;

final class OrderStatusManager
{
	/**
	 * @param OrderStatusChangedEvent[] $onChangeEvents
	 */
	public function __construct(
		private EntityManager $entityManager,
		private OrderWorkflow $workflow,
		private array $onChangeEvents = [],
	) {
	}


	/**
	 * @return OrderStatus[]
	 */
	public function getAllStatuses(): array
	{
		static $cache;
		if ($cache === null) {
			/** @var OrderStatus[] $cache */
			$cache = $this->entityManager->getRepository(OrderStatus::class)->findAll();
			if ($cache === []) {
				$this->initDefault();

				return $this->getAllStatuses();
			}
		}

		return $cache;
	}


	public function getStatusByCode(string $code): OrderStatus
	{
		foreach ($this->getAllStatuses() as $status) {
			if ($status->getCode() === $code) {
				return $status;
			}
		}

		throw new \InvalidArgumentException('Order status "' . $code . '" does not exist.');
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
		return [
			'all' => [
				'label' => 'VŠECHNY HODNOTY',
				'codes' => [],
			],
			'trzby' => [
				'label' => 'TRŽBY',
				'codes' => [],
			],
		];
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


	public function setStatus(Order $order, OrderStatus|string $status): void
	{
		$oldStatus = $order->getStatus();
		if (is_string($status)) {
			$status = $this->getStatusByCode($status);
		}

		$order->setStatus($status);
		$this->workflow->run($order);
		foreach ($this->onChangeEvents as $changedEvent) {
			$changedEvent->process($order, $oldStatus, $status);
		}

		$this->entityManager->flush();
	}


	public function initDefault(): void
	{
		foreach (OrderStatus::COMMON_STATUSES as $code) {
			$this->entityManager->persist(new OrderStatus($code, str_replace('-', ' ', $code)));
		}
		$this->entityManager->flush();
	}
}
