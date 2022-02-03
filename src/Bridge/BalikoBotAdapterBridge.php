<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Bridge;


use Baraja\PhoneNumber\PhoneNumberFormatter;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Order\Delivery\Carrier\CarrierAdapter;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderPackage;
use Doctrine\ORM\EntityManagerInterface;
use Inspirum\Balikobot\Model\Aggregates\PackageCollection;
use Inspirum\Balikobot\Model\Values\Package;
use Inspirum\Balikobot\Services\Balikobot;
use Inspirum\Balikobot\Services\Requester;

final class BalikoBotAdapterBridge implements CarrierAdapter
{
	private ?Balikobot $bot = null;


	public function __construct(
		private string $apiUser,
		private string $apiKey,
		private EntityManagerInterface $entityManager
	) {
	}


	/**
	 * @return array<int, string>
	 */
	public function getCompatibleCarriers(): array
	{
		return ['zasilkovna', 'gls'];
	}


	/**
	 * @param array<int, Order> $orders
	 */
	public function createPackages(array $orders): void
	{
		if (count($orders) === 0) {
			return;
		}
		$primaryShipper = null;
		foreach ($orders as $order) {
			$delivery = $order->getDelivery();
			if ($delivery === null) {
				continue;
			}
			$shipperTemp = $delivery->getBotShipper();
			if ($shipperTemp === null) {
				throw new \InvalidArgumentException('Shipper type can not be empty.');
			}
			if ($primaryShipper === null) {
				$primaryShipper = $shipperTemp;
			} elseif ($primaryShipper !== $shipperTemp) {
				throw new \InvalidArgumentException(
					'These orders cannot be sent at the same time. Always select only one carrier.',
				);
			}
		}

		// create new package collection for specific shipper
		assert($primaryShipper !== null);
		$packages = new PackageCollection($primaryShipper);

		// add package to collection
		foreach ($orders as $order) {
			$delivery = $order->getDelivery();
			if ($delivery === null || $delivery->getBotShipper() === null) {
				continue;
			}
			try {
				$packages->add($this->createPackageEntity($order, $delivery));
			} catch (\Throwable $e) {
				throw new \InvalidArgumentException(
					'Invalid order "' . $order->getNumber() . '": ' . $e->getMessage(),
					$e->getCode(),
					$e,
				);
			}
		}

		$bot = $this->getBotService();
		// upload packages to balikobot
		$responsePackages = $bot->addPackages($packages);

		// order shipment for packages
		$orderedShipment = $bot->orderShipment($responsePackages);

		// save track URL for each package
		foreach ($responsePackages as $key => $orderedPackage) {
			$order = $orders[$key] ?? null;
			if ($order === null) {
				continue;
			}
			$packageRecord = new OrderPackage(
				order: $order,
				orderId: $orderedShipment->getOrderId(),
				packageId: (int) $orderedPackage->getPackageId(),
				batchId: $orderedPackage->getBatchId(),
				shipper: $orderedPackage->getShipper(),
				carrierId: $orderedPackage->getCarrierId(),
			);
			$packageRecord->setTrackUrl($orderedPackage->getTrackUrl());
			$packageRecord->setLabelUrl($orderedPackage->getLabelUrl());
			$packageRecord->setCarrierIdSwap($orderedPackage->getCarrierIdSwap());
			$packageRecord->setPieces($orderedPackage->getPieces());
			$packageRecord->setFinalCarrierId($orderedPackage->getFinalCarrierId());
			$packageRecord->setFinalTrackUrl($orderedPackage->getFinalTrackUrl());
			$this->entityManager->persist($packageRecord);
			$this->entityManager->getUnitOfWork()->commit($packageRecord);
		}

		$handoverUrl = $orderedShipment->getHandoverUrl();
		foreach ($orders as $order) {
			if ($order !== null) {
				$order->setHandoverUrl($handoverUrl);
			}
		}
		$this->entityManager->flush();
	}


	private function createPackageEntity(Order $order, Delivery $delivery): Package
	{
		$serviceType = $delivery->getBotServiceType();
		if ($serviceType === null) {
			throw new \InvalidArgumentException(
				'Service type can not be undefined (delivery ID: ' . $delivery->getId() . ').',
			);
		}

		$package = new Package;
		$package->setServiceType($serviceType);
		$package->setRecEmail((string) $order->getCustomer()->getEmail());
		$package->setRecName($order->getDeliveryAddress()->getPersonName());
		$package->setRecStreet($order->getDeliveryAddress()->getStreet());
		$package->setRecCity($order->getDeliveryAddress()->getCity());
		$package->setRecZip($order->getDeliveryAddress()->getZip());
		$package->setRecCountry($order->getDeliveryAddress()->getCountry()->getCode());
		$phone = $this->formatPhone((string) $order->getCustomer()->getPhone());
		if ($phone !== null) {
			$package->setRecPhone($phone);
		}
		$package->setPrice((float) $order->getPrice()->getValue());
		$package->setInsCurrency($order->getCurrency()->getCode());
		$package->setWeight(0.4); // TODO: hardcoded
		if ($order->getDeliveryBranchId()) {
			$package->setBranchId((string) $order->getDeliveryBranchId());
		}

		return $package;
	}


	private function getBotService(): Balikobot
	{
		if ($this->bot === null) {
			$this->bot = new Balikobot(new Requester($this->apiUser, $this->apiKey));
		}

		return $this->bot;
	}


	private function formatPhone(string $phone): ?string
	{
		if (trim($phone) === '') {
			return null;
		}
		$phone = (string) preg_replace('/^\+\d{1,3}\s+/', '', PhoneNumberFormatter::fix($phone));

		return (string) preg_replace('/\D+/', '', $phone);
	}
}
