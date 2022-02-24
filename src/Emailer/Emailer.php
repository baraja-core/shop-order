<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\Service\OrderNotificationEmailProviderInterface;
use Baraja\Emailer\EmailerAccessor;
use Baraja\Shop\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Mail\Message;

final class Emailer implements OrderNotificationEmailProviderInterface
{
	public function __construct(
		private EmailerAccessor $emailer,
		private ShopInfo $shopInfo,
		private EntityManagerInterface $entityManager,
	) {
	}


	/**
	 * @param array<int, string> $attachments
	 */
	public function send(OrderInterface $order, string $subject, string $content, array $attachments = []): void
	{
		$customer = $order->getCustomer();
		$customerEmail = $customer?->getEmail();
		if ($customerEmail === null) {
			return;
		}

		$message = (new Message)
			->setFrom($this->getFrom())
			->setSubject($subject)
			->addTo($customerEmail)
			->setHtmlBody($this->renderTemplate($content));

		foreach ($attachments as $attachment) {
			$message->addAttachment($attachment);
		}

		$email = $this->emailer->get()->insertMessageToQueue($message);
		$email->setTag(sprintf('order-%s-%d', $order->getNumber(), $order->getId()));
		$this->entityManager->flush();
	}


	private function getFrom(): string
	{
		$from = $this->shopInfo->getOrderEmail();
		if ($from === null) {
			throw new \RuntimeException('Order from e-mail is not defined in your configuration.');
		}

		return $from;
	}


	/**
	 * Use default layout template and include content block.
	 */
	private function renderTemplate(string $content): string
	{
		ob_start(static function (){});

		$args = [
			'content' => $content,
			'logoUrl' => $this->shopInfo->getLogoUrl(),
			'customFooter' => $this->shopInfo->getCustomEmailFooter(),
		];

		/** @phpstan-ignore-next-line */
		extract($args, EXTR_OVERWRITE);

		try {
			require __DIR__ . '/layout.phtml';

			return (string) ob_get_clean();
		} catch (\Throwable $e) {
			ob_end_clean();
			throw new \RuntimeException($e->getMessage(), 500, $e);
		}
	}
}
