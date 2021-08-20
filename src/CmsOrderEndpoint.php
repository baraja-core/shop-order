<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Country\CountryManager;
use Baraja\Doctrine\EntityManager;
use Baraja\Search\Search;
use Baraja\Shop\Address\Entity\Address;
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
		private OrderGenerator $orderGenerator,
		private OrderDeliveryManager $deliveryManager,
		private Emailer $emailer,
		private OrderStatusManager $orderStatusManager,
		private BranchManager $branchManager,
		private CountryManager $countryManager,
		private OrderDocumentManager $documentManager,
		private Search $search,
		private OrderRepository $orderRepository,
		private ?InvoiceManagerInterface $invoiceManager = null,
	) {
	}


	public function actionDefault(
		?string $query = null,
		?string $status = null,
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
			limit: $limit,
			page: $page,
		);

		/** @var Delivery[] $deliveries */
		$deliveries = $this->entityManager->getRepository(Delivery::class)->findAll();

		/** @var Payment[] $payments */
		$payments = $this->entityManager->getRepository(Payment::class)->findAll();

		$sum = 0;
		$return = [];
		/** @var Order $order */
		foreach ($feed['orders'] as $order) {
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
				],
				'delivery' => [
					'id' => $order->getDelivery()->getId(),
					'name' => (string) $order->getDelivery()->getName(),
					'price' => $order->getDeliveryPrice(),
					'color' => $order->getDelivery()->getColor(),
				],
				'payment' => [
					'id' => $order->getPayment()->getId(),
					'name' => $order->getPayment()->getName(),
					'price' => $order->getPayment()->getPrice(),
					'color' => $order->getPayment()->getColor(),
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
				'invoices' => [],
				/*(function ($items) use ($order): array
				{
					$return = [];
					/** @var Invoice $item * /
					foreach ($items as $item) {
						$return[] = [
							'id' => $item->getId(),
							'number' => $item->getNumber(),
							'url' => $this->link(
								'Front:Invoice:default', [
									'number' => $item->getNumber(),
									'hash' => $order->getHash(),
								]
							),
						];
					}

					return $return;
				})(
					$order->getInvoices()
				),*/
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
				'statuses' => $this->formatBootstrapSelectArray($this->orderStatusManager->getKeyValueList()),
				'sum' => $sum,
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
				'paginator' => (new Paginator)
					->setItemCount($feed['count'])
					->setItemsPerPage($limit)
					->setPage($page),
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
		$items[] = [
			'id' => null,
			'name' => 'Doprava ' . $order->getDelivery()->getName(),
			'count' => 1,
			'price' => $order->getDeliveryPrice(),
			'type' => 'delivery',
		];
		$items[] = [
			'id' => null,
			'name' => 'Platba ' . $order->getPayment()->getName(),
			'count' => 1,
			'price' => $order->getPayment()->getPrice(),
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
		if ($branchId !== null) {
			$branch = $this->branchManager->getBranchById($order->getDelivery(), $branchId);
		}

		$formatAddress = static function (Address $address): array
		{
			return [
				'firstName' => $address->getFirstName(),
				'lastName' => $address->getLastName(),
				'street' => $address->getStreet(),
				'city' => $address->getCity(),
				'zip' => $address->getZip(),
				'country' => $address->getCountry(),
				'companyName' => $address->getCompanyName(),
				'ic' => $address->getCin(),
				'dic' => $address->getTin(),
			];
		};

		$this->sendJson(
			[
				'id' => $id,
				'number' => $order->getNumber(),
				'status' => $order->getStatus(),
				'price' => $order->getPrice(),
				'sale' => $order->getSale(),
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
				'deliveryList' => $this->formatBootstrapSelectArray($deliverySelectbox),
				'paymentList' => $this->formatBootstrapSelectArray($paymentSelectbox),
				'deliveryId' => $order->getDelivery()
					->getId(),
				'deliverPrice' => $order->getDeliveryPrice(),
				'deliveryBranch' => $branchId !== null
					? (static function (int $id, ?BranchInterface $branch)
					{
						if ($branch === null) {
							return [
								'id' => $id,
							];
						}

						return $branch;
					})(
						$branchId, $branch
					) : null,
				'deliveryBranchError' => $branchId !== null && $branch === null,
				'paymentId' => $order->getPayment()->getId(),
				'items' => $items,
				'transactions' => $transactions,
				'payments' => $payments,
				'invoices' => $invoices,
				'package' => $packages ?: null,
				'packageHandoverUrl' => $order->getHandoverUrl(),
			]
		);
	}


	public function postCreateEmptyOrder(int $customerId): void
	{
		try {
			/** @var Customer $customer */
			$customer = $this->entityManager->getRepository(Customer::class)
				->createQueryBuilder('c')
				->where('c.id = :id')
				->setParameter('id', $customerId)
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError('Customer "' . $customerId . '" does not exist.');
		}

		$order = $this->orderGenerator->createEmptyOrder($customer);
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

		$this->sendJson(
			[
				'items' => $return,
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

		foreach ($orders as $order) {
			try {
				$this->deliveryManager->sendOrder($order);
			} catch (\InvalidArgumentException $e) {
				$this->flashMessage($e->getMessage(), self::FLASH_MESSAGE_ERROR);
			}
		}

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
			$address->setCountry($this->countryManager->getByCode((string) $data['country']));
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
		$this->deliveryManager->sendOrder($order);
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
			$this->flashMessage('Invoice ' . $invoice->getNumber() . ' has been successfully created.', 'success');
		} catch (\Throwable $e) {
			Debugger::log($e, ILogger::CRITICAL);
			$this->flashMessage('Invoice failed to be issued:' . $e->getMessage(), 'error');
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
				$this->emailer->sendOrderInvoice($document, $this->invoiceManager->getInvoicePath($document));
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
