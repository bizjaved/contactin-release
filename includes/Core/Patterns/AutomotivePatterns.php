<?php
/**
 * Automotive Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class AutomotivePatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();

    $base['sales']['high'] = array_merge($base['sales']['high'], [
        // Vehicle Purchase
        'new car price', 'used car price', 'test drive booking',
        'vehicle booking', 'car availability', 'variant availability',
        'on-road price', 'ex-showroom price',

        // Financing/Exchange
        'car loan offer', 'auto finance', 'emi options',
        'down payment for car', 'exchange offer', 'trade-in value',
        'resale valuation',

        // Service Packages
        'annual maintenance contract', 'service package',
        'extended warranty', 'roadside assistance plan',
        'insurance renewal for car',

        // Parts & Accessories
        'genuine spare parts', 'accessories package',
        'alloy wheels', 'seat covers', 'infotainment upgrade'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'best car in budget', 'compare car variants',
        'delivery timeline for vehicle', 'booking amount details',
        'corporate fleet offer', 'festival car discounts',
        'service cost estimate', 'body shop estimate'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        // Service/Repair Issues
        'service appointment issue', 'car service delay',
        'vehicle not fixed', 'repeat repair issue',
        'engine warning light', 'battery issue', 'brake issue',
        'ac not working car', 'strange noise from engine',

        // Delivery/Booking Issues
        'vehicle delivery delayed', 'wrong variant delivered',
        'registration delay', 'number plate delay',

        // Warranty/Insurance Claims
        'warranty claim denied', 'insurance claim repair delay',
        'accident repair delay', 'parts not available',

        // Billing
        'service bill dispute', 'unexpected repair charges',
        'overcharged for parts', 'labor charges too high'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'reschedule service appointment', 'pickup and drop request',
        'service history request', 'invoice copy request',
        'software update request', 'wheel alignment request',
        'wheel balancing request', 'car wash quality issue'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'] ?? [], [
        'better workshop turnaround', 'improve diagnostic accuracy',
        'clearer service explanation', 'transparent estimate before repair',
        'better test drive experience', 'better sales follow-up',
        'better delivery handover process', 'improve spare parts availability',
        'improve customer lounge facilities', 'better after-sales support'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'service advisor communication', 'faster quote approvals',
        'better booking system', 'more service slots available',
        'clear warranty terms', 'better app reminders for service'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        'defective vehicle delivered', 'odometer tampering',
        'fake spare parts used', 'poor workmanship in workshop',
        'mis-selling of add-ons', 'forced insurance package',
        'hidden dealership charges', 'warranty voided unfairly',
        'unsafe repair quality', 'vehicle damaged during service'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'service delayed repeatedly', 'advisor not responding',
        'poor customer handling', 'unclear billing items',
        'booking promises not honored', 'spare delivery too late'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'] ?? [], [
        'what is on-road price', 'what is waiting period',
        'how to book test drive', 'what are emi options',
        'how long vehicle delivery takes', 'what is warranty period',
        'what is service interval', 'how to claim warranty',
        'what insurance options available', 'is roadside assistance included'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'which variant is best', 'which fuel type is better',
        'can i exchange old car', 'can i transfer booking',
        'what accessories are included', 'how to check service history',
        'is pickup and drop available', 'what is cancellation policy for booking'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'] ?? [], [
        'guaranteed car lottery winner', 'pay token now for instant allotment scam',
        'fake dealership payment link', 'phishing service payment link',
        'share otp for vehicle booking confirmation',
        'fake insurance renewal call for car', 'discounted car scam offer'
    ]);

    return $base;
    }
}