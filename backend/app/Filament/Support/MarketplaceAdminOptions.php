<?php

namespace App\Filament\Support;

class MarketplaceAdminOptions
{
    public static function localeOptions(): array
    {
        return [
            'el' => 'Greek',
            'en' => 'English',
        ];
    }

    public static function trustStatuses(): array
    {
        return [
            'new' => 'New',
            'reviewing' => 'Reviewing',
            'trusted' => 'Trusted',
            'flagged' => 'Flagged',
            'restricted' => 'Restricted',
        ];
    }

    public static function listingStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'pending_review' => 'Pending review',
            'needs_revision' => 'Needs revision',
            'published' => 'Published',
            'sold' => 'Sold',
            'suspended' => 'Suspended',
            'rejected' => 'Rejected',
            'archived' => 'Archived',
        ];
    }

    public static function saleFormats(): array
    {
        return [
            'fixed_price' => 'Fixed price',
            'auction' => 'Auction',
        ];
    }

    public static function verificationTypes(): array
    {
        return [
            'identity' => 'Identity',
            'address' => 'Address',
            'bank' => 'Bank account',
            'seller' => 'Seller',
            'enhanced' => 'Enhanced',
        ];
    }

    public static function verificationStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'under_review' => 'Under review',
            'approved' => 'Approved',
            'needs_revision' => 'Needs revision',
            'rejected' => 'Rejected',
        ];
    }

    public static function orderStatuses(): array
    {
        return [
            'pending_payment' => 'Pending payment',
            'paid_pending_release' => 'Paid / pending release',
            'released' => 'Released',
            'disputed' => 'Disputed',
            'refunded' => 'Refunded',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function escrowStatuses(): array
    {
        return [
            'pending_payment' => 'Pending payment',
            'paid_pending_release' => 'Paid / pending release',
            'released' => 'Released',
            'disputed' => 'Disputed',
            'refunded' => 'Refunded',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function payoutStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'queued' => 'Queued',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function supportStatuses(): array
    {
        return [
            'open' => 'Open',
            'investigating' => 'Investigating',
            'waiting_on_user' => 'Waiting on user',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
    }

    public static function supportCategories(): array
    {
        return [
            'general' => 'General question',
            'order_issue' => 'Order issue',
            'payment_issue' => 'Payment issue',
            'dispute' => 'Dispute',
            'seller_issue' => 'Seller issue',
            'account_safety' => 'Account safety',
            'dsa_notice' => 'DSA notice / illegal content',
        ];
    }

    public static function supportPriorities(): array
    {
        return [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
    }

    public static function drawCampaignTypes(): array
    {
        return [
            'platform_volume' => 'Platform volume',
            'community_raffle' => 'Community raffle',
        ];
    }

    public static function drawStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'review' => 'In review',
            'active' => 'Active',
            'locked' => 'Locked',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function taxonomyTypes(): array
    {
        return [
            'item_type' => 'Item type',
            'franchise' => 'Franchise',
            'series' => 'Series',
            'brand' => 'Brand',
            'publisher' => 'Publisher',
            'grading_company' => 'Grading company',
            'condition_scale' => 'Condition scale',
            'rarity' => 'Rarity',
        ];
    }

    public static function attributeFieldTypes(): array
    {
        return [
            'text' => 'Text',
            'textarea' => 'Textarea',
            'number' => 'Number',
            'date' => 'Date',
            'toggle' => 'Toggle',
            'select' => 'Select',
            'multiselect' => 'Multi-select',
            'tags' => 'Tags',
        ];
    }

    public static function blogStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'review' => 'In review',
            'published' => 'Published',
            'archived' => 'Archived',
        ];
    }

    public static function notificationTypes(): array
    {
        return [
            'order' => 'Order',
            'message' => 'Message',
            'listing' => 'Listing',
            'followed_seller_listing' => 'Followed seller listing',
            'profile_like' => 'Profile like',
            'profile_follow' => 'Profile follow',
            'verification' => 'Verification',
            'system' => 'System',
            'draw' => 'Draw',
            'auction' => 'Auction',
            'support' => 'Support',
        ];
    }
}
