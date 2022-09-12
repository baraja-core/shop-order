<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api;


use Baraja\Country\CountryManagerAccessor;
use Baraja\Doctrine\EntityManager;
use Baraja\EcommerceStandard\DTO\AddressInterface;
use Baraja\EcommerceStandard\DTO\InvoiceInterface;
use Baraja\EcommerceStandard\Service\InvoiceManagerInterface;
use Baraja\Localization\Localization;
use Baraja\Shop\Address\Entity\Address;
use Baraja\Shop\Currency\CurrencyManager;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Customer\Entity\CustomerRepository;
use Baraja\Shop\Delivery\BranchManager;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Delivery\Repository\DeliveryRepository;
use Baraja\Shop\Order\Api\DTO\CmsOrderFeedCustomer;
use Baraja\Shop\Order\Api\DTO\CmsOrderFeedDelivery;
use Baraja\Shop\Order\Api\DTO\CmsOrderFeedDocument;
use Baraja\Shop\Order\Api\DTO\CmsOrderFeedItem;
use Baraja\Shop\Order\Api\DTO\CmsOrderFeedPayment;
use Baraja\Shop\Order\Api\DTO\CmsOrderFeedList;
use Baraja\Shop\Order\Api\DTO\CmsOrderFeedResponse;
use Baraja\Shop\Order\Api\DTO\CmsOrderFeedStatus;
use Baraja\Shop\Order\Delivery\OrderDeliveryManager;
use Baraja\Shop\Order\Document\OrderDocumentManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderItem;
use Baraja\Shop\Order\Entity\OrderNotificationType;
use Baraja\Shop\Order\Entity\OrderOnlinePayment;
use Baraja\Shop\Order\Notification\OrderNotification;
use Baraja\Shop\Order\OrderGenerator;
use Baraja\Shop\Order\OrderGroupManager;
use Baraja\Shop\Order\OrderManager;
use Baraja\Shop\Order\OrderStatusManager;
use Baraja\Shop\Order\Repository\OrderFeedRepository;
use Baraja\Shop\Order\Status\OrderWorkflow;
use Baraja\Shop\Payment\Entity\Payment;
use Baraja\Shop\Payment\Repository\PaymentRepository;
use Baraja\Shop\Price\Price;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Utils\Paginator;
use Tracy\Debugger;
use Tracy\ILogger;

final class CmsOrderEndpoint extends BaseEndpoint
{
	private DeliveryRepository $deliveryRepository;

	private PaymentRepository $paymentRepository;

	private CustomerRepository $customerRepository;


	public function __construct(
		private EntityManager $entityManager,
		private OrderManager $orderManager,
		private OrderGroupManager $orderGroupManager,
		private OrderGenerator $orderGenerator,
		private OrderDeliveryManager $deliveryManager,
		private OrderStatusManager $orderStatusManager,
		private BranchManager $branchManager,
		private CountryManagerAccessor $countryManager,
		private OrderDocumentManager $documentManager,
		private OrderFeedRepository $orderFeedRepository,
		private OrderWorkflow $workflow,
		private OrderNotification $notification,
		private Localization $localization,
		private CurrencyManager $currencyManager,
		private ?InvoiceManagerInterface $invoiceManager = null,
	) {
		$deliveryRepository = $entityManager->getRepository(Delivery::class);
		$paymentRepository = $entityManager->getRepository(Payment::class);
		$customerRepository = $entityManager->getRepository(Customer::class);
		assert($deliveryRepository instanceof DeliveryRepository);
		assert($paymentRepository instanceof PaymentRepository);
		assert($customerRepository instanceof CustomerRepository);
		$this->deliveryRepository = $deliveryRepository;
		$this->paymentRepository = $paymentRepository;
		$this->customerRepository = $customerRepository;
	}


