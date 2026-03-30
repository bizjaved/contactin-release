<?php
/**
 * Logistics Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class LogisticsPatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();

    $base['sales']['high'] = array_merge($base['sales']['high'], [
        // Core Shipping Services
        'courier service', 'express delivery', 'same day shipping',
        'next day delivery', 'international shipping', 'domestic shipping',
        'freight service', 'air freight', 'sea freight', 'road freight',
        'last mile delivery', 'cold chain logistics',

        // B2B / Contract Logistics
        'warehouse service', 'fulfillment service', '3pl service', '4pl service',
        'inventory management', 'distribution service', 'line haul service',
        'contract logistics', 'ecommerce fulfillment',

        // Pricing & Commercial
        'shipping quote', 'freight quote', 'rate card', 'bulk shipping rates',
        'corporate shipping account', 'pickup schedule', 'cod service',
        'cash on delivery logistics', 'insurance for shipment'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'compare courier rates', 'best courier for business',
        'volume discount shipping', 'monthly shipment plan',
        'api integration for shipping', 'tracking integration',
        'return logistics setup', 'reverse logistics support'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        // Tracking/Delivery Issues
        'shipment delayed', 'parcel delayed', 'tracking not updating',
        'shipment stuck in transit', 'out for delivery but not delivered',
        'delivery attempt failed', 'proof of delivery missing',
        'wrong delivery address issue', 'parcel delivered to wrong address',

        // Damage/Loss
        'parcel lost', 'shipment lost', 'damaged shipment',
        'package tampered', 'missing contents',

        // Pickup/Operations
        'pickup not done', 'pickup delayed', 'pickup canceled',
        'awb not generated', 'label generation failed',
        'manifest issue', 'customs clearance delay',

        // Billing/Claims
        'freight overcharged', 'billing mismatch shipment',
        'claim for lost shipment', 'claim for damaged shipment',
        'cod remittance delayed'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'reschedule pickup', 'update delivery instructions',
        'address correction request', 'change receiver details',
        'invoice copy request', 'awb copy request',
        'need pod copy', 'weight dispute request',
        'shipment hold request', 'return to origin request'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'] ?? [], [
        'faster transit times', 'better delivery communication',
        'more accurate tracking events', 'better exception handling',
        'proactive delay alerts', 'better route optimization',
        'improve pickup punctuality', 'clearer surcharge transparency',
        'better customer support escalation', 'stronger parcel handling quality'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'better dashboard usability', 'improved shipment analytics',
        'more integration options', 'better invoice clarity',
        'improve return workflow', 'better driver professionalism'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        'frequent shipment loss', 'repeated damaged parcels',
        'false delivery attempt', 'forged proof of delivery',
        'delivery agent misconduct', 'theft during transit',
        'hidden logistics charges', 'wrong surcharges applied',
        'claim rejected unfairly', 'no compensation for loss',
        'no accountability from courier', 'service level agreement breach'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'late pickup repeatedly', 'slow customer support response',
        'unclear tracking status', 'poor communication on delays',
        'invoice errors often', 'delivery quality inconsistent'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'] ?? [], [
        'how to track shipment', 'how to book pickup',
        'what are shipping rates', 'what is transit time',
        'how to file lost shipment claim', 'how to file damage claim',
        'what is weight and size limit', 'how customs process works',
        'do you provide cod', 'how cod remittance works'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'which service is fastest', 'which service is cheapest',
        'is weekend delivery available', 'is insurance mandatory',
        'how to integrate shipping api', 'how to print shipping labels',
        'what is return to origin policy', 'how to get pod copy'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'] ?? [], [
        'your parcel is stuck pay now', 'fake customs duty payment link',
        'delivery failed click to reschedule scam',
        'phishing tracking link', 'share otp for delivery release',
        'fake courier call for address verification',
        'parcel lottery scam', 'credential theft through courier portal'
    ]);

    return $base;
    }
}