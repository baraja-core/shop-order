<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Newsletter\NewsletterManager;
use Baraja\Shop\Order\Bridge\HeurekaOverenoCreatedOrderEvent;
use Baraja\Shop\Order\Bridge\RegisterNewsletterCreatedOrderEvent;
use Baraja\Shop\Order\Command\CheckOrderCommand;
use Baraja\Shop\Order\Document\OrderDocumentManager;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Shop\Order\Status\OrderWorkflow;
use Heureka\ShopCertification;
use Nette\DI\CompilerExtension;

final class ShopOrderExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		OrmAnnotationsExtension::addAnnotationPathToManager($builder, 'Baraja\Shop\Order\Entity', __DIR__ . '/Entity');

		$builder->addDefinition($this->prefix('orderManager'))
			->setFactory(OrderManager::class);

		$builder->addDefinition($this->prefix('orderGenerator'))
			->setFactory(OrderGenerator::class);

		$builder->addDefinition($this->prefix('orderPaymentClient'))
			->setFactory(OrderPaymentClient::class);

		$builder->addDefinition($this->prefix('orderStatusManager'))
			->setFactory(OrderStatusManager::class);

		$builder->addDefinition($this->prefix('workflow'))
			->setFactory(OrderWorkflow::class);

		$builder->addDefinition($this->prefix('checkOrderCommand'))
			->setFactory(CheckOrderCommand::class);

		$builder->addDefinition($this->prefix('emailer'))
			->setFactory(Emailer::class);

		$builder->addDefinition($this->prefix('transactionManager'))
			->setFactory(TransactionManager::class);

		$builder->addDefinition($this->prefix('customerOrderBridge'))
			->setFactory(CustomerOrderBridge::class);

		$builder->addDefinition($this->prefix('orderDocumentManager'))
			->setFactory(OrderDocumentManager::class);

		// bridges
		if (class_exists(ShopCertification::class)) {
			$builder->addDefinition($this->prefix('heurekaOverenoBridgeEvent'))
				->setFactory(HeurekaOverenoCreatedOrderEvent::class)
				->setAutowired(HeurekaOverenoCreatedOrderEvent::class);
		}
		if (class_exists(NewsletterManager::class)) {
			$builder->addDefinition($this->prefix('registerNewsletterCreatedOrderEvent'))
				->setFactory(RegisterNewsletterCreatedOrderEvent::class)
				->setAutowired(RegisterNewsletterCreatedOrderEvent::class);
		}
	}
}
