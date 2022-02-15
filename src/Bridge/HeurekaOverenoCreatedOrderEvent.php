<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Bridge;


use Baraja\Doctrine\EntityManager;
use Baraja\DynamicConfiguration\Configuration;
use Baraja\Shop\Order\CreatedOrderEvent;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderMeta;
use Heureka\ShopCertification;
use Tracy\Debugger;
use Tracy\ILogger;

final class HeurekaOverenoCreatedOrderEvent implements CreatedOrderEvent
{
	private const CONFIGURATION_NAMESPACE = 'heureka-overeno';

	private const META_KEY = 'send-to-heureka-overeno';


	public function __construct(
		private Configuration $configuration,
		private EntityManager $entityManager,
	) {
	}


	public function process(Order $order): void
	{
		$apiKey = $this->getApiKey();
		$metaKey = $order->getMetaKey(self::META_KEY);
		if ($apiKey === null || $metaKey === 'true') {
			return;
		}
		$email = $order->getCustomer()->getEmail();
		if ($email === null) {
			throw new \InvalidArgumentException(sprintf('Customer e-mail for order "%s" does not exist.', $order->getNumber()));
		}
		try {
			$shopCertification = new ShopCertification($apiKey);
			$shopCertification->setEmail($email);
			$shopCertification->setOrderId((int) preg_replace('/\D+/', '', $order->getNumber()));
			$usedProducts = [];
			foreach ($order->getItems() as $item) {
				try {
					$productId = (string) $item->getProduct()->getId();
				} catch (\Throwable) { // Product can be broken.
					$productId = null;
				}
				if ($productId !== null && isset($usedProducts[$productId]) === false) {
					$shopCertification->addProductItemId($productId);
					$usedProducts[$productId] = true;
				}
			}
			$shopCertification->logOrder();
			$this->entityManager->persist(new OrderMeta($order, self::META_KEY, 'true'));
		} catch (\Throwable $e) {
			$order->addNotice('Heureka Ověřeno zákazníky: ' . $e->getMessage());
			Debugger::log($e, ILogger::CRITICAL);
		}

		$this->entityManager->flush();
	}


	public function getApiKey(): ?string
	{
		return $this->configuration->get('api-key', self::CONFIGURATION_NAMESPACE);
	}


	public function setApiKey(?string $key): void
	{
		$this->configuration->save('api-key', $key, self::CONFIGURATION_NAMESPACE);
	}
}
