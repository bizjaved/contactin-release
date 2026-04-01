<?php
/**
 * Telecom Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TelecomPatterns extends AbstractBusinessPattern {

	public static function get_patterns(): array {
		$base = self::base();

		$base['sales']['high'] = array_merge(
			$base['sales']['high'],
			array(
				// Plans & Services
				'mobile plan',
				'postpaid plan',
				'prepaid recharge',
				'data plan',
				'unlimited data plan',
				'broadband plan',
				'fiber internet plan',
				'home wifi',
				'business internet',
				'leased line',
				'sim card',
				'esim activation',
				'new connection',

				// Upgrades & Add-ons
				'plan upgrade',
				'speed upgrade',
				'add-on data',
				'international roaming',
				'family plan',
				'corporate telecom account',

				// Devices/Bundle
				'phone bundle offer',
				'router installation',
				'modem setup',
				'set top box package',
				'iptv package',
				'triple play bundle',
			)
		);

		$base['sales']['medium'] = array_merge(
			$base['sales']['medium'] ?? array(),
			array(
				'best internet plan for home',
				'compare broadband plans',
				'best prepaid offer',
				'network coverage in my area',
				'port number to your network',
				'mnp request',
				'corporate bulk sim',
				'discount for annual plan',
			)
		);

		$base['support']['high'] = array_merge(
			$base['support']['high'],
			array(
				// Connectivity Outages
				'internet down',
				'no internet',
				'broadband not working',
				'fiber down',
				'network outage',
				'service outage',
				'slow internet speed',
				'high latency',
				'packet loss',

				// Mobile/Voice Issues
				'call drop issue',
				'no signal',
				'weak network',
				'cannot make calls',
				'cannot receive calls',
				'sms not working',
				'otp not received',

				// Account/Billing
				'billing dispute telecom',
				'wrong bill amount',
				'unexpected data charges',
				'roaming charges dispute',
				'payment failed recharge',
				'account suspended',

				// Technical/Installation
				'router not working',
				'modem red light',
				'installation delay',
				'technician not arrived',
				'connection activation pending',
			)
		);

		$base['support']['medium'] = array_merge(
			$base['support']['medium'] ?? array(),
			array(
				'sim replacement request',
				'esim qr not working',
				'wifi password reset',
				'change billing cycle',
				'static ip request',
				'port forwarding support',
				'address change for connection',
				'connection relocation',
			)
		);

		$base['feedback']['high'] = array_merge(
			$base['feedback']['high'] ?? array(),
			array(
				'better network stability',
				'improve coverage quality',
				'faster issue resolution',
				'better outage communication',
				'transparent fair usage policy',
				'clear speed guarantees',
				'better customer support escalation',
				'improve app self-service',
			)
		);

		$base['feedback']['medium'] = array_merge(
			$base['feedback']['medium'] ?? array(),
			array(
				'better value plans',
				'more flexible add-ons',
				'better roaming packs',
				'clearer billing breakdown',
				'faster technician scheduling',
				'better installation experience',
			)
		);

		$base['complaint']['high'] = array_merge(
			$base['complaint']['high'],
			array(
				'frequent internet outages',
				'repeated call drops',
				'speed far below promised',
				'false unlimited claim',
				'hidden telecom charges',
				'unauthorized vas activation',
				'bill shock',
				'poor complaint handling telecom',
				'service terminated without notice',
				'no refund after cancellation',
			)
		);

		$base['complaint']['medium'] = array_merge(
			$base['complaint']['medium'] ?? array(),
			array(
				'slow support response',
				'ticket not resolved',
				'technician missed appointment',
				'inconsistent speeds',
				'unclear policy terms',
				'poor communication from provider',
			)
		);

		$base['question']['high'] = array_merge(
			$base['question']['high'] ?? array(),
			array(
				'what plans are available',
				'what speed will i get',
				'how to upgrade plan',
				'how to port my number',
				'how long installation takes',
				'what are installation charges',
				'how to cancel connection',
				'what is contract lock-in period',
				'is router included',
				'is static ip available',
			)
		);

		$base['question']['medium'] = array_merge(
			$base['question']['medium'] ?? array(),
			array(
				'which plan is best for streaming',
				'which plan is best for gaming',
				'is fair usage policy applied',
				'do you provide ipv6',
				'can i pause my connection',
				'what is reconnection fee',
				'is international roaming active by default',
				'how to block premium services',
			)
		);

		$base['spam']['high'] = array_merge(
			$base['spam']['high'] ?? array(),
			array(
				'your sim will be blocked click link',
				'update kyc now telecom scam',
				'fake recharge cashback link',
				'share otp to reactivate number',
				'phishing telecom app login',
				'free unlimited data hack',
				'fake tower installation investment scheme',
			)
		);

		return $base;
	}
}
