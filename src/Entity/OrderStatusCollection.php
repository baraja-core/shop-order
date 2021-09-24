<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Nette\Utils\Strings;

#[Entity]
#[Table(name: 'shop__order_status_collection')]
class OrderStatusCollection
{
	use IdentifierUnsigned;

	#[Column(type: 'string', length: 48, unique: true)]
	private string $code;

	#[Column(type: 'string', length: 48)]
	private string $label;

	#[Column(type: 'json')]
	private array $codes;


	/**
	 * @param array<int, string> $codes
	 */
	public function __construct(string $code, string $label, array $codes)
	{
		$this->setCode($code);
		$this->setLabel($label);
		$this->setCodes($codes);
	}


	public function getCode(): string
	{
		return $this->code;
	}


	public function setCode(string $code): void
	{
		$this->code = Strings::webalize($code);
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	public function setLabel(string $label): void
	{
		$this->label = Strings::upper(trim($label));
	}


	/**
	 * @return string[]
	 */
	public function getCodes(): array
	{
		return $this->codes;
	}


	/**
	 * @param string[] $codes
	 */
	public function setCodes(array $codes): void
	{
		$this->codes = $codes;
	}
}
