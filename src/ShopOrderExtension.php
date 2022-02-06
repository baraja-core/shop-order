<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Newsletter\NewsletterManager;
use Baraja\Plugin\Component\VueComponent;
use Baraja\Plugin\PluginManager;
use Baraja\Shop\Cart\ShopCartExtension;
use Baraja\Shop\Order\Application\WebController;
use Baraja\Shop\Order\Bridge\BalikoBotAdapterBridge;
use Baraja\Shop\Order\Bridge\HeurekaOverenoCreatedOrderEvent;
use Baraja\Shop\Order\Bridge\RegisterNewsletterCreatedOrderEvent;
use Baraja\Shop\Order\Command\CheckGatewayPaymentsCommand;
use Baraja\Shop\Order\Command\CheckOrderCommand;
use Baraja\Shop\Order\Delivery\OrderCarrierManager;
use Baraja\Shop\Order\Delivery\OrderDeliveryManager;
use Baraja\Shop\Order\Document\OrderDocumentManager;
use Baraja\Shop\Order\Notification\OrderNotification;
use Baraja\Shop\Order\Notification\OrderNotificationDataFactory;
use Baraja\Shop\Order\Notification\OrderNotificationDataFactoryAccessor;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Shop\Order\Repository\OrderFeedRepository;
use Baraja\Shop\Order\Status\OrderWorkflow;
use Baraja\Url\Url;
use Contributte\GopayInline\Client;
use Heureka\ShopCertification;
use Inspirum\Balikobot\Services\Balikobot;
use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;
use Tracy\Debugger;
use Tracy\ILogger;

final class ShopOrderExtension extends CompilerExtension
{
	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		return [ShopCartExtension::class];
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		OrmAnnotationsExtension::addAnnotationPathToManager($builder, 'Baraja\Shop\Order\Entity', __DIR__ . '/Entity');

		$builder->addDefinition($this->prefix('orderManager'))
			->setFactory(OrderManager::class)
			->setArgument('wwwDir', $builder->parameters['wwwDir'] ?? '');

		$builder->addAccessorDefinition($this->prefix('orderManagerAccessor'))
			->setImplement(OrderManagerAccessor::class);

		$builder->addDefinition($this->prefix('orderGroupManager'))
			->setFactory(OrderGroupManager::class);

		$builder->addDefinition($this->prefix('orderRepository'))
			->setFactory(OrderFeedRepository::class);

		$builder->addDefinition($this->prefix('orderGenerator'))
			->setFactory(OrderGenerator::class);

		$builder->addDefinition($this->prefix('orderDeliveryManager'))
			->setFactory(OrderDeliveryManager::class);

		$builder->addDefinition($this->prefix('orderCarrierManager'))
			->setFactory(OrderCarrierManager::class);

		$builder->addDefinition($this->prefix('orderPaymentClient'))
			->setFactory(OrderPaymentClient::class);

		$builder->addDefinition($this->prefix('orderStatusManager'))
			->setFactory(OrderStatusManager::class);

		$builder->addDefinition($this->prefix('workflow'))
			->setFactory(OrderWorkflow::class);

		$builder->addDefinition($this->prefix('notification'))
			->setFactory(OrderNotification::class);

		$builder->addDefinition($this->prefix('orderNotificationDataFactory'))
			->setFactory(OrderNotificationDataFactory::class);

		$builder->addAccessorDefinition($this->prefix('orderNotificationDataFactoryAccessor'))
			->setImplement(OrderNotificationDataFactoryAccessor::class);

		$builder->addDefinition($this->prefix('checkOrderCommand'))
			->setFactory(CheckOrderCommand::class);

		$builder->addDefinition($this->prefix('checkGatewayPaymentsCommand'))
			->setFactory(CheckGatewayPaymentsCommand::class);

		$builder->addDefinition($this->prefix('emailer'))
			->setFactory(Emailer::class);

		$builder->addDefinition($this->prefix('transactionManager'))
			->setFactory(OrderPaymentManager::class);

		$builder->addDefinition($this->prefix('customerOrderBridge'))
			->setFactory(CustomerOrderBridge::class);

		$builder->addDefinition($this->prefix('orderDocumentManager'))
			->setFactory(OrderDocumentManager::class);

		$builder->addDefinition($this->prefix('webController'))
			->setFactory(WebController::class);

		// bridges
		if (class_exists(Balikobot::class)) {
			$builder->addDefinition($this->prefix('balikoBotAdapterBridge'))
				->setFactory(BalikoBotAdapterBridge::class)
				->setAutowired(BalikoBotAdapterBridge::class);
		}
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

		/** @var ServiceDefinition $pluginManager */
		$pluginManager = $this->getContainerBuilder()->getDefinitionByType(PluginManager::class);
		$pluginManager->addSetup('?->addComponent(?)', ['@self', [
			'key' => 'orderDefault',
			'name' => 'cms-order-default',
			'implements' => CmsOrderPlugin::class,
			'componentClass' => VueComponent::class,
			'view' => 'default',
			'source' => __DIR__ . '/../templates/default.js',
			'position' => 100,
			'tab' => 'Order feed',
			'params' => [],
		]]);
		$pluginManager->addSetup('?->addComponent(?)', ['@self', [
			'key' => 'orderOverview',
			'name' => 'cms-order-overview',
			'implements' => CmsOrderPlugin::class,
			'componentClass' => VueComponent::class,
			'view' => 'detail',
			'source' => __DIR__ . '/../templates/overview.js',
			'position' => 100,
			'tab' => 'Overview',
			'params' => ['id'],
		]]);
		$pluginManager->addSetup('?->addComponent(?)', ['@self', [
			'key' => 'orderDocument',
			'name' => 'cms-order-document',
			'implements' => CmsOrderPlugin::class,
			'componentClass' => VueComponent::class,
			'view' => 'detail',
			'source' => __DIR__ . '/../templates/document.js',
			'position' => 70,
			'tab' => 'Documents',
			'params' => ['id'],
		]]);
		$pluginManager->addSetup('?->addComponent(?)', ['@self', [
			'key' => 'orderHistory',
			'name' => 'cms-order-history',
			'implements' => CmsOrderPlugin::class,
			'componentClass' => VueComponent::class,
			'view' => 'detail',
			'source' => __DIR__ . '/../templates/history.js',
			'position' => 40,
			'tab' => 'History',
			'params' => ['id'],
		]]);
		$pluginManager->addSetup('?->addComponent(?)', ['@self', [
			'key' => 'orderExport',
			'name' => 'cms-order-vat-export',
			'implements' => CmsOrderVatExportPlugin::class,
			'componentClass' => VueComponent::class,
			'view' => 'default',
			'source' => __DIR__ . '/../templates/export.js',
			'position' => 100,
			'tab' => 'vat export',
		]]);
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
			. "\t\t\t" . '$this->getService(?)->run();' . "\n"
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
