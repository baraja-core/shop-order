<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Country\CountryManagerAccessor;
use Baraja\Doctrine\EntityManager;
use Baraja\Search\Search;
use Baraja\Shop\Address\Entity\Address;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\Shop\Customer\CustomerManager;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\BranchManager;
use Baraja\Shop\Delivery\Entity\BranchInterface;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Order\Delivery\OrderDeliveryManager;
use Baraja\Shop\Order\Document\OrderDocumentManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderItem;
use Baraja\Shop\Order\Entity\OrderOnlinePayment;
use Baraja\Shop\Order\Repository\OrderRepository;
use Baraja\Shop\Order\Status\OrderWorkflow;
use Baraja\Shop\Payment\Entity\Payment;
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
	public function __construct(
		private EntityManager $entityManager,
		private OrderManager $orderManager,
		private CustomerManager $customerManager,
		private OrderGroupManager $orderGroupManager,
		private OrderGenerator $orderGenerator,
		private OrderDeliveryManager $deliveryManager,
		private Emailer $emailer,
		private OrderStatusManager $orderStatusManager,
		private BranchManager $branchManager,
		private CountryManagerAccessor $countryManager,
		private OrderDocumentManager $documentManager,
		private Search $search,
		private OrderRepository $orderRepository,
		private OrderWorkflow $workflow,
		private CurrencyManagerAccessor $currencyManager,
		private ?InvoiceManagerInterface $invoiceManager = null,
	) {
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
		int $limit = 128,
		int $page = 1,
	): void {
		$feed = $this->orderRepository->getFeed(
			query: $query,
			status: $status,
			delivery: $delivery,
			payment: $payment,
			orderBy: $orderBy,
			dateFrom: $dateFrom,
			dateTo: $dateTo,
			group: $group,
			limit: $limit,
			page: $page,
		);

		$sum = 0;
		$return = [];
		/** @var Order $order */
		foreach ($feed['orders'] as $order) {
			$deliveryItem = $order->getDelivery();
			$paymentItem = $order->getPayment();
			$return[] = [
				'id' => $order->getId(),
				'checked' => false,
				'number' => $order->getNumber(),
				'status' => [
					'code' => $order->getStatus()->getCode(),
					'color' => $order->getStatus()->getColor(),
					'label' => $order->getStatus()->getName(),
				],
				'price' => $order->getBasePrice(),
				'sale' => $order->getSale(),
				'finalPrice' => $order->getPrice(),
				'currency' => $order->getCurrency(),
				'notice' => $order->getNotice(),
				'insertedDate' => $order->getInsertedDate()->format('d.m.y H:i'),
				'updatedDate' => $order->getUpdatedDate()->format('d.m.y H:i'),
				'package' => count($order->getPackages()),
				'customer' => [
					'id' => $order->getCustomer()->getId(),
					'email' => $order->getCustomer()->getEmail(),
					'firstName' => $order->getCustomer()->getFirstName(),
					'lastName' => $order->getCustomer()->getLastName(),
					'phone' => $order->getCustomer()->getPhone(),
					'premium' => $order->getCustomer()->isPremium(),
					'ban' => $order->getCustomer()->isBan(),
				],
				'delivery' => [
					'id' => $deliveryItem === null ? null : $deliveryItem->getId(),
					'name' => $deliveryItem === null ? null : (string) $deliveryItem->getName(),
					'price' => $order->getDeliveryPrice(),
					'color' => $deliveryItem === null ? null : $deliveryItem->getColor(),
				],
				'payment' => [
					'id' => $paymentItem === null ? null : $paymentItem->getId(),
					'name' => $paymentItem === null ? null : $paymentItem->getName(),
					'price' => $paymentItem === null ? 0 : $paymentItem->getPrice(),
					'color' => $paymentItem === null ? null : $paymentItem->getColor(),
				],
				'items' => (static function ($items): array
				{
					$return = [];
					/** @var OrderItem $item */
					foreach ($items as $item) {
						$return[] = [
							'id' => $item->getId(),
							'name' => $item->getLabel(),
							'count' => $item->getCount(),
							'price' => $item->getPrice(),
							'sale' => $item->getSale(),
							'finalPrice' => $item->getFinalPrice(),
						];
					}

					return $return;
				})(
					$order->getItems()
				),
				'documents' => [
					[
						'url' => '#',
						'label' => 'Test',
					],
				],
				'payments' => (static function ($items): array
				{
					$return = [];
					/** @var OrderOnlinePayment $item */
					foreach ($items as $item) {
						$return[] = [
							'id' => $item->getId(),
						];
					}

					return $return;
				})(
					$order->getPayments()
				),
			];
			$sum += $order->getPrice();
		}

		$this->sendJson(
			[
				'items' => $return,
				'sum' => $sum,
				'sumCurrency' => $this->currencyManager->get()->getMainCurrency()->getCode(),
				'paginator' => (new Paginator)
					->setItemCount($feed['count'])
					->setItemsPerPage($limit)
					->setPage($page),
			]
		);
	}


	public function actionFilter(): void
	{
		/** @var Delivery[] $deliveries */
		$deliveries = $this->entityManager->getRepository(Delivery::class)->findAll();

		/** @var Payment[] $payments */
		$payments = $this->entityManager->getRepository(Payment::class)->findAll();

		$groups = [];
		foreach ($this->orderGroupManager->getGroups() as $groupItem) {
			$groups[$groupItem->getCode()] = (string) $groupItem->getName();
		}

		$this->sendJson(
			[
				'loaded' => true,
				'statuses' => $this->formatBootstrapSelectArray($this->orderStatusManager->getKeyValueList()),
				'defaultGroup' => $this->orderGroupManager->getDefaultGroup()->getCode(),
				'filterGroups' => $this->formatBootstrapSelectArray($groups),
				'filterStatuses' => $this->formatBootstrapSelectArray(
					[null => '- status -'] + $this->orderStatusManager->getKeyValueList(true)
				),
				'filterPayments' => $this->formatBootstrapSelectArray(
					[null => '- payment -'] + (static function (array $payments): array
					{
						$return = [];
						/** @var Payment $payment */
						foreach ($payments as $payment) {
							$return[$payment->getId()] = $payment->getName();
						}

						return $return;
					})(
						$payments
					)
				),
				'filterDeliveries' => $this->formatBootstrapSelectArray(
					[null => '- delivery -'] + (static function (array $deliveries): array
					{
						$return = [];
						/** @var Delivery $delivery */
						foreach ($deliveries as $delivery) {
							$return[$delivery->getId()] = (string) $delivery->getName();
						}

						return $return;
					})(
						$deliveries
					)
				),
				'orderByOptions' => $this->formatBootstrapSelectArray(
					[
						null => 'Latest',
						'old' => 'Oldest',
						'number' => 'Number ASC',
						'number-desc' => 'Number DESC',
					]
				),
			]
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
				'price' => $payment->getPrice(),
				'status' => $payment->getStatus(),
				'insertedDate' => $payment->getDate(),
			];
		}

		$items = [];
		foreach ($order->getItems() as $item) {
			$items[] = [
				'id' => $item->getId(),
				'productId' => $item->getProduct()->getId(),
				'variantId' => $item->getVariant() === null ? null : $item->getVariant()->getId(),
				'name' => $item->getLabel(),
				'count' => $item->getCount(),
				'price' => $item->getPrice(),
				'sale' => $item->getSale(),
				'finalPrice' => $item->getFinalPrice(),
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
			'price' => $order->getDeliveryPrice(),
			'type' => 'delivery',
		];
		$paymentItem = $order->getPayment();
		$items[] = [
			'id' => null,
			'name' => $paymentItem === null
				? 'Unknown payment'
				: 'Payment ' . $paymentItem->getName(),
			'count' => 1,
			'price' => $paymentItem === null ? 0 : $paymentItem->getPrice(),
			'type' => 'payment',
		];

		/** @var Delivery[] $deliveryList */
		$deliveryList = $this->entityManager->getRepository(Delivery::class)->findAll();
		$deliverySelectbox = [];
		foreach ($deliveryList as $delivery) {
			$deliverySelectbox[$delivery->getId()] = $delivery->getName() . ' (' . $delivery->getPrice() . ' Kč)';
		}

		/** @var Payment[] $paymentList */
		$paymentList = $this->entityManager->getRepository(Payment::class)->findAll();
		$paymentSelectbox = [];
		foreach ($paymentList as $payment) {
			$paymentSelectbox[$payment->getId()] = $payment->getName() . ' (' . $payment->getPrice() . ' Kč)';
		}

		$invoices = [];
		/*foreach ($order->getInvoices() as $invoice) {
			$invoices[] = [
				'id' => $invoice->getId(),
				'number' => $invoice->getNumber(),
				'price' => $invoice->getPrice(),
				'paid' => $invoice->isPaid(),
				'date' => $invoice->getInsertedDate(),
				'url' => $this->link(
					'Front:Invoice:default', [
						'number' => $invoice->getNumber(),
						'hash' => $order->getHash(),
					]
				),
			];
		}*/

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

		$branch = null;
		$branchId = $order->getDeliveryBranchId();
		if ($branchId !== null && $deliveryItem !== null) {
			$branch = $this->branchManager->getBranchById($deliveryItem, $branchId);
		}

		$formatAddress = static function (Address $address): array
		{
			return [
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
		};
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
				'status' => $order->getStatus(),
				'price' => $order->getPrice(),
				'sale' => $order->getSale(),
				'currency' => $order->getCurrency(),
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
				'deliveryId' => $deliveryItem === null ? null : $deliveryItem->getId(),
				'deliverPrice' => $order->getDeliveryPrice(),
				'deliveryBranch' => $branchId !== null
					? (static function (int $id, ?BranchInterface $branch): BranchInterface|array
					{
						return $branch ?? [
							'id' => $id,
						];
					})(
						$branchId, $branch
					) : null,
				'deliveryBranchError' => $branchId !== null && $branch === null,
				'paymentId' => $paymentItem === null ? null : $paymentItem->getId(),
				'items' => $items,
				'transactions' => $transactions,
				'payments' => $payments,
				'invoices' => $invoices,
				'package' => $packages ?: null,
				'packageHandoverUrl' => $order->getHandoverUrl(),
			]
		);
	}


	public function postCreateEmptyOrder(int $customerId, int $countryId, string $groupId): void
	{
		try {
			$customer = $this->customerManager->getById($customerId);
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
			group: $this->orderGroupManager->getByCode($groupId)
		);
		$this->flashMessage('Order "' . $order->getNumber() . '" has been created.', 'success');
		$this->sendJson(
			[
				'id' => $order->getId(),
				'number' => $order->getNumber(),
			]
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
			]
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

		$hydrate = function (Address $address, array $data): void
		{
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
		$hydrate($order->getInvoiceAddress(), $invoiceAddress);
		$this->flashMessage('The addresses have been successfully saved.', 'success');
		$this->entityManager->flush();

		if ($this->documentManager->isDocument((int) $order->getId())) {
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
		$order->setDeliveryPrice($deliverPrice);
		$order->setPrice($price);
		$this->orderManager->recountPrice($order);
		$this->entityManager->flush();
		$this->flashMessage(
			'Order ' . $order->getNumber() . ' has been saved.'
			. (abs($oldPrice - $order->getPrice()) > 0.001 ? ' The price has been recalculated.' : ''),
			'success'
		);
		$this->sendOk();
	}


	public function postChangeQuantity(int $id, array $items): void
	{
		$order = $this->getOrderById($id);
		foreach ($items as $item) {
			if ($item['type'] === 'product') {
				/** @var OrderItem $orderItem */
				$orderItem = $this->entityManager->getRepository(OrderItem::class)->find((int) $item['id']);
				$orderItem->setCount((int) $item['count']);
			}
		}

		$this->orderManager->recountPrice($order);
		$this->entityManager->flush();
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
				->getArrayResult()
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


	public function postCreateInvoice(int $id): void
	{
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


	public function postSendEmail(int $id, string $mail): void
	{
		$order = $this->getOrderById($id);
		if ($mail === 'new-order') {
			$this->emailer->sendNewOrder($order);
		}
		if ($mail === 'paid') {
			$this->emailer->sendOrderPaid($order);
		}
		if ($mail === 'invoice') {
			foreach ($this->documentManager->getDocuments((int) $order->getId()) as $document) {
				if ($document->hasTag('invoice')) {
					$this->emailer->sendOrderInvoice($document);
				}
			}
		}

		$this->flashMessage('E-mail "' . $mail . '" has been sent.', 'success');
		$this->sendOk();
	}


	public function postSetOrderSale(int $id, float $sale): void
	{
		$order = $this->getOrderById($id);
		$order->setSale($sale);
		$this->entityManager->flush();
		$this->flashMessage('The sale has been set.', 'success');
		$this->sendOk();
	}


	public function postSetItemSale(int $id, int $itemId, float $sale): void
	{
		$order = $this->getOrderById($id);
		foreach ($order->getItems() as $item) {
			if ($item->getId() === $itemId) {
				$item->setSale($sale);
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
			]
		);
	}


	public function actionStatusList(): void
	{
		$statuses = [];
		$selectList = [];
		foreach ($this->orderStatusManager->getAllStatuses() as $status) {
			$statuses[] = [
				'id' => $status->getId(),
				'name' => $status->getName(),
				'internalName' => $status->getInternalName(),
				'label' => $status->getLabel(),
				'publicLabel' => $status->getPublicLabel(),
				'systemHandle' => $status->getSystemHandle(),
				'position' => $status->getWorkflowPosition(),
				'code' => $status->getCode(),
				'color' => $status->getColor(),
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
			]
		);
	}


	/**
	 * @param array<int, array{id: int, code: string, internalName: string, label: string, publicLabel: string,
	 *     systemHandle: string|null, position: int, color: string}> $statusList
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
			$status->setColor($item['color']);
		}
		$this->entityManager->flush();
		$this->flashMessage('Status list has been updated.', self::FLASH_MESSAGE_SUCCESS);
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
			]
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
				'emailTemplate' => $event->getEmailTemplate(),
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
}
