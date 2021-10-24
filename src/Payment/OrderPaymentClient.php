<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Payment;


use Baraja\BankTransferAuthorizator\MultiAuthorizator;
use Baraja\Doctrine\EntityManager;
use Baraja\FioPaymentAuthorizator\FioPaymentAuthorizator;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Order\Application\WebController;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\OrderManager;
use Baraja\Shop\Order\Payment\Gateway\Gateway;
use Nette\Caching\Storage;

final class OrderPaymentClient
{
	/** @var OrderPaymentProvider[] */
	private array $providers = [];

	private ?OrderPaymentProvider $fallbackProvider = null;

	private OrderManager $orderManager;


	public function __construct(
		private EntityManager $entityManager,
		private Storage $storage,
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
			return;
		}

		$provider = $this->getBestCompatibleProvider($order);
		if ($provider instanceof Gateway) {
			$response = $provider->pay($order);
			$redirect = $response->getRedirect();
			$errorMessage = $response->getErrorMessage();
			if ($redirect !== null) {
				WebController::redirect($redirect);
			}
			if ($errorMessage !== null) {
				echo $errorMessage;
				die;
			}
		} else {
			throw new \LogicException(
				'Order can not be paid, '
				. 'because provider "' . $provider::class . '" is not gateway.',
			);
		}
		throw new \LogicException('Order can not be paid, because no provider exist.');
	}


	public function getBestCompatibleProvider(Order $order): OrderPaymentProvider
	{
		$orderPaymentCode = $order->getPayment()->getCode();
		foreach ($this->providers as $provider) {
			if ($provider->getPaymentMethodCode() === $orderPaymentCode) {
				return $provider;
			}
		}
		if ($this->fallbackProvider === null) {
			throw new \InvalidArgumentException(
				'Order can not be paid, because no compatible provider exist. '
				. 'Did you set fallback provider?',
			);
		}

		return $this->fallbackProvider;
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


	public function addPaymentProvider(OrderPaymentProvider $provider): void
	{
		$this->providers[] = $provider;
	}
}
