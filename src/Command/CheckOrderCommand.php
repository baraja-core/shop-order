<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Command;


use Baraja\BankTransferAuthorizator\Authorizator;
use Baraja\BankTransferAuthorizator\Transaction;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Currency\CurrencyManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\OrderPaymentManager;
use Baraja\Shop\Order\OrderStatusManager;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Shop\Order\Repository\OrderRepository;
use Baraja\Shop\Order\Status\OrderWorkflow;
use Nette\Application\UI\InvalidLinkException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckOrderCommand extends Command
{
	private OrderRepository $orderRepository;


	public function __construct(
		private EntityManager $entityManager,
		private OrderStatusManager $orderStatusManager,
		private OrderPaymentManager $tm,
		private OrderPaymentClient $orderPaymentClient,
		private CurrencyManager $currencyManager,
		private OrderWorkflow $workflow,
	) {
		parent::__construct();
		$orderRepository = $entityManager->getRepository(Order::class);
		assert($orderRepository instanceof OrderRepository);
		$this->orderRepository = $orderRepository;
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
		$orders = $this->orderRepository->getOrderForCheckPayment();

		$orderByVariable = $this->filterOrderByVariable($orders);
		$unauthorizedVariablesForCheck = $this->filterUnauthorizedVariablesForCheck($orders);

		foreach ($orders as $order) {
			echo sprintf(
				'%s [%s %s] [%s]',
				$order->getNumber(),
				$order->getPrice(),
				$order->getCurrencyCode(),
				$order->getInsertedDate()->format('Y-m-d H:i:s'),
			);
			echo "\n";
		}

		$authorizator = $this->orderPaymentClient->getAuthorizator();
		echo 'Check and save unmatched:' . "\n";
		$this->checkUnmatchedTransactions($unauthorizedVariablesForCheck, $authorizator);

		echo "\n\n" . 'Saving..,' . "\n\n";
		$this->entityManager->flush();
		echo "\n\n" . '------' . "\n\n" . 'Authorized:' . "\n\n";

		$this->authOrders($orderByVariable, $authorizator);

		echo "\n\n";
		echo 'Saving...' . "\n\n";
		$this->entityManager->flush();
		$this->checkSentOrders();
		$this->entityManager->flush();

		foreach ($this->orderRepository->getOrderForCheckPayment() as $order) {
			$this->updateStatusByWorkflow($order);
		}

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
			$this->orderStatusManager->setStatus($order, OrderStatus::STATUS_STORNO, force: true);
		}

		// send ping mail
		if (
			$cancel === false
			&& $order->isPinged() === false
			&& $now - $order->getInsertedDate()->getTimestamp() > $this->workflow->getIntervalForPingOrder()
		) {
			echo ' (ping mail)';
			$this->orderStatusManager->setStatus($order, OrderStatus::STATUS_PAYMENT_PING, force: true);
			$order->setPinged(true);
		}
	}


	/**
	 * @param array<int, int> $unauthorizedVariables
	 */
	private function checkUnmatchedTransactions(array $unauthorizedVariables, Authorizator $authorizator): void
	{
		$processed = [];
		$unmatchedTransactionsList = $authorizator->getUnmatchedTransactions($unauthorizedVariables);
		foreach ($unmatchedTransactionsList as $transaction) {
			assert($transaction instanceof \Baraja\FioPaymentAuthorizator\Transaction);
			$idTransaction = $transaction->getIdTransaction();
			if ($idTransaction === null) {
				continue;
			}
			if (
				isset($processed[$idTransaction]) === false
				&& $this->tm->transactionExist($idTransaction) === false
				&& $this->tm->orderHasPaidByVariableSymbol($transaction->getVariableSymbol()) === false
			) {
				if ($transaction->getPrice() > 0) {
					$this->tm->storeUnmatchedTransaction($transaction);
				}
				echo $idTransaction . "\n";
				$this->tm->storeTransaction($transaction, true);
				$processed[$idTransaction] = true;
			}
		}
	}


	/**
	 * @param array<numeric-string, Order> $orderByVariable
	 */
	private function authOrders(array $orderByVariable, Authorizator $authorizator): void
	{
		foreach ($this->currencyManager->getCurrencies() as $currency) {
			$orders = $this->orderRepository->getOrderForCheckPayment($currency->getCode());
			$unauthorizedVariables = $this->filterUnauthorizedVariables($orders);

			/** @var callable&(callable(Transaction): void)[] $callback */
			$callback = function (Transaction $transaction) use ($orderByVariable): void {
				assert($transaction instanceof \Baraja\FioPaymentAuthorizator\Transaction);
				$entity = null;
				if ($this->tm->transactionExist((int) $transaction->getIdTransaction()) === false) {
					$entity = $this->tm->storeTransaction($transaction);
				}
				$variable = (string) $transaction->getVariableSymbol();
				if ($variable !== '' && isset($orderByVariable[$variable])) {
					$order = $orderByVariable[$variable];
					$order->setPaid(true);
					$this->orderStatusManager->setStatus($order, OrderStatus::STATUS_PAID);
					$entity?->setOrder($order);
				}
				$this->entityManager->flush();
			};

			try {
				$authorizator->authOrders(
					$unauthorizedVariables,
					$callback,
					$currency->getCode(),
					0.25,
				);
			} catch (\Throwable $e) {
				throw new \RuntimeException(sprintf('Can not authorize orders: %s', $e->getMessage()), 500, $e);
			}
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
			->setParameter('days', new \DateTimeImmutable('now - 10 days'))
			->getQuery()
			->getResult();

		foreach ($orders as $order) {
			$this->orderStatusManager->setStatus($order, OrderStatus::STATUS_DONE, force: true);
		}
	}


	/**
	 * @param array<int, Order> $orders
	 * @return array<numeric-string, float>
	 */
	private function filterUnauthorizedVariables(array $orders): array
	{
		$return = [];
		foreach ($orders as $order) {
			/** @var numeric-string $number */
			$number = $order->getNumber();
			$return[$number] = (float) $order->getPrice()->getValue();
		}

		return $return;
	}


	/**
	 * @param array<int, Order> $orders
	 * @return array<numeric-string, Order>
	 */
	private function filterOrderByVariable(array $orders): array
	{
		$return = [];
		foreach ($orders as $order) {
			/** @var numeric-string $number */
			$number = $order->getNumber();
			$return[$number] = $order;
		}

		return $return;
	}


	/**
	 * @param array<int, Order> $orders
	 * @return array<int, int>
	 */
	private function filterUnauthorizedVariablesForCheck(array $orders): array
	{
		$return = [];
		foreach ($orders as $order) {
			$return[] = (int) $order->getNumber();
		}

		return $return;
	}
}
