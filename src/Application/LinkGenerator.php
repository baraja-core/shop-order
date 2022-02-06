<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Application;


use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\Url\Url;

final class LinkGenerator
{
	public function default(OrderInterface $order): string
	{
		return sprintf('%s/order/%s', $this->resolveBaseUrl(), urlencode($order->getHash()));
	}


	public function confirmOrder(OrderInterface $order): string
	{
		return sprintf('%s/order/confirm?hash=%s', $this->resolveBaseUrl(), urlencode($order->getHash()));
	}


	public function paymentGateway(OrderInterface $order): string
	{
		return sprintf('%s/order/gateway?hash=%s', $this->resolveBaseUrl(), urlencode($order->getHash()));
	}


	/**
	 * @param array<string, string|int|bool|null> $params
	 */
	public function paymentHandle(OrderInterface $order, string $handler, array $params = []): string
	{
		$url = new \Nette\Http\Url(sprintf('%s/order/payment-handle', $this->resolveBaseUrl()));
		$url->setQueryParameter('hash', $order->getHash());
		$url->setQueryParameter('handler', $handler);
		foreach ($params as $paramKey => $paramValue) {
			if ($paramKey === 'hash' || $paramKey === 'handler') {
				throw new \InvalidArgumentException(sprintf('Param key "%s" is reserved for internal use.', $paramKey));
			}
			$url->setQueryParameter($paramKey, $paramValue);
		}

		return $url->getAbsoluteUrl();
	}


	private function resolveBaseUrl(): string
	{
		try {
			return Url::get()->getBaseUrl();
		} catch (\Throwable) {
			return '/';
		}
	}
}
