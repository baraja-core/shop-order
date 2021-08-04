<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\DynamicConfiguration\Configuration;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\ShopInfo;
use Baraja\Url\Url;
use Latte\Engine;
use Latte\Runtime\FilterInfo;
use Nette\Application\LinkGenerator;
use Nette\Localization\Translator;
use Nette\Mail\Mailer;
use Nette\Mail\Message;

final class Emailer
{
	public function __construct(
		private Mailer $mailer,
		private ShopInfo $shopInfo,
		private Configuration $configuration,
		private LinkGenerator $linkGenerator,
		private ?Translator $translator = null
	) {
	}


	public function sendNewOrder(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->shopInfo->getOrderEmail())
				->setSubject('Vaše objednávka ' . $order->getNumber() . ' z ' . $this->shopInfo->getShopName())
				->addTo($order->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/order.latte',
							array_merge(
								$this->getDefaultParameters(),
								[
									'order' => $order,
									'items' => $order->getItems(),
									'delivery' => $order->getDelivery(),
									'payment' => $order->getPayment(),
									'orderLink' => $this->link(
										'Order:default', [
											'hash' => $order->getHash(),
										]
									),
									'linkGenerator' => $this->linkGenerator,
								],
							)
						)
				)
		);
	}


	public function sendOrderPaid(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->shopInfo->getOrderEmail())
				->setSubject('Objednávka ' . $order->getNumber() . ' byla zaplacena')
				->addTo($order->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/orderPaid.latte',
							array_merge(
								$this->getDefaultParameters(),
								[
									'order' => $order,
								],
							)
						)
				)
		);
	}


	public function sendOrderPreparing(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->shopInfo->getOrderEmail())
				->setSubject('Vaši objednávku ' . $order->getNumber() . ' právě připravujeme')
				->addTo($order->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/orderPreparing.latte',
							array_merge(
								$this->getDefaultParameters(),
								[
									'order' => $order,
								],
							)
						)
				)
		);
	}


	public function sendOrderPrepared(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->shopInfo->getOrderEmail())
				->setSubject('Vaše objednávka ' . $order->getNumber() . ' je připravena k vyzvednutí')
				->addTo($order->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/orderPrepared.latte',
							array_merge(
								$this->getDefaultParameters(),
								[
									'order' => $order,
								],
							)
						)
				)
		);
	}


	public function sendOrderSent(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->shopInfo->getOrderEmail())
				->setSubject('Vaši objednávku ' . $order->getNumber() . ' jsme předali dopravci')
				->addTo($order->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/orderSent.latte',
							array_merge(
								$this->getDefaultParameters(),
								[
									'order' => $order,
								],
							)
						)
				)
		);
	}


	public function sendOrderDone(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->shopInfo->getOrderEmail())
				->setSubject('Vaši objednávku ' . $order->getNumber() . ' jsme úspěšně dodali')
				->addTo($order->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/orderDone.latte',
							array_merge(
								$this->getDefaultParameters(),
								[
									'order' => $order,
								],
							)
						)
				)
		);
	}


	public function sendOrderStorno(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->shopInfo->getOrderEmail())
				->setSubject(
					'Storno objednávky ' . $order->getNumber() . ' z obchodu ' . $this->shopInfo->getShopName()
				)
				->addTo($order->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/orderStorno.latte',
							array_merge(
								$this->getDefaultParameters(),
								[
									'order' => $order,
								],
							)
						)
				)
		);
	}


	public function sendOrderMissingItem(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->shopInfo->getOrderEmail())
				->setSubject('Část objednávky ' . $order->getNumber() . ' není skladem')
				->addTo($order->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/orderMissingItem.latte',
							array_merge(
								$this->getDefaultParameters(),
								[
									'order' => $order,
								],
							)
						)
				)
		);
	}


	public function sendOrderPingMail(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->shopInfo->getOrderEmail())
				->setSubject(
					'Upomínka nezaplacené objednávky ' . $order->getNumber()
					. ' z obchodu ' . $this->shopInfo->getShopName()
				)
				->addTo($order->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/orderPing.latte',
							array_merge(
								$this->getDefaultParameters(),
								[
									'order' => $order,
									'orderDetailLink' => $this->internalLink('/objednavka/' . $order->getHash()),
								],
							)
						)
				)
		);
	}


	private function internalLink(string $path): string
	{
		return Url::get()->getBaseUrl() . '/' . $path;
	}


	/**
	 * @param array<string, mixed> $params
	 */
	private function link(string $dest, array $params = []): string
	{
		return $this->linkGenerator->link((($dest[0] ?? '') === ':' ? $dest : 'Front:' . $dest), $params);
	}


	/**
	 * @return array<string, mixed>
	 */
	private function getDefaultParameters(): array
	{
		return [
			'customFooter' => $this->configuration->get('email-footer', 'clever'),
		];
	}


	private function getEngine(): Engine
	{
		$engine = new Engine;
		if ($this->translator !== null) {
			$engine->addFilter(
				'translate',
				fn(FilterInfo $fi, ...$args): string => $this->translator->translate(...$args),
			);
		}

		return $engine;
	}
}
