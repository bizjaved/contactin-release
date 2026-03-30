<?php
/**
 * Travelagency Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class TravelAgencyPatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();

    $base['sales']['high'] = array_merge($base['sales']['high'], [
        'tour package', 'holiday package', 'honeymoon package', 'group tour',
        'flight booking', 'hotel booking', 'visa assistance', 'travel itinerary',
        'custom itinerary', 'airport transfer', 'cruise package', 'umrah package',
        'travel deal', 'seasonal package', 'all-inclusive package',

        // Destination & Segment
        'domestic package', 'international package', 'family vacation package',
        'adventure tour', 'luxury travel', 'budget package', 'corporate travel',
        'mice travel', 'business conference travel', 'pilgrimage package',

        // Transport & Add-ons
        'rail booking', 'bus booking', 'ferry booking',
        'travel insurance add-on', 'forex card', 'currency exchange',
        'meet and greet', 'lounge access', 'private transfer',

        // Pricing & Promotions
        'early bird discount', 'group discount', 'last minute deal',
        'custom quote', 'package price breakdown'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'best destination for season', 'family friendly itinerary',
        'visa on arrival options', 'multi-city itinerary',
        'customized travel plan', 'trip budget planning',
        'hotel options comparison', 'flight options comparison',
        'corporate travel account', 'student travel deal'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        'ticket not issued', 'booking not confirmed', 'pnr issue', 'flight reschedule',
        'flight cancellation', 'hotel voucher missing', 'transfer not arranged',
        'visa processing delay', 'travel date change request', 'name correction on ticket',

        // Disruptions
        'missed connection', 'schedule change not informed', 'overbooked flight',
        'denied boarding', 'hotel overbooked', 'no pickup at airport',
        'tour guide absent', 'activity canceled', 'excursion not provided',

        // Refunds/Reissues
        'refund status', 'refund delayed', 'ticket reissue pending',
        'credit shell issue', 'travel credit not usable',

        // Documentation
        'wrong passport details in ticket', 'visa document rejected',
        'insurance policy not received', 'invoice not received'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'meal preference update', 'seat selection issue', 'baggage add-on issue',
        'special assistance request', 'wheelchair request status',
        'late check-in support', 'hotel room preference request',
        'trip extension request', 'partial cancellation request'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'] ?? [], [
        'better disruption communication', 'better emergency support abroad',
        'proactive flight update alerts', 'clearer package inclusions',
        'more transparent exclusions', 'better local partner quality',
        'better destination recommendations', 'more flexible itinerary options',
        'improve visa documentation guidance', 'better refund tracking experience',
        'improved customer care responsiveness'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'better itinerary planning', 'more package options', 'better communication during trip',
        'real-time travel updates', 'improve booking experience',
        'better app usability', 'clearer trip checklist',
        'more local activity options', 'better support handoff'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        'hidden travel charges', 'refund not received', 'poor hotel quality',
        'misleading package details', 'wrong booking details', 'poor tour management',
        'local support unavailable',
        'bait and switch package', 'promised inclusions missing',
        'fraudulent booking confirmation', 'ticket canceled without notice',
        'visa service negligence', 'passport mishandling',
        'unethical upselling', 'agent stopped responding',
        'unsafe transport arranged', 'security concern during trip'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'slow response from agent', 'unclear cancellation charges',
        'late itinerary delivery', 'poor coordination',
        'hotel not as expected', 'long transfer wait',
        'incomplete trip briefing', 'post-trip support poor'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'] ?? [], [
        'what is included in package', 'visa requirements for destination',
        'best time to travel', 'cancellation policy for booking',
        'refund timeline', 'baggage allowance', 'travel insurance required',
        'how to apply visa', 'processing time for visa',
        'how to reschedule ticket', 'how to cancel booking',
        'passport validity requirement', 'covid travel rules',
        'entry requirements for destination', 'transit visa requirement'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'can i customize package', 'can i add extra nights',
        'what payment methods accepted', 'is emi available for package',
        'can i get invoice with tax details', 'what is check-in time hotel',
        'is airport transfer private or shared', 'what is child policy',
        'what documents needed at departure'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'] ?? [], [
        'free international trip winner', 'pay visa fee to personal account',
        'fake airline ticket generator', 'guaranteed visa without documents',
        'too cheap holiday scam', 'phishing travel portal link',
        'share passport copy and otp now', 'fake hotel confirmation',
        'fake pnr confirmation', 'travel lottery scam'
    ]);

    return $base;
    }
}