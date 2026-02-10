<?php
/**
 * Upgrade Modal Template
 * 
 * Displayed for Pro-only features in the free version
 * 
 * @package ContactInbox
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContactInbox\Core\Config;
?>

<div class="cin-upgrade-modal">
    <div class="cin-upgrade-modal-content">
        <h2><?php esc_html_e( 'Premium Feature', Config::TEXTDOMAIN ); ?></h2>
        <p><?php esc_html_e( 'This feature is available in Contact Inbox Pro.', Config::TEXTDOMAIN ); ?></p>
        <a href="https://wordpress.org/plugins/contact-inbox-pro/" target="_blank" rel="noopener noreferrer" class="button button-primary">
            <?php esc_html_e( 'Get Premium', Config::TEXTDOMAIN ); ?>
        </a>
    </div>
</div>

<style>
.cin-upgrade-modal {
    padding: 40px 20px;
    text-align: center;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 8px;
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cin-upgrade-modal-content {
    max-width: 400px;
}

.cin-upgrade-modal-content h2 {
    font-size: 28px;
    margin-bottom: 15px;
    color: #2c3e50;
}

.cin-upgrade-modal-content p {
    font-size: 16px;
    color: #555;
    margin-bottom: 25px;
}

.cin-upgrade-modal-content .button {
    padding: 12px 30px;
    font-size: 14px;
    text-decoration: none;
}
</style>
