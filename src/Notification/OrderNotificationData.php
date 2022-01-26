<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Notification;


use Baraja\DynamicConfiguration\Configuration;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\Shop\Price\Price;
use Baraja\Shop\ShopInfo;
use Baraja\SimpleTemplate\DTO\HTML;
use Baraja\SimpleTemplate\TemplateData;
use Baraja\Url\Url;

final class OrderNotificationData implements TemplateData
{
	public function __construct(
		private OrderInterface $order,
		private ShopInfo $shopInfo,
		private Configuration $configuration,
	) {
	}


	/**
	 * Returns a public order number for communication with the customer.
	 * The order number is unique within the order group.
	 */
	public function getNumber(): string
	{
		return $this->order->getNumber();
	}


	public function getShopName(): string
	{
		return $this->shopInfo->getShopName();
	}


	public function getSalutation(): string
	{
		return 'Dobrý den,';
	}


	public function getNotice(): string
	{
		return $this->order->getNotice() ?? '';
	}


	public function getItemList(): HTML
	{
		$lines = [];
		foreach ($this->order->getItems() as $item) {
			$ean = $item->getEan();
			$lines[] = sprintf(
				'<tr><td>%s%s</td><td style="text-align:right">%d</td><td style="text-align:right">%s</td></tr>',
				$item->getLabel(),
				$ean !== null ? '<br>EAN:&nbsp;' . $ean : '',
				$item->getAmount(),
				$item->getFinalPrice()->render(true),
			);
		}

		$delivery = $this->order->getDelivery();
		if ($delivery !== null) {
			$lines[] = sprintf(
				'<tr><td>Doprava %s</td><td style="text-align:right">1</td><td style="text-align:right">%s</td></tr>',
				$delivery->getLabel(),
				(new Price($delivery->getPrice(), $this->order->getCurrency()))->render(true),
			);
		}

		$payment = $this->order->getPayment();
		if ($payment !== null) {
			$lines[] = sprintf(
				'<tr><td>Platba %s</td><td style="text-align:right">1</td><td style="text-align:right">%s</td></tr>',
				$payment->getName(),
				(new Price($payment->getPrice(), $this->order->getCurrency()))->render(true),
			);
		}

		return new HTML(
			'<table style="width:100%">'
			. '<tr><th>Položka</th><th style="text-align:right">Množství</th><th style="text-align:right">Cena</th></tr>'
			. implode("\n", $lines)
			. '</table>'
		);
	}


	public function getBankAccount(): string
	{
		return $this->shopInfo->getBankAccount() ?? '';
	}


	public function getFinalPrice(): string
	{
		return $this->order->getPrice()->render();
	}


	public function getCustomFooter(): string
	{
		return $this->shopInfo->getCustomEmailFooter() ?? '';
	}


	public function getDetailLink(): string
	{
		return sprintf('%s/order/%s', Url::get()->getBaseUrl(), $this->order->getHash());
	}
}
