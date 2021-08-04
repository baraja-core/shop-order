<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\Order;
use Baraja\StructuredApi\BaseEndpoint;
use Nette\Http\Response;
use Nette\Utils\DateTime;

final class CmsOrderVatEndpoint extends BaseEndpoint
{
	public function __construct(
		private EntityManager $entityManager,
		private OrderStatusManager $orderStatusManager,
	) {
	}


	public function actionStatuses(): void
	{
		$this->sendJson(
			[
				'list' => $this->formatBootstrapSelectArray($this->orderStatusManager->getKeyValueList()),
			]
		);
	}


	public function actionExport(
		string $dateFrom,
		string $dateTo,
		array $statuses,
		string $filterBy = 'insertedDate'
	): void {
		$from = DateTime::from($dateFrom);
		$to = DateTime::from($dateTo);

		$selection = $this->entityManager->getRepository(Order::class)
			->createQueryBuilder('o')
			->select('o, invoice, payment, address')
			->leftJoin('o.invoices', 'invoice')
			->leftJoin('o.payment', 'payment')
			->leftJoin('o.invoiceAddress', 'address')
			->andWhere('o.status IN (:statuses)')
			->setParameters(
				[
					'dateFrom' => $from->format('Y-m-d 00:00:00'),
					'dateTo' => $to->format('Y-m-d 00:00:00'),
					'statuses' => $statuses,
				]
			)
			->orderBy('o.insertedDate', 'DESC');

		if ($filterBy === 'insertedDate') {
			$selection
				->andWhere('o.insertedDate >= :dateFrom')
				->andWhere('o.insertedDate < :dateTo');
		} elseif ($filterBy === 'invoiceDate') {
			$selection
				->andWhere('invoice.insertedDate >= :dateFrom')
				->andWhere('invoice.insertedDate < :dateTo');
		} else {
			$this->sendError('Invalid filter by, because "' . $filterBy . '" given.');
		}

		$orders = $selection->getQuery()->getArrayResult();

		$return = [];
		foreach ($orders as $order) {
			$return[] = [
				'id' => $order['id'],
				'cislo_objednavky' => $order['number'],
				'objednavka_vytvorena' => $order['insertedDate'],
				'cislo_faktury' => $order['invoices'][0]['number'] ?? null,
				'DUZP' => $order['invoices'][0]['insertedDate'] ?? null,
				'castka_celkem_s_DPH' => number_format($order['price'], 3, '.', ''),
				'castka_celkem_bez_DPH' => number_format($order['price'] / 1.21, 3, '.', ''),
				'DPH' => number_format($order['price'] - ($order['price'] / 1.21), 3, '.', ''),
				'typ_platby' => $order['payment']['name'],
				'stav_objednavky' => $order['status'],
				'jmeno' => $order['invoiceAddress']['firstName'] ?? '?',
				'prijmeni' => $order['invoiceAddress']['lastName'] ?? '?',
				'ulice' => $order['invoiceAddress']['street'] ?? '?',
				'psc' => $order['invoiceAddress']['zip'] ?? '?',
				'mesto' => $order['invoiceAddress']['city'] ?? '?',
				'zeme' => $order['invoiceAddress']['country'] ?? '?',
				'ic' => $order['invoiceAddress']['ic'] ?? null,
				'dic' => $order['invoiceAddress']['dic'] ?? null,
				'nazev_firmy' => $order['invoiceAddress']['companyName'] ?? null,
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
			'attachment; filename=vat-export-'
			. $from->format('Y-m-d')
			. '_' . $to->format('Y-m-d')
			. '.csv'
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

		$renderer = static function (mixed $item): string
		{
			if (is_bool($item)) {
				return $item ? 'y' : 'n';
			}
			if (is_numeric($item)) {
				return (string) $item;
			}
			if ($item === null) {
				return '';
			}
			if ($item instanceof \DateTimeInterface) {
				return '"' . $item->format('Y-m-d H:i:s') . '"';
			}

			return '"' . $item . '"';
		};

		echo implode(
			"\n",
			array_map(
				static function (array $haystack) use ($renderer): string
				{
					$line = '';
					foreach ($haystack as $item) {
						$line .= ($line ? ',' : '') . $renderer($item);
					}

					return $line;
				},
				$return
			)
		);
		die;
	}
}
