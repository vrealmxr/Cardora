<?php

namespace App\Enums;

enum BalanceTransactionType: string
{
    case PendingCredit = 'pending_credit';
    case ReleaseToAvailable = 'release_to_available';
    case TransferToConnectedAccount = 'transfer_to_connected_account';
    case Payout = 'payout';
    case Refund = 'refund';
    case Dispute = 'dispute';
    case Commission = 'commission';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
