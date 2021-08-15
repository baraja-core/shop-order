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
	public function getKeyValueList(): array
	{
		$return = [];
		foreach ($this->getAllStatuses() as $status) {
			$return[$status->getCode()] = $status->getName();
		}

		return $return;
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
		$this->entityManager->persist(new OrderStatus(OrderStatus::STATUS_NEW, 'New'));
		$this->entityManager->persist(new OrderStatus(OrderStatus::STATUS_SENT, 'Sent'));
		$this->entityManager->persist(new OrderStatus(OrderStatus::STATUS_DONE, 'Done'));
		$this->entityManager->persist(new OrderStatus(OrderStatus::STATUS_STORNO, 'Storno'));
		$this->entityManager->persist(new OrderStatus(OrderStatus::STATUS_TEST, 'Test'));
		$this->entityManager->persist(new OrderStatus(OrderStatus::STATUS_RETURNED, 'Returned'));
		$this->entityManager->flush();
	}
}
