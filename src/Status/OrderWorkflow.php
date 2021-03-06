<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Status;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderWorkflowEvent;
use Baraja\Shop\Order\Notification\OrderNotification;

final class OrderWorkflow
{
	public function __construct(
		private EntityManager $entityManager,
		private OrderNotification $notification,
	) {
	}


	/**
	 * @param array<int, string> $attachments
	 */
	public function run(Order $order, array $attachments = []): void
	{
		$this->processByStatus($order);
		$this->notification->sendEmail($order, attachments: $attachments);
		$this->notification->sendSms($order);
	}


	public function getIntervalForCancelOrder(): int
	{
		return 1_814_400; // 21 days
	}


	public function getIntervalForPingOrder(): int
	{
		return 604_800; // 7 days
	}


	public function processByStatus(Order $order): void
	{
		/** @var OrderWorkflowEvent[] $events */
		$events = $this->entityManager->getRepository(OrderWorkflowEvent::class)
			->createQueryBuilder('e')
			->where('e.status = :statusId')
			->andWhere('e.activeFrom >= :activeFrom')
			->andWhere('e.active = TRUE')
			->andWhere('e.automaticInterval IS NULL')
			->setParameter('statusId', $order->getStatus()->getId())
			->setParameter('activeFrom', new \DateTimeImmutable)
			->orderBy('e.priority', 'DESC')
			->getQuery()
			->getResult();

		foreach ($events as $event) {
			if ($this->processEvent($order, $event) && $event->isStopWorkflowIfMatch()) {
				break;
			}
		}
		$this->entityManager->flush();
	}


	public function processAutomatedWorkflow(): void
	{
		/** @var OrderWorkflowEvent[] $automatedEvents */
		$automatedEvents = $this->entityManager->getRepository(OrderWorkflowEvent::class)
			->createQueryBuilder('e')
			->andWhere('e.activeFrom >= :activeFrom')
			->andWhere('e.active = TRUE')
			->andWhere('e.automaticInterval IS NOT NULL')
			->andWhere('e.automaticInterval > 0')
			->setParameter('activeFrom', new \DateTimeImmutable)
			->orderBy('e.priority', 'DESC')
			->getQuery()
			->getResult();

		$now = time();
		foreach ($automatedEvents as $automatedEvent) {
			/** @var Order[] $orders */
			$orders = $this->entityManager->getRepository(Order::class)
				->createQueryBuilder('o')
				->where('o.status = :statusId')
				->andWhere('o.insertedDate - o.updatedDate')
				->setParameter('statusId', $automatedEvent->getId())
				->getQuery()
				->getResult();

			$interval = (int) $automatedEvent->getAutomaticInterval();
			foreach ($orders as $order) {
				if (
					$now - $order->getUpdatedDate()->getTimestamp() > $interval
					&& $this->processEvent($order, $automatedEvent)
					&& $automatedEvent->isStopWorkflowIfMatch()
				) {
					break;
				}
			}
		}
	}


	public function processEvent(Order $order, OrderWorkflowEvent $event): bool
	{
		if ($event->isIgnoreIfPinged() && $order->isPinged()) {
			return false;
		}
		$action = false;
		$newStatus = $event->getNewStatus();
		if ($newStatus !== null) {
			$order->setStatus($newStatus);
			$action = true;
		}
		if ($event->isSendNotification()) {
			// TODO: Send status notification
			$action = true;
		}
		if ($event->isMarkAsPinged()) {
			$order->setPinged();
			$action = true;
		}

		return $action;
	}


	/**
	 * @return array<int, OrderWorkflowEvent>
	 */
	public function getEvents(): array
	{
		return $this->entityManager->getRepository(OrderWorkflowEvent::class)
			->createQueryBuilder('e')
			->orderBy('e.active', 'DESC')
			->addOrderBy('e.status', 'ASC')
			->addOrderBy('e.priority', 'DESC')
			->getQuery()
			->getResult();
	}
}
