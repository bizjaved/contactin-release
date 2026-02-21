<?php
/**
 * Healthcare Business Patterns
 *
 * @package ContactInbox\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class HealthcarePatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();
    
    // Customize for Healthcare - expanded keywords
    $base['sales']['high'] = array_merge($base['sales']['high'], [
        // Insurance & Coverage
        'insurance', 'insurance coverage', 'insurance plan', 'health insurance',
        'coverage verification', 'coverage check', 'in network', 'out of network',
        'copay', 'copayment', 'coinsurance', 'deductible', 'out of pocket',
        'pre-authorization', 'prior approval', 'approval required',
        'insurance claim', 'claim submission', 'claim status',
        
        // Billing & Payment
        'billing', 'billing question', 'billing inquiry', 'billing issue',
        'payment plan', 'payment options', 'monthly payment', 'installment plan',
        'financial assistance', 'discount program', 'sliding scale',
        'patient payment', 'account balance', 'payment due',
        'self-pay', 'cash pay', 'upfront cost', 'cost estimate',
        
        // Appointments & Scheduling
        'appointment', 'schedule appointment', 'book appointment', 'appointment availability',
        'appointment time', 'appointment date', 'next available',
        'scheduling', 'appointment booking', 'calendar', 'availability',
        'urgent appointment', 'same day appointment', 'next day appointment',
        'telehealth appointment', 'video visit', 'virtual appointment',
        'in-person appointment', 'office visit',
        
        // Patients & Registration
        'new patient', 'new patient appointment', 'new patient registration',
        'patient intake', 'patient forms', 'health history',
        'established patient', 'returning patient',
        'patient portal', 'online registration', 'echeck-in',
        
        // Medical Services & Specialties
        'specialty', 'specialist', 'sub-specialty', 'specialty care',
        'primary care', 'family medicine', 'internal medicine',
        'urgent care', 'emergency care', 'surgical consultation',
        'diagnostic imaging', 'lab work', 'blood test',
        'immunization', 'vaccination', 'preventive care', 'wellness',
        'rehabilitation', 'physical therapy', 'occupational therapy',
        
        // Prescriptions & Medications
        'prescription', 'prescription refill', 'medication refill',
        'rx', 'medication', 'drug', 'pharmaceutical',
        'over-the-counter', 'otc', 'generic', 'brand name',
        'pharmacy', 'pharmacy transfer', 'mail order pharmacy',
        'prior authorization', 'quantity limit', 'step therapy'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'interested in', 'considering', 'thinking about', 'looking for',
        'provider search', 'find doctor', 'find specialist',
        'provider network', 'hospital affiliation', 'hospital privileges',
        'patient reviews', 'doctor rating', 'provider rating'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        // Appointment Issues
        'missed appointment', 'no-show', 'cancellation', 'cancel appointment',
        'reschedule', 'reschedule appointment', 'change appointment',
        'appointment reminder', 'appointment confirmation',
        'wait time', 'long wait', 'excessive wait', 'appointment late',
        'doctor running late', 'delayed start',
        
        // Medical Records & Results
        'medical records', 'patient records', 'health records', 'records request',
        'records transfer', 'records release', 'records access',
        'test result', 'lab result', 'imaging result', 'test pending',
        'pending results', 'when will results', 'where are results',
        'procedure result', 'pathology report', 'biopsy report',
        'discharge summary', 'progress note', 'clinical note',
        
        // Prescriptions & Medications
        'prescription refill', 'refill denied', 'refill rejected',
        'medication refill', 'medication availability', 'medication out of stock',
        'side effect', 'medication side effect', 'adverse effect',
        'allergic reaction', 'adverse reaction', 'drug interaction',
        'medication concern', 'medication question', 'drug allergy',
        'new medication', 'medication change', 'medication adjustment',
        
        // Urgent Issues
        'emergency', 'urgent', 'after hours', 'urgent care needed',
        'nurse line', 'on-call doctor', 'on-call physician',
        'emergency room', 'er visit', 'hospital admission',
        'critical condition', 'serious condition', 'worsening symptoms',
        
        // Procedure & Treatment
        'procedure', 'procedure scheduled', 'procedure date', 'pre-op instruction',
        'pre-operative', 'post-operative', 'post-op care', 'post-op recovery',
        'surgery', 'surgical procedure', 'surgical recovery',
        'treatment plan', 'treatment option', 'treatment decision',
        'therapy', 'physical therapy', 'occupational therapy',
        
        // Technical & Access
        'patient portal', 'portal access', 'cannot login', 'password reset',
        'medical record access', 'cannot view results', 'online access issue',
        'prescription request', 'refill request', 'appointment request'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'medication question', 'medication instruction', 'dosage question',
        'symptom concern', 'symptom worsening', 'new symptom',
        'follow-up needed', 'follow-up appointment', 'need to see doctor',
        'referral needed', 'referral pending', 'waiting for referral',
        'authorization pending', 'approval pending', 'pre-auth pending',
        'bill question', 'claim status', 'insurance verification'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'], [
        // Staff & Bedside Manner
        'staff courtesy', 'staff professionalism', 'rude staff', 'friendly staff',
        'nurse care', 'nursing staff', 'clinical staff',
        'doctor communication', 'physician communication', 'communication skills',
        'bedside manner', 'patient care', 'compassion', 'empathy',
        'listened', 'attentive', 'thorough', 'detailed',
        'explained well', 'answered questions', 'took time',
        
        // Facility & Environment
        'office environment', 'cleanliness', 'clean', 'sanitation',
        'facility', 'clinic', 'office', 'facility quality',
        'waiting room', 'examination room', 'comfort level',
        'modern equipment', 'outdated', 'facility upgrade',
        'accessibility', 'wheelchair accessible', 'parking',
        
        // Wait Times & Efficiency
        'wait time', 'appointment punctuality', 'on time',
        'efficient', 'efficient service', 'clinic efficiency',
        'scheduling efficiency', 'flow', 'process improvement',
        'check-in process', 'discharge process',
        
        // Follow-up & Education
        'follow-up care', 'follow-up communication', 'post-visit follow-up',
        'patient education', 'health education', 'disease education',
        'preventive education', 'wellness program', 'health coaching',
        'patient materials', 'information provided', 'education quality',
        
        // Continuity & Care Coordination
        'continuity of care', 'care coordination', 'specialist coordination',
        'referral coordination', 'appointment coordination',
        'care team', 'team communication', 'multidisciplinary care',
        'care transitions', 'care management',
        
        // Technology & Communication
        'patient portal quality', 'online communication', 'message response',
        'telemedicine quality', 'video visit quality', 'virtual care',
        'appointment reminder', 'appointment notification',
        'billing transparency', 'clear billing'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'providers available', 'appointment availability', 'hours of operation',
        'evening hours', 'weekend hours', 'extended hours',
        'location convenience', 'location accessibility',
        'wait time acceptable', 'shorter wait',
        'billing process', 'insurance handling', 'payment options',
        'patient experience', 'overall experience'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        // Medical Errors & Misdiagnosis
        'medical error', 'wrong diagnosis', 'misdiagnosis', 'delayed diagnosis',
        'wrong treatment', 'incorrect treatment', 'inappropriate care',
        'inadequate care', 'neglect', 'medical negligence',
        'medication error', 'wrong medication', 'wrong dose',
        'drug interaction missed', 'allergy not noted', 'contraindication missed',
        'surgical complication', 'surgical error', 'wrong site surgery',
        
        // Patient Safety & Harm
        'patient safety', 'safety concern', 'safety issue',
        'injury', 'harm', 'injured', 'worsened condition',
        'permanent damage', 'disability', 'long-term complication',
        'infection', 'hospital-acquired infection', 'nosocomial infection',
        'bed sore', 'pressure ulcer', 'fall in hospital',
        
        // Privacy & HIPAA
        'hipaa', 'hipaa violation', 'privacy breach', 'privacy violation',
        'unauthorized release', 'record shared', 'confidentiality breach',
        'patient privacy', 'privacy concern', 'information leaked',
        'data breach', 'data release', 'medical record breach',
        
        // Billing & Insurance Issues
        'billing error', 'incorrect billing', 'billing fraud', 'overbilling',
        'insurance claim denied', 'claim rejected', 'denied claim',
        'claim delay', 'claim not processed', 'unpaid claim',
        'insurance issue', 'coverage denied', 'coverage terminated',
        'collection agency', 'unexpected bill', 'surprise bill',
        'balance billing', 'out of network charge',
        
        // Care Quality & Communication
        'poor care', 'inadequate', 'rushed', 'not thorough', 'dismissed concerns',
        'not listened', 'condescending', 'rude doctor', 'rude staff',
        'unprofessional', 'unprofessional behavior', 'inappropriate behavior',
        'harassment', 'discrimination', 'unequal treatment',
        
        // Access & Availability
        'difficult to reach', 'cannot get appointment', 'no availability',
        'waiting months', 'long wait for appointment', 'appointment canceled',
        'cancellation without notice', 'without explanation',
        'after hours emergency', 'no emergency line', 'cannot reach on-call'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'staff attitude', 'staff demeanor', 'cold reception',
        'wait time excessive', 'extremely long wait',
        'communication poor', 'not explained well', 'unclear instructions',
        'follow-up lacking', 'no follow-up', 'left confused',
        'incomplete care', 'gaps in care', 'care interrupted',
        'medication not working', 'treatment ineffective'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'], [
        'how do i schedule', 'how to book appointment', 'how to make appointment',
        'what is your availability', 'when available', 'next appointment',
        'what specialists available', 'do you have', 'do you treat',
        'do you accept insurance', 'what insurance accepted', 'in network',
        'what is copay', 'what is deductible', 'what cost',
        'how much does cost', 'cost of visit', 'cost of procedure',
        'what prescribe', 'can you prescribe', 'refill prescription',
        'how to get medical records', 'how to transfer records',
        'telehealth available', 'video visit available'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'what test needed', 'what medication', 'what treatment',
        'how long treatment', 'recovery time', 'healing time',
        'side effect possible', 'is it safe', 'risks involved',
        'alternative treatment', 'alternative option',
        'second opinion', 'specialist referral',
        'pre-authorization required', 'insurance approval needed',
        'office hours', 'hours of operation', 'location',
        'new patient welcome', 'accepting new patient'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'], [
        'guaranteed cure', 'miracle cure', 'cure all', 'wonder drug',
        'cure cancer', 'cure diabetes', 'cure heart disease', 'cure stroke',
        'homeopathic cure', 'alternative cure', 'secret cure',
        'free medication', 'free prescription', 'free consultation',
        'no prescription needed', 'painless procedure', 'no side effect',
        'pharmaceutical scam', 'fake medication', 'counterfeit drug',
        'stolen prescription', 'doctor imposter', 'nurse imposter',
        'medicaid fraud', 'medicare fraud', 'insurance fraud'
    ]);

    return $base;
    }
}