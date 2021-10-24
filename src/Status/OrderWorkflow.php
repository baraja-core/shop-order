<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Status;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Emailer;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\Entity\OrderWorkflowEvent;
use Baraja\Shop\Order\InvoiceManagerInterface;
use Tracy\Debugger;
use Tracy\ILogger;

final class OrderWorkflow
{
	public function __construct(
		private EntityManager $entityManager,
		private Emailer $emailer,
		private ?InvoiceManagerInterface $invoiceManager = null,
	) {
	}


	public function run(Order $order): void
	{
		$status = $order->getStatus()->getCode();
		$this->processByStatus($order);
		if ($status === OrderStatus::STATUS_PAID) {
			$this->emailer->sendOrderPaid($order);
			try {
				$this->getInvoiceManager()->createInvoice($order);
			} catch (\Throwable $e) {
				Debugger::log($e, ILogger::CRITICAL);
			}
		} elseif ($status === OrderStatus::STATUS_PREPARING) {
			$this->emailer->sendOrderPreparing($order);
		} elseif ($status === OrderStatus::STATUS_PREPARED) {
			$this->emailer->sendOrderPrepared($order);
		} elseif ($status === OrderStatus::STATUS_SENT) {
			$this->emailer->sendOrderSent($order);
		} elseif ($status === OrderStatus::STATUS_DONE) {
			if (PHP_SAPI !== 'cli' && $this->getInvoiceManager()->isInvoice($order) === false) {
				try {
					$this->getInvoiceManager()->createInvoice($order);
				} catch (\Throwable $e) {
					Debugger::log($e, ILogger::CRITICAL);
				}
			}
		} elseif ($status === OrderStatus::STATUS_STORNO) {
			$this->emailer->sendOrderStorno($order);
		}
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
			$this->processEvent($order, $event);
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
				if ($now - $order->getUpdatedDate()->getTimestamp() > $interval) {
					$this->processEvent($order, $automatedEvent);
				}
			}
		}
	}


	public function processEvent(Order $order, OrderWorkflowEvent $event): void
	{
		if ($event->isIgnoreIfPinged() && $order->isPinged()) {
			return;
		}
		$newStatus = $event->getNewStatus();
		if ($newStatus !== null) {
			$order->setStatus($newStatus);
		}
		$emailTemplate = $event->getEmailTemplate();
		if ($emailTemplate !== null) {
			$this->emailer->sendTemplate($order, $emailTemplate);
		}
		if ($event->isMarkAsPinged()) {
			$order->setPinged();
		}
	}


	private function getInvoiceManager(): InvoiceManagerInterface
	{
		if ($this->invoiceManager === null) {
			throw new \LogicException('Invoice manager does not exist, but it is mandatory.');
		}

		return $this->invoiceManager;
	}
}
