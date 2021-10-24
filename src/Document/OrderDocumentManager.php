<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Document;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Order\Entity\Order;
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


	public function isDocument(Order|int $order): bool
	{
		return $this->getDocuments($order) !== [];
	}


	/**
	 * @return OrderDocument[]
	 */
	public function getDocuments(Order|int $order): array
	{
		$return = [];
		foreach ($this->getEntities() as $entity) {
			$return[] = $this->entityManager->getRepository($entity)
				->createQueryBuilder('e')
				->where('e.order = :orderId')
				->setParameter('orderId', is_int($order) ? $order : $order->getId())
				->getQuery()
				->getResult();
		}

		return array_merge([], ...$return);
	}


	public function getDocumentByTag(Order|int $order, string $tag): ?OrderDocument
	{
		foreach ($this->getDocuments($order) as $document) {
			if ($document->hasTag($tag)) {
				return $document;
			}
		}

		return null;
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
				'entity-list',
				$cache,
				[
					Cache::EXPIRATION => '2 hours',
				],
			);
		}

		return $cache;
	}
}
