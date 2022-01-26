<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Notification;


use Baraja\DynamicConfiguration\Configuration;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\Shop\ShopInfo;

final class OrderNotificationDataFactory
{
	private ?SampleOrderEntity $sampleOrder = null;


	public function __construct(
		private ShopInfo $shopInfo,
		private Configuration $configuration,
	) {
	}


	public function create(?OrderInterface $order = null): OrderNotificationData
	{
		return new OrderNotificationData(
			order: $order ?? $this->getSampleOrder(),
			shopInfo: $this->shopInfo,
			configuration: $this->configuration,
		);
	}


	private function getSampleOrder(): SampleOrderEntity
	{
		if ($this->sampleOrder === null) {
			$this->sampleOrder = new SampleOrderEntity;
		}

		return $this->sampleOrder;
	}
}
