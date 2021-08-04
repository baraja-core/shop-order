<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Nette\Utils\Strings;

#[Entity]
#[Table(name: 'shop__order_status')]
final class OrderStatus implements \Stringable
{
	use IdentifierUnsigned;

	public const
		STATUS_NEW = 'new',
		STATUS_PAID = 'paid',
		STATUS_PREPARING = 'preparing',
		STATUS_SENT = 'sent',
		STATUS_DONE = 'done',
		STATUS_PREPARED = 'prepared',
		STATUS_STORNO = 'storno',
		STATUS_TEST = 'test',
		STATUS_RETURNED = 'returned',
		STATUS_MISSING_ITEM = 'missing-item',
		STATUS_DEALER_PAID = 'dealer-paid',
		STATUS_DEALER_NON_PAID = 'dealer-non-paid';

	#[Column(type: 'string', length: 48, unique: true)]
	private string $code;

	#[Column(type: 'string', length: 48)]
	private string $internalName;

	#[Column(type: 'string', length: 48)]
	private string $label;

	#[Column(type: 'string', length: 48, nullable: true)]
	private ?string $publicLabel = null;

	#[Column(type: 'string', length: 128, nullable: true)]
	private ?string $systemHandle = null;

	#[Column(type: 'integer', nullable: true)]
	private ?int $workflowPosition = null;

	#[Column(type: 'string', length: 7, nullable: true)]
	private ?string $color = null;


	public function __construct(string $code, string $name)
	{
		$this->code = Strings::webalize($code);
		$this->internalName = Strings::firstUpper($name);
		$this->label = Strings::firstUpper($name);
	}


	public function __toString()
	{
		return $this->getCode();
	}


	public function getCode(): string
	{
		return $this->code;
	}


	public function setCode(string $code): void
	{
		$this->code = $code;
	}


	public function getName(): string
	{
		return ($this->workflowPosition !== null ? $this->workflowPosition . '. ' : '') . $this->getLabel();
	}


	public function getInternalName(): string
	{
		return $this->internalName;
	}


	public function setInternalName(string $internalName): void
	{
		$this->internalName = $internalName;
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	public function setLabel(string $label): void
	{
		$this->label = $label;
	}


	public function getPublicLabel(): ?string
	{
		return $this->publicLabel;
	}


	public function setPublicLabel(?string $publicLabel): void
	{
		$this->publicLabel = $publicLabel;
	}


	public function getSystemHandle(): ?string
	{
		return $this->systemHandle;
	}


	public function setSystemHandle(?string $systemHandle): void
	{
		$this->systemHandle = $systemHandle;
	}


	public function getWorkflowPosition(): ?int
	{
		return $this->workflowPosition;
	}


	public function setWorkflowPosition(?int $workflowPosition): void
	{
		$this->workflowPosition = $workflowPosition;
	}


	public function getColor(): string
	{
		return $this->color ?? '#000';
	}


	public function setColor(?string $color): void
	{
		$this->color = $color;
	}
}
