<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Notification;


use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\DTO\OrderStatusInterface;
use Baraja\Shop\Order\Entity\OrderNotification as NotificationEntity;
use Baraja\Shop\Order\Repository\OrderNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class OrderNotification
{
	private OrderNotificationRepository $notificationRepository;


	public function __construct(
		private EntityManagerInterface $entityManager,
	) {
		$notificationRepository = $entityManager->getRepository(NotificationEntity::class);
		assert($notificationRepository instanceof OrderNotificationRepository);
		$this->notificationRepository = $notificationRepository;
	}


	public function sendEmail(OrderInterface $order, ?OrderStatusInterface $status = null): void
	{
		$status ??= $order->getStatus();
		$template = $this->findTemplateByStatus($status, NotificationEntity::TYPE_EMAIL);
	}


	public function sendSms(OrderInterface $order, ?OrderStatusInterface $status = null): void
	{
		$status ??= $order->getStatus();
		$template = $this->findTemplateByStatus($status, NotificationEntity::TYPE_SMS);
	}


	public function findTemplateByStatus(OrderStatusInterface $status, string $type): NotificationEntity
	{
		return $this->notificationRepository->findByStatusAndType($status, $type);
	}
}
