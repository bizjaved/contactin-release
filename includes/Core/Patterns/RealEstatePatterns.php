<?php
/**
 * Realestate Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RealEstatePatterns extends AbstractBusinessPattern {

	public static function get_patterns(): array {
		$base = self::base();

		// Customize for Real Estate - Sales
		$base['sales']['high'] = array_merge(
			$base['sales']['high'],
			array(
				// Property Listings & Details
				'property listing',
				'mls listing',
				'new listing',
				'active listing',
				'featured property',
				'residential property',
				'commercial property',
				'land for sale',
				'investment property',
				'property address',
				'property details',
				'property features',
				'listing description',

				// Showings & Viewings
				'showing',
				'viewing',
				'showing appointment',
				'schedule showing',
				'showing time',
				'open house',
				'preview',
				'tour property',
				'walk-through',
				'site visit',

				// Offers & Negotiations
				'make offer',
				'submit offer',
				'offer price',
				'offer contingencies',
				'counter-offer',
				'accept offer',
				'offer negotiation',
				'negotiating terms',
				'offer terms',

				// Pricing & Valuation
				'asking price',
				'list price',
				'property price',
				'market value',
				'appraisal value',
				'home valuation',
				'comparable sales',
				'price reduction',
				'price adjustment',

				// Financing & Purchasing
				'mortgage',
				'home loan',
				'financing',
				'pre-approval',
				'pre-qualified',
				'down payment',
				'closing costs',
				'interest rate',
				'loan terms',
				'fha loan',
				'conventional loan',
				'va loan',
				'usda loan',
				'jumbo loan',
				'refinance',
			)
		);

		$base['sales']['medium'] = array_merge(
			$base['sales']['medium'] ?? array(),
			array(
				// Additional Sales Keywords
				'property investment',
				'purchase property',
				'buy home',
				'sell property',
				'real estate',
				'buyer interested',
				'looking at',
				'interested in property',
				'seriously interested',
				'first-time buyer',
				'move to neighborhood',
				'relocating',
				'house hunting',
				'real estate agent',
				'broker',
				'listing agent',
				'selling agent',
				'dual agent',
				'earnest money',
				'inspection period',
				'appraisal contingency',
				'financing contingency',
				'title insurance',
				'homeowners insurance',
				'property survey',
				'walk-through inspection',
				'due diligence',
				'contract signing',
				'sign documents',
				'documentation',
			)
		);

		// Support - Transaction Issues
		$base['support']['high'] = array_merge(
			$base['support']['high'],
			array(
				// Closing & Escrow
				'closing date',
				'closing process',
				'closing documents',
				'escrow',
				'escrow holdback',
				'title company',
				'closing agent',
				'final walkthrough',
				'final inspection',

				// Title & Legal Issues
				'title search',
				'title report',
				'clear title',
				'title defect',
				'title issue',
				'deed',
				'warranty deed',
				'quit claim deed',
				'property deed',
				'lien',
				'encumbrance',

				// Inspection & Appraisal
				'home inspection',
				'property inspection',
				'inspection report',
				'inspection results',
				'inspection findings',
				'appraisal',
				'appraisal report',
				'appraisal contingency',
				'appraiser',
				'appraisal comes in low',
				'appraisal waived',

				// Survey & Boundaries
				'survey',
				'property survey',
				'boundary survey',
				'survey stakes',
				'property lines',

				// Contingencies & Negotiations
				'contingency',
				'contingency period',
				'contingency removal',
				'extend contingency',
				'repair request',
				'repair negotiation',
				'renegotiate',
				'renegotiation',
			)
		);

		$base['support']['medium'] = array_merge(
			$base['support']['medium'] ?? array(),
			array(
				'closing timeline',
				'closing delay',
				'settlement',
				'settlement statement',
				'prorations',
				'property taxes',
				'homeowner association dues',
				'hoa fees',
				'utilities transfer',
				'utility accounts',
				'meter readings',
				'final readings',
				'hazard insurance',
				'flood insurance',
				'proof of insurance',
				'insurance quote',
				'wire funds',
				'closing funds',
				'certified funds',
				'cashier check',
				'document preparation',
				'document review',
				'document signing',
				'notary',
			)
		);

		// Feedback - Listing & Agent Performance
		$base['feedback']['high'] = array_merge(
			$base['feedback']['high'],
			array(
				// Listing Presentation
				'listing quality',
				'property photos',
				'photography',
				'photo quality',
				'photo staging',
				'virtual tour',
				'video tour',
				'property description',
				'listing writeup',
				'description accuracy',
				'listing accuracy',
				'property information accuracy',
				'featured listings',
				'listing promotion',

				// Agent Communication & Service
				'agent communication',
				'agent responsiveness',
				'agent availability',
				'quick response',
				'agent professionalism',
				'agent knowledge',
				'agent expertise',
				'agent helpfulness',
				'realtor quality',
				'agent courtesy',
				'agent patience',
				'agent guidance',

				// Website & Portal Access
				'listing website',
				'mls portal',
				'property portal',
				'website user-friendly',
				'portal access',
				'listing visibility',
				'search functionality',
			)
		);

		$base['feedback']['medium'] = array_merge(
			$base['feedback']['medium'] ?? array(),
			array(
				'neighborhood information',
				'market analysis',
				'market trends',
				'comparable properties',
				'competitive market',
				'buyer education',
				'seller education',
				'process education',
				'timeline management',
				'scheduling coordination',
				'appointment setting',
				'negotiation strategy',
				'marketing strategy',
				'listing strategy',
				'open house attendance',
				'showing schedule',
				'showing flexibility',
			)
		);

		// Complaint - Defects, Issues & Fraud
		$base['complaint']['high'] = array_merge(
			$base['complaint']['high'],
			array(
				// Property Defects & Condition Issues
				'hidden defect',
				'undisclosed defect',
				'property defects',
				'structural defect',
				'foundation issue',
				'foundation crack',
				'water damage',
				'water leak',
				'leak',
				'roof damage',
				'roof leak',
				'mold',
				'mold damage',
				'pest damage',
				'termites',
				'asbestos',
				'lead paint',
				'hazardous materials',
				'radon',
				'previous damage',
				'undisclosed issue',
				'material defect',
				'latent defect',
				'defect not disclosed',

				// Condition Misrepresentation
				'property condition misrepresented',
				'condition not as described',
				'condition worse than',
				'false representation',
				'misrepresentation',
				'property falsely advertised',
				'misleading photos',
				'photo misrepresentation',
				'property not shown accurately',

				// Title & Lien Issues
				'title issue',
				'title problem',
				'lien on property',
				'judgment lien',
				'tax lien',
				'homeowner association lien',
				'unrecorded lien',
				'title defect',
				'cloud on title',
				'unknown owner',
				'ownership dispute',

				// Fraud & Misconduct
				'fraud',
				'real estate fraud',
				'mortgage fraud',
				'transaction fraud',
				'title fraud',
				'agent fraud',
				'agent misconduct',
				'agent misrepresentation',
				'unethical practices',
				'illegal activity',
				'bait and switch',
				'hidden costs',
				'undisclosed fees',
			)
		);

		$base['complaint']['medium'] = array_merge(
			$base['complaint']['medium'] ?? array(),
			array(
				'contract violation',
				'contract breach',
				'contractual dispute',
				'breached agreement',
				'closing delay',
				'delayed closing',
				'unreasonable delay',
				'missed deadline',
				'surprise repair costs',
				'unexpected expenses',
				'inflated repair estimates',
				'repair not completed',
				'repair not done properly',
				'poor repairs',
				'shoddy work',
				'unsatisfactory service',
				'poor service quality',
				'lack of communication',
				'unresponsive agent',
				'agent unavailable',
				'agent unprepared',
				'overcharged',
				'excessive fees',
				'surprise fees',
				'hidden charges',
			)
		);

		// Question - Information Seeking
		$base['question']['high'] = array_merge(
			$base['question']['high'] ?? array(),
			array(
				'what is the price',
				'how much does property cost',
				'what is asking price',
				'how much is down payment',
				'what are closing costs',
				'what is interest rate',
				'how does mortgage work',
				'what is escrow',
				'what does appraisal include',
				'what is in inspection',
				'what is title insurance',
				'how long is closing',
				'what is contingency',
				'what is hoa',
				'what are hoa fees',
				'when can we view',
				'when is showing',
				'when is open house',
			)
		);

		$base['question']['medium'] = array_merge(
			$base['question']['medium'] ?? array(),
			array(
				'any improvements made',
				'what upgrades',
				'what repairs needed',
				'property history',
				'how old is roof',
				'when was built',
				'lot size',
				'square footage',
				'number of bedrooms',
				'number of bathrooms',
				'year built',
				'architectural style',
				'property zoning',
				'can we make offer',
				'how to submit offer',
				'can we negotiate',
				'what taxes',
				'property taxes amount',
				'how are utilities',
				'utilities included',
				'parking',
				'neighborhood schools',
				'nearby amenities',
				'crime rate',
				'neighborhood safety',
			)
		);

		// Spam - Suspicious & Fraudulent
		$base['spam']['high'] = array_merge(
			$base['spam']['high'] ?? array(),
			array(
				'guaranteed foreclosure deal',
				'guaranteed investment returns',
				'foreclosure scam',
				'fake listing',
				'stolen property listing',
				'fraudulent property',
				'too good to be true',
				'unbelievable price',
				'suspiciously low price',
				'foreclosure guaranteed profit',
				'we buy houses cash',
				'quick cash for property',
				'stop foreclosure now',
				'claim free grant money',
				'government property hidden',
				'bank property for penny',
				'verify credit card information',
				'verify identity info',
				'fake mortgage approval',
				'nigerian prince property',
				'phishing credential',
				'credential scam',
				'identity theft attempt',
			)
		);

		return $base;
	}
}
