<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\BankTransferAuthorizator\Transaction as BankTransaction;
use Baraja\Doctrine\EntityManager;
use Baraja\FioPaymentAuthorizator\Transaction as FioTransaction;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderBankPayment;
use Baraja\Shop\Order\Entity\OrderOnlinePayment;
use Baraja\Shop\Order\Entity\OrderPaymentEntity;
use Baraja\Shop\Order\Entity\OrderStatus;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

final class OrderPaymentManager
{
	private Cache $cache;


	public function __construct(
		private EntityManager $entityManager,
		Storage $storage,
	) {
		$this->cache = new Cache($storage, 'order-payment-manager');
	}


	/**
	 * @return OrderPaymentEntity[]
	 */
	public function getPayments(Order $order): array
	{
		$results = [];
		foreach ($this->getPaymentEntities() as $entity) {
			$results[] = $this->entityManager->getRepository($entity)
				->createQueryBuilder('payment')
				->where('payment.order = :orderId')
				->setParameter('orderId', $order->getId())
				->getQuery()
				->getResult();
		}

		/** @var OrderPaymentEntity[] $entities */
		$entities = array_merge([], ...$results);
		usort($entities, [$this, 'sortEntities']);

		return $entities;
	}


	/**
	 * @return array<int, class-string>
	 */
	public function getPaymentEntities(): array
	{
		$key = 'payment-entities';
		try {
			$cache = $this->cache->load($key);
		} catch (\Throwable) {
			$cache = null;
		}

		if ($cache === null) {
			$entities = [];
			foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $meta) {
				$ref = $meta->getReflectionClass();
				if ($ref->implementsInterface(OrderPaymentEntity::class) && $ref->hasProperty('order')) {
					$entities[] = $meta->getName();
				}
			}
			$this->cache->save($key, $entities);
			$cache = $entities;
		}

		return $cache;
	}


	public function storeTransaction(BankTransaction $transaction, bool $flush = false): OrderBankPayment
	{
		if ($transaction instanceof FioTransaction) {
			$transactionEntity = new OrderBankPayment(
				$transaction->getId(),
				$transaction->getDate(),
				(string) $transaction->getPrice(),
				$transaction->getCurrency(),
				$transaction->getToAccount(),
				$transaction->getToAccountName(),
				$transaction->getToBankCode(),
				$transaction->getToBankName(),
				$transaction->getConstantSymbol(),
				$transaction->getVariableSymbol(),
				$transaction->getSpecificSymbol(),
				$transaction->getUserNotice(),
				$transaction->getToMessage(),
				$transaction->getType(),
				$transaction->getSender(),
				$transaction->getMessage(),
				$transaction->getComment(),
				$transaction->getBic(),
				$transaction->getIdTransaction(),
			);
		} else {
			throw new \LogicException('Transaction type "' . get_debug_type($transaction) . '" is not supported now.');
		}

		$this->entityManager->persist($transactionEntity);
		if ($flush === true) {
			$this->entityManager->flush();
		}

		return $transactionEntity;
	}


	public function storeUnmatchedTransaction(BankTransaction $transaction): void
	{
	}


	public function transactionExist(int $idTransaction): bool
	{
		static $cache = [];
		if (isset($cache[$idTransaction]) === false) {
			try {
				$this->entityManager->getRepository(OrderBankPayment::class)
					->createQueryBuilder('transaction')
					->where('transaction.idTransaction = :idTransaction')
					->setParameter('idTransaction', $idTransaction)
					->getQuery()
					->getSingleResult();

				$cache[$idTransaction] = true;
			} catch (NonUniqueResultException | NoResultException) {
				// Silence is golden.
			}
		}

		return $cache[$idTransaction] ?? false;
	}


	public function orderHasPaidByVariableSymbol(?int $number): bool
	{
		if ($number === null) {
			return false;
		}

		static $cache = [];
		if (isset($cache[$number]) === false) {
			try {
				$this->entityManager->getRepository(OrderOnlinePayment::class)
					->createQueryBuilder('o')
					->leftJoin('o.order', 'realOrder')
					->where('realOrder.number = :number')
					->andWhere('o.status = :status')
					->setParameter('number', $number)
					->setParameter('status', OrderStatus::StatusPaid)
					->getQuery()
					->getSingleResult();

				$cache[$number] = true;
			} catch (NonUniqueResultException | NoResultException) {
				// Silence is golden.
			}
			$cache[$number] = false;
		}

		return $cache[$number];
	}


	private function sortEntities(OrderPaymentEntity $a, OrderPaymentEntity $b): int
	{
		return $a->getDate()->getTimestamp() < $b->getDate()->getTimestamp() ? 1 : -1;
	}
}
