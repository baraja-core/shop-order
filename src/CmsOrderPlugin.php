<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Cms\Search\SearchablePlugin;
use Baraja\Doctrine\EntityManager;
use Baraja\Plugin\BasePlugin;
use Baraja\Plugin\SimpleComponent\Button;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Repository\OrderRepository;
use Baraja\Url\Url;

final class CmsOrderPlugin extends BasePlugin implements SearchablePlugin
{
	private OrderRepository $orderRepository;


	public function __construct(
		private EntityManager $entityManager,
	) {
		$orderRepository = $entityManager->getRepository(Order::class);
		assert($orderRepository instanceof OrderRepository);
		$this->orderRepository = $orderRepository;
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
			$this->error(sprintf('Order %d does not exist.', $id));
		}
		try {
			$result = $this->orderRepository->getSimplePluginInfo($id);
		} catch (\InvalidArgumentException $e) {
			$this->error($e->getMessage());
		}
		$this->setTitle(sprintf('%s %s (%s)',
			(string) $result['order']['group']['name'],
			$result['order']['locale'],
			$result['order']['number'],
		));

		if ($result['before'] !== null) {
			$this->addButtonByOrder($result['before'], 'Last');
		}
		if ($result['after'] !== null) {
			$this->addButtonByOrder($result['after'], 'Next');
		}

		$this->addButton(
			new Button(
				variant: Button::VARIANT_SECONDARY,
				label: 'Print',
				action: Button::ACTION_LINK_TARGET,
				target: Url::get()->getBaseUrl() . '/order/print?hash=' . $result['order']['hash'],
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
	 * @param array{id: int, number: string} $order
	 */
	private function addButtonByOrder(array $order, string $label): Button
	{
		return new Button(
			variant: Button::VARIANT_INFO,
			label: sprintf('%s (%s)', $label, $order['number']),
			action: Button::ACTION_LINK,
			target: $this->link('CmsOrder:detail', [
				'id' => $order['id'],
			]),
		);
	}
}
