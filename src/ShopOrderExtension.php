<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Newsletter\NewsletterManager;
use Baraja\Shop\Order\Application\WebController;
use Baraja\Shop\Order\Bridge\HeurekaOverenoCreatedOrderEvent;
use Baraja\Shop\Order\Bridge\RegisterNewsletterCreatedOrderEvent;
use Baraja\Shop\Order\Command\CheckOrderCommand;
use Baraja\Shop\Order\Document\OrderDocumentManager;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Shop\Order\Status\OrderWorkflow;
use Baraja\Url\Url;
use Contributte\GopayInline\Client;
use Heureka\ShopCertification;
use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;
use Tracy\Debugger;
use Tracy\ILogger;

final class ShopOrderExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		OrmAnnotationsExtension::addAnnotationPathToManager($builder, 'Baraja\Shop\Order\Entity', __DIR__ . '/Entity');

		$builder->addDefinition($this->prefix('orderManager'))
			->setFactory(OrderManager::class);

		$builder->addAccessorDefinition($this->prefix('orderManagerAccessor'))
			->setImplement(OrderManagerAccessor::class);

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

		$builder->addDefinition($this->prefix('webController'))
			->setFactory(WebController::class);

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
		if (class_exists(Client::class)) {
			$builder->addDefinition($this->prefix('gopay'))
				->setFactory(GoPayGatewayBridge::class)
				->setAutowired(GoPayGatewayBridge::class);
		}
	}


	public function afterCompile(ClassType $class): void
	{
		$builder = $this->getContainerBuilder();

		/** @var ServiceDefinition $application */
		$application = $builder->getDefinitionByType(Application::class);

		/** @var ServiceDefinition $controller */
		$controller = $builder->getDefinitionByType(WebController::class);

		if (PHP_SAPI === 'cli') {
			return;
		}
		$class->getMethod('initialize')->addBody(
			'// shop order.' . "\n"
			. '(function (): void {' . "\n"
			. "\t" . 'if (str_starts_with(' . Url::class . '::get()->getRelativeUrl(), \'order\')) {' . "\n"
			. "\t\t" . '$this->getService(?)->onStartup[] = function(' . Application::class . ' $a): void {' . "\n"
			. "\t\t\t" . 'try {' . "\n"
			. "\t\t\t\t" . '$this->getService(?)->run();' . "\n"
			. "\t\t\t" . '} catch (\Throwable $e) {' . "\n"
			. "\t\t\t\t" . Debugger::class . '::log($e, \'' . ILogger::CRITICAL . '\'); die;' . "\n"
			. "\t\t\t" . '}' . "\n"
			. "\t\t" . '};' . "\n"
			. "\t" . '}' . "\n"
			. '})();',
			[
				$application->getName(),
				$controller->getName(),
			],
		);
	}
}
