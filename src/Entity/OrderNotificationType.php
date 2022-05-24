<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Entity;


enum OrderNotificationType: string
{
	case Email = 'email';

	case Sms = 'sms';
}
