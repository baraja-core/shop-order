<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


interface TransactionEntity extends OrderPaymentEntity
{
	/** @return int|string */
	public function getId();

	public function getCurrency(): string;

	public function getVariableSymbol(): ?int;

	/**
	 * @return array<string, string|int|float>
	 */
	public function getData(): array;
}
