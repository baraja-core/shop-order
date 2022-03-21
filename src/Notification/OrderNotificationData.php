<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Notification;


use Baraja\DynamicConfiguration\Configuration;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\Shop\Order\Application\WebController;
use Baraja\Shop\Price\Price;
use Baraja\Shop\ShopInfo;
use Baraja\SimpleTemplate\DTO\HTML;
use Baraja\SimpleTemplate\TemplateData;

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


	/**
	 * Renders a complete table with the order items.
	 * The table contains complete formatting and all data including shipping and payment.
	 */
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
			. '</table>',
		);
	}


	/**
	 * It contains the account number of this e-shop for payment of the order.
	 */
	public function getBankAccount(): string
	{
		return $this->shopInfo->getBankAccount() ?? '';
	}


	/**
	 * It will return the absolute price of the order, which must be paid by the customer.
	 */
	public function getFinalPrice(): string
	{
		return $this->order->getPrice()->render();
	}


	/**
	 * Returns the static text that has been set by the administrator as common to all emails.
	 * This variable is typically used for email footers.
	 */
	public function getCustomFooter(): string
	{
		return $this->shopInfo->getCustomEmailFooter() ?? '';
	}


	/**
	 * Creates a direct link to the order overview page.
	 * In some e-shops, it may redirect the customer to a payment gateway.
	 */
	public function getDetailLink(): string
	{
		return WebController::getLinkGenerator()->default($this->order);
	}


	/**
	 * It will generate a direct link to the order confirmation and thank you page.
	 */
	public function getConfirmLink(): string
	{
		return WebController::getLinkGenerator()->confirmOrder($this->order);
	}


	/**
	 * It generates a direct link to the payment gateway where the customer can pay for the order immediately.
	 */
	public function getGatewayLink(): string
	{
		return WebController::getLinkGenerator()->paymentGateway($this->order);
	}
}
