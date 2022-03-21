<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\EcommerceStandard\DTO\CartInterface;
use Baraja\EcommerceStandard\DTO\OrderInfoInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\Service\OrderManagerInterface;
use Baraja\Shop\Delivery\BranchManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderBankPayment;
use Baraja\Shop\Order\Entity\OrderFile;
use Baraja\Shop\Order\Entity\OrderOnlinePayment;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Shop\Order\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

final class OrderManager implements OrderManagerInterface
{
	private OrderRepository $orderRepository;

	private ?Seo $seo = null;


	public function __construct(
		OrderPaymentClient $paymentClient,
		private OrderGenerator $orderGenerator,
		private EntityManagerInterface $entityManager,
		private BranchManager $branchManager,
		private string $wwwDir,
	) {
		if (!is_dir($wwwDir)) {
			throw new \LogicException('Parameter wwwDir does not exist, because path "' . $wwwDir . '" given.');
		}
		$paymentClient->injectOrderManager($this);
		$orderRepository = $entityManager->getRepository(Order::class);
		assert($orderRepository instanceof OrderRepository);
		$this->orderRepository = $orderRepository;
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getOrderByHash(string $hash): Order
	{
		return $this->orderRepository->getAllByHash($hash);
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getOrderById(int $id): Order
	{
		return $this->orderRepository->getById($id);
	}


	/**
	 * Securely verify that the order has actually been paid for.
	 */
	public function isPaid(OrderInterface $order): bool
	{
		$sum = '0';
		assert($order instanceof Order);
		foreach ($order->getPayments() as $payment) {
			assert($payment instanceof OrderOnlinePayment);
			if ($payment->getStatus() === 'PAID') {
				$sum = bcadd($sum, $payment->getPrice());
			}
		}
		foreach ($order->getTransactions() as $transaction) {
			assert($transaction instanceof OrderBankPayment);
			$sum = bcadd($sum, $transaction->getPrice());
		}
		$orderPrice = $order->getBasePrice();
		$isPaid = $orderPrice->isEqualTo($sum) || $orderPrice->isSmallerThan($sum);
		if ($isPaid === true && $order->isPaid() === false) {
			$order->setPaid(true);
			$this->entityManager->flush();
		}

		return $isPaid;
	}


	public function setBranchId(Order $order, ?int $branchId): void
	{
		if ($branchId === null) {
			$order->setDeliveryBranchId(null);
		} else {
			$delivery = $order->getDelivery();
			if ($delivery !== null && $this->branchManager->getBranchById($delivery, $branchId) === null) {
				throw new \InvalidArgumentException(sprintf('Branch "%s" does not exist.', $branchId));
			}
			$order->setDeliveryBranchId($branchId);
		}
		$this->entityManager->flush();
	}


	public function createOrder(OrderInfoInterface $orderInfo, CartInterface $cart): OrderInterface
	{
		return $this->orderGenerator->createOrder($orderInfo, $cart);
	}


	public function recountPrice(Order $order): void
	{
		$order->recountPrice();
	}


	public function removeItem(Order $order, int $itemId): void
	{
		foreach ($order->getItems() as $item) {
			if ($item->getId() === $itemId) {
				$order->removeItem($itemId);
				$this->entityManager->remove($item);
				break;
			}
		}
		$order->recountPrice();
		$this->entityManager->flush();
	}


	public function addFile(
		Order $order,
		string|FileUpload $path,
		?string $name = null,
		?string $label = null,
		?string $number = null,
	): OrderFile {
		if (is_string($path)) {
			$pathString = $path;
		} else {
			$pathString = $path->getTemporaryFile();
		}
		if ($name === null) {
			$name = basename($pathString);
		}
		if (str_contains($name, '.') === false) {
			throw new \InvalidArgumentException(sprintf('File name must contains extension, but "%s" given.', $name));
		}
		if ($label === null) {
			$label = Strings::firstUpper((string) preg_replace('/^(.+)\..*$/', '$1', $name));
		}

		$file = new OrderFile($order, $number, $label, $name);
		$diskPath = sprintf('%s/%s', $this->wwwDir, OrderFile::getRelativePath($file));
		FileSystem::copy($pathString, $diskPath);
		$this->entityManager->persist($file);
		$this->entityManager->flush();

		return $file;
	}


	public function getSeo(): Seo
	{
		if ($this->seo === null) {
			$this->seo = new Seo;
		}

		return $this->seo;
	}
}
