<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Country\CountryManagerAccessor;
use Baraja\Country\Entity\Country;
use Baraja\Doctrine\EntityManager;
use Baraja\EcommerceStandard\DTO\CartInterface;
use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\OrderInfoInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\Localization\Localization;
use Baraja\Shop\Address\Entity\Address;
use Baraja\Shop\Cart\CartManager;
use Baraja\Shop\Cart\OrderInfo;
use Baraja\Shop\Cart\OrderInfoAddress;
use Baraja\Shop\Cart\OrderInfoBasic;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\Shop\Customer\CustomerManager;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderGroup;
use Baraja\Shop\Order\Entity\OrderItem;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Payment\Entity\Payment;
use Baraja\Shop\Price\Price;
use Baraja\VariableGenerator\Strategy\YearPrefixIncrementStrategy;
use Baraja\VariableGenerator\VariableGenerator;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Security\User;
use Psr\Log\LoggerInterface;

final class OrderGenerator
{
	/**
	 * @param CreatedOrderEvent[] $createdOrderEvents
	 */
	public function __construct(
		private EntityManager $entityManager,
		private CartManager $cartManager,
		private OrderStatusManager $statusManager,
		private OrderGroupManager $orderGroupManager,
		private Localization $localization,
		private CustomerManager $customerManager,
		private User $user,
		private CurrencyManagerAccessor $currencyManager,
		private CountryManagerAccessor $countryManager,
		private ?LoggerInterface $logger = null,
		private array $createdOrderEvents = [],
	) {
	}


	public function createOrder(
		OrderInfoInterface $orderInfo,
		CartInterface $cart,
		?OrderGroup $group = null,
	): OrderInterface {
		if ($cart->isEmpty()) {
			throw new \LogicException(sprintf('Can not create empty order (cart id: "%d").', $cart->getId()));
		}
		assert($orderInfo instanceof OrderInfo);
		$info = $orderInfo->getInfo();
		$addressInfo = $orderInfo->getAddress();
		$invoiceAddressInfo = $addressInfo->isInvoiceAddressIsDifferent()
			? $orderInfo->getInvoiceAddress()
			: $addressInfo;
		$deliveryAddress = $this->resolveAddress($orderInfo, $addressInfo, $cart);
		$invoiceAddress = $this->resolveAddress($orderInfo, $invoiceAddressInfo, $cart);

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
		if ($selectedPayment === null) {
			/** @var Payment $selectedPayment */
			$selectedPayment = $this->entityManager->getRepository(Payment::class)
				->createQueryBuilder('p')
				->setMaxResults(1)
				->getQuery()
				->getOneOrNullResult();
			$cart->setPayment($selectedPayment);
		}
		assert($selectedDelivery instanceof Delivery);
		assert($selectedPayment instanceof Payment);

		$group = $group ?? $this->orderGroupManager->getDefaultGroup();
		$itemsPrice = $cart->getItemsPrice()->getValue();
		$initStatus = $this->statusManager->getStatusByCode(OrderStatus::STATUS_NEW);
		$order = new Order(
			group: $group,
			status: $initStatus,
			customer: $this->processCustomer($info, $cart),
			deliveryAddress: $deliveryAddress,
			invoiceAddress: $invoiceAddress,
			number: $this->getNextVariable($group),
			locale: $this->localization->getLocale(),
			delivery: $selectedDelivery,
			payment: $selectedPayment,
			price: $itemsPrice,
			priceWithoutVat: $cart->getPriceWithoutVat()->getValue(),
			currency: $cart->getCurrency(),
		);
		if ($cart->getRuntimeContext()->getFreeDeliveryResolver()->isFreeDelivery($cart, $customer ?? $cart->getCustomer())) {
			$order->setDeliveryPrice('0');
		} else {
			$order->setDeliveryPrice($selectedDelivery->getPrice());
		}
		$order->setPaymentPrice($selectedPayment->getPrice());
		$order->setNotice($info->getNotice());
		if ($order->getCustomer()->getDefaultOrderSale() > 0) {
			$order->recountPrice();
			$orderBasePrice = $order->getBasePrice()->minus($order->getDeliveryPrice());
			$order->setSale($orderBasePrice->minus(new Price(
				bcmul(
					$orderBasePrice->getValue(),
					(string) ($order->getCustomer()->getDefaultOrderSale() / 100),
				),
				$cart->getCurrency(),
			)));
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
				$cartItem->getBasicPrice()->getValue()
			);
			$this->entityManager->persist($orderItem);
			$order->addItem($orderItem);
		}

