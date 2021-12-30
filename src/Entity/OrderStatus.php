<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Order\Repository\OrderStatusRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Nette\Utils\Strings;

#[Entity(repositoryClass: OrderStatusRepository::class)]
#[Table(name: 'shop__order_status')]
class OrderStatus implements \Stringable
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

	public const COMMON_STATUSES = [
		self::STATUS_NEW,
		self::STATUS_PAID,
		self::STATUS_PREPARING,
		self::STATUS_SENT,
		self::STATUS_DONE,
		self::STATUS_PREPARED,
		self::STATUS_STORNO,
		self::STATUS_TEST,
		self::STATUS_RETURNED,
		self::STATUS_MISSING_ITEM,
		self::STATUS_DEALER_PAID,
		self::STATUS_DEALER_NON_PAID,
	];

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

	#[Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
	private ?int $workflowPosition = null;

	#[Column(type: 'string', length: 7, nullable: true)]
	private ?string $color = null;


	public function __construct(string $code, string $name)
	{
		$this->code = Strings::webalize($code);
		$this->internalName = Strings::firstUpper($name);
		$this->label = Strings::firstUpper($name);
	}


	public function __toString(): string
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


	public function getPublicLabel(): string
	{
		return $this->publicLabel ?? $this->getName();
	}


	public function setPublicLabel(?string $publicLabel): void
	{
		if ($publicLabel !== null && preg_match('/^(?:\d+)\.\s+(.+)$/', $publicLabel, $parser) === 1) {
			$publicLabel = $parser[1] ?? $publicLabel;
		}
		if ($publicLabel !== $this->getPublicLabel()) {
			$this->publicLabel = $publicLabel;
		}
	}


	public function getSystemHandle(): ?string
	{
		return $this->systemHandle;
	}


	public function setSystemHandle(?string $systemHandle): void
	{
		if ($systemHandle !== null) {
			$systemHandle = trim($systemHandle);
			if ($systemHandle === '') {
				$systemHandle = null;
			}
		}
		$this->systemHandle = $systemHandle;
	}


	public function getWorkflowPosition(): ?int
	{
		return $this->workflowPosition ?? 0;
	}


	public function setWorkflowPosition(?int $workflowPosition): void
	{
		if ($workflowPosition === null) {
			$workflowPosition = 0;
		}
		if ($workflowPosition < 0) {
			$workflowPosition = 0;
		}
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
