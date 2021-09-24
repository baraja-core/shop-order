<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\OrderGroup;

final class OrderGroupManager
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function getDefaultGroup(): OrderGroup
	{
		$default = null;
		foreach ($this->getGroups() as $group) {
			if ($group->isDefault()) {
				$default = $group;
				break;
			}
		}
		if ($default === null) {
			$default = $this->createDefaultGroup();
			$this->getGroups(true);
		}

		return $default;
	}


	/**
	 * @return OrderGroup[]
	 */
	public function getGroups(bool $flush = false): array
	{
		static $cache;
		if ($cache === null || $flush === true) {
			/** @var OrderGroup[] $list */
			$list = $this->entityManager->getRepository(OrderGroup::class)
				->createQueryBuilder('og')
				->getQuery()
				->getResult();

			if ($list === []) {
				$list[] = $this->createDefaultGroup();
			}
			$cache = $list;
		}

		return $cache;
	}


	public function getById(int $id): OrderGroup
	{
		foreach ($this->getGroups() as $group) {
			if ($group->getId() === $id) {
				return $group;
			}
		}
		throw new \InvalidArgumentException('Group "' . $id . '" does not exist.');
	}


	public function getByCode(string $code): OrderGroup
	{
		foreach ($this->getGroups() as $group) {
			if ($group->getCode() === $code) {
				return $group;
			}
		}
		throw new \InvalidArgumentException('Group "' . $code . '" does not exist.');
	}


	public function setDefault(OrderGroup $group, bool $default = true): void
	{
		$groups = $this->getGroups();
		if (count($groups) < 2) {
			throw new \InvalidArgumentException('Can not change group visibility.');
		}
		$group->setDefault($default);
		foreach ($groups as $groupItem) {
			if ($groupItem->getId() !== $group->getId()) {
				$groupItem->setDefault(!$default);
				if ($default === false) {
					break;
				}
			}
		}
		$this->entityManager->flush();
		$this->getGroups(true);
	}


	public function create(string $name, string $code, bool $default = false): OrderGroup
	{
		$group = new OrderGroup($name, $code);
		$group->setDefault($default);
		$this->entityManager->persist($group);
		if ($default === true) {
			foreach ($this->getGroups() as $groupItem) {
				$groupItem->setDefault(false);
			}
		}
		$this->entityManager->flush();
		$this->getGroups(true);

		return $group;
	}


	private function createDefaultGroup(): OrderGroup
	{
		$group = new OrderGroup('default', 'default');
		$group->setDefault(true);
		$this->entityManager->persist($group);
		$this->entityManager->flush();

		return $group;
	}
}
