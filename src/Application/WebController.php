<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Application;


use Baraja\Doctrine\EntityManager;
use Baraja\ServiceMethodInvoker;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\OrderManagerAccessor;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Url\Url;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Latte\Engine;

final class WebController
{
	public function __construct(
		private EntityManager $entityManager,
		private OrderManagerAccessor $orderManager,
		private OrderPaymentClient $orderPaymentClient,
	) {
	}


	public static function getLinkGenerator(): LinkGenerator
	{
		static $cache;
		if ($cache === null) {
			$cache = new LinkGenerator;
		}

		return $cache;
	}


	/**
	 * @return never-return
	 */
	public static function redirect(string $url): void
	{
		header('Location: ' . $url);
		die;
	}


	/**
	 * @return never-return
	 */
	public function run(): void
	{
		$action = (string) preg_replace('~^order/?~', '', Url::get()->getRelativeUrl(false));
		$method = 'action' . ($action === '' ? 'default' : $action);
		if (method_exists($this, $method)) {
			(new ServiceMethodInvoker)->invoke(
				service: $this,
				methodName: $method,
				params: [],
			);
		}
		die;
	}


	public function actionDefault(): void
	{
		$order = $this->getOrder();
		assert($order !== null);

		(new Engine)->render(
			__DIR__ . '/template/default.latte',
			[
				'order' => $order,
				'isPaid' => $this->orderManager->get()->isPaid($order),
				'gatewayLink' => self::getLinkGenerator()->paymentGateway($order),
			]
		);
	}


	public function actionGateway(): void
	{
		$order = $this->getOrder();
		if ($order === null) {
			throw new \LogicException('Order does not exist.');
		}
		$this->orderPaymentClient->processPayment($order);
	}


	private function getOrder(): ?Order
	{
		$hash = trim($_GET['hash'] ?? '');
		if ($hash === '') { // Hash URL query parameter does not exist.
			return null;
		}

		try {
			return $this->entityManager->getRepository(Order::class)
				->createQueryBuilder('o')
				->where('o.hash = :hash')
				->setParameter('hash', $hash)
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			return null;
		}
	}
}
