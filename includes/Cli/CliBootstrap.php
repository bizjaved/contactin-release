<?php
namespace ContactInbox\Cli;

use ContactInbox\Traits\Singleton;

final class CliBootstrap {
    use Singleton;

    public function register(): void {
        // CLI commands are a Pro feature
        // Free version has no CLI commands
    }
}
