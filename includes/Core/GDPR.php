<?php
namespace ContactInbox\Core;

/**
 * GDPR stub - PRO feature removed in free version
 */
final class GDPR {
    const EXPIRATION_DAYS = 7;

    public static function generate_token(int $message_id) {
        return false;
    }

    public static function build_delete_link(string $token, string $email): string {
        return '';
    }

    public static function validate_token(string $token): bool {
        return false;
    }

    public static function delete_by_token(string $token): bool {
        return false;
    }
}
