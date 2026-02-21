<?php
/**
 * Ecommerce Business Patterns
 *
 * @package ContactInbox\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class EcommercePatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();
    
    // Customize for E-commerce - expanded keywords
    $base['sales']['high'] = array_merge($base['sales']['high'], [
        // Discounts & Promotions
        'discount code', 'discount code', 'coupon', 'coupon code', 'promo code',
        'promotion', 'promotional offer', 'sale', 'clearance sale',
        'black friday', 'cyber monday', 'seasonal sale', 'flash sale',
        'percentage off', 'buy one get one', 'bogo', 'bundle deal',
        'first time customer', 'loyalty discount', 'bulk discount',
        
        // Shipping & Delivery
        'shipping cost', 'shipping fee', 'shipping price', 'shipping rate',
        'flat rate shipping', 'free shipping', 'free shipping over',
        'expedited shipping', 'express shipping', 'overnight shipping',
        'international shipping', 'worldwide shipping', 'shipping to',
        'delivery time', 'delivery deadline', 'shipping speed',
        'shipping address', 'billing address', 'address change',
        
        // Products & Stock
        'stock', 'in stock', 'out of stock', 'back order', 'preorder',
        'availability', 'available now', 'coming soon', 'restock',
        'low stock', 'stock alert', 'inventory', 'quantity',
        'size', 'color', 'size chart', 'color options', 'variants',
        'product variant', 'material', 'fabric', 'weight', 'dimensions',
        'specifications', 'features', 'product details', 'product info',
        
        // Wholesale & B2B
        'wholesale', 'wholesale pricing', 'bulk order', 'bulk pricing',
        'distributor', 'reseller', 'reseller program', 'affiliate',
        'minimum order', 'moq', 'corporate account', 'business account'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'want to order', 'ready to purchase', 'looking for', 'searching for',
        'product recommendation', 'suggestion', 'similar product',
        'compatibility', 'fit my needs', 'right product',
        'payment plan', 'installment', 'financing', 'pay later',
        'gift card', 'store credit', 'voucher', 'certificate'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        // Order & Tracking
        'tracking number', 'tracking code', 'shipment tracking', 'track order',
        'order status', 'order number', 'order id', 'my order',
        'delivery status', 'where is my order', 'when will arrive',
        'arrived', 'not received', 'not arrived', 'delayed delivery',
        
        // Returns & Exchanges
        'return', 'return policy', 'return process', 'how to return',
        'return shipping', 'return address', 'return label', 'return postage',
        'return refund', 'return process', 'return timeframe',
        'exchange', 'replacement', 'refund', 'money back',
        'damaged', 'defective', 'broken', 'not working',
        'reorder', 'order again', 'replenish'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'product issue', 'quality issue', 'wear and tear',
        'size issue', 'size doesnt fit', 'runs small', 'runs large',
        'color difference', 'color not as shown', 'looks different',
        'missing parts', 'missing accessories', 'incomplete',
        'assembly issue', 'setup', 'installation',
        'warranty', 'warranty claim', 'product guarantee'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'], [
        // Product & Catalog
        'product quality', 'product durability', 'product value',
        'product design', 'product style', 'aesthetic', 'appearance',
        'packaging', 'packaging design', 'unboxing experience',
        'product range', 'product selection', 'more sizes', 'more colors',
        'similar items', 'related products', 'stock availability',
        
        // Website & Shopping Experience
        'website design', 'web design', 'user interface', 'ui', 'ux',
        'mobile app', 'mobile app design', 'app experience',
        'search functionality', 'search not working', 'search results',
        'filter options', 'filtering', 'sorting', 'sort by',
        'navigation', 'menu', 'categories', 'category organization',
        'product listing', 'product page', 'product description',
        'product image', 'product photo', 'image quality', 'zoom feature',
        'video', 'product video', 'model wearing', 'size guide',
        
        // Reviews & Ratings
        'reviews', 'customer reviews', 'review system', 'ratings',
        'leave review', 'write review', 'verified purchase',
        'review verification', 'customer feedback',
        
        // Checkout & Payment
        'checkout process', 'checkout flow', 'checkout experience',
        'payment options', 'payment methods', 'payment process',
        'guest checkout', 'express checkout', 'one-click purchase',
        'secure checkout', 'payment security', 'trust badge',
        'multiple payment methods', 'paypal', 'credit card', 'debit card',
        'digital wallet', 'apple pay', 'google pay',
        
        // Customer Communication
        'order confirmation', 'shipping notification', 'delivery confirmation',
        'order updates', 'email notification', 'sms notification',
        'customer service', 'support quality', 'response time',
        'return process', 'exchange process', 'refund process'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'loading speed', 'page speed', 'site performance', 'response time',
        'account features', 'account management', 'wish list', 'favorites',
        'saved items', 'cart management', 'abandoned cart recovery',
        'personalization', 'recommendations', 'suggested products',
        'subscription option', 'auto-replenish', 'subscription service'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        // Shipping & Delivery Issues
        'lost package', 'package lost', 'missing package', 'never arrived',
        'wrong address', 'went to wrong address', 'delivery address issue',
        'partial order', 'missing item', 'incomplete order', 'item missing',
        'wrong item', 'wrong item sent', 'sent wrong product',
        'damaged in shipping', 'arrived damaged', 'broken on arrival',
        'packaging damaged', 'water damage', 'crushed package',
        
        // Product Quality Issues
        'counterfeit', 'fake product', 'knock-off', 'not authentic',
        'defective product', 'defective item', 'doesn\'t work', 'broken',
        'poor quality', 'cheap quality', 'low quality', 'substandard',
        'not as described', 'not as pictured', 'false advertising',
        'misleading description', 'misleading photos', 'bait and switch',
        'wrong size', 'wrong specifications', 'expired', 'used item',
        'new but damaged', 'factory defect', 'manufacturing defect',
        
        // Refund & Return Issues
        'no refund', 'refund delayed', 'refund not received', 'refund missing',
        'refund rejected', 'refund refused', 'denied refund',
        'return rejected', 'return denied', 'return refused',
        'restocking fee', 'return fee', 'return shipping cost',
        'difficult return process', 'complicated return', 'return hassle',
        'warranty refused', 'warranty denial', 'out of warranty',
        
        // Billing & Payment Issues
        'unexpected charge', 'unauthorized charge', 'charge without consent',
        'duplicate charge', 'overcharge', 'billing error', 'billing issue',
        'credit card charged', 'wrong amount charged',
        'refund not credited', 'money not refunded',
        'subscription chaos', 'recurring charge', 'auto-renewal',
        'cancel subscription', 'hard to cancel', 'cannot cancel subscription'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'took too long to ship', 'slow to ship', 'delayed shipping',
        'poor packaging', 'excessive packaging', 'packaging waste',
        'inconsistent quality', 'quality varies', 'hit or miss',
        'customer service poor', 'rude', 'unhelpful staff',
        'no response', 'ignored email', 'no reply', 'slow response',
        'difficult to reach', 'hard to contact',
        'price increased', 'price change', 'price misleading'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'], [
        'what is the size', 'size of', 'dimensions of', 'how big is',
        'material of', 'what material', 'what is made of',
        'color available', 'available colors', 'available sizes',
        'how much', 'how much does it cost', 'price', 'cost',
        'shipping cost', 'what is shipping', 'do you ship to',
        'how long to deliver', 'delivery time', 'when will arrive',
        'how do i return', 'return policy', 'can i return',
        'does it fit', 'will fit', 'fit my', 'right size',
        'product available', 'in stock', 'when in stock'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'how to order', 'how do i buy', 'ordering process',
        'payment methods', 'do you accept', 'payment options',
        'how to use coupon', 'how to apply code', 'discount code',
        'warranty', 'guarantee', 'product guarantee',
        'what is difference', 'which to choose', 'recommendation',
        'compare products', 'similar product', 'alternative',
        'bulk order', 'wholesale pricing', 'business account'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'], [
        'free shipping everyone', 'free product', 'free money', 'free cash',
        'limited stock', 'only 2 left', 'buy now before gone', 'act fast',
        'fake offer', 'too good to be true', 'stolen credit card',
        'counterfeit goods', 'replica', 'knock-off', 'bootleg',
        'unauthorized reseller', 'gray market'
    ]);

    return $base;
    }
}