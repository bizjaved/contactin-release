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
			\WP_CLI::add_command( 'contactin crm', \ContactInbox\Cli\CRMCommand::class );
		}
	}
}
