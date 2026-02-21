<?php
/**
 * Legal Business Patterns
 *
 * @package ContactInbox\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class LegalPatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();

    $base['sales']['high'] = array_merge($base['sales']['high'], [
        // Practice Areas
        'legal consultation', 'book consultation', 'case evaluation',
        'family law', 'divorce lawyer', 'child custody lawyer',
        'criminal defense lawyer', 'civil litigation lawyer',
        'corporate law', 'contract law', 'employment law',
        'immigration lawyer', 'visa legal assistance',
        'real estate lawyer', 'property dispute lawyer',
        'intellectual property lawyer', 'trademark lawyer', 'patent lawyer',
        'tax lawyer', 'bankruptcy lawyer', 'injury lawyer',

        // Services
        'legal representation', 'court representation', 'legal notice drafting',
        'contract drafting', 'agreement review', 'document vetting',
        'notarization support', 'power of attorney drafting',
        'arbitration service', 'mediation service',

        // Commercial Terms
        'retainer fee', 'legal fee quote', 'fixed fee legal service',
        'hourly legal rate', 'contingency fee', 'success fee',
        'urgent legal advice'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'best lawyer for my case', 'need legal guidance',
        'free initial consultation', 'case strategy discussion',
        'legal opinion request', 'send engagement letter',
        'law firm onboarding', 'client intake form'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        // Case Handling
        'case status update', 'hearing date update', 'court date changed',
        'filing delayed', 'missed deadline in case', 'document not filed',
        'summons issue', 'notice not served',

        // Communication & Documentation
        'no response from lawyer', 'need urgent callback',
        'document submission issue', 'evidence submission issue',
        'wrong draft shared', 'agreement revision needed',
        'legal notice correction',

        // Billing/Retainer
        'invoice dispute legal fee', 'retainer exhausted',
        'payment receipt missing', 'unexpected legal charges'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'follow up on matter', 'status email request',
        'meeting reschedule request', 'consultation reschedule',
        'need copy of filed documents', 'need signed copy',
        'change authorized contact', 'case reference number request'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'] ?? [], [
        'clearer legal explanation', 'better case communication',
        'faster turnaround on drafts', 'improve response time',
        'more transparent fee breakdown', 'better client portal access',
        'proactive updates on milestones', 'better hearing preparation guidance'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'professional staff support', 'better scheduling process',
        'improved onboarding experience', 'clear engagement terms',
        'improve document checklist'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        'lawyer negligence', 'professional misconduct', 'conflict of interest',
        'confidentiality breach', 'privileged information leaked',
        'missed court deadline', 'poor representation in court',
        'overbilling legal fees', 'hidden legal charges',
        'misleading legal advice', 'complaint to bar council',
        'no communication during critical stage'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'slow legal response', 'draft quality poor',
        'case handling unsatisfactory', 'support staff unhelpful',
        'unclear billing entries', 'appointment delays'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'] ?? [], [
        'what documents needed for case', 'what are legal fees',
        'how long will case take', 'what are chances of success',
        'how to file legal notice', 'how to file case',
        'can you represent in court', 'what is consultation fee',
        'is online consultation available', 'what is next hearing date process'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'which practice area applies', 'do i need power of attorney',
        'can matter be settled out of court', 'is arbitration possible',
        'can i get legal opinion in writing', 'what is refund policy for retainers',
        'how often will updates be shared'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'] ?? [], [
        'guaranteed court win', '100 percent win guarantee',
        'fake legal notice payment link', 'pay now to avoid arrest scam',
        'impersonating lawyer office', 'bar registration fraud',
        'share id and otp for case filing', 'phishing legal portal link'
    ]);

    return $base;
    }
}