		$this->cartManager->removeCart($cart);
		$order->recountPrice();
		$this->entityManager->flush();

		$this->statusManager->setStatus($order, $initStatus, force: true);

		foreach ($this->createdOrderEvents as $createdOrderEvent) {
			try {
				$createdOrderEvent->process($order);
			} catch (\Throwable $eventException) {
				$this->logger?->critical($eventException->getMessage(), ['exception' => $eventException]);
			}
		}

		return $order;
	}


	public function createEmptyOrder(Customer $customer, Country $country, ?OrderGroup $group = null): Order
	{
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
		$group = $group ?? $this->orderGroupManager->getDefaultGroup();
		$order = new Order(
			group: $group,
			status: $this->statusManager->getStatusByCode(OrderStatus::STATUS_NEW),
			customer: $customer,
			deliveryAddress: $deliveryAddress,
			invoiceAddress: $invoiceAddress,
			number: $this->getNextVariable($group),
			locale: $this->localization->getLocale(),
			delivery: $delivery,
			payment: $payment,
			price: '0',
			priceWithoutVat: '0',
			currency: $this->getCurrentContextCurrency(),
		);
		$order->setDeliveryPrice($delivery->getPrice());
		$order->setPaymentPrice($payment->getPrice());
		$order->setNotice('Manually created order.');

		$this->entityManager->persist($deliveryAddress);
		$this->entityManager->persist($invoiceAddress);
		$this->entityManager->persist($order);
		$order->recountPrice();
		$this->entityManager->flush();
		foreach ($this->createdOrderEvents as $createdOrderEvent) {
			try {
				$createdOrderEvent->process($order);
			} catch (\Throwable $eventException) {
				$this->logger?->critical($eventException->getMessage(), ['exception' => $eventException]);
			}
		}

		return $order;
	}


	/**
	 * Atomically generates a new free order number.
	 * The order number generation uses an internal lock to prevent duplicate numbers from being generated.
	 */
	public function getNextVariable(OrderGroup $group): string
	{
		return (string) (new VariableGenerator(
			new VariableLoader($this->entityManager, $group),
			new YearPrefixIncrementStrategy(null, 9)
		))
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


	private function processCustomer(OrderInfoBasic $info, CartInterface $cart): Customer
	{
		$customer = $cart->getCustomer();
		if ($customer === null) {
			try {
				$customer = $this->customerManager->getByEmail($info->getEmail());
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

		assert($customer instanceof Customer);
		$customer->setFirstName($info->getFirstName());
		$customer->setLastName($info->getLastName());
		$customer->setPhone($info->getPhone());

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


	private function getCurrentContextCurrency(): CurrencyInterface
	{
		return $this->currencyManager->get()->getMainCurrency();
	}


	private function resolveAddress(OrderInfo $orderInfo, OrderInfoAddress $address, ?CartInterface $cart = null): Address
	{
		$data = $orderInfo->toArray($address);
		$countryId = $data['country'] ?? null;
		$country = null;
		if (is_int($countryId)) {
			$country = $this->countryManager->get()->getById($countryId);
		} else {
			try {
				$country = $cart?->getDelivery()?->getCountry();
			} catch (\Throwable) {
			}
		}
		if ($country === null) {
			$country = $this->countryManager->get()->getByCode('CZE'); // TODO: Load default country
		}
		assert($country instanceof Country);
		if ($country->isActive() === false) {
			throw new \InvalidArgumentException(sprintf('Country "%s" must be active.', $country->getCode()));
		}
		$realAddress = $data;
		$realAddress['country'] = $country;

		return Address::hydrateData($realAddress);
	}
}
