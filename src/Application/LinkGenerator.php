<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Application;


use Baraja\Shop\Order\Entity\Order;
use Baraja\Url\Url;

final class LinkGenerator
{
	public function default(Order $order): string
	{
		return Url::get()->getBaseUrl() . '/order?hash=' . urlencode($order->getHash());
	}


	public function confirmOrder(Order $order): string
	{
		return Url::get()->getBaseUrl() . '/order/confirm?hash=' . urlencode($order->getHash());
	}


	public function paymentGateway(Order $order): string
	{
		return Url::get()->getBaseUrl() . '/order/gateway?hash=' . urlencode($order->getHash());
	}


	/**
	 * @param array<string, string|int|bool|null> $params
	 */
	public function paymentHandle(Order $order, string $handler, array $params = []): string
	{
		$url = new \Nette\Http\Url(Url::get()->getBaseUrl() . '/order/payment-handle');
		$url->setQueryParameter('hash', $order->getHash());
		$url->setQueryParameter('handler', $handler);
		foreach ($params as $paramKey => $paramValue) {
			if ($paramKey === 'hash' || $paramKey === 'handler') {
				throw new \InvalidArgumentException('Param key "' . $paramKey . '" is reserved for internal use.');
			}
			$url->setQueryParameter($paramKey, $paramValue);
		}

		return $url->getAbsoluteUrl();
	}
}
