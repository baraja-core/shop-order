<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Cms\Search\SearchablePlugin;
use Baraja\Doctrine\EntityManager;
use Baraja\Plugin\BasePlugin;
use Baraja\Plugin\SimpleComponent\Button;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Url\Url;

final class CmsOrderPlugin extends BasePlugin implements SearchablePlugin
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function getBaseEntity(): string
	{
		return Order::class;
	}


	public function getName(): string
	{
		return 'Order manager';
	}


	public function getIcon(): ?string
	{
		return 'cash-stack';
	}


	public function actionDetail(int $id): void
	{
		if ($id < 1) {
			$results = [];
		} else {
			/** @var array<int, array{id: int, number: string, hash: string}> $results */
			$results = $this->entityManager->getRepository(Order::class)
				->createQueryBuilder('o')
				->select('PARTIAL o.{id, number, hash}')
				->where('o.id IN (:ids)')
				->setParameter('ids', [$id, $id - 1, $id + 1])
				->getQuery()
				->getArrayResult();
		}

		$order = $this->filterById($results, $id);
		if ($order === null) {
			$this->error('Order "' . $id . '" does not exist.');
		}
		$this->setTitle('(' . $order['number'] . ')');

		$last = $this->getButtonById($results, $id - 1, 'Last');
		if ($last !== null) {
			$this->addButton($last);
		}

		$next = $this->getButtonById($results, $id + 1, 'Next');
		if ($next !== null) {
			$this->addButton($next);
		}

		$this->addButton(
			new Button(
				variant: Button::VARIANT_SECONDARY,
				label: 'Print',
				action: Button::ACTION_LINK_TARGET,
				target: Url::get()->getBaseUrl() . '/order/print?hash=' . $order['hash'],
			)
		);
	}


	/**
	 * @return array<int, string>
	 */
	public function getSearchColumns(): array
	{
		return [':number', 'customer.email', 'customer.firstName', 'customer.lastName'];
	}


	/**
	 * @param array<int, array{id: int, number: string, hash: string}> $results
	 * @return array{id: int, number: string, hash: string}|null
	 */
	private function filterById(array $results, int $id): ?array
	{
		if ($id < 1) {
			return null;
		}
		foreach ($results as $result) {
			if ($result['id'] === $id) {
				return $result;
			}
		}

		return null;
	}


	/**
	 * @param array<int, array{id: int, number: string, hash: string}> $results
	 */
	private function getButtonById(array $results, int $id, string $label): ?Button
	{
		$order = $this->filterById($results, $id);
		if ($order !== null) {
			$link = $this->link(
				'CmsOrder:detail',
				[
					'id' => $order['id'],
				]
			);

			return new Button(
				variant: Button::VARIANT_INFO,
				label: $label . ' (' . $order['number'] . ')',
				action: Button::ACTION_LINK,
				target: $link,
			);
		}

		return null;
	}
}
