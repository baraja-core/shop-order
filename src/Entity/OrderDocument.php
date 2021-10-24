<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


interface OrderDocument
{
	public function getId(): ?int;

	public function getOrder(): Order;

	public function getNumber(): string;

	public function getLabel(): string;

	/**
	 * @return array<int, string>
	 */
	public function getTags(): array;

	public function addTag(string $tag): void;

	public function hasTag(string $tag): bool;

	public function removeTag(string $tag): void;

	public function getDownloadLink(): string;
}
