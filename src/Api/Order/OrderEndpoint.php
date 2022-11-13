<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Api\Order;


use Baraja\ImageGenerator\ImageGenerator;
use Baraja\Shop\Order\Application\WebController;
use Baraja\Shop\Order\OrderManager;
use Baraja\Shop\Order\Payment\Gateway\GatewayResponse;
use Baraja\Shop\Order\Payment\OrderPaymentClient;
use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Baraja\StructuredApi\Response\Status\ErrorResponse;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

#[PublicEndpoint]
final class OrderEndpoint extends BaseEndpoint
{
	public function __construct(
		private OrderManager $orderManager,
		private OrderPaymentClient $orderPaymentClient,
	) {
	}


	public function actionDetail(string $hash): OrderResponse
	{
		try {
			$order = $this->orderManager->getOrderByHash($hash);
		} catch (NoResultException | NonUniqueResultException) {
			ErrorResponse::invoke(sprintf('Order "%s" does not exist.', $hash));
		}

		$items = [];
		foreach ($order->getItems() as $orderItem) {
			$product = $orderItem->isRealProduct() ? $orderItem->getProduct() : null;
			$image = $product?->getMainImage();
			$items[] = new OrderItemResponse(
				id: $orderItem->getId(),
				slug: $product?->getSlug(),
				mainImageUrl: $image !== null
					? ImageGenerator::from($image->getUrl(), ['w' => 200, 'h' => 200])
					: null,
				label: $orderItem->getLabel(),
				count: $orderItem->getCount(),
				price: $orderItem->getFinalPrice()->render(true),
				ean: $orderItem->getEan(),
				sale: false,
			);
		}

		$messages = [];
		foreach ($order->getMessages() as $message) {
			if ($message->isShareWithCustomer() === false) {
				continue;
			}
			$messages[] = new OrderMessageResponse(
				message: $message->getMessage(),
				insertedDate: $message->getInsertedDate(),
			);
		}

		return new OrderResponse(
			hash: $hash,
			number: $order->getNumber(),
			variableSymbol: $order->getNumber(),
			locale: $order->getLocale(),
			status: $order->getStatus()->getPublicLabel(),
			statusColor: $order->getStatus()->getColor(),
			statusCode: $order->getStatus()->getCode(),
			pickupCode: $order->getPickupCode(),
			price: $order->getPrice()->render(true),
			currency: $order->getPrice()->getCurrency()->getCode(),
			isPaid: $order->isPaid() || $this->orderManager->isPaid($order),
			paymentAttemptOk: $order->isPaymentAttemptOk(),
			delivery: $order->getDelivery()?->getLabel(),
			payment: $order->getPayment()?->getName(),
			notice: $order->getNotice(),
			gatewayLink: WebController::getLinkGenerator()->paymentGateway($order),
			deliveryAddress: (string) $order->getDeliveryAddress(),
			paymentAddress: (string) $order->getPaymentAddress(),
			insertedDate: $order->getInsertedDate(),
			updatedDate: $order->getUpdatedDate(),
			items: $items,
			messages: $messages,
			dataLayerStructure: $this->orderManager->getSeo()->getDataLayerStructure($order),
		);
	}


	public function actionGateway(string $hash): GatewayResponse
	{
		try {
			$order = $this->orderManager->getOrderByHash($hash);
		} catch (NoResultException | NonUniqueResultException) {
			ErrorResponse::invoke(sprintf('Order "%s" does not exist.', $hash));
		}

		$response = $this->orderPaymentClient->processPayment($order);
		assert($response instanceof GatewayResponse);

		return $response;
	}
}
