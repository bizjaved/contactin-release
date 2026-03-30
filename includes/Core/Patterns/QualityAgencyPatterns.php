<?php
/**
 * Qualityagency Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class QualityAgencyPatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();

    $base['sales']['high'] = array_merge($base['sales']['high'], [
        // Certification Standards
        'iso certification', 'iso 9001', 'iso 14001', 'iso 27001',
        'iso 45001', 'iso 22000', 'iso 13485', 'iso 50001',
        'haccp certification', 'gmp certification', 'ce marking',

        // Audit Services
        'audit service', 'stage 1 audit', 'stage 2 audit',
        'surveillance audit', 'recertification audit',
        'internal audit', 'supplier audit', 'compliance audit',

        // Testing & Inspection
        'compliance assessment', 'quality inspection', 'factory inspection',
        'pre-shipment inspection', 'laboratory testing', 'product testing',
        'material testing', 'microbiological testing', 'chemical testing',
        'calibration service', 'equipment calibration', 'metrology service',

        // Certification Lifecycle
        'conformity assessment', 'certification quote', 'scope of certification',
        'certificate issuance', 'certificate transfer', 'multi-site certification',
        'accredited certification body', 'accreditation support'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'certification cost', 'audit timeline', 'implementation support',
        'gap assessment', 'readiness assessment', 'documentation templates',
        'training for iso', 'lead auditor training', 'awareness training',
        'industry-specific compliance', 'regulatory compliance advisory',
        'request for proposal', 'certification package'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        'audit schedule issue', 'certificate not received', 'nonconformity closure',
        'corrective action submission', 'test report delayed', 'inspection delay',
        'scope change request', 'certificate update request',
        'major nonconformity', 'minor nonconformity', 'nc closure deadline',
        'audit report missing', 'audit finding clarification',
        'sample collection issue', 'test sample rejected',
        'laboratory report mismatch', 'calibration certificate missing',
        'surveillance due date issue', 'certificate suspension warning',
        'certificate reinstatement request', 'logo usage query'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'audit rescheduling', 'auditor assignment query',
        'document review status', 'report revision request',
        'technical committee review pending', 'appeal submission status',
        'invoice for certification', 'payment receipt request',
        'certificate download issue', 'portal access issue'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'] ?? [], [
        'clearer nonconformity comments', 'more practical audit feedback',
        'faster certificate issuance', 'better turnaround for reports',
        'better technical guidance', 'improve audit planning communication',
        'improve transparency of grading', 'more consistent auditor approach',
        'better digital dashboard', 'real-time status tracking'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'clearer audit report', 'faster report turnaround', 'better auditor guidance',
        'improve audit communication', 'digital certificate access',
        'better scheduling process', 'better client onboarding',
        'clear fee structure', 'improve certificate verification portal'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        'audit findings unfair', 'inconsistent audit', 'certificate delay complaint',
        'poor auditor conduct', 'inspection quality issue', 'report quality issue',
        'biased auditor', 'conflict of interest', 'unprofessional behavior',
        'wrong nonconformity grading', 'incorrect report conclusions',
        'unexpected additional charges', 'hidden certification fees',
        'complaint ignored by certification body', 'data confidentiality breach'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'slow response from coordinator', 'unclear process steps',
        'repeated document requests', 'delayed inspection slots',
        'unclear invoice breakdown', 'communication gaps'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'] ?? [], [
        'how to get iso certified', 'certification process steps',
        'required documents for certification', 'audit preparation checklist',
        'certificate validity period', 'how to close nonconformity',
        'how long certification takes', 'what is stage 1 audit',
        'what is stage 2 audit', 'how surveillance audit works',
        'how to transfer certificate', 'how to handle major nc'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'how much does certification cost', 'who is accredited auditor',
        'can audit be remote', 'is multi site included',
        'how often calibration required', 'how to verify certificate online',
        'what are appeal timelines', 'when will certificate be issued'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'] ?? [], [
        'guaranteed iso certificate without audit',
        'buy iso certificate online', 'fake certificate provider',
        'instant certification no documents', 'pay and get certificate same day',
        'forged test report', 'counterfeit calibration certificate',
        'phishing certification portal', 'fake accreditation logo'
    ]);

    return $base;
    }
}