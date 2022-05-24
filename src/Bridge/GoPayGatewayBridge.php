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
	private OrderOnlinePaymentRepository $orderOnlinePaymentRepository;

	private ?Client $client = null;


	public function __construct(
		private OrderStatusManager $orderStatusManager,
		private EntityManagerInterface $entityManager,
		private Configuration $configuration,
		private ?LoggerInterface $logger = null,
	) {
		$orderOnlinePaymentRepository = $entityManager->getRepository(OrderOnlinePayment::class);
		assert($orderOnlinePaymentRepository instanceof OrderOnlinePaymentRepository);
		$this->orderOnlinePaymentRepository = $orderOnlinePaymentRepository;
	}


	public function pay(OrderInterface $order): GatewayResponse
	{
		$customer = $order->getCustomer();
		if ($customer === null) {
			throw new \InvalidArgumentException(sprintf('Customer for order %s is mandatory.', $order->getNumber()));
		}
		$linkGenerator = WebController::getLinkGenerator();
		$paymentEntity = PaymentFactory::create([
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
			'items' => $this->serializeItems($order),
			'callback' => [
				'return_url' => $linkGenerator->paymentHandle($order, handler: 'checkPayment'),
				'notify_url' => $linkGenerator->paymentHandle($order, handler: 'notify'),
			],
			'lang' => strtoupper($order->getLocale()),
		]);
		$response = $this->getClient()->payments->createPayment($paymentEntity);
		if (isset($response['gw_url'])) {
			$payment = new OrderOnlinePayment($order, (string) $response['id']);
			$this->entityManager->persist($payment);
			assert($order instanceof Order);
			$order->addPayment($payment);
			$this->entityManager->flush();

			return new GatewayResponse($response['gw_url']);
		}

		$this->logger?->critical(
			'Gateway error: ' . json_encode($response, JSON_THROW_ON_ERROR),
			iterator_to_array($response->getIterator()),
		);

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
		return OrderStatus::StatusNew;
	}


	public function checkPaymentStatus(OrderInterface $order, ?string $id = null): GatewayResponse
	{
		$linkGenerator = WebController::getLinkGenerator();
		try {
			$payment = $this->orderOnlinePaymentRepository->getByGoPayIdAndHash($order->getHash(), $id);
			$payment->setCheckedNow();
		} catch (NoResultException | NonUniqueResultException) {
			return new GatewayResponse(
				redirect: $linkGenerator->default($order),
				errorMessage: 'Order processing error.',
			);
		}
		if ($payment->getOrder()->getStatus()->getCode() === OrderStatus::StatusPaid) {
			return new GatewayResponse(
				redirect: $linkGenerator->default($order),
				errorMessage: 'The order has already been paid for.',
			);
		}

		$client = $this->getClient();
		$status = $client->payments->verify((int) $payment->getGatewayId())->getData()['state'] ?? '';

		$statusChanged = $status !== $payment->getStatus();
		$payment->setStatus($status);
		$this->entityManager->flush();

		if ($status === PaymentState::PAID) {
			$order->setPaid(true);
			$this->orderStatusManager->setStatus($payment->getOrder(), OrderStatus::StatusPaid, force: true);

			return new GatewayResponse(
				redirect: $linkGenerator->default($order),
			);
		}
		if ($statusChanged === true) {
			assert($order instanceof Order);
			$this->orderStatusManager->setStatus($order, OrderStatus::StatusPaymentFailed, force: true);
		}

		return new GatewayResponse(
			redirect: $linkGenerator->default($order),
			errorMessage:
			'An error occurred during the payment processing. Please try to pay the order again. We have also sent you the details by email.',
		);
	}


	private function getClient(): Client
	{
		if ($this->client === null) {
			/** @var array{go-id: string, client-id: string, client-secret: string, environment: string} $config */
			$config = $this->configuration->getMultipleMandatory(['go-id', 'client-id', 'client-secret', 'environment'], 'gopay');

			$this->client = new Client(new Config(
				(float) $config['go-id'],
				$config['client-id'],
				$config['client-secret'],
				strtoupper($config['environment']) === 'TEST' ? Config::TEST : Config::PROD,
			));
		}

		return $this->client;
	}


	/**
	 * @return array<int, array{name: string, amount: float}>
	 */
	private function serializeItems(OrderInterface $order): array
	{
		$return = [];
		foreach ($order->getItems() as $orderItem) {
			$orderItemUnitPrice = (float) $orderItem->getFinalPrice()->getValue();
			$return[] = [
				'name' => $orderItem->getLabel(),
				'amount' => $orderItemUnitPrice * $orderItem->getCount(),
				'count' => $orderItem->getCount(),
				'vat_rate' => (int) $orderItem->getVat()->getValue(),
			];
		}
		$delivery = $order->getDelivery();
		if ($delivery !== null) {
			$return[] = [
				'name' => $delivery->getLabel(),
				'amount' => (float) $order->getDeliveryPrice()->getValue(),
				'count' => 1,
				'vat_rate' => 21,
			];
		}
		$payment = $order->getPayment();
		if ($payment !== null) {
			$return[] = [
				'name' => $payment->getName(),
				'amount' => (float) $order->getPaymentPrice()->getValue(),
				'count' => 1,
				'vat_rate' => 21,
			];
		}

		$finalPrice = 0;
		foreach ($return as $item) {
			$finalPrice += $item['amount'];
		}

		$diff = ((float) $order->getPrice()->getValue()) - $finalPrice;
		if (abs($diff) > 0) {
			$return[] = [
				'name' => 'Rounding',
				'amount' => $diff,
				'count' => 1,
				'vat_rate' => 0,
			];
		}

		return $return;
	}
}
