<?php
namespace ContactInbox\Cli;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class CliBootstrap {
	use Singleton;

	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			// CRM CLI command is disabled in this build.
		}
	}
}
