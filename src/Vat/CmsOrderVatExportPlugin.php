<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Plugin\BasePlugin;

final class CmsOrderVatExportPlugin extends BasePlugin
{
	public function getName(): string
	{
		return 'VAT export';
	}
}
