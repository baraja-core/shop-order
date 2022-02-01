<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Shop\Price\Price;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__transaction')]
class OrderBankPayment implements TransactionEntity
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'transactions')]
	private ?Order $order = null;

	/** ID of bank transaction. */
	#[ORM\Column(type: 'bigint', unique: true)]
	private int $bankTransactionId;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $date;

	/** @var numeric-string */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private string $price;

	#[ORM\Column(type: 'string', length: 3)]
	private string $currency;

	#[ORM\Column(type: 'string', nullable: true)]
	private ?string $toAccount;

	#[ORM\Column(type: 'string', nullable: true)]
	private ?string $toAccountName;

	#[ORM\Column(type: 'bigint', nullable: true)]
	private ?int $toBankCode;

	#[ORM\Column(type: 'string', nullable: true)]
	private ?string $toBankName;

	#[ORM\Column(type: 'bigint', nullable: true)]
	private ?int $constantSymbol;

	#[ORM\Column(type: 'bigint', nullable: true)]
	private ?int $variableSymbol;

	#[ORM\Column(type: 'bigint', nullable: true)]
	private ?int $specificSymbol;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $userNotice;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $toMessage;

	#[ORM\Column(type: 'string', nullable: true)]
	private ?string $type;

	#[ORM\Column(type: 'string', nullable: true)]
	private ?string $sender;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $message;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $comment;

	#[ORM\Column(type: 'string', nullable: true)]
	private ?string $bic;

	#[ORM\Column(type: 'bigint', nullable: true)]
	private ?int $idTransaction;


	/**
	 * @param numeric-string $price
	 */
	public function __construct(
		int $bankTransactionId,
		\DateTimeInterface $date,
		string $price,
		string $currency,
		?string $toAccount,
		?string $toAccountName,
		?int $toBankCode,
		?string $toBankName,
		?int $constantSymbol,
		?int $variableSymbol,
		?int $specificSymbol,
		?string $userNotice,
		?string $toMessage,
		?string $type,
		?string $sender,
		?string $message,
		?string $comment,
		?string $bic,
		?int $idTransaction
	) {
		$this->bankTransactionId = $bankTransactionId;
		$this->date = $date;
		$this->price = Price::normalize($price);
		$this->currency = $currency;
		$this->toAccount = $toAccount;
		$this->toAccountName = $toAccountName;
		$this->toBankCode = $toBankCode;
		$this->toBankName = $toBankName;
		$this->constantSymbol = $constantSymbol;
		$this->variableSymbol = $variableSymbol;
		$this->specificSymbol = $specificSymbol;
		$this->userNotice = $userNotice;
		$this->toMessage = $toMessage;
		$this->type = $type;
		$this->sender = $sender;
		$this->message = $message;
		$this->comment = $comment;
		$this->bic = $bic;
		$this->idTransaction = $idTransaction;
	}


	public function getId(): int
	{
		return $this->id;
	}


	/**
	 * @return array<string, string|int|float>
	 */
	public function getData(): array
	{
		return [];
	}


	public function getOrder(): ?Order
	{
		return $this->order;
	}


	public function setOrder(?Order $order): void
	{
		$this->order = $order;
	}


	public function getBankTransactionId(): int
	{
		return $this->bankTransactionId;
	}


	public function setBankTransactionId(int $bankTransactionId): void
	{
		$this->bankTransactionId = $bankTransactionId;
	}


	public function getDate(): \DateTimeInterface
	{
		return $this->date;
	}


	public function setDate(\DateTime $date): void
	{
		$this->date = $date;
	}


	/**
	 * @return  numeric-string
	 */
	public function getPrice(): string
	{
		return Price::normalize($this->price);
	}


	/**
	 * @param numeric-string $price
	 */
	public function setPrice(string $price): void
	{
		$this->price = Price::normalize($price);
	}


	public function getCurrency(): string
	{
		return $this->currency;
	}


	public function setCurrency(string $currency): void
	{
		$this->currency = $currency;
	}


	public function getToAccount(): ?string
	{
		return $this->toAccount;
	}


	public function setToAccount(?string $toAccount): void
	{
		$this->toAccount = $toAccount;
	}


	public function getToAccountName(): ?string
	{
		return $this->toAccountName;
	}


	public function setToAccountName(?string $toAccountName): void
	{
		$this->toAccountName = $toAccountName;
	}


	public function getToBankCode(): ?int
	{
		return $this->toBankCode;
	}


	public function setToBankCode(?int $toBankCode): void
	{
		$this->toBankCode = $toBankCode;
	}


	public function getToBankName(): ?string
	{
		return $this->toBankName;
	}


	public function setToBankName(?string $toBankName): void
	{
		$this->toBankName = $toBankName;
	}


	public function getConstantSymbol(): ?int
	{
		return $this->constantSymbol;
	}


	public function setConstantSymbol(?int $constantSymbol): void
	{
		$this->constantSymbol = $constantSymbol;
	}


	public function getVariableSymbol(): ?int
	{
		return $this->variableSymbol;
	}


	public function setVariableSymbol(?int $variableSymbol): void
	{
		$this->variableSymbol = $variableSymbol;
	}


	public function getSpecificSymbol(): ?int
	{
		return $this->specificSymbol;
	}


	public function setSpecificSymbol(?int $specificSymbol): void
	{
		$this->specificSymbol = $specificSymbol;
	}


	public function getUserNotice(): ?string
	{
		return $this->userNotice;
	}


	public function setUserNotice(?string $userNotice): void
	{
		$this->userNotice = $userNotice;
	}


	public function getToMessage(): ?string
	{
		return $this->toMessage;
	}


	public function setToMessage(?string $toMessage): void
	{
		$this->toMessage = $toMessage;
	}


	public function getType(): ?string
	{
		return $this->type;
	}


	public function setType(?string $type): void
	{
		$this->type = $type;
	}


	public function getSender(): ?string
	{
		return $this->sender;
	}


	public function setSender(?string $sender): void
	{
		$this->sender = $sender;
	}


	public function getMessage(): ?string
	{
		return $this->message;
	}


	public function setMessage(?string $message): void
	{
		$this->message = $message;
	}


	public function getComment(): ?string
	{
		return $this->comment;
	}


	public function setComment(?string $comment): void
	{
		$this->comment = $comment;
	}


	public function getBic(): ?string
	{
		return $this->bic;
	}


	public function setBic(?string $bic): void
	{
		$this->bic = $bic;
	}


	public function getIdTransaction(): ?int
	{
		return $this->idTransaction;
	}


	public function setIdTransaction(?int $idTransaction): void
	{
		$this->idTransaction = $idTransaction;
	}


	public function getGatewayId(): string
	{
		throw new \LogicException('Gateway ID is not relevant for bank account.');
	}


	public function getLastCheckedDate(): \DateTimeInterface
	{
		return $this->date;
	}


	public function getStatus(): ?string
	{
		return 'paid';
	}


	public function setCheckedNow(): void
	{
	}
}
