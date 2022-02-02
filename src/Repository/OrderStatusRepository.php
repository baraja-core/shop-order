<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Repository;


use Baraja\Shop\Order\Entity\OrderStatus;
use Doctrine\ORM\EntityRepository;

final class OrderStatusRepository extends EntityRepository
{
	/**
	 * @return OrderStatus[]
	 */
	public function getAll(): array
	{
		/** @var OrderStatus[] $return */
		$return = $this->createQueryBuilder('status')
			->orderBy('status.workflowPosition', 'ASC')
			->getQuery()
			->getResult();

		$changed = false;
		foreach ($return as $position => $status) {
			$position++;
			if ($status->getWorkflowPosition() !== $position) {
				$status->setWorkflowPosition($position);
				$changed = true;
			}
		}
		if ($changed === true) {
			$this->_em->flush();
		}

		return $return;
	}
}
