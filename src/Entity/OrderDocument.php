<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


interface OrderDocument
{
	public function getId(): ?int;

	public function getOrder(): Order;

	public function getNumber(): string;

	public function getLabel(): string;

	public function getDownloadLink(): string;
}