	public function actionDefault(
		?string $query = null,
		?string $status = null,
		?string $group = null,
		?int $delivery = null,
		?int $payment = null,
		?string $orderBy = null,
		?string $dateFrom = null,
		?string $dateTo = null,
		?string $currency = null,
		int $limit = 128,
		int $page = 1,
	): CmsOrderFeedResponse {
		$feed = $this->orderFeedRepository->getFeed(
			query: $query,
			status: $status,
			delivery: $delivery,
			payment: $payment,
			orderBy: $orderBy,
			dateFrom: $dateFrom,
			dateTo: $dateTo,
			currency: $currency,
			group: $group,
			limit: $limit,
			page: $page,
		);

		$sum = [];
		$return = [];
		foreach ($feed['orders'] as $order) {
			$documents = [];
			if (isset($feed['invoices'][$order->getId()])) {
				$invoice = $feed['invoices'][$order->getId()];
				$documents[] = new CmsOrderFeedDocument(
					url: $invoice->getDownloadLink(),
					label: '🧾',
				);
			}

			$deliveryItem = $order->getDelivery();
			$paymentItem = $order->getPayment();
			$return[] = new CmsOrderFeedList(
				id: $order->getId(),
				checked: false,
				number: $order->getNumber(),
				status: new CmsOrderFeedStatus(
					code: $order->getStatus()->getCode(),
					color: $order->getStatus()->getColor(),
					label: $order->getStatus()->getName(),
				),
				paid: $order->isPaid(),
				pinged: $order->isPinged(),
				price: $order->getBasePrice(),
				sale: $order->getSale(),
				finalPrice: $order->getPrice()->render(true),
				currency: $order->getCurrencyCode(),
				notice: $order->getNotice(),
				insertedDate: $order->getInsertedDate()->format('d.m.y H:i'),
				updatedDate: $order->getUpdatedDate()->format('d.m.y H:i'),
				package: count($order->getPackages()),
				customer: new CmsOrderFeedCustomer(
					id: $order->getCustomer()->getId(),
					email: $order->getCustomer()->getEmail(),
					firstName: $order->getCustomer()->getFirstName(),
					lastName: $order->getCustomer()->getLastName(),
					phone: $order->getCustomer()->getPhone(),
					premium: $order->getCustomer()->isPremium(),
					ban: $order->getCustomer()->isBan(),
				),
				delivery: $deliveryItem !== null
					? new CmsOrderFeedDelivery(
						id: $deliveryItem->getId(),
						name: $deliveryItem->getLabel(),
						price: $order->getDeliveryPrice()->render(true),
						color: $deliveryItem->getColor(),
					) : null,
				payment: $paymentItem !== null
					? new CmsOrderFeedPayment(
						id: $paymentItem->getId(),
						name: $paymentItem->getName(),
						price: $order->getPaymentPrice()->render(true),
						color: $paymentItem->getColor(),
					) : null,
				items: (static function ($items): array {
					$return = [];
					foreach ($items as $item) {
						assert($item instanceof OrderItem);
						$return[] = new CmsOrderFeedItem(
							id: $item->getId(),
							name: $item->getLabel(),
							count: $item->getCount(),
							price: $item->getPrice(),
							sale: $item->getSale(),
							finalPrice: $item->getFinalPrice(),
						);
					}

					return $return;
				})(
					$order->getItems(),
				),
				documents: $documents,
				payments: (static function ($items): array {
					$return = [];
					foreach ($items as $item) {
						$return[] = [
							'id' => $item->getId(),
						];
					}

					return $return;
				})(
					$order->getPayments(),
				),
			);

			$price = $order->getPrice();
			$priceCurrency = $price->getCurrency()->getCode();
			$sum[$priceCurrency] = bcadd($sum[$priceCurrency] ?? '0', $price->getValue(), 4);
		}

		return new CmsOrderFeedResponse(
			items: $return,
			sum: $this->formatSumPrices($sum),
			paginator: (new Paginator)
				->setItemCount($feed['count'])
				->setItemsPerPage($limit)
				->setPage($page),
		);
	}


	public function actionFilter(): void
	{
		$groups = [];
		foreach ($this->orderGroupManager->getGroups() as $groupItem) {
			$groups[$groupItem->getCode()] = (string) $groupItem->getName();
		}

		$payments = [];
		foreach ($this->paymentRepository->findAll() as $payment) {
			$payments[$payment->getId()] = $payment->getName();
		}

		$deliveries = [];
		foreach ($this->deliveryRepository->findAll() as $delivery) {
			$deliveries[$delivery->getId()] = (string) $delivery->getName();
		}

		$this->sendJson(
			[
				'loaded' => true,
				'statuses' => $this->formatBootstrapSelectArray($this->orderStatusManager->getKeyValueList()),
				'defaultGroup' => $this->orderGroupManager->getDefaultGroup()->getCode(),
				'filterGroups' => $this->formatBootstrapSelectArray($groups),
				'filterStatuses' => $this->formatBootstrapSelectArray(
					[null => '- status -'] + $this->orderStatusManager->getKeyValueList(true),
				),
				'filterPayments' => $this->formatBootstrapSelectArray(
					[null => '- payment -'] + $payments,
				),
				'filterDeliveries' => $this->formatBootstrapSelectArray(
					[null => '- delivery -'] + $deliveries,
				),
				'orderByOptions' => $this->formatBootstrapSelectArray(
					[
						null => 'Latest',
						'old' => 'Oldest',
						'number' => 'Number ASC',
						'number-desc' => 'Number DESC',
					],
				),
			],
		);
	}


