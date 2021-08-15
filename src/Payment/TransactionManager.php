<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\BankTransferAuthorizator\Transaction as BankTransaction;
use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\OrderPayment;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\Entity\Transaction;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class TransactionManager
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function storeTransaction(BankTransaction $transaction, bool $flush = false): Transaction
	{
		$transactionEntity = new Transaction(
			$transaction->getId(),
			$transaction->getDate(),
			$transaction->getPrice(),
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
			$transaction->getIdTransaction()
		);

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
				$this->entityManager->getRepository(Transaction::class)
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
				$this->entityManager->getRepository(OrderPayment::class)
					->createQueryBuilder('o')
					->where('o.number = :number')
					->setParameter('number', $number)
					->andWhere('o.status = :status')
					->setParameter('status', OrderStatus::STATUS_PAID)
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
}
