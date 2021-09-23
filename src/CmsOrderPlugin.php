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
		/** @var Order $order */
		$order = $this->entityManager->getRepository(Order::class)->find($id);

		$this->setTitle('(' . $order->getNumber() . ')');

		/** @var Order|null $beforeOrder */
		$beforeOrder = $this->entityManager->getRepository(Order::class)->find($id - 1);
		if ($beforeOrder !== null) {
			$this->addButton(new Button(Button::VARIANT_INFO, 'Last (' . $beforeOrder->getNumber() . ')', Button::ACTION_LINK, $this->link('CmsOrder:detail', [
				'id' => $beforeOrder->getId(),
			])));
		}

		/** @var Order|null $nextOrder */
		$nextOrder = $this->entityManager->getRepository(Order::class)->find($id + 1);
		if ($nextOrder !== null) {
			$this->addButton(new Button(Button::VARIANT_INFO, 'Next (' . $nextOrder->getNumber() . ')', Button::ACTION_LINK, $this->link('CmsOrder:detail', [
				'id' => $nextOrder->getId(),
			])));
		}

		$this->addButton(new Button(Button::VARIANT_SECONDARY, 'Print', Button::ACTION_LINK_TARGET, Url::get()->getBaseUrl() . '/order/print?hash=' . $order->getHash()));
	}


	/**
	 * @return string[]
	 */
	public function getSearchColumns(): array
	{
		return [':number', 'customer.email', 'customer.firstName', 'customer.lastName'];
	}
}
