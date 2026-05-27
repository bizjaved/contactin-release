<?php
/**
 * Template: Get Started / Onboarding Page
 */

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cin-get-started-wrap">
	<div class="cin-get-started-header">
		<div class="cin-gs-header-content">
			<h1><?php esc_html_e( 'Welcome to ContactIn! 🎉', 'contactin' ); ?></h1>
			<p class="cin-gs-subtitle"><?php esc_html_e( 'Let\'s get you set up in just 2 simple steps', 'contactin' ); ?></p>
		</div>
	</div>

	<div class="cin-get-started-container">
		
		<!-- Card 1: Setup Contact Form -->
		<div class="cin-gs-card">
			<div class="cin-gs-card-header">
				<div class="cin-gs-card-icon cin-gs-icon-form">
					<span class="dashicons dashicons-feedback"></span>
				</div>
				<div>
					<h2><?php esc_html_e( '1. Add Contact Form to a Page', 'contactin' ); ?></h2>
					<p class="cin-gs-card-desc"><?php esc_html_e( 'Display the contact form on any page or post using a shortcode', 'contactin' ); ?></p>
				</div>
			</div>
			
			<div class="cin-gs-card-content">
				<div class="cin-gs-step">
					<div class="cin-gs-step-number">1</div>
					<div class="cin-gs-step-content">
						<h3><?php esc_html_e( 'Create or edit a page', 'contactin' ); ?></h3>
						<p><?php esc_html_e( 'Go to Pages → Add New or edit an existing page where you want the contact form', 'contactin' ); ?></p>
					</div>
				</div>

				<div class="cin-gs-step">
					<div class="cin-gs-step-number">2</div>
					<div class="cin-gs-step-content">
						<h3><?php esc_html_e( 'Add the contact form', 'contactin' ); ?></h3>
						<p><?php esc_html_e( 'Choose any of the three methods below:', 'contactin' ); ?></p>

						<p style="margin:10px 0 4px;"><strong><?php esc_html_e( 'Shortcode', 'contactin' ); ?></strong> &mdash; <?php esc_html_e( 'paste into any page, post, or text widget:', 'contactin' ); ?></p>
						<div class="cin-gs-shortcode-box">
							<code>[contactin_form]</code>
							<button type="button" class="cin-gs-copy-btn" data-clipboard="[contactin_form]">
								<span class="dashicons dashicons-admin-page"></span>
								<span class="cin-copy-text"><?php esc_html_e( 'Copy', 'contactin' ); ?></span>
							</button>
						</div>

						<p style="margin:12px 0 4px;"><strong><?php esc_html_e( 'Gutenberg Block', 'contactin' ); ?></strong> &mdash; <?php esc_html_e( 'in the block editor, click the + inserter and search for', 'contactin' ); ?> <em><?php esc_html_e( 'ContactIn Form', 'contactin' ); ?></em>.</p>

						<p style="margin:8px 0 4px;"><strong><?php esc_html_e( 'Elementor Widget', 'contactin' ); ?></strong> &mdash; <?php esc_html_e( 'in Elementor, search for', 'contactin' ); ?> <em><?php esc_html_e( 'ContactIn Form', 'contactin' ); ?></em> <?php esc_html_e( 'in the widget panel and drag it onto your page.', 'contactin' ); ?></p>
					</div>
				</div>

				<div class="cin-gs-step">
					<div class="cin-gs-step-number">3</div>
					<div class="cin-gs-step-content">
						<h3><?php esc_html_e( 'Publish and view', 'contactin' ); ?></h3>
						<p><?php esc_html_e( 'Save/publish your page and visit it to see your contact form in action!', 'contactin' ); ?></p>
					</div>
				</div>

				<div class="cin-gs-card-footer">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>" class="cin-gs-btn cin-gs-btn-primary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Create New Page', 'contactin' ); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- Card 2: Plugin Settings -->
		<div class="cin-gs-card">
			<div class="cin-gs-card-header">
				<div class="cin-gs-card-icon cin-gs-icon-settings">
					<span class="dashicons dashicons-admin-settings"></span>
				</div>
				<div>
					<h2><?php esc_html_e( '2. Configure Plugin Settings', 'contactin' ); ?></h2>
					<p class="cin-gs-card-desc"><?php esc_html_e( 'Customize your contact form and email notifications', 'contactin' ); ?></p>
				</div>
			</div>
			
			<div class="cin-gs-card-content">
				<div class="cin-gs-feature-list">
					<div class="cin-gs-feature">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<strong><?php esc_html_e( 'Email Configuration', 'contactin' ); ?></strong>
							<p><?php esc_html_e( 'Set up SMTP server for reliable email delivery', 'contactin' ); ?></p>
						</div>
					</div>

					<div class="cin-gs-feature">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<strong><?php esc_html_e( 'Form Customization', 'contactin' ); ?></strong>
							<p><?php esc_html_e( 'Enable subject field, salutation, and file attachments', 'contactin' ); ?></p>
						</div>
					</div>

					<div class="cin-gs-feature">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<strong><?php esc_html_e( 'Spam Protection', 'contactin' ); ?></strong>
							<p><?php esc_html_e( 'Configure reCAPTCHA v3 to prevent spam submissions', 'contactin' ); ?></p>
						</div>
					</div>

					<div class="cin-gs-feature">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<strong><?php esc_html_e( 'Privacy & GDPR', 'contactin' ); ?></strong>
							<p><?php esc_html_e( 'Customize consent text and privacy policy link', 'contactin' ); ?></p>
						</div>
					</div>

					<div class="cin-gs-feature">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<strong><?php esc_html_e( 'AI Intent Classification', 'contactin' ); ?></strong>
							<p><?php esc_html_e( 'Select your Business Type under the Classification tab so incoming messages are automatically sorted into Sales, Support, Feedback, Complaints, and more using industry-specific keywords.', 'contactin' ); ?></p>
						</div>
					</div>
				</div>

				<div class="cin-gs-card-footer">
					<a href="<?php echo esc_url( $settings_url ); ?>" class="cin-gs-btn cin-gs-btn-primary">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Go to Settings', 'contactin' ); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- Quick Links -->
		<div class="cin-gs-quick-links">
			<h3><?php esc_html_e( 'Quick Links', 'contactin' ); ?></h3>
			<div class="cin-gs-links-grid">
				<a href="<?php echo esc_url( $inbox_url ); ?>" class="cin-gs-link">
					<span class="dashicons dashicons-email-alt"></span>
					<?php esc_html_e( 'View Inbox', 'contactin' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'page', Config::MENU_CONTACTS, $admin_url ) ); ?>" class="cin-gs-link">
					<span class="dashicons dashicons-groups"></span>
					<?php esc_html_e( 'Manage Contacts', 'contactin' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'page', 'contactin-analytics', $admin_url ) ); ?>" class="cin-gs-link">
					<span class="dashicons dashicons-chart-line"></span>
					<?php esc_html_e( 'View Analytics', 'contactin' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'page', Config::MENU_EMAIL_LOG, $admin_url ) ); ?>" class="cin-gs-link">
					<span class="dashicons dashicons-email"></span>
					<?php esc_html_e( 'Email Log', 'contactin' ); ?>
				</a>
			</div>
		</div>

		<div class="cin-gs-card" style="margin-top: 20px;">
			<div class="cin-gs-card-header">
				<div class="cin-gs-card-icon cin-gs-icon-settings">
					<span class="dashicons dashicons-star-filled"></span>
				</div>
				<div>
					<h2><?php esc_html_e( 'Need More Advanced Workflows?', 'contactin' ); ?></h2>
					<p class="cin-gs-card-desc"><?php esc_html_e( 'If your team is growing, compare plans to see whether ContactIn Pro fits your operational needs.', 'contactin' ); ?></p>
				</div>
			</div>
			<div class="cin-gs-card-content">
				<div class="cin-gs-feature-list">
					<div class="cin-gs-feature">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<strong><?php esc_html_e( 'Plan Comparison', 'contactin' ); ?></strong>
							<p><?php esc_html_e( 'Review free and pro capabilities side-by-side before making any decision.', 'contactin' ); ?></p>
						</div>
					</div>
				</div>

				<div class="cin-gs-card-footer">
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="cin-gs-btn cin-gs-btn-primary" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'Compare Free vs Pro', 'contactin' ); ?>
					</a>
				</div>
			</div>
		</div>

	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Copy shortcode to clipboard
	$('.cin-gs-copy-btn').on('click', function() {
		var btn = $(this);
		var text = btn.data('clipboard');
		var textArea = document.createElement('textarea');
		textArea.value = text;
		textArea.style.position = 'fixed';
		textArea.style.left = '-9999px';
		document.body.appendChild(textArea);
		textArea.select();
		
		try {
			document.execCommand('copy');
			btn.find('.cin-copy-text').text('<?php esc_html_e( 'Copied!', 'contactin' ); ?>');
			setTimeout(function() {
				btn.find('.cin-copy-text').text('<?php esc_html_e( 'Copy', 'contactin' ); ?>');
			}, 2000);
		} catch (err) {
			console.error('Failed to copy:', err);
		}
		
		document.body.removeChild(textArea);
	});
});
</script>
