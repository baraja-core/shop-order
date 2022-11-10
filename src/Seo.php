<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\EcommerceStandard\DTO\OrderInterface;

final class Seo
{
	public function getDataLayer(OrderInterface $order): string
	{
		return json_encode(
			$this->buildDataLayerStructure($order),
			JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
		);
	}


	/**
	 * @return array<string, mixed>
	 */
	private function buildDataLayerStructure(OrderInterface $order): array
	{
		$products = [];
		foreach ($order->getItems() as $item) {
			$product = $item->getProduct();
			$variant = $item->getVariant();
			$products[] = [
				'name' => $product->getLabel(),
				'id' => (string) $product->getId(),
				'price' => $item->getFinalPrice()->getValue(),
				'brand' => $product->getBrand()?->getName() ?? 'Other',
				'category' => $product->getMainCategory()?->getLabel(),
				'variant' => $variant?->getName(),
				'quantity' => $item->getCount(),
			];
		}

		return [
			'ecommerce' => [
				'purchase' => [
					'actionField' => [
						'id' => $order->getNumber(), // Transaction ID. Required for purchases and refunds.
						'affiliation' => 'Online Store',
						'revenue' => $order->getPrice()->getValue(), // Total transaction value (incl. tax and shipping)
						'tax' => $order->getPriceWithoutVat()->getValue(),
						'shipping' => $order->getDeliveryPrice()->getValue(),
						// TODO: 'coupon' => 'SUMMER_SALE',
					],
					'products' => $products,
				],
			],
		];
	}
}
