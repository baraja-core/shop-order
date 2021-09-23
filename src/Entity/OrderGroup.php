<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Localization\TranslateObject;
use Baraja\Localization\Translation;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\Strings;

/**
 * @method Translation getName(?string $locale = null)
 * @method void setName(string $name, ?string $locale = null)
 */
#[ORM\Entity]
#[ORM\Table(name: 'shop__order_group')]
class OrderGroup
{
	use IdentifierUnsigned;
	use TranslateObject;

	#[ORM\Column(type: 'translate')]
	private Translation $name;

	#[ORM\Column(type: 'string', length: 16, unique: true)]
	private string $code;

	#[ORM\Column(name: '`default`', type: 'boolean')]
	private bool $default = false;

	#[ORM\Column(type: 'boolean')]
	private bool $active = true;


	public function __construct(string $name, string $code)
	{
		$name = Strings::firstUpper(trim($name));
		if ($name === '') {
			throw new \InvalidArgumentException('Order group name can not be empty.');
		}
		$this->setName($name);
		$this->setCode($code);
	}


	public function getCode(): string
	{
		return $this->code;
	}


	public function setCode(string $code): void
	{
		$code = Strings::webalize($code);
		if ($code === '') {
			throw new \InvalidArgumentException('Order group code can not be empty.');
		}
		$this->code = $code;
	}


	public function isDefault(): bool
	{
		return $this->default;
	}


	public function setDefault(bool $default): void
	{
		$this->default = $default;
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active): void
	{
		$this->active = $active;
	}
}
