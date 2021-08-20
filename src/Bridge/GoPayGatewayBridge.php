<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Doctrine\EntityManager;
use Baraja\DynamicConfiguration\Configuration;
use Baraja\Shop\Order\Application\WebController;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderOnlinePayment;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\Payment\Gateway\Gateway;
use Baraja\Shop\Order\Payment\Gateway\GatewayResponse;
use Baraja\Shop\Order\Payment\OrderPaymentProvider;
use Contributte\GopayInline\Api\Entity\PaymentFactory;
use Contributte\GopayInline\Api\Lists\Currency;
use Contributte\GopayInline\Api\Lists\PaymentInstrument;
use Contributte\GopayInline\Api\Lists\PaymentState;
use Contributte\GopayInline\Client;
use Contributte\GopayInline\Config;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\ILogger;

final class GoPayGatewayBridge implements Gateway, OrderPaymentProvider
{
	public function __construct(
		private OrderStatusManager $orderStatusManager,
		private EntityManager $entityManager,
		private Configuration $configuration,
		private Emailer $emailer,
	) {
	}


	public function pay(Order $order): GatewayResponse
	{
		$response = $this->getClient()->payments->createPayment(
			PaymentFactory::create(
				[
					'payer' => [
						'default_payment_instrument' => PaymentInstrument::PAYMENT_CARD,
						'allowed_payment_instruments' => [PaymentInstrument::PAYMENT_CARD],
						'contact' => [
							'first_name' => $order->getCustomer()->getFirstName(),
							'last_name' => $order->getCustomer()->getLastName(),
							'email' => $order->getCustomer()->getEmail(),
						],
					],
					'amount' => $order->getPrice(),
					'currency' => Currency::CZK, // TODO: Use default currency
					'order_number' => $order->getNumber(),
					'order_description' => 'Objednávka ' . $order->getNumber(),
					'items' => [
						[
							'name' => 'Objednávka ' . $order->getNumber(),
							'amount' => $order->getPrice(),
						],
					],
					'callback' => [
						'return_url' => WebController::getLinkGenerator()->paymentHandle(
							order: $order,
							handler: 'checkPayment',
							params: ['hash' => $order->getHash()],
						),
						'notify_url' => WebController::getLinkGenerator()->paymentHandle(
							order: $order,
							handler: 'notify',
							params: ['orderId' => $order->getHash()],
						),
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

		Debugger::log(new \Exception(Dumper::toText($response)), ILogger::CRITICAL);

		return new GatewayResponse(
			WebController::getLinkGenerator()->default($order),
			'An error occurred during payment processing.',
		);
	}


	public function getPaymentMethodCode(): string
	{
		return 'gopay';
	}


	public function getPaymentStatus(Order $order): string
	{
		return 'new';
	}


	public function handleCheckPayment(string $hash, ?int $id = null): GatewayResponse
	{
		/** @var Order $order */
		$order = $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->where('o.hash = :hash')
			->setParameter('hash', $hash)
			->getQuery()
			->getSingleResult();

		try {
			/** @var OrderOnlinePayment $payment */
			$payment = $this->entityManager->getRepository(OrderOnlinePayment::class)
				->createQueryBuilder('payment')
				->leftJoin('payment.order', 'o')
				->where('payment.gopayId = :gopayId')
				->andWhere('o.hash = :orderHash')
				->setParameters(
					[
						'gopayId' => $id,
						'orderHash' => $hash,
					]
				)
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			return new GatewayResponse(
				redirect: WebController::getLinkGenerator()->default($order),
				errorMessage: 'Chyba při zpracování objednávky.'
			);
		}
		if ($payment->getOrder()->getStatus()->getCode() === OrderStatus::STATUS_PAID) {
			return new GatewayResponse(
				redirect: WebController::getLinkGenerator()->default($order),
				errorMessage: 'Objednávka již byla zaplacena.'
			);
		}

		$client = $this->getClient();
		$status = $client->payments->verify((int) $payment->getGatewayId())->getData()['state'] ?? '';

		$statusChanged = $status !== $payment->getStatus();
		$payment->setStatus($status);
		$this->entityManager->flush();

		if ($status === PaymentState::PAID) {
			try {
				$this->orderStatusManager->setStatus($payment->getOrder(), OrderStatus::STATUS_PAID);
			} catch (\Throwable $e) {
				Debugger::log($e);

				return new GatewayResponse(
					redirect: WebController::getLinkGenerator()->default($order),
					errorMessage: 'Odeslání e-mailu se stavem objednávky selhalo.'
				);
			}

			return new GatewayResponse(
				redirect: WebController::getLinkGenerator()->default($order),
			);
		}
		if ($statusChanged === true) {
			try {
				$this->emailer->sendOrderFailMail($payment);
			} catch (\Throwable $e) {
				Debugger::log($e);

				return new GatewayResponse(
					redirect: null,
					errorMessage: 'Odeslání e-mailu se stavem objednávky selhalo.'
				);
			}
		}

		return new GatewayResponse(
			redirect: WebController::getLinkGenerator()->default($order),
			errorMessage:
			'Při zpracování platby došlo k chybě. Prosím, pokuste se objednávku znovu zaplatit. Podrobnosti jsme Vám poslali také na e-mail.'
		);
	}


	public function handleNotify(string $orderId, ?int $id = null): GatewayResponse
	{
		return $this->handleCheckPayment($orderId, $id);
	}


	private function getClient(): Client
	{
		/** @var array{go-id: string, client-id: string, client-secret: string} $config */
		$config = $this->configuration->getMultipleMandatory(['go-id', 'client-id', 'client-secret'], 'gopay');

		return new Client(
			new Config(
				(float) $config['go-id'],
				$config['client-id'],
				$config['client-secret'],
				Config::PROD,
			)
		);
	}
}
