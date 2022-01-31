<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Payment;


use Baraja\BankTransferAuthorizator\MultiAuthorizator;
use Baraja\Doctrine\EntityManager;
use Baraja\EcommerceStandard\Service\OrderPaymentGatewayInterface;
use Baraja\FioPaymentAuthorizator\FioPaymentAuthorizator;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Order\Application\WebController;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\OrderManager;
use Nette\Caching\Storage;

final class OrderPaymentClient
{
	private OrderManager $orderManager;


	/**
	 * @param OrderPaymentGatewayInterface[] $providers
	 */
	public function __construct(
		private EntityManager $entityManager,
		private Storage $storage,
		private array $providers = [],
	) {
	}


	/** @internal */
	public function injectOrderManager(OrderManager $orderManager): void
	{
		$this->orderManager = $orderManager;
	}


	public function pay(Order $order): OrderPaymentResponse
	{
		$url = $this->orderManager->isPaid($order)
			? WebController::getLinkGenerator()->confirmOrder($order)
			: WebController::getLinkGenerator()->paymentGateway($order);

		return new OrderPaymentResponse($url);
	}


	/**
	 * @return never-return
	 */
	public function processPayment(Order $order): void
	{
		if ($this->orderManager->isPaid($order)) {
			echo 'Order has been paid.';
			return;
		}

		$provider = $this->getBestCompatibleProvider($order);
		$response = $provider->pay($order);
		$redirect = $response->getRedirect();
		$errorMessage = $response->getErrorMessage();
		if ($redirect !== null) {
			WebController::redirect($redirect);
		}
		if ($errorMessage !== null) {
			echo htmlspecialchars($errorMessage);
			die;
		}
	}


	public function getBestCompatibleProvider(Order $order): OrderPaymentGatewayInterface
	{
		$payment = $order->getPayment();
		if ($payment === null) {
			throw new \InvalidArgumentException(sprintf('Payment for order "%s" has not set.', $order->getNumber()));
		}
		$orderPaymentCode = $payment->getCode();
		foreach ($this->providers as $provider) {
			if ($provider->getPaymentMethodCode() === $orderPaymentCode) {
				return $provider;
			}
		}

		throw new \InvalidArgumentException('Order can not be paid, because no compatible provider exist.');
	}


	public function getAuthorizator(): MultiAuthorizator
	{
		/** @var Delivery[] $deliveries */
		$deliveries = $this->entityManager->getRepository(Delivery::class)->findAll();

		$services = [];
		foreach ($deliveries as $delivery) {
			$authorizatorKey = $delivery->getAuthorizatorKey();
			if ($authorizatorKey !== null && $delivery->getCode() === 'fio') {
				$services[] = new FioPaymentAuthorizator($authorizatorKey, $this->storage);
			}
		}

		return new MultiAuthorizator($services);
	}


	public function addPaymentProvider(OrderPaymentGatewayInterface $provider): void
	{
		$this->providers[] = $provider;
	}
}
