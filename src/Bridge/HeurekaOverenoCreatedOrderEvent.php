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

		try {
			$shopCertification = new ShopCertification($apiKey);
			$shopCertification->setEmail($order->getCustomer()->getEmail());
			$shopCertification->setOrderId((int) preg_replace('/\D+/', '', $order->getNumber()));
			$usedProducts = [];
			foreach ($order->getItems() as $item) {
				$productId = (string) $item->getProduct()->getId();
				if (isset($usedProducts[$productId]) === false) {
					$shopCertification->addProductItemId((string) $item->getProduct()->getId());
					$usedProducts[$productId] = true;
				}
			}
			$shopCertification->logOrder();
		} catch (\Throwable $e) {
			$order->addNotice('Heureka Ověřeno zákazníky: ' . $e->getMessage());
			Debugger::log($e, ILogger::CRITICAL);
		}

		$this->entityManager->persist(new OrderMeta($order, self::META_KEY, 'true'));
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
