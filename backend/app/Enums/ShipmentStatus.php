<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case PendingLabel = 'pending_label';
    case LabelCreated = 'label_created';
    case InTransit = 'in_transit';
    case ReadyForCollection = 'ready_for_collection';
    case Delivered = 'delivered';
    case Exception = 'exception';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
