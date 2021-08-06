<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Command;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\FioPaymentAuthorizator\Transaction;
use Baraja\Shop\Order\Emailer;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\OrderStatusManager;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Shop\Order\TransactionManager;
use Nette\Application\UI\InvalidLinkException;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;
use Tracy\ILogger;

final class CheckOrderCommand extends Command
{
	public function __construct(
		private EntityManager $entityManager,
		private OrderStatusManager $orderStatusManager,
		private Emailer $emailer,
		private TransactionManager $transactionManager,
		private OrderPaymentClient $orderPaymentClient,
	) {
		parent::__construct();
	}


	protected function configure(): void
	{
		$this->setName('baraja:shop:check-order')
			->setDescription('Check all orders and update status.');
	}


	/**
	 * @throws EntityManagerException|InvalidLinkException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		/** @var Order[] $orders */
		$orders = $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('orderEntity')
			->where('orderEntity.status = :status')
			->setParameter('status', OrderStatus::STATUS_NEW)
			->getQuery()
			->getResult();

		$unauthorizedVariables = [];
		$orderByVariable = [];
		$now = \time();

		foreach ($orders as $order) {
			$unauthorizedVariables[$order->getNumber()] = $order->getPrice();
			$orderByVariable[$order->getNumber()] = $order;
			$cancel = false;

			echo $order->getNumber() . ' [' . $order->getPrice() . ' CZK]';
			echo ' ['
				. $order->getInsertedDate()
					->format('Y-m-d H:i:s')
				. ']';

			// 21 days - cancel order
			if ($now - $order->getInsertedDate()
					->getTimestamp() > 1_814_400) {
				$cancel = true;
				echo ' (cancel mail)';
				$this->orderStatusManager->setStatus($order, OrderStatus::STATUS_STORNO);
			}

			// 7 days - send ping mail
			if (
				$cancel === false
				&& $order->isSendPingMail() === false
				&& $now - $order->getInsertedDate()
					->getTimestamp() > 604_800
			) {
				echo ' (ping mail)';
				$this->emailer->sendOrderPingMail($order);
				$order->setSendPingMail(true);
			}

			echo "\n";
		}

		$authorizator = $this->orderPaymentClient->getAuthorizator();
		echo 'Check and save umatched:' . "\n";
		try {
			$unmatchedTransactions = [];
			$processed = [];
			/** @var Transaction $unmatchedTransaction */
			foreach ($authorizator->getUnmatchedTransactions(
				array_keys($unauthorizedVariables)
			) as $unmatchedTransaction) {
				if (isset($processed[$unmatchedTransaction->getIdTransaction()]) === false
					&& $this->transactionManager->transactionExist($unmatchedTransaction->getIdTransaction()) === false
					&& $this->transactionManager->orderWasPaidByVariableSymbol(
						$unmatchedTransaction->getVariableSymbol()
					) === false) {
					if ($unmatchedTransaction->getPrice() > 0) {
						$unmatchedTransactions[] = $unmatchedTransaction;
					}
					echo $unmatchedTransaction->getIdTransaction() . "\n";
					$this->transactionManager->storeToDb($unmatchedTransaction, true);
					$processed[$unmatchedTransaction->getIdTransaction()] = true;
				}
			}
		} catch (\Throwable $e) {
			Debugger::log($e, ILogger::CRITICAL);
			die;
		}

		echo "\n\n";
		echo 'Saving..,' . "\n\n";
		$this->entityManager->flush();
		echo "\n\n";

		echo '------' . "\n\n" . 'Authorized:' . "\n\n";

		try {
			$authorizator->authOrders(
				$unauthorizedVariables,
				function (Transaction $transaction) use ($orderByVariable): void
				{
					$entity = null;
					if ($this->transactionManager->transactionExist($transaction->getIdTransaction()) === false) {
						$entity = $this->transactionManager->storeToDb($transaction);
					}
					$variable = $transaction->getVariableSymbol();
					if ($variable !== null) {
						$this->orderStatusManager->setStatus($orderByVariable[$variable], OrderStatus::STATUS_PAID);
						if ($entity !== null) {
							$entity->setOrder($orderByVariable[$variable]);
						}
					}
					$this->entityManager->flush();
				},
				'CZK',
				0.25
			);
		} catch (\Throwable $e) {
			Debugger::log($e, ILogger::CRITICAL);
			die;
		}

		echo "\n\n";
		echo 'Saving...' . "\n\n";
		$this->entityManager->flush();
		$this->checkSentOrders();
		$this->entityManager->flush();

		return 0;
	}


	private function checkSentOrders(): void
	{
		/** @var Order[] $orders */
		$orders = $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->where('o.status = :status')
			->andWhere('o.updatedDate <= :days')
			->setParameter('status', OrderStatus::STATUS_SENT)
			->setParameter('days', DateTime::from('now - 10 days'))
			->getQuery()
			->getResult();

		foreach ($orders as $order) {
			$this->orderStatusManager->setStatus($order, OrderStatus::STATUS_DONE);
		}
	}
}