	public function actionOverview(int $id): void
	{
		$order = $this->getOrderById($id);

		$transactions = [];
		foreach ($order->getTransactions() as $transaction) {
			$transactions[] = [
				'id' => $transaction->getId(),
				'price' => $transaction->getPrice(),
				'date' => $transaction->getDate(),
			];
		}
		$payments = [];
		foreach ($order->getPayments() as $payment) {
			$payments[] = [
				'gopayId' => $payment->getGatewayId(),
				'price' => (new Price($payment->getPrice(), $order->getCurrency()))->render(true),
				'status' => $payment->getStatus(),
				'insertedDate' => $payment->getDate(),
				'lastCheckedDate' => $payment->getLastCheckedDate(),
			];
		}

		$items = [];
		foreach ($order->getItems() as $item) {
			assert($item instanceof OrderItem);
			$items[] = [
				'id' => $item->getId(),
				'productId' => $item->isRealProduct() ? $item->getProduct()->getId() : null,
				'variantId' => $item->getVariant() === null ? null : $item->getVariant()->getId(),
				'name' => $item->getLabel(),
				'count' => $item->getCount(),
				'price' => $item->getPrice()->getValue(),
				'sale' => $item->getSale()->getValue(),
				'finalPrice' => $item->getFinalPrice()->getValue(),
				'vat' => $item->getVat()->getValue(),
				'type' => 'product',
			];
		}
		$deliveryItem = $order->getDelivery();
		$items[] = [
			'id' => null,
			'name' => $deliveryItem === null
				? 'Unknown delivery'
				: 'Delivery ' . $deliveryItem->getName(),
			'count' => 1,
			'price' => (float) $order->getDeliveryPrice()->getValue(),
			'type' => 'delivery',
		];
		$paymentItem = $order->getPayment();
		$items[] = [
			'id' => null,
			'name' => $paymentItem === null
				? 'Unknown payment'
				: 'Payment ' . $paymentItem->getName(),
			'count' => 1,
			'price' => (float) $order->getPaymentPrice()->getValue(),
			'type' => 'payment',
		];

		$deliverySelectbox = [
			null => '- Select delivery -',
		];
		foreach ($this->deliveryRepository->findAll() as $delivery) {
			$deliverySelectbox[$delivery->getId()] = sprintf(
				'%s (%d %s)',
				(string) $delivery->getName(),
				$delivery->getPrice(),
				$order->getCurrencyCode(),
			);
		}

		$paymentSelectbox = [
			null => '- Select payment -',
		];
		foreach ($this->paymentRepository->findAll() as $payment) {
			$paymentSelectbox[$payment->getId()] = sprintf(
				'%s (%d %s)',
				$payment->getName(),
				$payment->getPrice(),
				$order->getCurrencyCode(),
			);
		}

		$packages = [];
		foreach ($order->getPackages() as $package) {
			$packages[] = [
				'orderId' => $package->getOrderId(),
				'packageId' => $package->getPackageId(),
				'shipper' => $package->getShipper(),
				'carrierId' => $package->getCarrierId(),
				'trackUrl' => $package->getTrackUrl(),
				'labelUrl' => $package->getLabelUrl(),
				'carrierIdSwap' => $package->getCarrierIdSwap(),
			];
		}

		$formatAddress = static fn(AddressInterface $address): array => [
			'firstName' => $address->getFirstName(),
			'lastName' => $address->getLastName(),
			'street' => $address->getStreet(),
			'city' => $address->getCity(),
			'zip' => $address->getZip(),
			'country' => $address->getCountry()->getCode(),
			'companyName' => $address->getCompanyName(),
			'ic' => $address->getCin(),
			'dic' => $address->getTin(),
		];
		$countryList = [];
		foreach ($this->countryManager->get()->getAll() as $countryItem) {
			if ($countryItem->isActive() === false) {
				continue;
			}
			$countryList[$countryItem->getCode()] = $countryItem->getName();
		}

		$this->sendJson(
			[
				'id' => $id,
				'number' => $order->getNumber(),
				'invoiceNumber' => $this->documentManager->getDocumentByTag($order, 'invoice')?->getNumber(),
				'locale' => $order->getLocale(),
				'status' => $order->getStatus(),
				'price' => $order->getPrice()->getValue(),
				'sale' => $order->getSale(),
				'paid' => $order->isPaid(),
				'currency' => $order->getCurrencyCode(),
				'statuses' => $this->formatBootstrapSelectArray($this->orderStatusManager->getKeyValueList()),
				'notice' => $order->getNotice(),
				'customer' => [
					'id' => $order->getCustomer()->getId(),
					'name' => $order->getCustomer()->getName(),
					'email' => $order->getCustomer()->getEmail(),
					'phone' => $order->getCustomer()->getPhone(),
				],
				'deliveryAddress' => $formatAddress($order->getDeliveryAddress()),
				'invoiceAddress' => $formatAddress($order->getDeliveryAddress()),
				'countryList' => $this->formatBootstrapSelectArray($countryList),
				'deliveryList' => $this->formatBootstrapSelectArray($deliverySelectbox),
				'paymentList' => $this->formatBootstrapSelectArray($paymentSelectbox),
				'deliveryId' => $deliveryItem?->getId(),
				'deliverPrice' => $order->getDeliveryPrice()->getValue(),
				'deliveryBranch' => $order->getDeliveryBranchId(),
				'paymentId' => $paymentItem?->getId(),
				'items' => $items,
				'transactions' => $transactions,
				'payments' => $payments,
				'package' => $packages,
				'packageHandoverUrl' => $order->getHandoverUrl(),
				'notifications' => $this->notification->getActiveStatusTypes($order->getLocale()),
			],
		);
	}


