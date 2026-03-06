<?php
/**
 * Hospitality Business Patterns
 *
 * @package ContactInbox\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class HospitalityPatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();
    
    // Customize for Hospitality - expanded keywords
    $base['sales']['high'] = array_merge($base['sales']['high'], [
        // Bookings & Reservations
        'reservation', 'reservations', 'book', 'booking', 'reserve',
        'availability', 'available', 'dates available', 'open dates',
        'check-in', 'check-in date', 'check-in time', 'arrival',
        'check-out', 'check-out date', 'check-out time', 'departure',
        'length of stay', 'stay duration', 'number of nights',
        'room availability', 'room type', 'room category', 'suite type',
        'single room', 'double room', 'deluxe', 'suite', 'penthouse',
        'bed type', 'king bed', 'queen bed', 'twin bed',
        'handicap room', 'accessible room', 'pet-friendly room',
        'smoking room', 'non-smoking room', 'room with view',
        
        // Pricing & Rates
        'rate', 'room rate', 'nightly rate', 'daily rate', 'night rate',
        'price', 'room price', 'cost per night', 'pricing',
        'special rate', 'group rate', 'corporate rate', 'negotiated rate',
        'senior discount', 'military discount', 'AAA discount',
        'early bird rate', 'last minute rate', 'early check-in rate',
        'advance purchase rate', 'non-refundable rate', 'best rate guarantee',
        'price match', 'lowest price promise',
        
        // Packages & Deals
        'package', 'package deal', 'package rate', 'bundled package',
        'honeymoon package', 'romantic getaway', 'couples package',
        'family package', 'spring break package', 'winter escape',
        'all-inclusive', 'meal plan', 'resort credit', 'resort voucher',
        'breakfast included', 'meal included', 'airport transfer',
        'promotion', 'special offer', 'special promotion', 'flash deal',
        'loyalty program', 'rewards program', 'member benefits',
        'points', 'loyalty points', 'redeem points', 'elite status',
        
        // Groups & Events
        'group', 'group booking', 'group rate', 'group discount',
        'group size', 'group dinner', 'group function',
        'event', 'events', 'conference', 'meeting', 'wedding',
        'banquet', 'gala', 'celebration', 'party',
        'wedding package', 'conference package', 'retreat',
        
        // Policies
        'cancellation policy', 'cancellation deadline', 'cancellation fee',
        'refund policy', 'refundable', 'non-refundable',
        'modification policy', 'change policy', 'rebooking',
        'minimum stay', 'minimum night', 'booking requirements'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'interested in', 'looking for', 'considering', 'thinking about',
        'when available', 'available for', 'can you accommodate',
        'meet our budget', 'within budget', 'affordable',
        'nearby attractions', 'location', 'walking distance',
        'transportation', 'directions', 'how to get there'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        // Reservation Issues
        'reservation issue', 'booking issue', 'booking problem',
        'reservation problem', 'reservation error', 'booking error',
        'double booking', 'overbooking', 'lost reservation',
        'reservation confirmation', 'confirmation number',
        'can\'t find reservation', 'cannot locate booking',
        'reservation lost', 'booking deleted',
        
        // Changes & Cancellations
        'cancel', 'cancellation', 'cancel booking', 'cancel reservation',
        'cancel trip', 'cancel stay', 'need to cancel', 'want to cancel',
        'modify', 'modification', 'change reservation',
        'change dates', 'alter dates', 'reschedule', 'reschedule booking',
        'extend stay', 'shorten stay', 'change room type',
        'upgrade room', 'downgrade room', 'room change',
        'early checkout', 'late checkout', 'late arrival',
        
        // Check-in & Check-out
        'check-in', 'checking in', 'check-in process', 'check-in time',
        'early check-in', 'late check-in', 'no-show', 'failed to arrive',
        'check-out', 'checkout time', 'late checkout',
        'key card', 'room key', 'room access', 'cannot access',
        'locked out', 'lost key', 'broken lock', 'access denied',
        'early arrival', 'late arrival', 'arrival time',
        
        // Room Issues
        'room issue', 'room problem', 'wrong room', 'room change needed',
        'room not ready', 'room dirty', 'not cleaned', 'unclean',
        'maintenance issue', 'maintenance needed', 'repair needed',
        'broken', 'not working', 'doesn\'t work', 'out of order',
        'air conditioning', 'heating', 'hot water', 'water pressure',
        'plumbing issue', 'electrical issue', 'light not working',
        'tv not working', 'wifi not working', 'wifi issue',
        'noise complaint', 'too noisy', 'noise from neighbors',
        'room temperature', 'too hot', 'too cold', 'uncomfortable',
        'bed uncomfortable', 'mattress issue', 'pillow issue',
        'bathroom issue', 'shower issue', 'toilet issue',
        
        // Amenities & Services
        'amenity', 'amenities', 'facility', 'facilities', 'facility issue',
        'restaurant', 'dining', 'bar', 'lounge', 'pool', 'gym',
        'spa', 'sauna', 'hot tub', 'fitness center', 'business center',
        'parking', 'parking issue', 'parking fee', 'valet parking',
        'wifi', 'internet', 'internet not working',
        'room service', 'service issue', 'slow service',
        'laundry', 'dry cleaning', 'ironing', 'housekeeping',
        'concierge', 'customer service', 'front desk',
        'luggage', 'luggage storage', 'luggage lost',
        
        // Billing & Payment
        'bill', 'final bill', 'invoice', 'charge', 'unexpected charge',
        'payment', 'payment issue', 'billing error', 'billing dispute',
        'incidental charge', 'resort fee', 'service charge', 'gratuity',
        'deposit', 'damage deposit', 'refund deposit',
        'credit card declined', 'payment declined', 'payment failed'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'question about staying', 'what to bring', 'what to expect',
        'location information', 'area information', 'nearby activities',
        'restaurant recommendation', 'dining option', 'what to eat',
        'transportation option', 'airport shuttle', 'how to get around',
        'hours of operation', 'facility hours', 'checkout procedures',
        'special request', 'special arrangement', 'accommodation need',
        'pet policy', 'children policy', 'family friendly'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'], [
        // Room & Cleanliness
        'room quality', 'room condition', 'room cleanliness', 'clean', 'cleanliness',
        'housekeeping', 'housekeeping quality', 'clean sheets', 'fresh linens',
        'maintenance quality', 'well-maintained', 'attention to detail',
        'decor', 'room design', 'room furnishings', 'furniture quality',
        'modern', 'updated', 'recently renovated', 'renovation needed',
        'room comfort', 'comfortable bed', 'comfortable stay',
        'noise level', 'quiet', 'peaceful', 'sleepiness', 'noise insulation',
        'room size', 'spacious', 'cramped', 'layout', 'layout issue',
        
        // Staff & Service
        'staff', 'staff quality', 'staff friendliness', 'staff courtesy',
        'staff professionalism', 'helpful staff', 'courteous', 'welcoming',
        'rude staff', 'unfriendly', 'impolite', 'attitude problem',
        'service quality', 'service level', 'excellent service', 'poor service',
        'responsive', 'attentive', 'quick service', 'slow service',
        'check-in experience', 'checkout experience', 'customer service',
        'front desk staff', 'housekeeping staff', 'maintenance staff',
        'service recovery', 'complaint handling', 'problem resolution',
        
        // Amenities & Facilities
        'amenity quality', 'amenity condition', 'facility quality', 'facility cleanliness',
        'pool quality', 'pool condition', 'pool temperature', 'pool maintenance',
        'gym', 'gym facility', 'exercise equipment', 'fitness facility',
        'spa quality', 'spa service', 'massage quality', 'sauna', 'hot tub',
        'restaurant quality', 'restaurant service', 'food quality',
        'dining experience', 'breakfast quality', 'breakfast variety',
        'bar service', 'beverage quality', 'drink quality',
        'wifi quality', 'internet speed', 'connectivity', 'online access',
        'parking quality', 'parking convenience', 'parking safety',
        'front desk service', 'concierge service', 'room service',
        
        // Location & Access
        'location', 'location quality', 'convenient location', 'location convenience',
        'proximity', 'near attractions', 'near downtown', 'accessibility',
        'public transportation', 'walking distance', 'walkable',
        'neighborhood', 'area safety', 'safe area', 'surrounding area',
        'nearby restaurants', 'nearby shopping', 'nearby activities',
        
        // Food & Dining
        'breakfast quality', 'breakfast variety', 'breakfast option',
        'breakfast buffet', 'included breakfast', 'dining quality',
        'food quality', 'meal quality', 'restaurant' , 'cuisine quality',
        'portion size', 'food freshness', 'food taste',
        'dining service', 'dining options', 'variety of food',
        'special diet', 'allergen information', 'dietary accommodation',
        
        // Value & Overall
        'value', 'value for money', 'worth the cost', 'price value',
        'good value', 'poor value', 'overpriced', 'affordable',
        'overall experience', 'stay experience', 'guest experience',
        'would return', 'recommend', 'recommend to friend',
        'uniqueness', 'memorable', 'special touch', 'personalization'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'checkout process', 'checkin process', 'booking process',
        'amenity availability', 'facility availability', 'service hours',
        'room view', 'view quality', 'scenic view', 'city view',
        'natural light', 'daylight', 'window quality',
        'information provided', 'helpful information', 'clear direction',
        'welcome amenity', 'turndown service', 'chocolate on pillow',
        'personalization', 'special request', 'special service',
        'return visit', 'loyalty', 'repeat business'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        // Room Quality Issues
        'dirty room', 'unclean room', 'room filthy', 'disgusting room',
        'hair in bed', 'stains', 'debris', 'trash left in room',
        'smell', 'bad smell', 'odor', 'smelly room', 'mold smell',
        'mold', 'mildew', 'water damage', 'water stains', 'damp room',
        'pest', 'pests', 'bed bugs', 'cockroach', 'insect',
        'unhygienic', 'unsanitary', 'hygiene issue', 'health hazard',
        
        // Maintenance & Damage
        'maintenance issue', 'broken', 'doesn\'t work', 'not working',
        'air conditioning broken', 'no heat', 'no hot water',
        'tv broken', 'remote broken', 'phone broken',
        'broken furniture', 'broken lamp', 'broken chair', 'broken door',
        'lock broken', 'window broken', 'window damaged',
        'wall damage', 'floor damage', 'ceiling issue', 'ceiling leak',
        'water leak', 'plumbing leak', 'sewage issue', 'smell issue',
        'electrical hazard', 'fire hazard', 'safety issue',
        
        // Service Failures
        'rude staff', 'unprofessional staff', 'unresponsive staff',
        'no customer service', 'ignored complaint', 'complaint ignored',
        'unwelcoming', 'unwelcome', 'hostile', 'aggressive',
        'discrimination', 'theft', 'missing items', 'stolen',
        'no help', 'no assistance', 'unable to help',
        'long wait', 'slow service', 'wait too long', 'room service slow',
        'wrong order', 'incorrect order', 'missing item from order',
        'cold food', 'bad food', 'spoiled food', 'food poisoning',
        'no answer', 'cannot reach', 'no response', 'unresponsive',
        
        // Policy & Billing Issues
        'overbooking', 'overbooked', 'no room available',
        'wrong room', 'different room', 'inferior room',
        'charge dispute', 'unexpected charge', 'unauthorized charge',
        'billing error', 'incorrect bill', 'overcharge', 'overbilled',
        'hidden fee', 'surprise fee', 'resort fee', 'mandatory fee',
        'cancellation issue', 'refund denied', 'no refund', 'refused refund',
        'deposit not refunded', 'damage charge', 'questionable charge',
        'credit card charged', 'double charge', 'fraudulent charge',
        
        // Misrepresentation
        'not as advertised', 'false advertising', 'misleading advertisement',
        'misleading description', 'photo not accurate', 'pictures deceiving',
        'different than picture', 'downgrade forced',
        'promised amenity missing', 'missing service', 'unavailable amenity',
        'room not as described', 'facility not as described',
        'false promise', 'promised not fulfilled', 'broken promise',
        
        // Safety & Security
        'safety concern', 'safety issue', 'unsafe', 'security issue',
        'security breach', 'theft', 'robbery', 'mugging', 'assault',
        'unsafe neighborhood', 'dangerous area', 'crime in area',
        'window lock broken', 'door lock broken', 'no secure lock',
        'no safety precaution', 'no security measure',
        
        // Other Complaints
        'noise', 'excessive noise', 'noisy neighbors', 'noise disturbance',
        'party noise', 'music noise', 'construction noise', 'traffic noise',
        'no quiet hour', 'inconsiderate guests', 'no enforcement',
        'overcrowded', 'too many guest', 'too busy',
        'uncomfortable stay', 'bad experience', 'worst stay',
        'betrayed', 'disappointed', 'frustrated with',
        'never again', 'never return', 'tell everyone'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'inconvenient location', 'location too far', 'not close to',
        'poor parking', 'parking expensive', 'no parking',
        'breakfast poor', 'breakfast limited', 'breakfast terrible',
        'wifi slow', 'internet slow', 'connection slow',
        'limited amenity', 'no amenity', 'amenity unavailable',
        'outdated', 'worn out', 'needs renovation', 'old property',
        'small room', 'tiny room', 'cramped', 'not spacious',
        'no view', 'bad view', 'view blocked', 'unwanted view',
        'rip-off', 'ripoff', 'not worth', 'too expensive',
        'waste of money', 'poor value', 'disappointing'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'], [
        'can i make reservation', 'how to book', 'how do i reserve',
        'are you available', 'do you have availability', 'are you open',
        'what dates available', 'when available', 'what room type',
        'what is rate', 'how much cost', 'what is price',
        'do you have room', 'can you accommodate', 'can you fit',
        'what included', 'what amenities', 'what features',
        'do you have parking', 'free wifi', 'do you allow pet',
        'what is cancellation policy', 'can i cancel', 'refundable',
        'what is check-in time', 'what is check-out time',
        'how far from airport', 'how get to', 'transportation',
        'do you allow children', 'family friendly', 'kids welcome',
        'disabled access', 'wheelchair accessible', 'accessible room',
        'near what', 'what nearby', 'walking distance to'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'room quality', 'room condition', 'room clean', 'how is room',
        'what to expect', 'what is breakfast', 'breakfast included',
        'what time breakfast', 'dining option', 'restaurant on site',
        'what activities', 'what to do', 'what attractions nearby',
        'what entertainment', 'live music', 'evening program',
        'group rate available', 'corporate rate', 'long stay discount',
        'military discount', 'senior discount', 'any discount',
        'deposit required', 'payment method', 'when pay',
        'last minute availability', 'happy hour', 'special event'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'], [
        'free stay', 'free vacation', 'free hotel stay', 'won vacation',
        'free night certificate', 'timeshare', 'vacation ownership',
        'limited time offer', 'exclusive offer', 'urgent booking',
        'act now', 'book now', 'limited availability',
        'fake booking', 'bogus hotel', 'fake resort', 'non-existent',
        'stolen credit card', 'fraud', 'scam hotel',
        'not real business', 'shell company', 'phishing',
        'misleading picture', 'fake picture', 'stock photo',
        'impossible price', 'too good to be true'
    ]);

    return $base;
    }
}