<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Status;


use Baraja\Shop\Invoice\InvoiceManager;
use Baraja\Shop\Order\Emailer;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Tracy\Debugger;
use Tracy\ILogger;

final class OrderWorkflow
{
	public function __construct(
		private Emailer $emailer,
		private InvoiceManager $invoiceManager,
	) {
	}


	public function run(Order $order): void
	{
		$status = $order->getStatus()->getCode();
		if ($status === OrderStatus::STATUS_PAID) {
			$this->emailer->sendOrderPaid($order);
			try {
				$this->invoiceManager->createInvoice($order);
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
			if (PHP_SAPI !== 'cli' && $order->isInvoice() === false) {
				try {
					$this->invoiceManager->createInvoice($order);
				} catch (\Throwable $e) {
					Debugger::log($e, ILogger::CRITICAL);
				}
			}
		} elseif ($status === OrderStatus::STATUS_STORNO) {
			$this->emailer->sendOrderStorno($order);
		}
	}
}