	public function actionDeliveryBranch(int $id): void
	{
		$order = $this->getOrderById($id);
		$delivery = $order->getDelivery();

		$return = null;
		$branchId = $order->getDeliveryBranchId();
		if ($branchId !== null && $delivery !== null) {
			try {
				$branch = $this->branchManager->getBranchById($delivery, $branchId);
				if ($branch === null) {
					$return = ['id' => $id];
				} else {
					$return = [
						'id' => $id,
						'name' => $branch->getName(),
						'labelRouting' => $branch->getLabelRouting(),
						'latitude' => $branch->getLatitude(),
						'longitude' => $branch->getLongitude(),
						'mapsUrl' => sprintf(
							'https://www.google.com/maps/search/?api=1&query=%s%%2C%s',
							$branch->getLatitude(),
							$branch->getLongitude(),
						),
						'mapsStaticUrl' => sprintf(
							'https://mapy.cz/screenshoter?url=%s&width=500&height=400',
							urlencode(
								sprintf(
									'https://frame.mapy.cz/zakladni?%s',
									http_build_query([
										'x' => $branch->getLongitude(),
										'y' => $branch->getLatitude(),
										'z' => 16,
									]),
								),
							),
						),
					];
				}
			} catch (\InvalidArgumentException $e) {
				$this->flashMessage($e->getMessage(), self::FLASH_MESSAGE_INFO);
			}
		}

		$this->sendJson([
			'branch' => $return,
			'error' => $branchId !== null && $return === null,
		]);
	}


	public function postCreateEmptyOrder(int $customerId, int $countryId, string $groupId): void
	{
		try {
			$customer = $this->customerRepository->getById($customerId);
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError('Customer "' . $customerId . '" does not exist.');
		}
		try {
			$country = $this->countryManager->get()->getById($countryId);
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError('Country "' . $countryId . '" does not exist.');
		}

		$order = $this->orderGenerator->createEmptyOrder(
			customer: $customer,
			country: $country,
			group: $this->orderGroupManager->getByCode($groupId),
		);
		$this->flashMessage('Order "' . $order->getNumber() . '" has been created.', 'success');
		$this->sendJson(
			[
				'id' => $order->getId(),
				'number' => $order->getNumber(),
			],
		);
	}


	public function actionCustomerList(?string $query = null): void
	{
		$selector = $this->entityManager->getRepository(Customer::class)
			->createQueryBuilder('c')
			->setMaxResults(10)
			->orderBy('c.insertedDate', 'DESC');

		if ($query !== null) {
			$selector->orWhere('c.firstName LIKE :query')
				->orWhere('c.lastName LIKE :query')
				->orWhere('c.email LIKE :query')
				->setParameter('query', $query . '%');
		}

		/** @var Customer[] $customers */
		$customers = $selector->getQuery()->getResult();

		$return = [];
		foreach ($customers as $customer) {
			$return[] = [
				'id' => $customer->getId(),
				'name' => $customer->getName(),
			];
		}

		$countries = [];
		foreach ($this->countryManager->get()->getAll() as $country) {
			if ($country->isActive()) {
				$countries[$country->getId()] = $country->getName();
			}
		}

		$this->sendJson(
			[
				'items' => $return,
				'countries' => $this->formatBootstrapSelectArray($countries),
			],
		);
	}


