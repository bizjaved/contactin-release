<?php
namespace ContactInbox\Cli;

use WP_CLI;
use ContactInbox\Core\CRMFieldMapper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI commands for CRM integration testing.
 */
final class CRMCommand
{
    /**
     * Test name splitting and mapping.
     *
     * ## OPTIONS
     *
     * <full-name>
     * : The full name string to test.
     *
     * ## EXAMPLES
     *
     *     wp contactin crm:test "Alice Marie Johnson"
     *
     * @when after_wp_load
     */
    public function test($args, $assoc_args)
    {
        $fullName = $args[0] ?? '';
        $parts = CRMFieldMapper::split_name($fullName);

        WP_CLI::success("Testing name split for: {$fullName}");
        WP_CLI::log("First:  {$parts['first']}");
        WP_CLI::log("Middle: {$parts['middle']}");
        WP_CLI::log("Last:   {$parts['last']}");
    }
}
