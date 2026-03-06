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
 * @package ContactInbox\Core
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
            'generic'       => __('Generic / Multi-Industry', 'contact-inbox'),
            'saas'          => __('SaaS / Software', 'contact-inbox'),
            'ecommerce'     => __('E-commerce / Retail', 'contact-inbox'),
            'service'       => __('Service / Consulting', 'contact-inbox'),
            'healthcare'    => __('Healthcare / Medical', 'contact-inbox'),
            'education'     => __('Education / Training', 'contact-inbox'),
            'hospitality'   => __('Hospitality / Travel', 'contact-inbox'),
            'banking'       => __('Banking / Financial Services', 'contact-inbox'),
            'insurance'     => __('Insurance', 'contact-inbox'),
            'embassy'       => __('Embassy / High Commission', 'contact-inbox'),
            'qualityagency' => __('Quality Agency / Certification', 'contact-inbox'),
            'travelagency'  => __('Travel Agency / Tours', 'contact-inbox'),
            'supermarket'   => __('Supermarket / Grocery', 'contact-inbox'),
            'legal'         => __('Legal Services / Law Firm', 'contact-inbox'),
            'logistics'     => __('Logistics / Courier', 'contact-inbox'),
            'telecom'       => __('Telecom / ISP', 'contact-inbox'),
            'automotive'    => __('Automotive / Dealership', 'contact-inbox'),
            'construction'  => __('Construction / Home Services', 'contact-inbox'),
            'realestate'    => __('Real Estate', 'contact-inbox'),
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