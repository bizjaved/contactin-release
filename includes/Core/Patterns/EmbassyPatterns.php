<?php
/**
 * Embassy Business Patterns
 *
 * @package ContactInbox\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class EmbassyPatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();

    $base['sales']['high'] = array_merge($base['sales']['high'] ?? [], [
        // Visa & Consular Services
        'visa application', 'visa category', 'tourist visa', 'visit visa',
        'student visa', 'work visa', 'business visa', 'family visa',
        'transit visa', 'long stay visa', 'residence visa',

        // Passport & Travel Documents
        'passport renewal', 'new passport', 'lost passport replacement',
        'emergency travel document', 'laissez-passer', 'temporary passport',

        // Attestation / Legalization
        'document attestation', 'document legalization', 'apostille support',
        'notarial service', 'power of attorney attestation',
        'birth certificate attestation', 'marriage certificate attestation',
        'degree attestation', 'police clearance attestation',

        // Citizenship / Nationality
        'citizenship application', 'nationality service', 'consular registration',
        'birth registration abroad', 'renunciation service'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'visa appointment', 'consular appointment', 'passport renewal appointment',
        'document attestation service', 'legalization service', 'notarial service',
        'consular camp', 'mobile consular service', 'priority appointment',
        'premium lounge service', 'courier return service'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        'visa application delayed', 'passport not received', 'appointment unavailable',
        'consular service issue', 'emergency travel document', 'lost passport abroad',
        'document attestation delayed', 'appointment booking issue',
        'application stuck', 'status not updated', 'processing hold',
        'document rejected', 'biometric issue', 'photo rejected',
        'payment portal failed', 'reference number not found',
        'passport collection issue', 'courier tracking issue',
        'wrong details in passport', 'name correction request',
        'urgent travel due to emergency', 'consular emergency line'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'appointment reschedule', 'appointment cancellation',
        'application form correction', 'missing document follow up',
        'fee payment query', 'receipt not generated',
        'document submission checklist', 'biometric appointment guidance',
        'pickup authorization letter', 'tracking number request'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'] ?? [], [
        'faster visa processing', 'clearer eligibility guidance',
        'better appointment availability', 'better online booking system',
        'transparent processing timeline', 'improve queue discipline',
        'friendlier counter service', 'better multilingual support',
        'clearer document checklist', 'better emergency response support',
        'real-time status notifications', 'better call center response'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'consular service feedback', 'appointment system improvement',
        'queue management', 'faster processing', 'clearer documentation guidance',
        'improved website navigation', 'better holiday notices',
        'clear fee table', 'better waiting area facilities'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        'consular staff rude', 'unreasonable delay', 'no response from embassy',
        'visa refusal without clarity', 'poor service at counter', 'mishandled documents',
        'lost submitted documents', 'wrongful rejection',
        'discriminatory treatment', 'harassment at counter',
        'appointment system unfair', 'bribery demand', 'corruption complaint',
        'privacy breach of applicant data', 'passport error caused travel loss'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'no callback from embassy', 'helpline unreachable', 'email unanswered',
        'unclear instructions', 'conflicting information',
        'long wait in queue', 'repeated visit required',
        'counter closed unexpectedly', 'poor communication'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'] ?? [], [
        'visa requirements', 'required documents for visa', 'processing time for visa',
        'passport renewal requirements', 'attestation requirements',
        'consular fees', 'embassy working hours', 'holiday closure',
        'how to book visa appointment', 'how to track visa status',
        'how to renew passport abroad', 'how to attest documents',
        'how long attestation takes', 'how to apply emergency passport'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'can i submit by courier', 'is walk-in allowed', 'is appointment mandatory',
        'what payment methods accepted', 'can fee be refunded',
        'do i need translated documents', 'do minors need both parents',
        'where to collect passport', 'how to authorize representative pickup'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'] ?? [], [
        'guaranteed embassy visa', 'instant visa approval guaranteed',
        'pay bribe for visa', 'priority visa through contact',
        'fake embassy appointment link', 'embassy payment scam',
        'share passport details now', 'share otp for visa processing',
        'phishing consular portal', 'fake high commission call'
    ]);

    return $base;
    }
}