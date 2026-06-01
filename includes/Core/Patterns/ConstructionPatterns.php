<?php
/**
 * Construction Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConstructionPatterns extends AbstractBusinessPattern {

	public static function get_patterns(): array {
		$base = self::base();

		$base['sales']['high'] = array_merge(
			$base['sales']['high'],
			array(
				// Project Types
				'new construction project',
				'home renovation',
				'kitchen remodel',
				'bathroom renovation',
				'office fit out',
				'interior design execution',
				'civil work',
				'masonry work',
				'plumbing service',
				'electrical service',
				'painting contract',
				'roofing service',
				'flooring installation',

				// Commercial Terms
				'construction quote',
				'bill of quantities',
				'boq estimate',
				'site survey',
				'project estimate',
				'labor cost estimate',
				'material cost estimate',
				'turnkey project',
				'fixed price contract',
				'milestone payment plan',
				'project timeline commitment',

				// Compliance & Delivery
				'building permit support',
				'approval drawings',
				'structural design',
				'project management service',
				'contractor onboarding',
				'maintenance contract for property',
			)
		);

		$base['sales']['medium'] = array_merge(
			$base['sales']['medium'] ?? array(),
			array(
				'cost per square foot',
				'budget friendly renovation',
				'compare contractor proposals',
				'scope of work discussion',
				'site visit booking',
				'material specification options',
				'completion deadline planning',
				'design and build package',
			)
		);

		$base['support']['high'] = array_merge(
			$base['support']['high'],
			array(
				// Execution Delays & Quality
				'project delay',
				'work stopped on site',
				'missed milestone date',
				'poor workmanship',
				'defective construction',
				'rework needed',
				'water leakage after work',
				'electrical fault after installation',
				'plumbing leak after handover',

				// Material/Scope Issues
				'wrong material used',
				'material not as agreed',
				'scope not completed',
				'incomplete handover',

				// Coordination/Billing
				'site supervisor not responding',
				'contractor no show',
				'invoice dispute construction',
				'unexpected variation charges',
				'final handover delayed',
			)
		);

		$base['support']['medium'] = array_merge(
			$base['support']['medium'] ?? array(),
			array(
				'reschedule site visit',
				'change work order request',
				'progress report request',
				'updated boq request',
				'material approval pending',
				'drawing revision request',
				'warranty service request',
				'snag list follow up',
			)
		);

		$base['feedback']['high'] = array_merge(
			$base['feedback']['high'] ?? array(),
			array(
				'better project communication',
				'more transparent progress tracking',
				'improve workmanship standards',
				'better quality control checks',
				'clearer change order process',
				'better coordination with client',
				'cleaner site management',
				'faster defect rectification',
				'better post-handover support',
			)
		);

		$base['feedback']['medium'] = array_merge(
			$base['feedback']['medium'] ?? array(),
			array(
				'better timeline updates',
				'clear labor and material split',
				'improved supervisor availability',
				'better vendor coordination',
				'more frequent site photos',
				'better safety compliance communication',
			)
		);

		$base['complaint']['high'] = array_merge(
			$base['complaint']['high'],
			array(
				'contractor abandoned project',
				'serious structural defect',
				'unsafe construction practice',
				'code violation construction',
				'fraudulent billing',
				'overbilling without approval',
				'substandard materials used',
				'work quality unacceptable',
				'damage to existing property',
				'non-compliance with contract terms',
			)
		);

		$base['complaint']['medium'] = array_merge(
			$base['complaint']['medium'] ?? array(),
			array(
				'slow progress on site',
				'poor supervision',
				'messy site conditions',
				'frequent worker absence',
				'unclear cost escalation',
				'delayed response from contractor',
			)
		);

		$base['question']['high'] = array_merge(
			$base['question']['high'] ?? array(),
			array(
				'how much renovation cost',
				'how long project takes',
				'what is included in quotation',
				'do you provide permits',
				'what is payment schedule',
				'what warranty do you provide',
				'how to start construction project',
				'what documents are required',
				'can you do turnkey execution',
				'how to handle design changes',
			)
		);

		$base['question']['medium'] = array_merge(
			$base['question']['medium'] ?? array(),
			array(
				'which material is best',
				'which flooring option is durable',
				'can i stay during renovation',
				'is weekend work possible',
				'how often will site updates be shared',
				'is gst included in quote',
				'can project be phased',
			)
		);

		$base['spam']['high'] = array_merge(
			$base['spam']['high'] ?? array(),
			array(
				'guaranteed low cost construction scam',
				'advance payment and no contract',
				'fake contractor license',
				'pay now for permit approval scam',
				'phishing invoice for site payment',
				'too cheap renovation package fraud',
			)
		);

		return $base;
	}
}
