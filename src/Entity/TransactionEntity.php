<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


interface TransactionEntity
{
	/** @return int|string */
	public function getId();

	public function getCurrency(): string;

	public function getPrice(): float;

	public function getVariableSymbol(): ?int;

	public function getDate(): \DateTimeInterface;

	/**
	 * @return array<string, string|int|float>
	 */
	public function getData(): array;
}
