<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Payment\Gateway;


final class GatewayResponse
{
	public function __construct(
		private ?string $redirect = null,
		private ?string $errorMessage = null,
	) {
	}


	public function getRedirect(): ?string
	{
		return $this->redirect;
	}


	public function setRedirect(?string $redirect): void
	{
		$this->redirect = $redirect;
	}


	public function getErrorMessage(): ?string
	{
		return $this->errorMessage;
	}


	public function setErrorMessage(?string $errorMessage): void
	{
		$this->errorMessage = $errorMessage;
	}
}
