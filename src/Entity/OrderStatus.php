<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\EcommerceStandard\DTO\OrderStatusInterface;
use Baraja\Shop\Order\Repository\OrderStatusRepository;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\Strings;

#[ORM\Entity(repositoryClass: OrderStatusRepository::class)]
#[ORM\Table(name: 'shop__order_status')]
class OrderStatus implements OrderStatusInterface
{
	public const
		StatusNew = 'new',
		StatusPaid = 'paid',
		StatusPreparing = 'preparing',
		StatusSent = 'sent',
		StatusDone = 'done',
		StatusPrepared = 'prepared',
		StatusStorno = 'storno',
		StatusPaymentPing = 'payment-ping',
		StatusPaymentFailed = 'payment-failed',
		StatusTest = 'test',
		StatusReturned = 'returned',
		StatusMissingItem = 'missing-item',
		StatusDealerPaid = 'dealer-paid',
		StatusDealerNonPaid = 'dealer-non-paid';

	public const CommonStatuses = [
		self::StatusNew,
		self::StatusPaid,
		self::StatusPreparing,
		self::StatusSent,
		self::StatusDone,
		self::StatusPrepared,
		self::StatusStorno,
		self::StatusPaymentPing,
		self::StatusPaymentFailed,
		self::StatusTest,
		self::StatusReturned,
		self::StatusMissingItem,
		self::StatusDealerPaid,
		self::StatusDealerNonPaid,
	];

	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\Column(type: 'string', length: 48, unique: true)]
	private string $code;

	#[ORM\Column(type: 'string', length: 48)]
	private string $internalName;

	#[ORM\Column(type: 'string', length: 48)]
	private string $label;

	#[ORM\Column(type: 'string', length: 48, nullable: true)]
	private ?string $publicLabel = null;

	#[ORM\Column(type: 'string', length: 128, nullable: true)]
	private ?string $systemHandle = null;

	#[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
	private ?int $workflowPosition = null;

	#[ORM\Column(type: 'boolean')]
	private bool $markAsPaid = false;

	#[ORM\Column(type: 'boolean')]
	private bool $createInvoice = false;

	/**
	 * Internal smart logic.
	 * Some order states can only serve as a virtual state used by third-party logic.
	 * For example, the payment gateway will set the status to "payment failed" in case of a failure,
	 * which will trigger internal workflow rules, but at the same time the order
	 * will be immediately switched to the correct state.
	 */
	#[ORM\ManyToOne(targetEntity: self::class)]
	private ?self $redirectTo = null;

	#[ORM\Column(type: 'string', length: 7, nullable: true)]
	private ?string $color = null;


	public function __construct(string $code, string $name)
	{
		$this->code = Strings::webalize($code);
		$this->internalName = Strings::firstUpper(str_replace('-', ' ', $name));
		$this->label = Strings::firstUpper($name);
	}


	public function getId(): int
	{
		return $this->id;
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


	public function getWorkflowPosition(): int
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


	public function isMarkAsPaid(): bool
	{
		return $this->markAsPaid;
	}


	public function setMarkAsPaid(bool $markAsPaid): void
	{
		$this->markAsPaid = $markAsPaid;
	}


	public function isCreateInvoice(): bool
	{
		return $this->createInvoice;
	}


	public function setCreateInvoice(bool $createInvoice): void
	{
		$this->createInvoice = $createInvoice;
	}


	public function getRedirectTo(): ?self
	{
		return $this->redirectTo;
	}


	public function setRedirectTo(?self $redirectTo): void
	{
		if ($redirectTo !== null) {
			$id = $this->getId();
			$parent = $redirectTo;
			do {
				if ($parent->getId() === $id) {
					throw new \InvalidArgumentException('Please never create circular redirect.');
				}
				$parent = $parent->getRedirectTo();
			} while ($parent !== null);
		}
		$this->redirectTo = $redirectTo;
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
