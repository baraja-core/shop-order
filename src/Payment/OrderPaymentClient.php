<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Payment;


use Baraja\BankTransferAuthorizator\MultiAuthorizator;
use Baraja\Doctrine\EntityManager;
use Baraja\FioPaymentAuthorizator\FioPaymentAuthorizator;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Order\Entity\Order;
use Contributte\GopayInline\Client;
use Contributte\GopayInline\Config;
use Nette\Caching\Storage;

final class OrderPaymentClient
{
	private int $goId;

	private string $clientId;

	private string $clientSecret;

	/** @var OrderPaymentProvider[] */
	private array $providers = [];

	private ?OrderPaymentProvider $fallbackProvider = null;


	public function __construct(
		private EntityManager $entityManager,
		private Storage $storage,
	) {
	}


	public function pay(Order $order): OrderPaymentResponse
	{
		return new OrderPaymentResponse('');
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


	public function getGoPayClient(): Client
	{
		return new Client(new Config($this->goId, $this->clientId, $this->clientSecret, Config::PROD));
	}


	public function getAuthorizator(): MultiAuthorizator
	{
		/** @var Delivery[] $deliveries */
		$deliveries = $this->entityManager->getRepository(Delivery::class)->findAll();

		$services = [];
		foreach ($deliveries as $delivery) {
			if ($delivery->getCode() === 'fio') {
				$services[] = new FioPaymentAuthorizator($delivery->getAuthorizatorKey(), $this->storage);
			}
		}

		return new MultiAuthorizator($services);
	}
}
