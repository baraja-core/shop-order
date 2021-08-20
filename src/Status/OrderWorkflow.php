<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Status;


use Baraja\Shop\Order\Emailer;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\InvoiceManagerInterface;
use Tracy\Debugger;
use Tracy\ILogger;

final class OrderWorkflow
{
	public function __construct(
		private Emailer $emailer,
		private ?InvoiceManagerInterface $invoiceManager = null,
	) {
	}


	public function run(Order $order): void
	{
		$status = $order->getStatus()->getCode();
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


	private function getInvoiceManager(): InvoiceManagerInterface
	{
		if ($this->invoiceManager === null) {
			throw new \LogicException('Invoice manager does not exist, but it is mandatory.');
		}

		return $this->invoiceManager;
	}
}