	/**
	 * @param array<int, array{id: int}> $items
	 */
	public function postProcessPacketMultiple(array $items): void
	{
		/** @var Order[] $orders */
		$orders = $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->where('o.id IN (:ids)')
			->setParameter('ids', array_map(static fn(array $item): int => $item['id'], $items))
			->getQuery()
			->getResult();

		$this->deliveryManager->sendOrders($orders);

		$this->flashMessage('Shipments have been sent.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	/**
	 * @param array<string, mixed> $deliveryAddress
	 * @param array<string, mixed> $invoiceAddress
	 */
	public function postSaveAddress(int $id, array $deliveryAddress, array $invoiceAddress): void
	{
		$order = $this->getOrderById($id);

		$hydrate = function (AddressInterface $address, array $data): void {
			assert($address instanceof Address);
			$address->setFirstName((string) $data['firstName']);
			$address->setLastName((string) $data['lastName']);
			$address->setStreet((string) $data['street']);
			$address->setCity((string) $data['city']);
			$address->setZip((string) $data['zip']);
			$address->setCountry($this->countryManager->get()->getByCode((string) $data['country']));
			$address->setCompanyName((string) $data['companyName']);
			$address->setCin((string) $data['ic']);
			$address->setTin((string) $data['dic']);
		};

		$hydrate($order->getDeliveryAddress(), $deliveryAddress);
		$hydrate($order->getPaymentAddress(), $invoiceAddress);
		$this->flashMessage('The addresses have been successfully saved.', 'success');
		$this->entityManager->flush();

		if ($this->documentManager->isDocument($order->getId())) {
			if ($this->invoiceManager === null) {
				throw new \LogicException('Invoice manager has not been installed.');
			}
			$this->invoiceManager->createInvoice($order);
			$this->flashMessage('The revised invoice has been sent to the customer.', 'success');
		}

		$this->entityManager->flush();
		$this->sendOk();
	}


	public function postCreatePackage(int $id): void
	{
		$order = $this->getOrderById($id);
		$this->deliveryManager->sendOrders([$order]);
		$this->flashMessage('Package has been created.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function postChangePaymentStatus(int $id): void
	{
		$order = $this->getOrderById($id);
		$order->setPaid($order->isPaid() === false);
		$this->entityManager->flush();
		$this->flashMessage('Payment status has been changed.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function postChangeDeliveryAndPayment(int $id, int $deliveryId, int $paymentId): void
	{
		$order = $this->getOrderById($id);

		/** @var Delivery $delivery */
		$delivery = $this->entityManager->getRepository(Delivery::class)->find($deliveryId);

		/** @var Payment $payment */
		$payment = $this->entityManager->getRepository(Payment::class)->find($paymentId);

		$order->setDelivery($delivery);
		$order->setPayment($payment);

		$this->orderManager->recountPrice($order);
		$this->entityManager->flush();
		$this->flashMessage('Delivery and payment has been changed.', 'success');
		$this->sendOk();
	}


	public function postChangeStatus(int $id, string $status): void
	{
		$order = $this->getOrderById($id);
		$this->orderStatusManager->setStatus($order, $status);
		$this->flashMessage('Status of order ' . $order->getNumber() . ' has been changed.', 'success');
		$this->sendOk();
	}


	public function postRemoveItem(int $orderId, int $itemId): void
	{
		$order = $this->getOrderById($orderId);
		$this->orderManager->removeItem($order, $itemId);
		$this->flashMessage('The item has been removed.', 'success');
		$this->sendOk();
	}


	public function postSave(int $id, float $price, int $deliverPrice, ?string $notice = null): void
	{
		$order = $this->getOrderById($id);
		$oldPrice = $order->getPrice();
		$order->setNotice($notice);
		$order->setDeliveryPrice((string) $deliverPrice);
		$order->setBasePrice(new Price($price, $order->getCurrency()));
		$this->orderManager->recountPrice($order);
		$this->entityManager->flush();
		$this->flashMessage(
			sprintf('Order "%s" has been saved.', $order->getNumber())
			. (abs((float) $oldPrice->minus($order->getPrice())->getValue()) > 0.001 ? ' The price has been recalculated.' : ''),
			'success',
		);
		$this->sendOk();
	}


	/**
	 * @param array<int, array{id: numeric-string, type: string, count: numeric-string, vat: numeric-string, price: numeric-string}> $items
	 */
	public function postChangeItems(int $id, array $items): void
	{
		$order = $this->getOrderById($id);
		foreach ($items as $item) {
			if ($item['type'] === 'product') {
				/** @var OrderItem $orderItem */
				$orderItem = $this->entityManager->getRepository(OrderItem::class)->find((int) $item['id']);
				$orderItem->setCount((int) $item['count']);
				$orderItem->setVat(new Price($item['vat'], $order->getCurrency()));
				$orderItem->dangerouslySetPrice(new Price($item['price'], $order->getCurrency()));
			}
		}

		$this->orderManager->recountPrice($order);
		$this->entityManager->flush();
		$this->flashMessage('Items has been changed.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function actionItems(int $id): void
	{
		$order = $this->getOrderById($id);
		$itemIds = [];
		foreach ($order->getItems() as $item) {
			$itemIds[] = $item->getId();
		}

		$selection = $this->entityManager->getRepository(Product::class)
			->createQueryBuilder('product')
			->select('PARTIAL product.{id, name, price, slug}')
			->addSelect('PARTIAL variant.{id, relationHash, price}')
			->leftJoin('product.variants', 'variant')
			->orderBy('product.position', 'DESC')
			->addOrderBy('product.name', 'ASC');

		if ($itemIds !== []) {
			$selection->where('product.id NOT IN (:ids)')
				->setParameter('ids', $itemIds);
		}

		$this->sendJson(
			$selection->getQuery()
				->getArrayResult(),
		);
	}


	public function postSetBranchId(int $orderId, ?int $branchId = null): void
	{
		$order = $this->getOrderById($orderId);
		$this->orderManager->setBranchId($order, $branchId);
		$this->sendOk();
	}


	public function postAddItem(int $orderId, int $itemId, ?int $variantId = null): void
	{
		$order = $this->getOrderById($orderId);
		/** @var Product $product */
		$product = $this->entityManager->getRepository(Product::class)->find($itemId);
		$price = $product->getSalePrice();
		$variant = null;
		if ($variantId !== null) {
			/** @var ProductVariant $variant */
			$variant = $this->entityManager->getRepository(ProductVariant::class)->find($variantId);
			$price = $variant->getPrice();
		}

		$item = new OrderItem($order, $product, $variant, 1, $price);
		$order->addItem($item);
		$this->orderManager->recountPrice($order);
		$this->entityManager->persist($item);
		$this->entityManager->flush();

		$this->sendOk();
	}


	/**
	 * @param numeric-string $price
	 */
	public function postAddVirtualItem(int $orderId, string $name, string $price): void
	{
		$order = $this->getOrderById($orderId);
		$item = new OrderItem($order, null, null, 1, $price);
		$item->setLabel($name);
		$order->addItem($item);
		$this->orderManager->recountPrice($order);
		$this->entityManager->persist($item);
		$this->entityManager->flush();

		$this->sendOk();
	}


	public function postCreateInvoice(int $id): void
	{
		if ($this->invoiceManager === null) {
			throw new \LogicException('Invoice manager has not been installed.');
		}
		$order = $this->getOrderById($id);
		try {
			$invoice = $this->invoiceManager->createInvoice($order);
			$this->flashMessage(sprintf('Invoice "%s" has been successfully created.', $invoice->getNumber()), 'success');
		} catch (\Throwable $e) {
			Debugger::log($e, ILogger::CRITICAL);
			$this->flashMessage(sprintf('Invoice failed to be issued: %s', $e->getMessage()), 'error');
		}
		$this->entityManager->flush();
		$this->sendOk();
	}


	public function postSendEmail(int $id, int $notificationId): void
	{
		$this->notification->sendNotification(
			order: $this->getOrderById($id),
			notification: $notificationId,
		);
		$this->flashMessage('Notification has been queued for sending.', 'success');
		$this->sendOk();
	}


	public function postSetOrderSale(int $id, string $sale): void
	{
		$order = $this->getOrderById($id);
		$order->setSale(new Price($sale, $order->getCurrency()));
		$this->entityManager->flush();
		$this->flashMessage('The sale has been set.', 'success');
		$this->sendOk();
	}


	public function postSetItemSale(int $id, int $itemId, string $sale): void
	{
		$order = $this->getOrderById($id);
		foreach ($order->getItems() as $item) {
			if ($item->getId() === $itemId) {
				$item->setSale(new Price($sale, $order->getCurrency()));
				$this->flashMessage('The sale has been set.', 'success');
				break;
			}
		}
		$this->orderManager->recountPrice($order);
		$this->entityManager->flush();
		$this->sendOk();
	}


	public function actionGroupList(): void
	{
		$groups = [];
		foreach ($this->orderGroupManager->getGroups() as $group) {
			$groups[] = [
				'id' => $group->getId(),
				'name' => $group->getName(),
				'code' => $group->getCode(),
				'active' => $group->isActive(),
				'default' => $group->isDefault(),
				'nextVariable' => $this->orderGenerator->getNextVariable($group),
			];
		}

		$this->sendJson(
			[
				'groups' => $groups,
			],
		);
	}


	public function actionStatusList(): void
	{
		$locale = $this->localization->getLocale();
		$notificationReadyToSend = $this->notification->getAvailableNotificationReadyToSend($locale);
		$notificationTypes = $this->notification->getAvailableTypes();

		$statusList = $this->orderStatusManager->getAllStatuses();

		$statuses = [];
		$selectList = [];
		foreach ($statusList as $status) {
			$notifications = [];
			foreach ($notificationTypes as $notificationType) {
				$key = sprintf('%s-%s-%s', $locale, $status->getCode(), $notificationType->value);
				$notifications[$notificationType->value] = isset($notificationReadyToSend[$key]);
			}
			$redirectOptions = [];
			foreach ($statusList as $redirectOption) {
				if ($redirectOption->getId() !== $status->getId()) {
					$redirectOptions[$redirectOption->getId()] = $redirectOption->getName();
				}
			}
			$redirectTo = $status->getRedirectTo();
			$statuses[] = [
				'id' => $status->getId(),
				'name' => $status->getName(),
				'internalName' => $status->getInternalName(),
				'label' => $status->getLabel(),
				'publicLabel' => $status->getPublicLabel(),
				'systemHandle' => $status->getSystemHandle(),
				'position' => $status->getWorkflowPosition(),
				'markAsPaid' => $status->isMarkAsPaid(),
				'createInvoice' => $status->isCreateInvoice(),
				'code' => $status->getCode(),
				'color' => $status->getColor(),
				'redirectTo' => $redirectTo?->getId(),
				'redirectOptions' => $this->formatBootstrapSelectArray([null => '- no -'] + $redirectOptions),
				'notification' => $notifications,
			];
			$selectList[$status->getCode()] = $status->getName();
		}

		$collections = [];
		foreach ($this->orderStatusManager->getCollections() as $collectionCode => $collection) {
			$collectionCodes = [];
			foreach ($collection['codes'] as $collectionCodeString) {
				$collectionCodeEntity = $this->orderStatusManager->getStatusByCode($collectionCodeString);
				$collectionCodes[] = [
					'label' => $collectionCodeEntity->getLabel(),
					'color' => $collectionCodeEntity->getColor(),
				];
			}
			$collections[] = [
				'code' => $collectionCode,
				'label' => $collection['label'],
				'codes' => $collectionCodes,
			];
		}

		$this->sendJson(
			[
				'statuses' => $statuses,
				'collections' => $collections,
				'selectList' => $this->formatBootstrapSelectArray($selectList),
			],
		);
	}


	/**
	 * @param array<int, array{
	 *     id: int,
	 *     code: string,
	 *     internalName: string,
	 *     label: string,
	 *     publicLabel: string,
	 *     systemHandle: string|null,
	 *     position: int|string,
	 *     markAsPaid: bool,
	 *     createInvoice: bool,
	 *     color: string,
	 *     redirectTo: int|null
	 * }> $statusList
	 */
	public function postSaveStatusList(array $statusList): void
	{
		foreach ($statusList as $item) {
			$status = $this->orderStatusManager->getStatusByCode($item['code']);
			$status->setInternalName($item['internalName']);
			$status->setLabel($item['label']);
			$status->setPublicLabel($item['publicLabel']);
			$status->setSystemHandle($item['systemHandle']);
			$status->setWorkflowPosition((int) $item['position']);
			$status->setMarkAsPaid($item['markAsPaid']);
			$status->setCreateInvoice($item['createInvoice']);
			$status->setColor($item['color']);
			$status->setRedirectTo(
				$item['redirectTo'] !== null
					? $this->orderStatusManager->getStatusById($item['redirectTo'])
					: null,
			);
		}
		$this->entityManager->flush();
		$this->flashMessage('Status list has been updated.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function actionNotificationDetail(int $statusId, OrderNotificationType $type, ?string $locale = null): void
	{
		$status = $this->orderStatusManager->getStatusById($statusId);
		$locale ??= $this->localization->getLocale();
		$this->sendJson(
			$this->notification->getNotificationData($status, $locale, $type),
		);
	}


	public function postSaveNotification(
		int $statusId,
		OrderNotificationType $type,
		string $subject,
		string $content,
		bool $active,
		?string $locale = null,
	): void {
		$status = $this->orderStatusManager->getStatusById($statusId);
		$locale ??= $this->localization->getLocale();
		$this->notification->setNotification(
			status: $status,
			locale: $locale,
			type: $type,
			subject: $subject,
			content: $content,
			active: $active,
		);
		$this->sendOk();
	}


	public function postCreateGroup(string $name, string $code): void
	{
		$this->orderGroupManager->create($name, $code);
		$this->entityManager->flush();
		$this->flashMessage('Group has been created.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function postCreateStatus(string $name, string $code): void
	{
		$this->orderStatusManager->createStatus($name, $code);
		$this->flashMessage('Status has been created.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	/**
	 * @param array<int, string> $statuses
	 */
	public function postCreateStatusCollection(string $code, string $label, array $statuses): void
	{
		$this->orderStatusManager->createCollection($code, $label, $statuses);
		$this->flashMessage('Status collection has been created.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function actionDocument(int $id): void
	{
		$order = $this->getOrderById($id);
		$documents = [];
		foreach ($this->documentManager->getDocuments($order) as $document) {
			$documents[] = [
				'id' => $document->getId(),
				'number' => $document->getNumber(),
				'label' => $document->getLabel(),
				'tags' => $document->getTags(),
				'downloadLink' => $document->getDownloadLink(),
			];
		}

		$this->sendJson(
			[
				'items' => $documents,
			],
		);
	}


	public function actionHistory(int $id): void
	{
		$order = $this->getOrderById($id);

		$statuses = [];
		foreach ($this->orderStatusManager->getHistory($order) as $statusHistory) {
			$statuses[] = [
				'id' => $statusHistory->getId(),
				'status' => $statusHistory->getStatus()->getLabel(),
				'insertedDate' => $statusHistory->getInsertedDate(),
			];
		}

		$notifications = [];
		foreach ($this->notification->getHistory($order) as $notificationHistory) {
			$notifications[] = [
				'id' => $notificationHistory->getId(),
				'label' => $notificationHistory->getNotification()->getStatus()->getLabel(),
				'type' => $notificationHistory->getNotification()->getType(),
				'subject' => $notificationHistory->getSubject(),
				'content' => $notificationHistory->getPlaintextContent(),
				'insertedDate' => $notificationHistory->getInsertedDate(),
			];
		}

		$this->sendJson(
			[
				'statusList' => $statuses,
				'notificationList' => $notifications,
			],
		);
	}


	public function actionWorkflowRules(): void
	{
		$events = [];
		foreach ($this->workflow->getEvents() as $event) {
			$newStatus = $event->getNewStatus();
			$events[] = [
				'id' => $event->getId(),
				'status' => $event->getStatus()->getName(),
				'newStatus' => $newStatus === null ? null : $newStatus->getName(),
				'label' => $event->getLabel(),
				'priority' => $event->getPriority(),
				'active' => $event->isActive(),
				'automaticInterval' => $event->getAutomaticInterval(),
				'insertedDate' => $event->getInsertedDate(),
				'activeFrom' => $event->getActiveFrom(),
				'activeTo' => $event->getActiveTo(),
				'ignoreIfPinged' => $event->isIgnoreIfPinged(),
				'markAsPinged' => $event->isMarkAsPinged(),
			];
		}

		$this->sendJson([
			'events' => $events,
		]);
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	private function getOrderById(int $id): Order
	{
		return $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->where('o.id = :id')
			->setParameter('id', $id)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @param array<string, numeric-string> $sum
	 * @return array<string, string>
	 */
	private function formatSumPrices(array $sum): array
	{
		$return = [];
		foreach ($sum as $currencyCode => $value) {
			$currency = $this->currencyManager->getCurrency($currencyCode);
			$return[$currencyCode] = (new Price($value, $currency))->render(true);
		}

		return $return;
	}
}
