<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Command;


use Baraja\Shop\Order\Entity\OrderOnlinePayment;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\Shop\Order\Repository\OrderOnlinePaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckGatewayPaymentsCommand extends Command
{
	private OrderOnlinePaymentRepository $orderOnlinePaymentRepository;


	public function __construct(
		private EntityManagerInterface $entityManager,
		private OrderPaymentClient $orderPaymentClient,
		private LoggerInterface $logger,
	) {
		parent::__construct();
		$orderOnlinePaymentRepository = $entityManager->getRepository(OrderOnlinePayment::class);
		assert($orderOnlinePaymentRepository instanceof OrderOnlinePaymentRepository);
		$this->orderOnlinePaymentRepository = $orderOnlinePaymentRepository;
	}


	protected function configure(): void
	{
		$this->setName('baraja:shop:check-gateway-payments')
			->setDescription('Check all waiting or error gateway transactions.');
	}


	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		echo 'Checking...' . "\n";
		foreach ($this->orderOnlinePaymentRepository->getUnresolvedPayments() as $payment) {
			echo sprintf('%s: %s - ', $payment->getGatewayId(), $payment->getStatus());
			try {
				$this->orderPaymentClient->checkPaymentStatusOnly($payment->getOrder(), $payment->getGatewayId());
				echo 'OK';
			} catch (\Throwable $e) {
				$this->logger->critical($e->getMessage(), ['exception' => $e]);
				echo htmlspecialchars($e->getMessage());
			}
			echo "\n";
		}
		echo 'Saving...';
		$this->entityManager->flush();
		echo 'Done.';

		return 0;
	}
}
