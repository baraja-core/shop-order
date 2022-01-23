<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Notification;


use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\DTO\OrderStatusInterface;
use Baraja\EcommerceStandard\Service\OrderNotificationEmailProviderInterface;
use Baraja\EcommerceStandard\Service\OrderNotificationSmsProviderInterface;
use Baraja\Shop\Order\Entity\OrderNotification as NotificationEntity;
use Baraja\Shop\Order\Entity\OrderStatus;
use Baraja\Shop\Order\Repository\OrderNotificationRepository;
use Baraja\SimpleTemplate\Engine;
use Baraja\SimpleTemplate\InvalidTemplateIntegrityException;
use Doctrine\ORM\EntityManagerInterface;

final class OrderNotification
{
	private OrderNotificationRepository $notificationRepository;

	private ?Engine $engine = null;


	public function __construct(
		private EntityManagerInterface $entityManager,
		private ?OrderNotificationEmailProviderInterface $emailProvider = null,
		private ?OrderNotificationSmsProviderInterface $smsProvider = null,
	) {
		$notificationRepository = $entityManager->getRepository(NotificationEntity::class);
		assert($notificationRepository instanceof OrderNotificationRepository);
		$this->notificationRepository = $notificationRepository;
	}


	/**
	 * @return array<int, string>
	 */
	public function getAvailableTypes(): array
	{
		$return = [];
		if ($this->emailProvider !== null) {
			$return[] = NotificationEntity::TYPE_EMAIL;
		}
		if ($this->smsProvider !== null) {
			$return[] = NotificationEntity::TYPE_SMS;
		}

		return $return;
	}


	public function sendEmail(OrderInterface $order, ?OrderStatusInterface $status = null): void
	{
		$status ??= $order->getStatus();
		$template = $this->findTemplateByStatus($status, $order->getLocale(), NotificationEntity::TYPE_EMAIL, true);
		if ($template === null) {
			return;
		}
		if ($this->emailProvider === null) {
			throw new \LogicException('Email provider is not available now.');
		}
		$content = $this->renderTemplate((string) $template->getContent(), $order);
		if ($content === '') {
			return;
		}
		$subject = $this->renderTemplate((string) $template->getSubject(), $order);
		if ($subject === '') {
			$subject = sprintf('Order %s', $order->getNumber());
		}
		$this->emailProvider->send($order, $subject, $content);
	}


	public function sendSms(OrderInterface $order, ?OrderStatusInterface $status = null): void
	{
		$status ??= $order->getStatus();
		$template = $this->findTemplateByStatus($status, $order->getLocale(), NotificationEntity::TYPE_SMS, true);
		if ($template === null) {
			return;
		}
		if ($this->emailProvider === null) {
			throw new \LogicException('SMS provider is not available now.');
		}
		$content = $this->renderTemplate((string) $template->getContent(), $order);
		if ($content === '') {
			return;
		}
		$this->smsProvider->send($order, $content);
	}


	public function findTemplateByStatus(
		OrderStatusInterface $status,
		string $locale,
		string $type,
		bool $activeOnly = false,
	): ?NotificationEntity {
		return $this->notificationRepository->findByStatusAndType($status, $locale, $type, $activeOnly);
	}


	public function setNotification(
		OrderStatusInterface $status,
		string $locale,
		string $type,
		string $subject,
		string $content,
		bool $active = false,
	): void {
		try {
			$this->validateTemplate($subject);
			$this->validateTemplate($content);
		} catch (InvalidTemplateIntegrityException $e) {
			$errorMessage = $e->getMessage();
			$message = 'Invalid template' . ($errorMessage !== '' ? ': ' . $errorMessage : '');
			if ($e->getErrors() !== []) {
				$message .= "\n\n" . 'Errors: ' . implode('; ', $e->getErrors());
			}
			throw new \InvalidArgumentException($message, 500, $e);
		}

		$notification = $this->findTemplateByStatus($status, $locale, $type);
		if ($notification === null) {
			assert($status instanceof OrderStatus);
			$notification = new NotificationEntity($status, $locale, $type);
			$this->entityManager->persist($notification);
		}
		$notification->setSubject($subject);
		$notification->setContent($content);
		$notification->setActive($active);
		$this->entityManager->flush();
	}


	/**
	 * @return array{
	 *     statusId: int,
	 *     type: string,
	 *     subject: string,
	 *     content: string,
	 *     active: bool,
	 *     insertedDate: \DateTimeInterface,
	 *     documentation: array<int, array{name: string, documentation: string|null}>
	 * }
	 */
	public function getNotificationData(OrderStatusInterface $status, string $locale, string $type): ?array
	{
		$template = $this->findTemplateByStatus($status, $locale, $type);
		if ($template === null) {
			return [
				'exist' => false,
				'statusId' => $status->getId(),
				'type' => $type,
				'subject' => '',
				'content' => '',
				'active' => false,
				'insertedDate' => null,
				'documentation' => $this->getDocumentation(),
			];
		}

		return [
			'exist' => true,
			'statusId' => $status->getId(),
			'type' => $type,
			'subject' => $template->getSubject() ?? '',
			'content' => $template->getContent() ?? '',
			'active' => $template->isActive(),
			'insertedDate' => $template->getInsertedDate(),
			'documentation' => $this->getDocumentation(),
		];
	}


	public function getTemplateEngine(): Engine
	{
		if ($this->engine === null) {
			$this->engine = new Engine;
		}

		return $this->engine;
	}


	/**
	 * @throws InvalidTemplateIntegrityException
	 */
	public function validateTemplate(string $template): void
	{
		$order = new SampleOrderEntity;
		$data = new OrderNotificationData($order);
		$this->getTemplateEngine()->validateTemplate($template, $data);
	}


	/**
	 * @param array<int, string> $types
	 * @return array<string, NotificationEntity>
	 */
	public function getAvailableNotificationReadyToSend(string $locale, array $types = []): array
	{
		return $this->notificationRepository->getAllReadyToSend($locale, $types);
	}


	private function renderTemplate(string $content, OrderInterface $order): string
	{
		if ($content === '') {
			return '';
		}

		return $this->getTemplateEngine()->renderTemplate(
			template: $content,
			data: new OrderNotificationData($order),
		);
	}


	/**
	 * @return array<int, array{name: string, documentation: string|null}>
	 */
	private function getDocumentation(): array
	{
		$order = new SampleOrderEntity;
		$data = new OrderNotificationData($order);

		$return = [];
		foreach ($this->getTemplateEngine()->parseAvailableVariables($data) as $variable) {
			$return[] = [
				'name' => $variable->getName(),
				'documentation' => $variable->getDocumentation(),
			];
		}

		return $return;
	}
}
