<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\DynamicConfiguration\Configuration;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\Service\OrderNotificationEmailProviderInterface;
use Baraja\Emailer\EmailerAccessor;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderOnlinePayment;
use Baraja\Shop\ShopInfo;
use Baraja\Url\Url;
use Doctrine\ORM\EntityManagerInterface;
use Latte\Engine;
use Latte\Runtime\FilterInfo;
use Nette\Application\LinkGenerator;
use Nette\Localization\Translator;
use Nette\Mail\Mailer;
use Nette\Mail\Message;

final class Emailer implements OrderNotificationEmailProviderInterface
{
	public function __construct(
		private Mailer $mailer,
		private EmailerAccessor $emailer,
		private ShopInfo $shopInfo,
		private Configuration $configuration,
		private LinkGenerator $linkGenerator,
		private EntityManagerInterface $entityManager,
		private ?Translator $translator = null
	) {
	}


	public function send(OrderInterface $order, string $subject, string $content): void
	{
		$customer = $order->getCustomer();
		if ($customer === null) {
			return;
		}

		$message = (new Message)
			->setFrom($this->getFrom())
			->setSubject($subject)
			->addTo($customer->getEmail())
			->setHtmlBody($content);

		$email = $this->emailer->get()->send($message);
		$email->setTag(sprintf('order-%s', $order->getNumber()));
		$this->entityManager->flush();
	}


	public function sendOrderPingMail(Order $order): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->getFrom())
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


	public function sendOrderFailMail(OrderOnlinePayment $payment): void
	{
		$this->mailer->send(
			(new Message)
				->setFrom($this->getFrom())
				->setSubject('Platba objednávky ' . $payment->getOrder()->getNumber() . ' selhala')
				->addTo($payment->getOrder()->getCustomer()->getEmail())
				->setHtmlBody(
					$this->getEngine()
						->renderToString(
							__DIR__ . '/templates/orderFailGopay.latte', array_merge(
								$this->getDefaultParameters(), [
									'order' => $payment->getOrder(),
									'orderDetailLink' => $this->internalLink(
										'/objednavka/' . $payment->getOrder()->getHash()
									),
								]
							)
						)
				)
		);
	}


	private function getFrom(): string
	{
		$from = $this->shopInfo->getOrderEmail();
		if ($from === null) {
			throw new \RuntimeException('Order from e-mail is not defined in your configuration.');
		}

		return $from;
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
