<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\DynamicConfiguration\Configuration;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\Service\OrderPaymentGatewayInterface;
use Baraja\Shop\Order\Application\WebController;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderOnlinePayment;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\Payment\Gateway\GatewayResponse;
use Baraja\Shop\Order\Repository\OrderOnlinePaymentRepository;
use Baraja\Shop\Order\Repository\OrderRepository;
use Contributte\GopayInline\Api\Entity\PaymentFactory;
use Contributte\GopayInline\Api\Lists\PaymentInstrument;
use Contributte\GopayInline\Api\Lists\PaymentState;
use Contributte\GopayInline\Client;
use Contributte\GopayInline\Config;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Psr\Log\LoggerInterface;

final class GoPayGatewayBridge implements OrderPaymentGatewayInterface
{
	private OrderRepository $orderRepository;

	private OrderOnlinePaymentRepository $orderOnlinePaymentRepository;

	private ?Client $client = null;


	public function __construct(
		private OrderStatusManager $orderStatusManager,
		private EntityManagerInterface $entityManager,
		private Configuration $configuration,
		private ?LoggerInterface $logger = null,
	) {
		$orderRepository = $entityManager->getRepository(Order::class);
		$orderOnlinePaymentRepository = $entityManager->getRepository(OrderOnlinePayment::class);
		assert($orderRepository instanceof OrderRepository);
		assert($orderOnlinePaymentRepository instanceof OrderOnlinePaymentRepository);
		$this->orderRepository = $orderRepository;
		$this->orderOnlinePaymentRepository = $orderOnlinePaymentRepository;
	}


	public function pay(OrderInterface $order): GatewayResponse
	{
		$customer = $order->getCustomer();
		if ($customer === null) {
			throw new \InvalidArgumentException(sprintf('Customer for order %s is mandatory.', $order->getNumber()));
		}
		$linkGenerator = WebController::getLinkGenerator();

		$items = [];
		foreach ($order->getItems() as $orderItem) {
			$items[] = [
				'name' => $orderItem->getLabel(),
				'amount' => (float) $orderItem->getFinalPrice()->getValue(),
			];
		}

		$response = $this->getClient()->payments->createPayment(
			PaymentFactory::create(
				[
					'payer' => [
						'default_payment_instrument' => PaymentInstrument::PAYMENT_CARD,
						'allowed_payment_instruments' => [PaymentInstrument::PAYMENT_CARD],
						'contact' => [
							'first_name' => $customer->getFirstName(),
							'last_name' => $customer->getLastName(),
							'email' => $customer->getEmail(),
						],
					],
					'amount' => (float) $order->getPrice()->getValue(),
					'currency' => $order->getCurrency()->getCode(),
					'order_number' => $order->getNumber(),
					'order_description' => (string) $order->getNotice(),
					'items' => $items,
					'callback' => [
						'return_url' => $linkGenerator->paymentHandle(order: $order, handler: 'checkPayment'),
						'notify_url' => $linkGenerator->paymentHandle(order: $order, handler: 'notify'),
					],
					'lang' => strtoupper($order->getLocale()),
				]
			)
		);

		if (isset($response['gw_url'])) {
			$payment = new OrderOnlinePayment($order, (string) $response['id']);
			$this->entityManager->persist($payment);
			$order->addPayment($payment);
			$this->entityManager->flush();

			return new GatewayResponse($response['gw_url']);
		}

		if ($this->logger !== null) {
			$this->logger->critical(
				'Gateway error: ' . json_encode($response, JSON_THROW_ON_ERROR),
				iterator_to_array($response->getIterator()),
			);
		}

		return new GatewayResponse(
			$linkGenerator->default($order),
			'An error occurred during payment processing.',
		);
	}


	public function getPaymentMethodCode(): string
	{
		return 'gopay';
	}


	public function getPaymentStatus(OrderInterface $order): string
	{
		return OrderStatus::STATUS_NEW;
	}


	public function handleCheckPayment(string $hash, ?int $id = null): GatewayResponse
	{
		$linkGenerator = WebController::getLinkGenerator();
		$order = $this->orderRepository->getByHash($hash);
		try {
			$payment = $this->orderOnlinePaymentRepository->getByGoPayIdAndHash($hash, $id);
		} catch (NoResultException | NonUniqueResultException) {
			return new GatewayResponse(
				redirect: $linkGenerator->default($order),
				errorMessage: 'Order processing error.'
			);
		}
		if ($payment->getOrder()->getStatus()->getCode() === OrderStatus::STATUS_PAID) {
			return new GatewayResponse(
				redirect: $linkGenerator->default($order),
				errorMessage: 'The order has already been paid for.'
			);
		}

		$client = $this->getClient();
		$status = $client->payments->verify((int) $payment->getGatewayId())->getData()['state'] ?? '';

		$statusChanged = $status !== $payment->getStatus();
		$payment->setStatus($status);
		$this->entityManager->flush();

		if ($status === PaymentState::PAID) {
			$order->setPaid(true);
			$this->orderStatusManager->setStatus($payment->getOrder(), OrderStatus::STATUS_PAID);

			return new GatewayResponse(
				redirect: $linkGenerator->default($order),
			);
		}
		if ($statusChanged === true) {
			$this->orderStatusManager->setStatus($order, OrderStatus::STATUS_PAYMENT_FAILED);
		}

		return new GatewayResponse(
			redirect: $linkGenerator->default($order),
			errorMessage:
			'An error occurred during the payment processing. Please try to pay the order again. We have also sent you the details by email.'
		);
	}


	public function handleNotify(string $orderId, ?int $id = null): GatewayResponse
	{
		return $this->handleCheckPayment($orderId, $id);
	}


	private function getClient(): Client
	{
		if ($this->client === null) {
			/** @var array{go-id: string, client-id: string, client-secret: string, environment: string} $config */
			$config = $this->configuration->getMultipleMandatory(['go-id', 'client-id', 'client-secret', 'environment'], 'gopay');

			$this->client = new Client(
				new Config(
					(float) $config['go-id'],
					$config['client-id'],
					$config['client-secret'],
					$config['environment'],
				)
			);
		}

		return $this->client;
	}
}
