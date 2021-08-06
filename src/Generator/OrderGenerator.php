<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Country\Entity\Country;
use Baraja\Doctrine\EntityManager;
use Baraja\Localization\Localization;
use Baraja\Shop\Address\Entity\Address;
use Baraja\Shop\Cart\CartManager;
use Baraja\Shop\Cart\Entity\Cart;
use Baraja\Shop\Cart\Entity\OrderNumber;
use Baraja\Shop\Cart\OrderInfo;
use Baraja\Shop\Cart\OrderInfoBasic;
use Baraja\Shop\Customer\CustomerManager;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderItem;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Payment\Entity\Payment;
use Baraja\VariableGenerator\Order\DefaultOrderVariableLoader;
use Baraja\VariableGenerator\VariableGenerator;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Security\User;
use Tracy\Debugger;
use Tracy\ILogger;

final class OrderGenerator
{
	/**
	 * @param CreatedOrderEvent[] $createdOrderEvents
	 */
	public function __construct(
		private EntityManager $entityManager,
		private CartManager $cartManager,
		private OrderStatusManager $statusManager,
		private Localization $localization,
		private CustomerManager $customerManager,
		private User $user,
		private Emailer $emailer,
		private array $createdOrderEvents = [],
	) {
	}


	public function createOrder(OrderInfo $orderInfo, Cart $cart): OrderNumber
	{
		if ($cart->isEmpty()) {
			throw new \LogicException('Can not create empty order (cart id: "' . $cart->getId() . '").');
		}
		$info = $orderInfo->getInfo();
		$address = $orderInfo->getAddress();
		$deliveryAddress = Address::hydrateData($orderInfo->toArray($address));
		$invoiceAddress = Address::hydrateData(
			$orderInfo->toArray(
				$address->isInvoiceAddressIsDifferent()
					? $orderInfo->getInvoiceAddress()
					: $address
			)
		);

		if ($this->user->isLoggedIn()) {
			/** @var Customer|null $customer */
			$customer = $this->entityManager->getRepository(Customer::class)->find($this->user->getId());
			if ($customer !== null) {
				$this->mapCustomer($info, $deliveryAddress, $customer);
			}
		}

		$selectedDelivery = $cart->getDelivery();
		$selectedPayment = $cart->getPayment();
		if ($selectedDelivery === null) {
			/** @var Delivery $selectedDelivery */
			$selectedDelivery = $this->entityManager->getRepository(Delivery::class)
				->createQueryBuilder('d')
				->setMaxResults(1)
				->getQuery()
				->getOneOrNullResult();
			$cart->setDelivery($selectedDelivery);
		}
		$deliveryAddress->setCountry($selectedDelivery->getCountry());
		$invoiceAddress->setCountry($selectedDelivery->getCountry());
		if ($selectedPayment === null) {
			/** @var Payment $selectedPayment */
			$selectedPayment = $this->entityManager->getRepository(Payment::class)
				->createQueryBuilder('p')
				->setMaxResults(1)
				->getQuery()
				->getOneOrNullResult();

			$cart->setPayment($selectedPayment);
		}

		$order = new Order(
			status: $this->statusManager->getStatusByCode(OrderStatus::STATUS_NEW),
			customer: $this->processCustomer($info, $cart),
			deliveryAddress: $deliveryAddress,
			invoiceAddress: $invoiceAddress,
			number: $this->getNextVariable(),
			locale: $this->localization->getLocale(),
			delivery: $selectedDelivery,
			payment: $selectedPayment,
			price: $cart->getPrice(),
			priceWithoutVat: $cart->getPriceWithoutVat(),
		);
		$order->setNotice($info->getNotice());
		if ($selectedDelivery === null) {
			$order->addNotice('Delivery has not been selected.');
		}
		if ($selectedPayment === null) {
			$order->addNotice('Payment method has not been selected.');
		}
		if ($order->getCustomer()->getDefaultOrderSale() > 0) {
			$order->recountPrice();
			$orderBasePrice = $order->getBasePrice() - $order->getDeliveryPrice();
			$order->setSale(
				$orderBasePrice - $orderBasePrice * ($order->getCustomer()->getDefaultOrderSale() / 100)
			);
		}
		$order->setDeliveryBranchId($cart->getDeliveryBranchId());

		$this->entityManager->persist($deliveryAddress);
		$this->entityManager->persist($invoiceAddress);
		$this->entityManager->persist($order);

		foreach ($cart->getItems() as $cartItem) {
			$orderItem = new OrderItem(
				$order,
				$cartItem->getProduct(),
				$cartItem->getVariant(),
				$cartItem->getCount(),
				$cartItem->getBasicPrice()
			);
			$this->entityManager->persist($orderItem);
			$order->addItem($orderItem);
		}

		$this->cartManager->removeCart($cart);
		$this->entityManager->flush();

		try {
			$this->emailer->sendNewOrder($order);
		} catch (\Throwable $e) {
			Debugger::log($e, ILogger::CRITICAL);
		}
		foreach ($this->createdOrderEvents as $createdOrderEvent) {
			$createdOrderEvent->process($order);
		}

		return $order;
	}


