<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Payment;


use Nette\Utils\Validators;

final class OrderPaymentResponse
{
	public function __construct(
		private string $redirectUrl,
	) {
		if (Validators::isUrl($redirectUrl) === false) {
			throw new \InvalidArgumentException('Redirect URL "' . $redirectUrl . '" does not exist.');
		}
	}


	/**
	 * Absolute URL to the payment gateway.
	 * To continue, redirect the customer here.
	 */
	public function getRedirectUrl(): string
	{
		return $this->redirectUrl;
	}
}
