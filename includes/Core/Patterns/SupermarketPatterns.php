<?php
/**
 * Supermarket Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class SupermarketPatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();

    $base['sales']['high'] = array_merge($base['sales']['high'], [
        'grocery delivery', 'same day delivery', 'weekly grocery order',
        'bulk grocery order', 'wholesale grocery', 'fresh produce offer',
        'discounted items', 'promotional offer', 'membership savings',
        'loyalty card', 'store pickup', 'click and collect',

        // Product Segments
        'organic products', 'fresh vegetables', 'fresh fruits', 'dairy products',
        'bakery items', 'frozen food', 'meat and poultry', 'seafood section',
        'household essentials', 'baby care products', 'pet food',

        // Shopper Programs
        'subscription grocery plan', 'membership discount', 'reward points',
        'cashback offer', 'coupon offer', 'bundle offer',

        // Business/Institutional
        'corporate pantry supply', 'restaurant bulk supply',
        'hotel bulk supply', 'institutional grocery order'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'weekly offers', 'monthly deals', 'festive discounts',
        'family pack deals', 'value pack', 'private label products',
        'best price guarantee', 'price match request',
        'delivery subscription', 'scheduled delivery slots'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        'order missing items', 'wrong item delivered', 'expired product',
        'damaged package', 'delivery delayed', 'delivery not received',
        'billing mismatch', 'checkout issue', 'payment failed at checkout',

        // Order/Delivery
        'order canceled unexpectedly', 'partial delivery', 'delivery rider issue',
        'cold chain broken', 'melted frozen item',

        // Product/Inventory
        'out of stock after order', 'substituted item not acceptable',
        'wrong quantity delivered', 'incorrect weight item',

        // In-store Support
        'barcode not scanning', 'self checkout issue', 'pos machine not working',
        'long queue at checkout', 'refund not processed',

        // Account/App
        'app not working', 'cart not updating', 'coupon not applying',
        'loyalty points not credited'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'delivery slot change request', 'address update for order',
        'order reschedule request', 'return pickup request',
        'invoice request', 'tax invoice needed',
        'product availability check', 'restock estimate request'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'] ?? [], [
        'better freshness control', 'improve produce quality',
        'faster doorstep delivery', 'better packaging for perishables',
        'clear expiry date display', 'clear nutritional labels',
        'better app search and filters', 'more accurate stock visibility',
        'better customer support responsiveness', 'more affordable staples',
        'improve checkout speed', 'better in-store staff assistance'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'more fresh options', 'better stock availability', 'faster checkout lanes',
        'improve app ordering', 'better delivery slots', 'clearer product labeling',
        'more organic range', 'better loyalty benefits',
        'more payment options', 'better promotions visibility'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        'spoiled product', 'stale items', 'poor product quality',
        'overcharged at checkout', 'incorrect pricing', 'price mismatch shelf vs bill',
        'rude cashier', 'poor customer service in store',
        'tampered packaging', 'unsafe food item', 'contaminated product',
        'allergen not declared', 'food safety issue',
        'fraudulent discount claim', 'fake promotion',
        'refund denied without reason', 'complaint ignored',
        'delivery agent misconduct', 'missing expensive item'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'late delivery repeatedly', 'frequent stockouts',
        'small quantity substitutions', 'poor replacement choices',
        'app experience poor', 'customer care unresponsive',
        'billing confusion', 'queue management poor'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'] ?? [], [
        'store opening hours', 'home delivery areas', 'minimum order for delivery',
        'return policy for groceries', 'exchange policy', 'available payment methods',
        'is item in stock', 'do you accept food stamps', 'do you have organic products',
        'how to redeem loyalty points', 'how to apply coupon code',
        'how long delivery takes', 'same day delivery eligibility',
        'do you provide cold delivery', 'bulk order discount available'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'can i schedule recurring delivery', 'can i modify order after placing',
        'what is cancellation window', 'what is replacement policy',
        'are imported products available', 'do you have gluten free products',
        'is cash on delivery available', 'how to get tax invoice'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'] ?? [], [
        'free grocery voucher click now', 'claim supermarket prize now',
        'fake loyalty points redemption link', 'phishing grocery app login',
        'pay small fee to unlock coupons', 'too good to be true grocery deal',
        'fake delivery refund link', 'share otp for order verification scam'
    ]);

    return $base;
    }
}