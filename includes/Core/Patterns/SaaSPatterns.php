<?php
/**
 * Saas Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SaaSPatterns extends AbstractBusinessPattern {

	public static function get_patterns(): array {
		$base = self::base();

		// Customize for SaaS - expanded keywords
		$base['sales']['high'] = array_merge(
			$base['sales']['high'],
			array(
				// API & Integration
				'api',
				'rest api',
				'graphql',
				'api key',
				'api endpoint',
				'integration',
				'third-party integration',
				'webhook',
				'webhooks',
				'workflow automation',
				'zapier',
				'integromat',
				'make',
				'automation platform',

				// Authentication & Access
				'sso',
				'single sign-on',
				'oauth',
				'oauth2',
				'saml',
				'two-factor authentication',
				'2fa',
				'mfa',
				'multi-factor authentication',
				'enterprise security',
				'access control',
				'role-based access',
				'rbac',
				'permissions',

				// Deployment & Infrastructure
				'deployment',
				'cloud',
				'aws',
				'azure',
				'google cloud',
				'gcp',
				'on-premise',
				'on-prem',
				'self-hosted',
				'docker',
				'kubernetes',
				'container',
				'microservices',
				'serverless',
				'infrastructure',
				'scalability',
				'scalic',
				'uptime sla',
				'sla',
				'availability',

				// Licensing & Seats
				'user seats',
				'concurrent users',
				'parallel users',
				'seat pricing',
				'per-user pricing',
				'team plan',
				'team license',
				'bulk license',
				'volume pricing',
				'enterprise plan',
				'enterprise license',
				'monthly license',
				'annual license',
				'licensing model',

				// Data & Migration
				'data migration',
				'data import',
				'bulk import',
				'csv import',
				'data export',
				'bulk export',
				'data transfer',
				'migration service',
				'legacy system',
				'system migration',
				'upgrade path',

				// Collaboration & Features
				'collaboration features',
				'team collaboration',
				'shared workspace',
				'real-time collaboration',
				'version control',
				'api documentation',
			)
		);

		$base['sales']['medium'] = array_merge(
			$base['sales']['medium'] ?? array(),
			array(
				'framework',
				'technology stack',
				'tech stack',
				'open source',
				'supported languages',
				'programming language',
				'development tools',
				'sandbox environment',
				'test account',
				'staging',
				'production',
				'sso pricing',
				'advanced analytics',
				'reporting feature',
				'custom branding',
				'white label',
				'customization',
				'service level agreement',
				'support tier',
				'response time',
			)
		);

		$base['support']['high'] = array_merge(
			$base['support']['high'],
			array(
				// API & Integration Issues
				'api error',
				'api failure',
				'api not working',
				'api broken',
				'webhook failed',
				'webhook not firing',
				'webhook delivery',
				'integration not working',
				'integration broken',
				'integration failed',
				'sync issue',
				'sync failed',
				'sync not working',
				'data sync',
				'rate limit',
				'rate limiting',
				'quota exceeded',
				'request throttled',

				// Authentication Issues
				'account locked',
				'locked out',
				'cannot login',
				'login failed',
				'password reset',
				'forgot password',
				'password recovery',
				'login not working',
				'authentication error',
				'auth failure',
				'sso not working',
				'saml error',
				'oauth error',
				'401 error',
				'403 error',
				'unauthorized',
				'forbidden',

				// Data & Performance Issues
				'data loss',
				'lost data',
				'data corruption',
				'data incomplete',
				'missing data',
				'data mismatch',
				'data inconsistency',
				'performance issue',
				'slow performance',
				'slow loading',
				'slow response',
				'timeout',
				'connection timeout',
				'request timeout',
				'database timeout',
				'connection error',
				'connection lost',
				'connection refused',
				'network error',
				'latency',
				'lag',

				// Deployment & Technical Issues
				'deployment failed',
				'deployment issue',
				'deployment error',
				'build failed',
				'build error',
				'compilation error',
				'version incompatibility',
				'backward compatibility',
				'upgrade issue',
				'downtime',
				'outage',
				'service unavailable',
				'error 500',
				'502 error',
				'503 error',
				'gateway error',

				// Access & Permission Issues
				'access denied',
				'permission denied',
				'insufficient permissions',
				'cannot access',
				'access issue',
				'access error',
			)
		);

		$base['support']['medium'] = array_merge(
			$base['support']['medium'] ?? array(),
			array(
				'feature not working',
				'feature broken',
				'feature issue',
				'button not working',
				'form not submitting',
				'form issue',
				'export not working',
				'import not working',
				'download issue',
				'browser compatibility',
				'mobile app issue',
				'desktop app issue',
				'configuration issue',
				'setup issue',
				'installation issue',
				'update issue',
				'upgrade problem',
				'migration issue',
				'add-on not working',
				'plugin issue',
				'extension not loading',
				'notification not received',
				'email not received',
				'alert issue',
			)
		);

		$base['feedback']['high'] = array_merge(
			$base['feedback']['high'],
			array(
				// API & Documentation
				'api endpoint',
				'api documentation',
				'api docs',
				'api reference',
				'code example',
				'sample code',
				'code snippet',
				'documentation missing',
				'sdk',
				'sdk documentation',
				'client library',
				'language support',
				'python sdk',
				'javascript sdk',
				'java sdk',
				'go sdk',
				'ruby sdk',

				// Features & Automation
				'batch processing',
				'batch operations',
				'bulk operations',
				'webhooks',
				'event streaming',
				'event-driven',
				'real-time updates',
				'automation',
				'workflow automation',
				'scheduled tasks',
				'cron jobs',
				'reporting',
				'advanced reporting',
				'custom reports',
				'report builder',
				'analytics',
				'usage analytics',
				'performance metrics',
				'dashboard',
				'export options',
				'export formats',
				'csv export',
				'json export',

				// User Experience & Interface
				'user interface',
				'ui design',
				'ux improvement',
				'onboarding',
				'search functionality',
				'filter options',
				'sorting',
				'pagination',
				'dark mode',
				'keyboard shortcuts',
				'accessibility',
				'mobile responsive',
				'performance optimization',
				'load time',
				'response time',

				// Team & Collaboration Features
				'team features',
				'team management',
				'user roles',
				'permissions system',
				'audit log',
				'activity log',
				'change history',
				'version history',
				'collaboration tools',
				'commenting',
				'notifications',
				'alerts',
				'sharing features',
				'shared resources',
				'workspace management',
			)
		);

		$base['feedback']['medium'] = array_merge(
			$base['feedback']['medium'] ?? array(),
			array(
				'ui improvements',
				'design suggestion',
				'feature request',
				'additional field',
				'custom field',
				'custom attribute',
				'integration wanted',
				'third-party support',
				'plugin',
				'extension',
				'language support',
				'localization',
				'multi-language',
				'roadmap',
				'future plans',
				'beta feature',
				'documentation improvement',
				'tutorial',
				'guide needed',
				'training',
				'webinar',
				'certification',
			)
		);

		$base['complaint']['high'] = array_merge(
			$base['complaint']['high'],
			array(
				// Security & Data
				'security breach',
				'security incident',
				'data breach',
				'data leak',
				'unauthorized access',
				'breach of privacy',
				'privacy violation',
				'gdpr violation',
				'gdpr compliance',
				'data protection',
				'compliance issue',
				'customer data exposure',
				'data exposure',
				'data vulnerability',
				'encryption',
				'ssl certificate',
				'tls',
				'security flaw',
				'vulnerability',
				'security risk',
				'security patch needed',

				// Billing & Service
				'unexpected charge',
				'unauthorized charge',
				'billing error',
				'overcharge',
				'duplicate charge',
				'billing issue',
				'payment issue',
				'subscription issue',
				'cancellation issue',
				'refund issue',
				'service degradation',
				'poor service',
				'unreliable service',
				'constant downtime',
				'frequent outages',
				'service quality',

				// Support & Communication
				'no support response',
				'slow support',
				'poor support',
				'unhelpful support',
				'support not responding',
				'ignored ticket',
				'unresolved issue',
				'missed deadline',
				'missed sla',
				'sla violation',
			)
		);

		$base['complaint']['medium'] = array_merge(
			$base['complaint']['medium'] ?? array(),
			array(
				'feature removed',
				'deprecated feature',
				'breaking change',
				'api change',
				'api deprecation',
				'incompatible change',
				'undocumented change',
				'no migration guide',
				'poor documentation',
				'confusing interface',
				'poor ux',
				'missing feature',
				'expensive',
				'overpriced',
				'poor value',
				'value for money',
				'better alternatives',
				'switch to competitor',
			)
		);

		$base['question']['high'] = array_merge(
			$base['question']['high'],
			array(
				'how do i use api',
				'how to integrate',
				'how to connect',
				'how to authenticate',
				'how to deploy',
				'how to scale',
				'what is webhook',
				'what is sso',
				'what is saml',
				'what is oauth',
				'where is api documentation',
				'where is sdk',
				'where is example',
				'which language supported',
				'which framework supported',
				'can i use docker',
				'can i use kubernetes',
				'can i host on-premise',
				'how to migrate data',
				'how to export data',
				'how to import data',
				'how to set permissions',
				'how to manage users',
			)
		);

		$base['question']['medium'] = array_merge(
			$base['question']['medium'] ?? array(),
			array(
				'what is supported',
				'what requirements',
				'what compatibility',
				'how long does migration take',
				'how much data can i store',
				'what are limits',
				'what quota',
				'rate limits',
				'how to troubleshoot',
				'how to debug',
				'how to monitor',
				'is there api limit',
				'is there storage limit',
			)
		);

		$base['spam']['high'] = array_merge(
			$base['spam']['high'],
			array(
				'free api',
				'free credits',
				'free trial forever',
				'unlimited free',
				'make money with api',
				'side hustle',
				'passive income coding',
				'crack the system',
				'bypass authentication',
				'exploit vulnerability',
				'download crack',
				'pirated software',
				'keygen',
				'license crack',
				'stolen api key',
				'leaked credentials',
				'free account access',
			)
		);

		return $base;
	}
}
