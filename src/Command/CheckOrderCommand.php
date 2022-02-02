<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Command;


use Baraja\BankTransferAuthorizator\Authorizator;
use Baraja\BankTransferAuthorizator\Transaction;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\OrderStatusManager;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Shop\Order\Status\OrderWorkflow;
use Baraja\Shop\Order\OrderPaymentManager;
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
		private OrderPaymentManager $tm,
		private OrderPaymentClient $orderPaymentClient,
		private OrderWorkflow $workflow,
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
			->leftJoin('orderEntity.status', 'status')
			->where('status.code = :code')
			->setParameter('code', OrderStatus::STATUS_NEW)
			->getQuery()
			->getResult();

		/** @var array<string, float> $unauthorizedVariables */
		$unauthorizedVariables = [];
		/** @var array<string, Order> $orderByVariable */
		$orderByVariable = [];

		foreach ($orders as $order) {
			$unauthorizedVariables[$order->getNumber()] = $order->getPrice();
			$orderByVariable[$order->getNumber()] = $order;

			echo $order->getNumber() . ' [' . $order->getPrice() . ' ' . $order->getCurrencyCode() . ']';
			echo ' [' . $order->getInsertedDate()->format('Y-m-d H:i:s') . ']';
			$this->updateStatusByWorkflow($order);
			echo "\n";
		}

		$authorizator = $this->orderPaymentClient->getAuthorizator();
		echo 'Check and save unmatched:' . "\n";
		$this->checkUnmatchedTransactions(array_keys($unauthorizedVariables), $authorizator);

		echo "\n\n" . 'Saving..,' . "\n\n";
		$this->entityManager->flush();
		echo "\n\n" . '------' . "\n\n" . 'Authorized:' . "\n\n";

		$this->authOrders($unauthorizedVariables, $orderByVariable, $authorizator);

		echo "\n\n";
		echo 'Saving...' . "\n\n";
		$this->entityManager->flush();
		$this->checkSentOrders();
		$this->entityManager->flush();

		return 0;
	}


	private function updateStatusByWorkflow(Order $order): void
	{
		$cancel = false;
		$now = time();
		// cancel expired order
		if ($now - $order->getInsertedDate()->getTimestamp() > $this->workflow->getIntervalForCancelOrder()) {
			$cancel = true;
			echo ' (cancel mail)';
			$this->orderStatusManager->setStatus($order, OrderStatus::STATUS_STORNO);
		}

		// send ping mail
		if (
			$cancel === false
			&& $order->isPinged() === false
			&& $now - $order->getInsertedDate()->getTimestamp() > $this->workflow->getIntervalForPingOrder()
		) {
			echo ' (ping mail)';
			try {
				$this->orderStatusManager->setStatus($order, OrderStatus::STATUS_PAYMENT_PING);
			} catch (\InvalidArgumentException) {
				// status ping is not implemented
			}
			$order->setPinged(true);
		}
	}


	/**
	 * @param int[] $unauthorizedVariables
	 */
	private function checkUnmatchedTransactions(array $unauthorizedVariables, Authorizator $authorizator): void
	{
		try {
			$processed = [];
			$unmatchedTransactionsList = $authorizator->getUnmatchedTransactions($unauthorizedVariables);
			foreach ($unmatchedTransactionsList as $transaction) {
				assert($transaction instanceof \Baraja\FioPaymentAuthorizator\Transaction);
				if (
					isset($processed[$transaction->getIdTransaction()]) === false
					&& $this->tm->transactionExist((int) $transaction->getIdTransaction()) === false
					&& $this->tm->orderHasPaidByVariableSymbol($transaction->getVariableSymbol()) === false
				) {
					if ($transaction->getPrice() > 0) {
						$this->tm->storeUnmatchedTransaction($transaction);
					}
					echo $transaction->getIdTransaction() . "\n";
					$this->tm->storeTransaction($transaction, true);
					$processed[$transaction->getIdTransaction()] = true;
				}
			}
		} catch (\Throwable $e) {
			Debugger::log($e, ILogger::CRITICAL);
			die;
		}
	}


	/**
	 * @param array<string, float> $unauthorizedVariables
	 * @param array<string, Order> $orderByVariable
	 */
	private function authOrders(array $unauthorizedVariables, array $orderByVariable, Authorizator $authorizator): void
	{
		try {
			$authorizator->authOrders(
				$unauthorizedVariables,
				function (Transaction $transaction) use ($orderByVariable): void
				{
					$entity = null;
					if ($this->tm->transactionExist((int) $transaction->getIdTransaction()) === false) {
						$entity = $this->tm->storeTransaction($transaction);
					}
					$variable = (string) $transaction->getVariableSymbol();
					if ($variable !== '') {
						$this->orderStatusManager->setStatus($orderByVariable[$variable], OrderStatus::STATUS_PAID);
						$entity?->setOrder($orderByVariable[$variable]);
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
	}


	private function checkSentOrders(): void
	{
		/** @var Order[] $orders */
		$orders = $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->leftJoin('o.status', 'status')
			->where('status.code = :status')
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
