<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Cart\Entity\Cart;
use Baraja\Shop\Cart\Entity\OrderNumber;
use Baraja\Shop\Cart\OrderInfo;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\BranchManager;
use Baraja\Shop\Invoice\InvoiceManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Contributte\GopayInline\Client;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class OrderManager implements \Baraja\Shop\Cart\OrderManager
{
	public function __construct(
		private OrderPaymentClient $paymentClient,
		private OrderStatusManager $statusManager,
		private OrderGenerator $orderGenerator,
		private EntityManager $entityManager,
		private InvoiceManager $invoiceManager,
		private BranchManager $branchManager,
		private Emailer $emailer,
	) {
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getOrderByHash(string $hash): Order
	{
		return $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->select('o, customer, deliveryAddress, invoiceAddress, delivery, payment')
			->leftJoin('o.customer', 'customer')
			->leftJoin('o.deliveryAddress', 'deliveryAddress')
			->leftJoin('o.invoiceAddress', 'invoiceAddress')
			->leftJoin('o.delivery', 'delivery')
			->leftJoin('o.payment', 'payment')
			->where('o.hash = :hash')
			->setParameter('hash', $hash)
			->getQuery()
			->getSingleResult();
	}


	public function setBranchId(Order $order, ?int $branchId): void
	{
		if ($branchId === null) {
			$order->setDeliveryBranchId(null);
		} elseif ($this->branchManager->getBranchById($order->getDelivery(), $branchId) === null) {
			throw new \InvalidArgumentException('Branch "' . $branchId . '" does not exist.');
		} else {
			$order->setDeliveryBranchId($branchId);
		}
		$this->entityManager->flush();
	}


	/**
	 * @deprecated use native service
	 */
	public function setStatus(Order $order, string $status): void
	{
		$this->statusManager->setStatus($order, $status);
	}


	/**
	 * @deprecated use native service
	 */
	public function getGoPayClient(): Client
	{
		return $this->paymentClient->getGoPayClient();
	}


	/**
	 * @deprecated use native service
	 */
	public function createOrder(OrderInfo $orderInfo, Cart $cart): OrderNumber
	{
		return $this->orderGenerator->createOrder($orderInfo, $cart);
	}


	/**
	 * @deprecated use native service
	 */
	public function createEmptyOrder(Customer $customer): Order
	{
		return $this->orderGenerator->createEmptyOrder($customer);
	}
}
