<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Bridge;


use Baraja\Newsletter\NewsletterManagerAccessor;
use Baraja\Shop\Order\CreatedOrderEvent;
use Baraja\Shop\Order\Entity\Order;

final class RegisterNewsletterCreatedOrderEvent implements CreatedOrderEvent
{
	public function __construct(
		private NewsletterManagerAccessor $newsletterManagerAccessor,
	) {
	}


	public function process(Order $order): void
	{
		$email = $order->getCustomer()->getEmail();
		if ($email !== null && $order->getCustomer()->isNewsletter()) {
			$this->newsletterManagerAccessor->get()->register($email, 'shop-order');
		}
	}
}
