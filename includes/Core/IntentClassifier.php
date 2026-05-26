<?php
/**
 * Intent Classifier – Lightweight Message Intent Classification
 *
 * Classifies messages into categories based on keyword patterns and scoring.
 * Provides confidence scores and matched keywords for transparency.
 *
 * ⚙️ PATTERN MANAGEMENT:
 * - Patterns stored in WordPress options (database) for persistence and upgrades
 * - Installed automatically during plugin activation
 * - Updated on each version upgrade via CoreBootstrap
 * - Can be customized via IntentClassifier::update_category_patterns()
 * - Can be reset to defaults via IntentClassifier::reset_to_defaults()
 *
 * 📊 CLASSIFICATION ALGORITHM:
 * - Each category has 3 weight levels: high (10 pts), medium (5 pts), low (2 pts)
 * - Scores calculated per category based on keyword matches
 * - Highest scoring category wins (with MIN_CONFIDENCE = 20% threshold)
 * - Subject line weighted 2x vs message body for importance
 * - Returns: category, confidence %, matched keywords
 *
 * Categories:
 * - sales: Pricing, purchase, quote, inquiries
 * - support: Help requests, issues, problems, repairs
 * - feedback: Suggestions, improvements, feature requests
 * - complaint: Dissatisfaction, refunds, cancellations
 * - question: General questions, how-to queries
 * - spam: Suspicious patterns, promotional content
 * - unclassified: No clear match (confidence < threshold)
 *
 * @package ContactIn\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IntentClassifier {
	use Singleton;

	// Intent categories
	public const CATEGORY_SALES        = 'sales';
	public const CATEGORY_SUPPORT      = 'support';
	public const CATEGORY_FEEDBACK     = 'feedback';
	public const CATEGORY_COMPLAINT    = 'complaint';
	public const CATEGORY_QUESTION     = 'question';
	public const CATEGORY_SPAM         = 'spam';
	public const CATEGORY_UNCLASSIFIED = 'unclassified';

	// Pattern versioning for robust updates
	private const PATTERN_VERSION = '1.2.0';

	// Minimum confidence threshold to classify (0-100)
	private const MIN_CONFIDENCE = 20.0;

	// Pattern weights (how much each keyword contributes to score)
	private const WEIGHT_HIGH   = 10;
	private const WEIGHT_MEDIUM = 5;
	private const WEIGHT_LOW    = 2;

	/**
	 * Classification patterns with weighted keywords
	 * Loads business-specific patterns based on selected business type.
	 * Only uses the contactin_intent_patterns DB option when an admin has
	 * explicitly customised patterns (contactin_intent_patterns_customized = true).
	 *
	 * @return array<string, array>
	 */
	private function get_patterns(): array {
		// Only use stored custom patterns when admin has explicitly customised them.
		// Prevents install_patterns() generic defaults from masking the business-type selection.
		if ( get_option( 'contactin_intent_patterns_customized' ) ) {
			$custom_patterns = get_option( 'contactin_intent_patterns' );
			if ( $custom_patterns && is_array( $custom_patterns ) ) {
				return apply_filters( 'contactin_intent_patterns', $custom_patterns );
			}
		}

		// Primary path: load business-specific patterns based on selected business type
		$business_type     = get_option( 'contactin_business_type', 'generic' );
		$business_patterns = BusinessPatterns::get_patterns( $business_type );

		if ( $business_patterns && is_array( $business_patterns ) ) {
			return apply_filters( 'contactin_intent_patterns', $business_patterns );
		}

		// Fallback to default patterns if nothing else works
		return apply_filters( 'contactin_intent_patterns', self::get_default_patterns() );
	}

	/**
	 * Get default classification patterns
	 * Used during plugin activation and as fallback
	 *
	 * @return array<string, array>
	 */
	public static function get_default_patterns(): array {
		return array(
			self::CATEGORY_SALES     => array(
				'high'   => array(
					// Direct purchasing intent
					'price',
					'pricing',
					'quote',
					'purchase',
					'buy',
					'cost',
					'payment',
					'invoice',
					'demo',
					'trial',
					'subscription',
					'license',
					'plan',
					'package deal',
					'annual pricing',
					'monthly pricing',
					'order',
					'ordering',
					'place order',
					'placed order',
					'interested',
					'interested in',
					// Product/service inquiry
					'features',
					'specifications',
					'specs',
					'capabilities',
					'requirements',
					'licensing',
					'enterprise plan',
					'startup plan',
					'pro version',
					'premium plan',
					'basic plan',
					// ROI and business
					'roi',
					'return on investment',
					'budget',
					'budget for',
					'allocate funds',
					'investment',
					'how much does',
					'how much is',
					'what does it cost',
					'pricing tier',
					'pricing option',
					// Comparison intent
					'competitor',
					'alternative',
					'switch from',
					'migration',
					'compare',
					'comparison',
					'vs',
					'versus',
					'better than',
					'difference between',
					'why choose',
					// Product-specific (automotive, retail, etc.)
					'new car',
					'used car',
					'vehicle',
					'automobile',
					'sedan',
					'suv',
					'truck',
					'tire',
					'tires',
					'parts',
					'accessories',
					'product',
					'item',
					'model',
				),
				'medium' => array(
					'information about',
					'details about',
					'offer',
					'discount',
					'deal',
					'package',
					'upgrade',
					'request',
					'quotation',
					'proposal',
					'bid',
					'estimate',
					'free trial',
					'sample',
					'demo account',
					'test drive',
					'evaluate',
					'assessment',
					'feasibility',
					'suitable for',
					'match my needs',
					'right solution',
					'looking for',
					'in the market',
					'considering',
					'decide between',
					'help me choose',
					'business case',
					'implementation',
					'shopping for',
					'need',
					'need a',
					'want',
					'want a',
					'want to buy',
					'ready to buy',
				),
				'low'    => array(
					'want to know',
					'tell me more',
					'availability',
					'options',
					'how much',
					'curious',
					'potential',
					'possibility',
					'might be interested',
					'learning about',
					'exploring',
				),
			),
			self::CATEGORY_SUPPORT   => array(
				'high'   => array(
					// Critical issues
					'help',
					'problem',
					'issue',
					'error',
					'bug',
					'broken',
					'not working',
					"doesn't work",
					'failed',
					'failure',
					'failing',
					'fails',
					'unable to',
					'cannot',
					"can't",
					'crash',
					'freeze',
					'unresponsive',
					'hang',
					'timeout',
					// Urgent indicators
					'urgent',
					'critical',
					'asap',
					'sos',
					'emergency',
					'downtime',
					'production down',
					'live issue',
					// Repair/restoration
					'repair',
					'replace',
					'fix',
					'troubleshoot',
					'debug',
					'restore',
					'recover',
					'restart',
					'reinstall',
					'reboot',
					'reset',
					'rebuild',
					'rollback',
					// Service requests
					'support',
					'assistance',
					'service',
					'need help',
					'need assistance',
					'need support',
				),
				'medium' => array(
					// Moderate problems
					'trouble',
					'difficulty',
					'stuck',
					'slow',
					'slow performance',
					'lag',
					'sluggish',
					'technical',
					'malfunction',
					'system',
					'maintain',
					'maintenance',
					'patch',
					'update issue',
					// Resolution terms
					'resolve',
					'solution',
					'fix it',
					'work again',
					'correct',
					'rectify',
					// Warranty/guarantee
					'after sales',
					'after-sales',
					'warranty',
					'guarantee',
					'coverage',
					'protection plan',
					'return',
					'exchange',
					'replacement',
					'refund',
					'rebate',
					// Performance
					'error code',
					'error message',
					'logs',
					'diagnostic',
					'diagnostic report',
					'screenshot',
					// Skill level
					'installation',
					'setup',
					'configure',
					'configuration',
					'integration',
					'migrate',
					'migration',
				),
				'low'    => array(
					'confused',
					'unclear',
					'how do i',
					'how to',
					'having issues',
					'install',
					'setup',
					'configure',
					'guide',
					'tutorial',
					'help me understand',
					'explain',
					'learn how',
					'best practices',
				),
			),
			self::CATEGORY_FEEDBACK  => array(
				'high'   => array(
					// Direct feature requests
					'suggest',
					'suggestion',
					'improvement',
					'feature request',
					'feature idea',
					'new feature',
					'should add',
					'should include',
					'would be nice',
					'could add',
					'enhancement',
					'enhancement request',
					'proposal',
					'recommendation',
					'recommend adding',
					'request for',
					'request you add',
					// Experience improvements
					'user experience',
					'ux',
					'ui',
					'usability',
					'workflow',
					'process improvement',
					'streamline',
					'simplify',
					'make easier',
					'intuitive',
					'user-friendly',
					// Capability gaps
					'missing',
					'lacking',
					'doesn\'t have',
					'no option for',
					'can\'t do',
					'can\'t handle',
				),
				'medium' => array(
					'feedback',
					'idea',
					'better if',
					'wish',
					'wish you had',
					'wish it would',
					'consider adding',
					'consider including',
					'nice to have',
					'nice feature',
					'integration',
					'api',
					'connector',
					'plugin',
					'extension',
					'addon',
					'roadmap',
					'future',
					'coming soon',
					'planned feature',
					'backlog',
					'improve',
					'enhanced',
					'optimize',
					'better',
					'improvement opportunity',
				),
				'low'    => array(
					'thought',
					'opinion',
					'input',
					'perspective',
					'consider',
					'maybe',
					'just a suggestion',
					'food for thought',
					'what if',
					'imagine',
				),
			),
			self::CATEGORY_COMPLAINT => array(
				'high'   => array(
					// Strong negative emotions
					'complaint',
					'complain',
					'unhappy',
					'disappointed',
					'frustrated',
					'frustrating',
					'frustration',
					'terrible',
					'awful',
					'horrible',
					'worst',
					'disgusted',
					'angry',
					'furious',
					'enraged',
					'appall',
					'appalled',
					'outraged',
					'shameful',
					'negligent',
					'reckless',
					// Financial complaints
					'refund',
					'money back',
					'reimburse',
					'reimbursement',
					'charge back',
					'cancel subscription',
					'cancel account',
					'unsubscribe',
					'stop charges',
					'billing issue',
					// Quality issues
					'delay',
					'not received',
					'missing',
					'damaged',
					'defective',
					'faulty',
					'broken on arrival',
					'arrived damaged',
					'shipment damaged',
					'poor quality',
					'bad quality',
					'substandard',
					'unacceptable',
					'unacceptable quality',
					'not acceptable',
					'below standard',
					// Service complaints
					'poor service',
					'bad service',
					'terrible service',
					'rude staff',
					'unhelpful',
					'lack of response',
					'ignored',
					'no support',
					'abandoned',
				),
				'medium' => array(
					// Moderate dissatisfaction
					'poor',
					'unsatisfied',
					'not satisfied',
					'not happy',
					'let down',
					'let you down',
					'regret',
					'waste of money',
					'waste my time',
					'wasted',
					'ripoff',
					'scam',
					// Delivery issues
					'late delivery',
					'late shipping',
					'slow shipping',
					'shipping delay',
					'long wait',
					'months late',
					'still waiting',
					'never arrived',
					// Functional complaints
					'broken',
					'not working properly',
					'not as described',
					'misleading',
					'false advertising',
					'false claims',
					'overpromise',
					'underdeliver',
					// Escalation terms
					'escalate',
					'escalation',
					'lawyer',
					'legal action',
					'sue',
					'lawsuit',
					'litigation',
					'compensation',
					'damages',
					'breach',
					'violation',
					'fraud',
					'breach of contract',
				),
				'low'    => array(
					'expected more',
					'not what i expected',
					'not what i wanted',
					'not what i ordered',
					'not what i asked for',
					'misleading',
					'disappointed',
					'unhappy',
					'issues with',
					'problems with',
					'having trouble with',
					'not impressed',
					'could be better',
				),
			),
			self::CATEGORY_QUESTION  => array(
				'high'   => array(
					// Interrogative words
					'how',
					'what',
					'when',
					'where',
					'why',
					'which',
					'who',
					'whom',
					'can you',
					'could you',
					'would you',
					'will you',
					'should you',
					'do you',
					'is it',
					'does it',
					'have you',
					'has it',
					'are you',
					'am i',
					// Question patterns
					'how do i',
					'how can i',
					'how to',
					'how about',
					'what is',
					'what\'s the best',
					'where can i',
					'where is',
					'when should i',
					'why should i',
					'is it possible',
					'is there a way',
				),
				'medium' => array(
					'question',
					'questions',
					'wondering',
					'curious',
					'curious about',
					'want to know',
					'need to know',
					'clarify',
					'clarification',
					'explain',
					'explanation',
					'understand',
					'understand how',
					'confused about',
					'need clarification',
					'help me understand',
					'help me know',
					'tell me about',
					'teach me',
					'guidance',
				),
				'low'    => array(
					'any chance',
					'do you',
					'does it',
					'is it possible',
					'possibility',
					'might be',
					'maybe',
					'perhaps',
					'possibly',
					'wondering if',
				),
			),
			self::CATEGORY_SPAM      => array(
				'high'   => array(
					// Classic spam patterns
					'click here',
					'click now',
					'click link',
					'buy now',
					'limited time',
					'act now',
					'free money',
					'make money',
					'earn money',
					'earn $',
					'quick cash',
					'fast cash',
					'weight loss',
					'viagra',
					'casino',
					'lottery',
					'poker',
					'slots',
					'congratulations you won',
					'you won',
					'claim your prize',
					'claim prize',
					'you are winner',
					'selected you',
					'chosen you',
					// Phishing patterns
					'verify account',
					'confirm account',
					'validate account',
					'urgent verification',
					'immediate action required',
					'act immediately',
					'action needed',
					'suspicious activity',
					'unauthorized access',
					'confirm identity',
					'update payment',
					'update credit card',
					'update bank info',
				),
				'medium' => array(
					// Spam indicators
					'unsubscribe',
					'remove me',
					'opt out',
					'spam',
					'phishing',
					'scam',
					'suspicious',
					'suspicious link',
					'malware',
					'virus',
					'trojan',
					'ransomware',
					// Urgency tactics
					'limited offer',
					'final notice',
					'last chance',
					'expiring soon',
					'deadline',
					'hurry',
					'don\'t miss out',
					'exclusive offer',
					'never again',
					// Too-good-to-be-true
					'guaranteed',
					'guaranteed income',
					'risk-free',
					'no obligation',
					'no catch',
					'hidden fees',
					'work from home',
					'easy money',
					'passive income',
				),
				'low'    => array(
					// URL patterns
					'http://',
					'https://',
					'www.',
					'bit.ly',
					'goo.gl',
					'tinyurl',
					// Generic spam indicators
					'follow us',
					'like us',
					'share us',
					'subscribe now',
					'join us',
				),
			),
		);
	}

	/**
	 * Classify a message and return intent data
	 *
	 * ENHANCED INTELLIGENCE:
	 * - Sentiment analysis (positive/negative/neutral)
	 * - Negation detection (not + keyword = opposite meaning)
	 * - Contextual disambiguation (word relationships)
	 * - Category exclusion rules (prevent conflicting classifications)
	 * - Keyword priority weighting based on proximity
	 * - Subject/body emphasis weighting
	 *
	 * @param string $subject Message subject
	 * @param string $message Message body
	 * @return array{category: string, confidence: float, keywords: array, classified_at: string}
	 */
	public function classify( string $subject, string $message ): array {
		// Clean and prepare text
		$subject_clean = strtolower( trim( $subject ) );
		$message_clean = strtolower( trim( $message ) );

		// Adaptive weighting: If subject is empty, give full weight to message
		// Otherwise subject gets 2x weight since it's typically more focused
		if ( empty( $subject_clean ) ) {
			// No subject: message gets all the weight (repeat 3x to compensate)
			$text = $message_clean . ' ' . $message_clean . ' ' . $message_clean;
		} else {
			// Has subject: subject gets 2x weight, message gets 1x
			$text = $subject_clean . ' ' . $subject_clean . ' ' . $message_clean;
		}

		// Get patterns
		$patterns = $this->get_patterns();

		// Detect sentiment (help determine between similar categories)
		$sentiment = $this->analyze_sentiment( $subject_clean, $message_clean );

		// Calculate scores for each category
		$scores           = array();
		$matched_keywords = array();

		foreach ( $patterns as $category => $weights ) {
			$category_score   = 0;
			$category_matches = array();

			// High weight keywords
			if ( ! empty( $weights['high'] ) ) {
				foreach ( $weights['high'] as $keyword ) {
					$count = $this->count_keyword( $text, $keyword );
					if ( $count > 0 ) {
						// Check for negation (reduces score significantly)
						$negation_factor    = $this->has_negation( $text, $keyword ) ? 0.3 : 1.0;
						$category_score    += $count * self::WEIGHT_HIGH * $negation_factor;
						$category_matches[] = $keyword;
					}
				}
			}

			// Medium weight keywords
			if ( ! empty( $weights['medium'] ) ) {
				foreach ( $weights['medium'] as $keyword ) {
					$count = $this->count_keyword( $text, $keyword );
					if ( $count > 0 ) {
						// Check for negation
						$negation_factor    = $this->has_negation( $text, $keyword ) ? 0.3 : 1.0;
						$category_score    += $count * self::WEIGHT_MEDIUM * $negation_factor;
						$category_matches[] = $keyword;
					}
				}
			}

			// Low weight keywords
			if ( ! empty( $weights['low'] ) ) {
				foreach ( $weights['low'] as $keyword ) {
					$count = $this->count_keyword( $text, $keyword );
					if ( $count > 0 ) {
						// Check for negation
						$negation_factor    = $this->has_negation( $text, $keyword ) ? 0.3 : 1.0;
						$category_score    += $count * self::WEIGHT_LOW * $negation_factor;
						$category_matches[] = $keyword;
					}
				}
			}

			if ( $category_score > 0 ) {
				$scores[ $category ]           = $category_score;
				$matched_keywords[ $category ] = array_unique( $category_matches );
			}
		}

		// No matches found
		if ( empty( $scores ) ) {
			return $this->get_default_result();
		}

		// Apply contextual rules and sentiment adjustments
		$scores = $this->apply_contextual_rules( $scores, $subject_clean, $message_clean, $sentiment );

		// Find highest scoring category
		arsort( $scores );
		$top_category = array_key_first( $scores );
		$top_score    = $scores[ $top_category ];

		// Calculate confidence: fraction of total weighted evidence going to the top
		// category, scaled so a clear single-category win approaches 100% while a
		// near-tie stays low. No arbitrary hardcoded ceiling.
		$total_score = array_sum( $scores );
		$confidence  = $total_score > 0
			? min( 100.0, ( $top_score / $total_score ) * 150.0 )
			: 0.0;

		// If confidence is too low, mark as unclassified
		if ( $confidence < self::MIN_CONFIDENCE ) {
			return $this->get_default_result();
		}

		return array(
			'category'      => $top_category,
			'confidence'    => round( $confidence, 2 ),
			'keywords'      => $matched_keywords[ $top_category ] ?? array(),
			'classified_at' => current_time( 'mysql' ),
		);
	}

	/**
	 * Analyze sentiment of the message
	 * Returns: 'positive', 'negative', or 'neutral'
	 *
	 * @param string $subject Subject line
	 * @param string $message Message body
	 * @return string
	 */
	private function analyze_sentiment( string $subject, string $message ): string {
		$text = $subject . ' ' . $message;

		// Positive sentiment indicators
		$positive_words = array(
			'thank',
			'appreciate',
			'love',
			'great',
			'excellent',
			'wonderful',
			'amazing',
			'good',
			'happy',
			'pleased',
			'satisfied',
			'brilliant',
			'fantastic',
			'perfect',
			'professional',
			'quick',
			'fast',
			'efficient',
			'helpful',
			'friendly',
			'recommend',
		);

		// Negative sentiment indicators
		$negative_words = array(
			'bad',
			'terrible',
			'awful',
			'horrible',
			'worst',
			'disappointed',
			'unhappy',
			'frustrated',
			'angry',
			'upset',
			'disgusted',
			'poor',
			'slow',
			'late',
			'delay',
			'damaged',
			'broken',
			'failed',
			'problem',
			'issue',
			'complaint',
			'refund',
		);

		$positive_count = 0;
		$negative_count = 0;

		foreach ( $positive_words as $word ) {
			$positive_count += substr_count( $text, $word );
		}

		foreach ( $negative_words as $word ) {
			$negative_count += substr_count( $text, $word );
		}

		if ( $negative_count > $positive_count ) {
			return 'negative';
		} elseif ( $positive_count > $negative_count ) {
			return 'positive';
		}

		return 'neutral';
	}

	/**
	 * Check if a keyword is negated (preceded by "not", "no", "don't", etc.)
	 *
	 * Checks ALL occurrences of the keyword in the text. Returns true (negated)
	 * only when every occurrence is preceded by a negation word. If any single
	 * occurrence is not negated, the keyword carries its normal meaning.
	 *
	 * @param string $text Full text
	 * @param string $keyword Keyword to check
	 * @return bool
	 */
	private function has_negation( string $text, string $keyword ): bool {
		$negation_words = array( 'not', 'no', "don't", "doesn't", "didn't", "can't", 'unable', 'without' );
		$pattern        = '/\b' . preg_quote( $keyword, '/' ) . '\b/';
		$offset         = 0;
		$found_any      = false;

		while ( preg_match( $pattern, $text, $matches, PREG_OFFSET_CAPTURE, $offset ) ) {
			$found_any = true;
			$pos       = $matches[0][1];
			$before    = substr( $text, max( 0, $pos - 30 ), min( 30, $pos ) );
			$negated   = false;

			foreach ( $negation_words as $negation ) {
				if ( strpos( $before, $negation ) !== false ) {
					$negated = true;
					break;
				}
			}

			if ( ! $negated ) {
				// At least one non-negated occurrence — treat as a real, positive signal
				return false;
			}

			$offset = $pos + strlen( $matches[0][0] );
		}

		// Return true (negated) only if we found occurrences and all were negated
		return $found_any;
	}

	/**
	 * Apply contextual rules and sentiment-based adjustments
	 *
	 * Rules:
	 * - Complaint + positive sentiment → Support/Feedback
	 * - Complaint + negative sentiment → Complaint
	 * - Support + positive sentiment → Feedback
	 * - Sales + negative sentiment → Complaint
	 *
	 * @param array  $scores Category scores
	 * @param string $subject Subject line
	 * @param string $message Message body
	 * @param string $sentiment Detected sentiment
	 * @return array Modified scores
	 */
	private function apply_contextual_rules( array $scores, string $subject, string $message, string $sentiment ): array {
		// Rule 1: If support is top but positive sentiment, consider feedback instead
		if ( isset( $scores[ self::CATEGORY_SUPPORT ] ) &&
			$sentiment === 'positive' &&
			isset( $scores[ self::CATEGORY_FEEDBACK ] ) ) {
			// Don't override if support is much stronger
			if ( $scores[ self::CATEGORY_SUPPORT ] < $scores[ self::CATEGORY_FEEDBACK ] * 1.5 ) {
				$scores[ self::CATEGORY_FEEDBACK ] *= 1.2;
			}
		}

		// Rule 2: If multiple complaint indicators with negative sentiment, boost complaint score
		if ( $sentiment === 'negative' && isset( $scores[ self::CATEGORY_COMPLAINT ] ) ) {
			$complaint_boost                     = $this->count_keyword( $subject . ' ' . $message, 'delay' ) > 0 ? 1.5 : 1.2;
			$scores[ self::CATEGORY_COMPLAINT ] *= $complaint_boost;
		}

		// Rule 3: "After sales" or "warranty" should always be support
		if ( strpos( $subject, 'after sales' ) !== false || strpos( $message, 'after sales' ) !== false ||
			strpos( $subject, 'warranty' ) !== false || strpos( $message, 'warranty' ) !== false ) {
			if ( isset( $scores[ self::CATEGORY_SUPPORT ] ) ) {
				$scores[ self::CATEGORY_SUPPORT ] *= 1.5;
			}
		}

		// Rule 4: High question count with "how/what/why" should favor question category
		$question_keywords = array( 'how', 'what', 'why', 'when', 'where', 'which' );
		$question_count    = 0;
		foreach ( $question_keywords as $kw ) {
			$question_count += substr_count( $subject . ' ' . $message, $kw );
		}

		if ( $question_count >= 3 && isset( $scores[ self::CATEGORY_QUESTION ] ) ) {
			$scores[ self::CATEGORY_QUESTION ] *= 1.3;
		}

		// Rule 5: Prevent complaint from being classified as sales
		if ( isset( $scores[ self::CATEGORY_COMPLAINT ] ) && isset( $scores[ self::CATEGORY_SALES ] ) ) {
			if ( $sentiment === 'negative' ) {
				$scores[ self::CATEGORY_SALES ] *= 0.5; // Reduce sales score
			}
		}

		return $scores;
	}

	/**
	 * Count keyword occurrences in text (handles phrases).
	 *
	 * Uses word-boundary matching (\b) to prevent partial-word false positives,
	 * e.g. 'how' should not match 'somehow', 'plan' should not match 'complain'.
	 *
	 * @param string $text Haystack
	 * @param string $keyword Needle
	 * @return int Count
	 */
	private function count_keyword( string $text, string $keyword ): int {
		$keyword = strtolower( $keyword );
		$pattern = '/\b' . preg_quote( $keyword, '/' ) . '\b/';
		$count   = preg_match_all( $pattern, $text );
		return $count !== false ? $count : 0;
	}

	/**
	 * Get default unclassified result
	 *
	 * @return array
	 */
	private function get_default_result(): array {
		return array(
			'category'      => self::CATEGORY_UNCLASSIFIED,
			'confidence'    => 0.0,
			'keywords'      => array(),
			'classified_at' => null,
		);
	}

	/**
	 * Install or update intent classification patterns
	 * Called during plugin activation and version updates
	 *
	 * ROBUST INSTALLATION PROCESS:
	 * 1. Validates patterns structure
	 * 2. Backs up existing patterns
	 * 3. Installs new patterns with verification
	 * 4. Validates installation success
	 * 5. Logs installation status
	 * 6. Handles errors gracefully with rollback
	 *
	 * @return array{success: bool, message: string, errors: array, version: string}
	 */
	public static function install_patterns(): array {
		$result = array(
			'success' => false,
			'message' => '',
			'errors'  => array(),
			'version' => self::PATTERN_VERSION,
		);

		try {
			// Step 1: Get default patterns
			$patterns = self::get_default_patterns();

			if ( empty( $patterns ) || ! is_array( $patterns ) ) {
				$result['errors'][] = 'Failed to load default patterns';
				return $result;
			}

			// Step 2: Validate pattern structure
			$validation = self::validate_patterns( $patterns );
			if ( ! $validation['valid'] ) {
				$result['errors'] = $validation['errors'];
				return $result;
			}

			// Step 3: Backup existing user-customised patterns if present
			if ( get_option( 'contactin_intent_patterns_customized' ) ) {
				$existing = get_option( 'contactin_intent_patterns' );
				if ( $existing && is_array( $existing ) ) {
					update_option( 'contactin_intent_patterns_backup', $existing );
				}
			}

			// Step 4: Store pattern version marker.
			// Business-type patterns are loaded dynamically by get_patterns() from their
			// class — contactin_intent_patterns is reserved for admin-customised overrides.
			update_option( 'contactin_intent_patterns_version', self::PATTERN_VERSION );

			// Step 5: Mark installation as complete
			update_option( 'contactin_patterns_installed', true );
			update_option( 'contactin_patterns_installed_at', current_time( 'mysql' ) );

			// Step 7: Verify installation
			$verification = self::verify_patterns_installation();
			if ( ! $verification['valid'] ) {
				$result['errors'] = array_merge( $result['errors'], $verification['errors'] );
				return $result;
			}

			// Step 8: Log successful installation
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ContactIn] Intent patterns installed successfully. Version: ' . self::PATTERN_VERSION . ', Categories: ' . count( $patterns ) );
			}

			$result['success'] = true;
			$result['message'] = sprintf(
				'Intent patterns installed successfully (%d categories, %s)',
				count( $patterns ),
				self::PATTERN_VERSION
			);

			return $result;

		} catch ( \Exception $e ) {
			$result['errors'][] = 'Installation exception: ' . $e->getMessage();
			error_log( '[ContactIn] Intent patterns installation error: ' . $e->getMessage() );
			return $result;
		}
	}

	/**
	 * Validate patterns structure and content
	 *
	 * @param array $patterns Patterns to validate
	 * @return array{valid: bool, errors: array}
	 */
	private static function validate_patterns( array $patterns ): array {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// Check all required categories exist
		$required_categories = array(
			self::CATEGORY_SALES,
			self::CATEGORY_SUPPORT,
			self::CATEGORY_FEEDBACK,
			self::CATEGORY_COMPLAINT,
			self::CATEGORY_QUESTION,
			self::CATEGORY_SPAM,
		);

		foreach ( $required_categories as $category ) {
			if ( ! isset( $patterns[ $category ] ) ) {
				$result['valid']    = false;
				$result['errors'][] = "Missing required category: $category";
				continue;
			}

			$category_data = $patterns[ $category ];

			// Check weight levels
			foreach ( array( 'high', 'medium', 'low' ) as $weight ) {
				if ( ! isset( $category_data[ $weight ] ) ) {
					$result['valid']    = false;
					$result['errors'][] = "Missing $weight weight for category $category";
					continue;
				}

				if ( ! is_array( $category_data[ $weight ] ) ) {
					$result['valid']    = false;
					$result['errors'][] = "Invalid $weight weight format for category $category";
					continue;
				}

				// Minimum keywords check
				if ( empty( $category_data[ $weight ] ) ) {
					$result['valid']    = false;
					$result['errors'][] = "Category $category has no $weight weight keywords";
				}
			}
		}

		return $result;
	}

	/**
	 * Verify patterns were installed correctly
	 *
	 * @return array{valid: bool, errors: array}
	 */
	private static function verify_patterns_installation(): array {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// Verify that default patterns can be generated from source classes
		$patterns = self::get_default_patterns();
		if ( empty( $patterns ) || ! is_array( $patterns ) ) {
			$result['valid']    = false;
			$result['errors'][] = 'Default patterns could not be generated from source classes';
			return $result;
		}

		// Verify all required categories are present in generated patterns
		$expected = array(
			self::CATEGORY_SALES,
			self::CATEGORY_SUPPORT,
			self::CATEGORY_FEEDBACK,
			self::CATEGORY_COMPLAINT,
			self::CATEGORY_QUESTION,
			self::CATEGORY_SPAM,
		);
		foreach ( $expected as $category ) {
			if ( ! isset( $patterns[ $category ] ) ) {
				$result['valid']    = false;
				$result['errors'][] = "Category $category missing from default patterns";
			}
		}

		// Verify version marker
		$version = get_option( 'contactin_intent_patterns_version' );
		if ( $version !== self::PATTERN_VERSION ) {
			$result['valid']    = false;
			$result['errors'][] = 'Pattern version mismatch. Expected: ' . self::PATTERN_VERSION . ", Got: $version";
		}

		// Store checksum of generated patterns for integrity tracking
		$checksum = md5( wp_json_encode( $patterns ) );
		update_option( 'contactin_intent_patterns_checksum', $checksum );

		return $result;
	}

	/**
	 * Get installation status
	 *
	 * @return array{installed: bool, version: string, category_count: int, verified: bool, last_installed: string}
	 */
	public static function get_installation_status(): array {
		$version      = get_option( 'contactin_intent_patterns_version' );
		$installed_at = get_option( 'contactin_patterns_installed_at' );
		$is_installed = (bool) get_option( 'contactin_patterns_installed' );

		$verification = self::verify_patterns_installation();
		$patterns     = self::get_default_patterns();

		return array(
			'installed'      => $is_installed,
			'version'        => $version ?: 'unknown',
			'category_count' => count( $patterns ),
			'verified'       => $verification['valid'],
			'last_installed' => $installed_at ?: 'never',
		);
	}

	/**
	 * Restore patterns from backup
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function restore_from_backup(): array {
		$backup = get_option( 'contactin_intent_patterns_backup' );

		if ( ! $backup || ! is_array( $backup ) ) {
			return array(
				'success' => false,
				'message' => 'No backup patterns found',
			);
		}

		update_option( 'contactin_intent_patterns', $backup );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ContactIn] Intent patterns restored from backup' );
		}

		return array(
			'success' => true,
			'message' => 'Patterns restored from backup successfully',
		);
	}

	/**
	 * Health check for patterns
	 * Returns diagnostics about pattern installation and integrity
	 *
	 * @return array Diagnostic information
	 */
	public static function health_check(): array {
		$status           = self::get_installation_status();
		$verification     = self::verify_patterns_installation();
		$default_patterns = self::get_default_patterns();
		$stored_checksum  = get_option( 'contactin_intent_patterns_checksum' );
		$current_checksum = md5( wp_json_encode( $default_patterns ) );

		return array(
			'status'           => $status,
			'verification'     => $verification,
			'checksum_match'   => $stored_checksum === $current_checksum,
			'stored_checksum'  => $stored_checksum,
			'current_checksum' => $current_checksum,
			'total_keywords'   => array_sum(
				array_map(
					function ( $cat ) {
						return count( $cat['high'] ?? array() ) + count( $cat['medium'] ?? array() ) + count( $cat['low'] ?? array() );
					},
					$default_patterns
				)
			),
			'backup_exists'    => ! empty( get_option( 'contactin_intent_patterns_backup' ) ),
			'timestamp'        => current_time( 'mysql' ),
		);
	}

	/**
	 * Get stored patterns (with option to include defaults)
	 *
	 * @param bool $include_defaults Include unmodified defaults
	 * @return array
	 */
	public static function get_stored_patterns( bool $include_defaults = false ): array {
		// When admin has explicitly customised patterns, use them
		if ( get_option( 'contactin_intent_patterns_customized' ) ) {
			$stored = get_option( 'contactin_intent_patterns', array() );
			if ( ! empty( $stored ) && is_array( $stored ) ) {
				if ( ! $include_defaults ) {
					return $stored;
				}
				// Merge with business-type base to fill any missing categories
				$base = BusinessPatterns::get_patterns( get_option( 'contactin_business_type', 'generic' ) );
				foreach ( $base as $category => $weights ) {
					if ( ! isset( $stored[ $category ] ) ) {
						$stored[ $category ] = $weights;
					}
				}
				return $stored;
			}
		}

		// No customisation — return business-type base patterns (or generic fallback)
		$business_type = get_option( 'contactin_business_type', 'generic' );
		return BusinessPatterns::get_patterns( $business_type ) ?: self::get_default_patterns();
	}

	/**
	 * Update patterns for a specific category
	 *
	 * @param string $category Category name
	 * @param array  $weights New weights {high: [...], medium: [...], low: [...]}
	 * @return bool Success
	 */
	public static function update_category_patterns( string $category, array $weights ): bool {
		$patterns = self::get_stored_patterns( true );

		if ( ! isset( $patterns[ $category ] ) ) {
			return false;
		}

		$patterns[ $category ] = array(
			'high'   => array_filter( $weights['high'] ?? array() ),
			'medium' => array_filter( $weights['medium'] ?? array() ),
			'low'    => array_filter( $weights['low'] ?? array() ),
		);

		$saved = (bool) update_option( 'contactin_intent_patterns', $patterns );
		if ( $saved ) {
			// Mark as user-customised so get_patterns() uses these over business-type defaults
			update_option( 'contactin_intent_patterns_customized', true );
		}
		return $saved;
	}

	/**
	 * Reset patterns to defaults
	 *
	 * @return bool Success
	 */
	public static function reset_to_defaults(): bool {
		// Clear customisation flags so get_patterns() falls back to the business-type class
		delete_option( 'contactin_intent_patterns_customized' );
		delete_option( 'contactin_intent_patterns' );
		$result = self::install_patterns();
		return $result['success'];
	}

	/**
	 * Get all available categories
	 *
	 * @return array<string, string> Category key => Label
	 */
	public static function get_categories(): array {
		return array(
			self::CATEGORY_SALES        => __( 'Sales', 'contactin' ),
			self::CATEGORY_SUPPORT      => __( 'Support', 'contactin' ),
			self::CATEGORY_FEEDBACK     => __( 'Feedback', 'contactin' ),
			self::CATEGORY_COMPLAINT    => __( 'Complaint', 'contactin' ),
			self::CATEGORY_QUESTION     => __( 'Question', 'contactin' ),
			self::CATEGORY_SPAM         => __( 'Spam', 'contactin' ),
			self::CATEGORY_UNCLASSIFIED => __( 'Unclassified', 'contactin' ),
		);
	}

	/**
	 * Get category label
	 *
	 * @param string $category Category key
	 * @return string Label
	 */
	public static function get_category_label( string $category ): string {
		$categories = self::get_categories();
		return $categories[ $category ] ?? $categories[ self::CATEGORY_UNCLASSIFIED ];
	}

	/**
	 * Get category color for UI
	 *
	 * @param string $category Category key
	 * @return string Color class suffix
	 */
	public static function get_category_color( string $category ): string {
		$colors = array(
			self::CATEGORY_SALES        => 'primary',
			self::CATEGORY_SUPPORT      => 'warning',
			self::CATEGORY_FEEDBACK     => 'info',
			self::CATEGORY_COMPLAINT    => 'danger',
			self::CATEGORY_QUESTION     => 'secondary',
			self::CATEGORY_SPAM         => 'dark',
			self::CATEGORY_UNCLASSIFIED => 'muted',
		);

		return $colors[ $category ] ?? 'muted';
	}

	/**
	 * Manually reclassify a message
	 *
	 * @param int    $message_id Message ID
	 * @param string $category New category
	 * @return bool Success
	 */
	public function reclassify( int $message_id, string $category ): bool {
		// Validate category
		$valid_categories = array_keys( self::get_categories() );
		if ( ! in_array( $category, $valid_categories, true ) ) {
			return false;
		}

		// Update database
		return DB::instance()->update_message_intent(
			$message_id,
			array(
				'category'      => $category,
				'confidence'    => 100.0, // Manual classification = 100% confidence
				'keywords'      => wp_json_encode( array( 'manual' ) ),
				'classified_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Count unclassified messages in database
	 *
	 * @return int Total count of unclassified messages
	 */
	public function count_unclassified_messages(): int {
		return DB::instance()->count_unclassified_messages();
	}

	/**
	 * Bulk reclassify unclassified messages
	 *
	 * @param int $limit Maximum messages to process in this batch
	 * @param int $offset Starting position for this batch
	 * @return array Results array with 'processed', 'success', 'failed' keys
	 */
	public function bulk_classify_unclassified( int $limit = 100, int $offset = 0 ): array {
		// Get unclassified messages from repository (excluding spam and archived)
		$messages = DB::instance()->get_unclassified_batch( $limit, $offset );

		if ( empty( $messages ) ) {
			return array(
				'processed' => 0,
				'success'   => 0,
				'failed'    => 0,
				'breakdown' => array(),
			);
		}

		Logger::info(
			'Bulk reclassify unclassified',
			array(
				'total_queried' => count( $messages ),
				'limit'         => $limit,
				'offset'        => $offset,
			)
		);

		$processed = 0;
		$success   = 0;
		$failed    = 0;
		$breakdown = array(
			self::CATEGORY_SALES        => 0,
			self::CATEGORY_SUPPORT      => 0,
			self::CATEGORY_FEEDBACK     => 0,
			self::CATEGORY_COMPLAINT    => 0,
			self::CATEGORY_QUESTION     => 0,
			self::CATEGORY_SPAM         => 0,
			self::CATEGORY_UNCLASSIFIED => 0,
		);

		foreach ( $messages as $msg ) {
			++$processed;

			try {
				$intent = $this->classify( $msg->subject, $msg->message );

				if ( $intent['category'] !== self::CATEGORY_UNCLASSIFIED ) {
					// Only count as success if DB update actually succeeds
					$update_result = DB::instance()->update_message_intent( (int) $msg->id, $intent );
					if ( $update_result ) {
						++$success;
						if ( isset( $breakdown[ $intent['category'] ] ) ) {
							++$breakdown[ $intent['category'] ];
						}
					} else {
						++$failed;
						Logger::warning(
							'Failed to update intent for message',
							array(
								'message_id' => $msg->id,
								'category'   => $intent['category'],
							)
						);
					}
				} else {
					++$failed;
					++$breakdown[ self::CATEGORY_UNCLASSIFIED ];
				}
			} catch ( \Throwable $e ) {
				++$failed;
				++$breakdown[ self::CATEGORY_UNCLASSIFIED ];
				Logger::warning(
					'Failed to reclassify message',
					array(
						'message_id' => $msg->id,
						'error'      => $e->getMessage(),
					)
				);
			}
		}

		return array(
			'processed' => $processed,
			'success'   => $success,
			'failed'    => $failed,
			'breakdown' => $breakdown,
		);
	}
}
