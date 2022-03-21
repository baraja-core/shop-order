<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Url\Url;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__order_file')]
class OrderFile implements OrderDocument
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Order::class)]
	private Order $order;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private ?string $number;

	#[ORM\Column(type: 'string', length: 128)]
	private string $label;

	#[ORM\Column(type: 'string', length: 128)]
	private string $filename;

	/** @var array<int, string> */
	#[ORM\Column(type: 'json')]
	private array $tags = [];

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;


	public function __construct(Order $order, ?string $number, string $label, string $filename)
	{
		$this->order = $order;
		$this->number = $number;
		$this->label = $label;
		$this->filename = $filename;
		$this->insertedDate = new \DateTimeImmutable;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public static function getRelativePath(self $orderFile): string
	{
		return sprintf(
			'order-file/%s/%s/%s',
			$orderFile->getInsertedDate()->format('Y-m-d'),
			$orderFile->getOrder()->getHash(),
			$orderFile->getFilename(),
		);
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getNumber(): string
	{
		return $this->number ?? $this->order->getNumber();
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	public function setLabel(string $label): void
	{
		$this->label = $label;
	}


	/**
	 * @return array<int, string>
	 */
	public function getTags(): array
	{
		return $this->tags;
	}


	public function addTag(string $tag): void
	{
		$this->tags[] = $tag;
	}


	public function hasTag(string $tag): bool
	{
		return in_array($tag, $this->getTags(), true);
	}


	public function removeTag(string $tag): void
	{
		$return = [];
		foreach ($this->getTags() as $tagItem) {
			if ($tagItem !== $tag) {
				$return[] = $tag;
			}
		}
		$this->tags = $return;
	}


	public function getDownloadLink(): string
	{
		return Url::get()->getBaseUrl() . '/' . self::getRelativePath($this);
	}


	public function getFilename(): string
	{
		return $this->filename;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}
}
