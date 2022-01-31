<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Application;


/**
 * A general interface for rendering the frontend of an e-shop for users.
 * A service implementing this interface may not exist.
 */
interface FrontendInterface
{
	/**
	 * Returns the absolute disk path to the Latte template with the order detail.
	 */
	public function getDefaultTemplatePath(): ?string;
}
