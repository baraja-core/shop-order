<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Document;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\OrderDocument;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

final class OrderDocumentManager
{
	private Cache $cache;


	public function __construct(
		private EntityManager $entityManager,
		Storage $storage,
	) {
		$this->cache = new Cache($storage, 'order-document-manager');
	}


	/**
	 * @return OrderDocument[]
	 */
	public function getDocuments(int $orderId): array
	{
		$return = [];
		foreach ($this->getEntities() as $entity) {
			$return[] = $this->entityManager->getRepository($entity)
				->createQueryBuilder('e')
				->where('e.order = :orderId')
				->setParameter('orderId', $orderId)
				->setMaxResults(1)
				->getQuery()
				->getResult();
		}

		return array_merge([], ...$return);
	}


	/**
	 * @return array<int, class-string>
	 */
	public function getEntities(): array
	{
		$cache = $this->cache->load('entity-list');
		if ($cache === null) {
			$return = [];
			foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metaData) {
				if ($metaData->getReflectionClass()->implementsInterface(OrderDocument::class)) {
					$return[] = $metaData->getName();
				}
			}
			$cache = $return;
			$this->cache->save(
				'entity-list', $cache, [
					Cache::EXPIRATION => '2 hours',
				]
			);
		}

		return $cache;
	}
}
