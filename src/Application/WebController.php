<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Application;


use Baraja\Doctrine\EntityManager;
use Baraja\ServiceMethodInvoker;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\OrderManagerAccessor;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Shop\Order\Repository\OrderRepository;
use Baraja\Url\Url;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Latte\Engine;
use Psr\Log\LoggerInterface;

final class WebController
{
	public const FLASH_MESSAGE_KEY = '_brj-order-flash-message';

	private OrderRepository $orderRepository;


	public function __construct(
		EntityManager $entityManager,
		private OrderManagerAccessor $orderManager,
		private OrderPaymentClient $orderPaymentClient,
		private ?FrontendInterface $frontend = null,
		private ?LoggerInterface $logger = null,
	) {
		$orderRepository = $entityManager->getRepository(Order::class);
		assert($orderRepository instanceof OrderRepository);
		$this->orderRepository = $orderRepository;
	}


	public static function getLinkGenerator(): LinkGenerator
	{
		static $cache;
		if ($cache === null) {
			$cache = new LinkGenerator;
		}

		return $cache;
	}


	public static function setFlashMessage(string $message): void
	{
		$_SESSION[self::FLASH_MESSAGE_KEY] = [$message, time()];
	}


	/**
	 * @return never-return
	 */
	public static function redirect(string $url): void
	{
		header(sprintf('Location: %s', $url));
		die;
	}


	/**
	 * @return never-return
	 */
	public function run(): void
	{
		try {
			$action = (string) preg_replace('~^order/?~', '', Url::get()->getRelativeUrl(false));
			if ($action === '' || strlen($action) === 32) {
				$method = 'actionDefault';
			} else {
				$action = (string) preg_replace_callback(
					'/-([a-z])/',
					static fn(array $match): string => strtoupper($match[1] ?? ''),
					$action,
				);
				$method = 'action' . $action;
			}
			if (method_exists($this, $method)) {
				(new ServiceMethodInvoker)->invoke(
					service: $this,
					methodName: $method,
					params: $_GET,
				);
			}
		} catch (\Throwable $e) {
			echo '<h1>Internal server error</h1><p>An error occurred during the processing of your order.</p>';
			$this->logger?->critical($e->getMessage(), ['exception' => $e]);
			bdump($e);
			die;
		}
		die;
	}


	public function actionDefault(): void
	{
		$order = $this->getOrder();
		if ($order === null) {
			throw new \LogicException('Order does not exist.');
		}
		if ($_GET !== []) { // canonize url
			self::redirect(self::getLinkGenerator()->default($order));
		}

		$templatePath = $this->frontend?->getDefaultTemplatePath();
		(new Engine)->render(
			$templatePath ?? __DIR__ . '/template/default.latte',
			[
				'order' => $order,
				'isPaid' => $this->orderManager->get()->isPaid($order),
				'gatewayLink' => self::getLinkGenerator()->paymentGateway($order),
				'flashMessage' => $this->getFlashMessage(),
			],
		);
	}


	public function actionGateway(): void
	{
		$order = $this->getOrder();
		if ($order === null) {
			throw new \LogicException('Order does not exist.');
		}
		if ($this->orderManager->get()->isPaid($order)) {
			$this->actionDefault();
			die;
		}
		try {
			$this->orderPaymentClient->processPayment($order);
		} catch (\InvalidArgumentException $e) {
			echo htmlspecialchars($e->getMessage());
		}
	}


	public function actionPaymentHandle(string $handler, ?string $id = null): void
	{
		$order = $this->getOrder();
		if ($order === null) {
			throw new \LogicException('Order does not exist.');
		}
		try {
			$this->orderPaymentClient->checkPaymentStatus($order, $id);
		} catch (\InvalidArgumentException $e) {
			echo htmlspecialchars($e->getMessage());
		}
	}


	public function actionPrint(): void
	{
		$order = $this->getOrder();
		if ($order === null) {
			throw new \LogicException('Order does not exist.');
		}

		$templatePath = $this->frontend?->getDefaultPrintTemplatePath();
		(new Engine)->render(
			$templatePath ?? __DIR__ . '/template/print.latte',
			[
				'order' => $order,
				'isPaid' => $this->orderManager->get()->isPaid($order),
			],
		);
	}


	private function getOrder(): ?Order
	{
		$hash = trim($_GET['hash'] ?? '');
		if ($hash === '') { // Hash URL query parameter does not exist.
			$action = (string) preg_replace('~^order/?~', '', Url::get()->getRelativeUrl(false));
			if (strlen($action) === 32) {
				$hash = $action;
			}
		}
		if ($hash === '') {
			return null;
		}

		try {
			return $this->orderRepository->getByHash($hash);
		} catch (NoResultException | NonUniqueResultException) {
			return null;
		}
	}


	private function getFlashMessage(): ?string
	{
		/** @var array{0?: string, 1?: int} $message */
		$message = $_SESSION[self::FLASH_MESSAGE_KEY] ?? null;
		$minAllowedTime = time() - 6;
		if (is_array($message)
			&& isset($message[0], $message[1])
			&& is_string($message[0])
			&& is_int($message[1])
			&& $message[1] >= $minAllowedTime
		) {
			return $message[0];
		}

		return null;
	}
}
