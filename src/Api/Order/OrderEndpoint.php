<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\Order;


use Baraja\Shop\Order\OrderManager;
use Baraja\StructuredApi\BaseEndpoint;

final class OrderEndpoint extends BaseEndpoint
{
	public function __construct(
		private OrderManager $orderManager,
	) {
	}


	public function actionDetail(string $hash): OrderResponse
	{
		$order = $this->orderManager->getOrderByHash($hash);

		return new OrderResponse(
			hash: $hash,
			isPaid: $this->orderManager->isPaid($order),
			dataLayerStructure: $this->orderManager->getSeo()->getDataLayerStructure($order),
		);
	}
}
