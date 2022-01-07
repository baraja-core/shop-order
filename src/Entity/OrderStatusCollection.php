<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\Strings;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order_status_collection')]
class OrderStatusCollection
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\Column(type: 'string', length: 48, unique: true)]
	private string $code;

	#[ORM\Column(type: 'string', length: 48)]
	private string $label;

	/** @var array<int, string> */
	#[ORM\Column(type: 'json')]
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


	public function getId(): int
	{
		return $this->id;
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
	 * @return array<int, string>
	 */
	public function getCodes(): array
	{
		return $this->codes;
	}


	/**
	 * @param array<int, string> $codes
	 */
	public function setCodes(array $codes): void
	{
		$this->codes = $codes;
	}
}
