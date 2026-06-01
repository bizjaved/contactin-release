<?php
/**
 * Insurance Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InsurancePatterns extends AbstractBusinessPattern {

	public static function get_patterns(): array {
		$base = self::base();

		$base['sales']['high'] = array_merge(
			$base['sales']['high'],
			array(
				// Policy Shopping
				'insurance quote',
				'premium quote',
				'policy purchase',
				'buy policy',
				'instant quote',
				'compare insurance plans',
				'best insurance plan',

				// Product Types
				'life insurance',
				'term life insurance',
				'whole life insurance',
				'health insurance',
				'family floater',
				'critical illness cover',
				'travel insurance',
				'vehicle insurance',
				'car insurance',
				'bike insurance',
				'home insurance',
				'property insurance',
				'business insurance',
				'liability insurance',
				'professional indemnity',
				'cyber insurance',
				'marine insurance',
				'fire insurance',

				// Coverage & Structure
				'coverage options',
				'sum insured',
				'policy term',
				'deductible',
				'co-pay',
				'rider',
				'add-on cover',
				'waiver of premium rider',
				'maternity cover',
				'accidental death benefit',
				'hospital cash rider',
				'own damage cover',
				'third party cover',
				'comprehensive cover',

				// Commercial/Group
				'group insurance',
				'employee health plan',
				'corporate insurance',
				'fleet insurance',
				'shop insurance',
				'warehouse insurance',

				// Pricing/Value
				'premium discount',
				'no claim bonus',
				'renewal offer',
				'long term policy',
				'multi-year policy',
			)
		);

		$base['sales']['medium'] = array_merge(
			$base['sales']['medium'] ?? array(),
			array(
				'policy recommendation',
				'suitable plan',
				'coverage advice',
				'customized policy',
				'tailored coverage',
				'premium affordability',
				'claim ratio',
				'insurer rating',
				'network hospital list',
				'cashless hospitalization',
				'paperless claim process',
				'switch insurer',
				'port policy',
				'policy migration',
			)
		);

		$base['support']['high'] = array_merge(
			$base['support']['high'],
			array(
				// Claims
				'claim status',
				'file a claim',
				'claim rejected',
				'claim denied',
				'claim settlement delay',
				'claim not processed',
				'claim under review',
				'surveyor not assigned',
				'documents pending for claim',

				// Policy Lifecycle
				'policy renewal issue',
				'renewal failed',
				'policy lapsed',
				'policy reinstatement',
				'payment failed',
				'auto debit failed',
				'policy document not received',
				'wrong policy details',
				'endorsement request',
				'policy update issue',

				// Health Insurance Operations
				'hospital cashless issue',
				'pre authorization issue',
				'cashless denied',
				'network hospital rejected',
				'room rent limit issue',

				// Motor Claims
				'motor claim delayed',
				'garage approval pending',
				'cashless garage issue',
				'repair estimate rejected',
				'total loss settlement issue',

				// KYC/Verification
				'kyc pending',
				'kyc rejected',
				'identity verification failed',
				'nominee update issue',
				'proposal form issue',
			)
		);

		$base['support']['medium'] = array_merge(
			$base['support']['medium'] ?? array(),
			array(
				'claim form help',
				'document upload issue',
				'survey appointment',
				'policy cancellation request',
				'free look cancellation',
				'change policy details',
				'address update in policy',
				'mobile number update',
				'email update',
				'bank details update',
				'premium receipt request',
				'tax certificate request',
				'renewal reminder not received',
				'agent callback request',
			)
		);

		$base['feedback']['high'] = array_merge(
			$base['feedback']['high'] ?? array(),
			array(
				'faster claim adjudication',
				'better claim communication',
				'transparent settlement calculation',
				'simpler claim documents',
				'clear policy brochure',
				'easy to understand exclusions',
				'improve cashless experience',
				'faster pre-auth approvals',
				'better mobile app for claims',
				'self-service endorsement flow',
				'proactive renewal alerts',
				'better advisor support',
			)
		);

		$base['feedback']['medium'] = array_merge(
			$base['feedback']['medium'] ?? array(),
			array(
				'faster claim settlement',
				'clear policy wording',
				'better claim experience',
				'transparent exclusions',
				'simpler onboarding',
				'easier renewal process',
				'fewer follow ups',
				'single point of contact',
				'better branch support',
				'better online quote journey',
				'improved renewal portal',
			)
		);

		$base['complaint']['high'] = array_merge(
			$base['complaint']['high'],
			array(
				'claim wrongly denied',
				'mis-sold policy',
				'policy misrepresentation',
				'hidden exclusions',
				'unfair premium increase',
				'agent misled me',
				'delay in claim payment',
				'poor claim handling',
				'fraud allegation without basis',
				'forced add-on',
				'unauthorized deduction',
				'partial settlement without reason',
				'underpaid claim',
				'wrong depreciation applied',
				'salvage dispute',
				'harassment by recovery/survey team',
				'complaint ignored by insurer',
				'privacy breach in policy data',
			)
		);

		$base['complaint']['medium'] = array_merge(
			$base['complaint']['medium'] ?? array(),
			array(
				'slow claim response',
				'too many document requests',
				'unclear rejection reason',
				'helpline not helpful',
				'agent unresponsive',
				'renewal premium too high',
				'renewal discount not applied',
				'service quality poor',
				'branch support poor',
			)
		);

		$base['question']['high'] = array_merge(
			$base['question']['high'] ?? array(),
			array(
				'what is covered',
				'what is not covered',
				'waiting period',
				'grace period',
				'how to renew policy',
				'no claim bonus',
				'how to file a claim',
				'required claim documents',
				'network hospitals',
				'what is deductible',
				'what is co-pay',
				'what is sum insured',
				'how premium is calculated',
				'how to port policy',
				'how to add nominee',
				'how to add rider',
				'cashless claim process',
				'reimbursement claim process',
			)
		);

		$base['question']['medium'] = array_merge(
			$base['question']['medium'] ?? array(),
			array(
				'which plan is best for family',
				'difference between term and whole life',
				'when does policy start',
				'what is free look period',
				'is medical test required',
				'what documents for proposal',
				'how long claim settlement takes',
				'how to download policy copy',
				'what is policy surrender value',
				'loan against policy available',
			)
		);

		$base['spam']['high'] = array_merge(
			$base['spam']['high'] ?? array(),
			array(
				'guaranteed claim approval',
				'100 percent claim settlement guaranteed',
				'instant policy no verification',
				'free insurance for life',
				'share otp to process claim',
				'claim your bonus now click link',
				'fake insurer call',
				'impersonating insurance regulator',
				'policy lapse scam',
				'refund processing fee scam',
				'phishing insurance portal',
				'credential theft via claim link',
			)
		);

		return $base;
	}
}
