<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Payment;


use Baraja\BankTransferAuthorizator\MultiAuthorizator;
use Baraja\Doctrine\EntityManager;
use Baraja\EcommerceStandard\DTO\OrderGatewayResponseInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\Service\OrderPaymentGatewayInterface;
use Baraja\FioPaymentAuthorizator\FioPaymentAuthorizator;
use Baraja\Shop\Order\Application\WebController;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\OrderManager;
use Baraja\Shop\Payment\Entity\Payment;
use Nette\Caching\Storage;

final class OrderPaymentClient
{
	private const FallbackProviders = ['gopay'];


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
	public function processPayment(OrderInterface $order): void
	{
		if ($this->orderManager->isPaid($order)) {
			echo 'Order has been paid.';
			die;
		}

		$provider = $this->getBestCompatibleProvider($order);
		$response = $provider->pay($order);
		$this->processResponse($response);
	}


	/**
	 * @return never-return
	 */
	public function checkPaymentStatus(OrderInterface $order, ?string $id = null): void
	{
		$provider = $this->getBestCompatibleProvider($order);
		$response = $provider->checkPaymentStatus($order, $id);
		$this->processResponse($response);
	}


	/**
	 * Check an internal payment status and starts workflow.
	 */
	public function checkPaymentStatusOnly(OrderInterface $order, ?string $id = null): void
	{
		$this->getBestCompatibleProvider($order)->checkPaymentStatus($order, $id);
	}


	public function getBestCompatibleProvider(OrderInterface $order): OrderPaymentGatewayInterface
	{
		$payment = $order->getPayment();
		if ($payment === null) {
			throw new \InvalidArgumentException(sprintf('Payment for order "%s" has not set.', $order->getNumber()));
		}
		$fallback = null;
		$orderPaymentCode = $payment->getCode();
		foreach ($this->providers as $provider) {
			$providerCode = $provider->getPaymentMethodCode();
			if ($providerCode === $orderPaymentCode) {
				return $provider;
			}
			if ($fallback === null && in_array($providerCode, self::FallbackProviders, true)) {
				$fallback = $provider;
			}
		}
		if ($fallback !== null) {
			return $fallback;
		}

		throw new \InvalidArgumentException('Order can not be paid, because no compatible provider exist.');
	}


	public function getAuthorizator(): MultiAuthorizator
	{
		/** @var Payment[] $payments */
		$payments = $this->entityManager->getRepository(Payment::class)->findAll();

		$services = [];
		foreach ($payments as $payment) {
			$authorizatorKey = $payment->getAuthorizatorKey();
			if ($authorizatorKey !== null && $payment->getCode() === 'fio') {
				$services[] = new FioPaymentAuthorizator($authorizatorKey, $this->storage);
			}
		}

		return new MultiAuthorizator($services);
	}


	public function addPaymentProvider(OrderPaymentGatewayInterface $provider): void
	{
		$this->providers[] = $provider;
	}


	/**
	 * @return never-return
	 */
	private function processResponse(OrderGatewayResponseInterface $response): void
	{
		$redirect = $response->getRedirect();
		$errorMessage = $response->getErrorMessage();
		if ($redirect !== null) {
			if ($errorMessage !== null) {
				WebController::setFlashMessage($errorMessage);
			}
			WebController::redirect($redirect);
		}
		if ($errorMessage !== null) {
			echo htmlspecialchars($errorMessage);
		}
		die;
	}
}
