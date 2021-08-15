<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\CustomerSupport;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderMessage;

final class CustomerSupportManager
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	/**
	 * @return OrderMessage[]
	 */
	public function getChat(Order $order): array
	{
		return $this->entityManager->getRepository(OrderMessage::class)
			->createQueryBuilder('m')
			->where('m.order = :orderId')
			->setParameter('orderId', $order->getId())
			->orderBy('m.insertedDate', 'DESC')
			->getQuery()
			->getResult();
	}
}
