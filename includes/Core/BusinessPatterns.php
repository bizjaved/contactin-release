<?php
/**
 * Business-Specific Intent Classification Patterns — Registry
 *
 * Acts as a registry that maps business-type keys to their pattern classes.
 * Each business type is implemented in its own class under
 * ContactInbox\Core\Patterns\ for easy extensibility.
 *
 * Supported Business Types:
 * - generic       General / Multi-Industry
 * - saas          SaaS / Software
 * - ecommerce     E-commerce / Retail
 * - service       Service / Consulting
 * - healthcare    Healthcare / Medical
 * - education     Education / Training
 * - hospitality   Hospitality / Travel
 * - banking       Banking / Financial Services
 * - insurance     Insurance
 * - embassy       Embassy / High Commission
 * - qualityagency Quality Agency / Certification
 * - travelagency  Travel Agency / Tours
 * - supermarket   Supermarket / Grocery
 * - legal         Legal Services / Law Firm
 * - logistics     Logistics / Courier
 * - telecom       Telecom / ISP
 * - automotive    Automotive / Dealership
 * - construction  Construction / Home Services
 * - realestate    Real Estate
 *
 * To add a new type:
 *  1. Create includes/Core/Patterns/YourTypePatterns.php implementing
 *     ContactInbox\Core\Patterns\BusinessPatternInterface.
 *  2. Add a 'yourtype' => Patterns\YourTypePatterns::class entry to TYPE_CLASS_MAP.
 *  3. Add a label to get_business_types().
 *
 * @package ContactIn\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class BusinessPatterns
{
    /**
     * Maps business-type keys to their pattern class.
     *
     * @var array<string, class-string>
     */
    private const TYPE_CLASS_MAP = [
        'generic' => Patterns\GenericPatterns::class,
        'saas' => Patterns\SaaSPatterns::class,
        'ecommerce' => Patterns\EcommercePatterns::class,
        'service' => Patterns\ServicePatterns::class,
        'healthcare' => Patterns\HealthcarePatterns::class,
        'education' => Patterns\EducationPatterns::class,
        'hospitality' => Patterns\HospitalityPatterns::class,
        'banking' => Patterns\BankingPatterns::class,
        'insurance' => Patterns\InsurancePatterns::class,
        'embassy' => Patterns\EmbassyPatterns::class,
        'qualityagency' => Patterns\QualityAgencyPatterns::class,
        'travelagency' => Patterns\TravelAgencyPatterns::class,
        'supermarket' => Patterns\SupermarketPatterns::class,
        'legal' => Patterns\LegalPatterns::class,
        'logistics' => Patterns\LogisticsPatterns::class,
        'telecom' => Patterns\TelecomPatterns::class,
        'automotive' => Patterns\AutomotivePatterns::class,
        'construction' => Patterns\ConstructionPatterns::class,
        'realestate' => Patterns\RealEstatePatterns::class,
    ];

    /**
     * Get all available business types with human-readable labels.
     *
     * @return array<string, string>
     */
    public static function get_business_types(): array
    {
        return [
            'generic'       => __('Generic / Multi-Industry',  'contactin'),
            'saas'          => __('SaaS / Software',  'contactin'),
            'ecommerce'     => __('E-commerce / Retail',  'contactin'),
            'service'       => __('Service / Consulting',  'contactin'),
            'healthcare'    => __('Healthcare / Medical',  'contactin'),
            'education'     => __('Education / Training',  'contactin'),
            'hospitality'   => __('Hospitality / Travel',  'contactin'),
            'banking'       => __('Banking / Financial Services',  'contactin'),
            'insurance'     => __('Insurance',  'contactin'),
            'embassy'       => __('Embassy / High Commission',  'contactin'),
            'qualityagency' => __('Quality Agency / Certification',  'contactin'),
            'travelagency'  => __('Travel Agency / Tours',  'contactin'),
            'supermarket'   => __('Supermarket / Grocery',  'contactin'),
            'legal'         => __('Legal Services / Law Firm',  'contactin'),
            'logistics'     => __('Logistics / Courier',  'contactin'),
            'telecom'       => __('Telecom / ISP',  'contactin'),
            'automotive'    => __('Automotive / Dealership',  'contactin'),
            'construction'  => __('Construction / Home Services',  'contactin'),
            'realestate'    => __('Real Estate',  'contactin'),
        ];
    }

    /**
     * Get intent classification patterns for the given business type.
     *
     * Falls back to 'generic' for unrecognised or empty values.
     *
     * @param  string $business_type Business-type key (see TYPE_CLASS_MAP).
     * @return array<string, array<string, list<string>>>
     */
    public static function get_patterns(string $business_type = 'generic'): array
    {
        $type  = strtolower(trim($business_type));
        $class = self::TYPE_CLASS_MAP[$type] ?? Patterns\GenericPatterns::class;

        return $class::get_patterns();
    }

    /**
     * Returns the generic base patterns directly.
     *
     * Kept for backwards compatibility with any external callers.
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function get_generic_patterns(): array
    {
        return Patterns\GenericPatterns::get_patterns();
    }
}