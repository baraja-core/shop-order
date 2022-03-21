<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Repository\OrderRepository;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Http\Response;

final class CmsOrderVatEndpoint extends BaseEndpoint
{
	private OrderRepository $orderRepository;


	public function __construct(
		EntityManagerInterface $entityManager,
		private OrderStatusManager $orderStatusManager,
	) {
		$orderRepository = $entityManager->getRepository(Order::class);
		assert($orderRepository instanceof OrderRepository);
		$this->orderRepository = $orderRepository;
	}


	public function actionStatuses(): void
	{
		$this->sendJson(
			[
				'list' => $this->formatBootstrapSelectArray($this->orderStatusManager->getKeyValueList()),
			],
		);
	}


	/**
	 * @param array<int, string> $statuses
	 */
	public function actionExport(
		string $dateFrom,
		string $dateTo,
		array $statuses,
		string $filterBy = 'insertedDate',
	): void {
		$from = new \DateTimeImmutable($dateFrom);
		$to = new \DateTimeImmutable($dateTo);

		$orders = $this->orderRepository->getBasicVatExport(
			from: $from,
			to: $to,
			statuses: $statuses,
			filterBy: $filterBy,
		);

		$return = [];
		foreach ($orders as $order) {
			$return[] = [
				'id' => $order['id'],
				'cislo_objednavky' => $order['number'],
				'objednavka_vytvorena' => $order['insertedDate'],
				'cislo_faktury' => $order['invoices'][0]['number'] ?? null,
				'DUZP' => $order['invoices'][0]['insertedDate'] ?? null,
				'castka_celkem_s_DPH' => number_format((float) $order['price'], 3, '.', ''),
				'castka_celkem_bez_DPH' => number_format((float) $order['price'] / 1.21, 3, '.', ''),
				'DPH' => number_format($order['price'] - ($order['price'] / 1.21), 3, '.', ''),
				'typ_platby' => $order['payment']['name'],
				'stav_objednavky' => $order['status']['code'],
				'jmeno' => $order['paymentAddress']['firstName'] ?? '?',
				'prijmeni' => $order['paymentAddress']['lastName'] ?? '?',
				'ulice' => $order['paymentAddress']['street'] ?? '?',
				'psc' => $order['paymentAddress']['zip'] ?? '?',
				'mesto' => $order['paymentAddress']['city'] ?? '?',
				'zeme' => $order['paymentAddress']['country']['isoCode'] ?? '?',
				'ic' => $order['paymentAddress']['ic'] ?? null,
				'dic' => $order['paymentAddress']['dic'] ?? null,
				'nazev_firmy' => $order['paymentAddress']['companyName'] ?? null,
			];
		}
		if ($return === []) {
			$this->sendError('Report is empty.');
		}

		/** @var Response $httpResponse */
		$httpResponse = $this->container->getByType(Response::class);
		$httpResponse->setHeader('Content-type', 'text/csv; charset=utf-8');
		$httpResponse->setHeader(
			'Content-Disposition',
			sprintf(
				'attachment; filename=vat-export-%s_%s.csv',
				$from->format('Y-m-d'),
				$to->format('Y-m-d'),
			),
		);
		$httpResponse->setHeader('Pragma', 'public');
		$httpResponse->setHeader('Expires', '0');
		$httpResponse->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
		$httpResponse->setHeader('Content-Transfer-Encoding', 'binary');
		$httpResponse->setHeader('Content-Description', 'File Transfer');

		// header
		echo implode(
			',',
			array_map(static fn(string $item): string => '"' . $item . '"', array_keys($return[0])),
		);
		echo "\n";
		echo implode("\n", array_map([$this, 'renderRow'], $return));
		die;
	}


	/**
	 * @param array<int, mixed> $haystack
	 */
	private function renderRow(array $haystack): string
	{
		$return = '';
		foreach ($haystack as $item) {
			$return .= ($return !== '' ? ',' : '') . $this->renderCel($item);
		}

		return $return;
	}


	private function renderCel(mixed $value): string
	{
		if (is_bool($value)) {
			return $value ? 'y' : 'n';
		}
		if (is_numeric($value)) {
			return (string) $value;
		}
		if ($value === null) {
			return '';
		}
		if ($value instanceof \DateTimeInterface) {
			return sprintf('"%s"', $value->format('Y-m-d H:i:s'));
		}

		return '"' . $value . '"';
	}
}
