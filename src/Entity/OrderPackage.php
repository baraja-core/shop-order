<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="shop__order_package")
 */
class OrderPackage
{
	use IdentifierUnsigned;

	/** @ORM\ManyToOne(targetEntity="Order", inversedBy="packages") */
	private Order $order;

	/** @ORM\Column(type="string", name="`bot_order_id`") */
	private string $orderId;

	/** @ORM\Column(type="integer") */
	private int $packageId;

	/**
	 * Package batch ID (EID)
	 *
	 * @ORM\Column(type="string")
	 */
	private string $batchId;

	/** @ORM\Column(type="string") */
	private string $shipper;

	/**
	 * Carrier ID (for package tracking)
	 *
	 * @ORM\Column(type="string")
	 */
	private string $carrierId;

	/** @ORM\Column(type="string", nullable=true) */
	private ?string $trackUrl = null;

	/** @ORM\Column(type="string", nullable=true) */
	private ?string $labelUrl = null;

	/** @ORM\Column(type="string", nullable=true) */
	private ?string $carrierIdSwap = null;

	/**
	 * @var string[]
	 * @ORM\Column(type="json", nullable=true)
	 */
	private array $pieces = [];

	/** @ORM\Column(type="string", nullable=true) */
	private ?string $finalCarrierId = null;

	/** @ORM\Column(type="string", nullable=true) */
	private ?string $finalTrackUrl = null;


	public function __construct(
		Order $order,
		string $orderId,
		int $packageId,
		string $batchId,
		string $shipper,
		string $carrierId
	) {
		$this->order = $order;
		$this->orderId = $orderId;
		$this->packageId = $packageId;
		$this->batchId = $batchId;
		$this->shipper = $shipper;
		$this->carrierId = $carrierId;
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getPackageId(): int
	{
		return $this->packageId;
	}


	public function getOrderId(): string
	{
		return $this->orderId;
	}


	public function getBatchId(): string
	{
		return $this->batchId;
	}


	public function setBatchId(string $batchId): void
	{
		$this->batchId = $batchId;
	}


	public function getShipper(): string
	{
		return $this->shipper;
	}


	public function setShipper(string $shipper): void
	{
		$this->shipper = $shipper;
	}


	public function getCarrierId(): string
	{
		return $this->carrierId;
	}


	public function setCarrierId(string $carrierId): void
	{
		$this->carrierId = $carrierId;
	}


	public function getTrackUrl(): ?string
	{
		return $this->trackUrl;
	}


	public function setTrackUrl(?string $trackUrl): void
	{
		$this->trackUrl = $trackUrl;
	}


	public function getLabelUrl(): ?string
	{
		return $this->labelUrl;
	}


	public function setLabelUrl(?string $labelUrl): void
	{
		$this->labelUrl = $labelUrl;
	}


	public function getCarrierIdSwap(): ?string
	{
		return $this->carrierIdSwap;
	}


	public function setCarrierIdSwap(?string $carrierIdSwap): void
	{
		$this->carrierIdSwap = $carrierIdSwap;
	}


	/**
	 * @return string[]
	 */
	public function getPieces(): array
	{
		return $this->pieces;
	}


	/**
	 * @param string[] $pieces
	 */
	public function setPieces(array $pieces): void
	{
		$this->pieces = $pieces;
	}


	public function getFinalCarrierId(): ?string
	{
		return $this->finalCarrierId;
	}


	public function setFinalCarrierId(?string $finalCarrierId): void
	{
		$this->finalCarrierId = $finalCarrierId;
	}


	public function getFinalTrackUrl(): ?string
	{
		return $this->finalTrackUrl;
	}


	public function setFinalTrackUrl(?string $finalTrackUrl): void
	{
		$this->finalTrackUrl = $finalTrackUrl;
	}
}