	public function createEmptyOrder(Customer $customer, ?Country $country = null): Order
	{
		if ($country === null) {
			throw new \InvalidArgumentException('Country is mandatory.');
		}

		$address = static function (Customer $customer) use ($country): Address
		{
			return new Address(
				$country,
				$customer->getFirstName(),
				$customer->getLastName(),
				(string) $customer->getStreet(),
				(string) $customer->getCity(),
				(int) $customer->getZip(),
			);
		};

		/** @var Delivery $delivery */
		$delivery = $this->entityManager->getRepository(Delivery::class)
			->createQueryBuilder('d')
			->where('d.code = :code')
			->setParameter('code', 'branch')
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();

		/** @var Payment $payment */
		$payment = $this->entityManager->getRepository(Payment::class)
			->createQueryBuilder('p')
			->where('p.code = :code')
			->setParameter('code', 'cash')
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();

		$deliveryAddress = $address($customer);
		$invoiceAddress = $address($customer);
		$order = new Order(
			status: $this->statusManager->getStatusByCode(OrderStatus::STATUS_NEW),
			customer: $customer,
			deliveryAddress: $deliveryAddress,
			invoiceAddress: $invoiceAddress,
			number: $this->getNextVariable(),
			locale: $this->localization->getLocale(),
			delivery: $delivery,
			payment: $payment,
			price: 0,
			priceWithoutVat: 0
		);
		$order->setNotice('Manually created order.');

		$this->entityManager->persist($deliveryAddress);
		$this->entityManager->persist($invoiceAddress);
		$this->entityManager->persist($order);
		$this->entityManager->flush();
		foreach ($this->createdOrderEvents as $createdOrderEvent) {
			$createdOrderEvent->process($order);
		}

		return $order;
	}


	/**
	 * Atomically generates a new free order number.
	 * The order number generation uses an internal lock to prevent duplicate numbers from being generated.
	 */
	public function getNextVariable(): string
	{
		return (string) (new VariableGenerator(new DefaultOrderVariableLoader($this->entityManager, Order::class)))
			->generate();
	}


	private function mapCustomer(OrderInfoBasic $info, Address $deliveryAddress, Customer $customer): void
	{
		if (!$customer->getFirstName()) {
			$customer->setFirstName($info->getFirstName());
		}
		if (!$customer->getLastName()) {
			$customer->setLastName($info->getLastName());
		}
		if (!$customer->getPhone()) {
			$customer->setPhone($info->getPhone());
		}
		if (!$customer->getStreet()) {
			$customer->setStreet($deliveryAddress->getStreet());
		}
		if (!$customer->getCity()) {
			$customer->setCity($deliveryAddress->getCity());
		}
		if (!$customer->getZip()) {
			$customer->setZip((int) $deliveryAddress->getZip());
		}
		if (!$customer->getCompanyName()) {
			$customer->setCompanyName($deliveryAddress->getCompanyName());
		}
		if (!$customer->getIc()) {
			$customer->setIc($deliveryAddress->getCin());
		}
		if (!$customer->getDic()) {
			$customer->setDic($deliveryAddress->getTin());
		}
	}


	private function processCustomer(OrderInfoBasic $info, Cart $cart): Customer
	{
		$userExist = false;
		$customer = $cart->getCustomer();
		if ($customer === null) {
			try {
				$customer = $this->customerManager->getByEmail($info->getEmail());
				$userExist = true;
			} catch (NoResultException | NonUniqueResultException) {
				$customer = new Customer(
					$info->getEmail(),
					$info->getFirstName(),
					$info->getLastName(),
					$info->getRegisterPassword(),
				);
				$this->entityManager->persist($customer);
				$this->entityManager->flush();
			}
		}

		$customer->setFirstName($info->getFirstName());
		$customer->setLastName($info->getLastName());
		$customer->setPhone($info->getPhone());

		// TODO: if ($userExist === false && $info->isRegister()) {
		// TODO: $this->emailer->sendRegister($customer);
		// TODO: }
		if ($info->isNewsletter()) {
			$customer->setNewsletter(true);
		}
		if ($customer->getPassword() === null && $info->isRegister() === true) {
			$password = $info->getRegisterPassword();
			if ($password === null) {
				throw new \InvalidArgumentException('Enter your password to create an account.');
			}
			$customer->setPassword($password);
		}

		return $customer;
	}
}
