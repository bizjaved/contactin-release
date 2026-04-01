<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Consolidated Inbox Admin Page Template
 *
 * Unified view combining Main Inbox, Spam, and Archives in tabs
 * This page aggregates all three message folders into one interface
 *
 * @package ContactIn\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.MissingTranslatorsComment

// Variables passed from display_page()
$messages     = $messages ?? array();
$paged        = (int) ( $paged ?? 1 );
$pages        = (int) ( $pages ?? 1 );
$search       = $search ?? '';
$folder       = sanitize_key( $_GET['folder'] ?? 'main' ); // main, spam, or archived
$status       = $status ?? 'all';
$intent       = $intent ?? 'all';
$total_items  = (int) ( $total_items ?? 0 );
$unread_count = (int) ( $unread_count ?? 0 );
$orderby      = $orderby ?? 'submitted_at';
$order        = $order ?? 'DESC';
$per_page     = (int) ( $per_page ?? Config::INBOX_PER_PAGE );
$contact_id   = isset( $contact_id ) ? (int) $contact_id : 0;

$base_url = add_query_arg( array( 'page' => Config::MENU_INBOX_UNIFIED ), admin_url( 'admin.php' ) );
if ( $contact_id ) {
	$base_url = add_query_arg( 'contact_id', $contact_id, $base_url );
}
if ( $folder && $folder !== 'main' ) {
	$base_url = add_query_arg( 'folder', $folder, $base_url );
}

// Safeguard: recompute total pages based on total_items and per_page
$per_page_safe = max( 1, $per_page );
$pages         = max( 1, (int) ceil( $total_items / $per_page_safe ) );

$pagination_args = array(
	'base'     => add_query_arg( 'paged', '%#%', $base_url ),
	'format'   => '',
	'current'  => max( 1, $paged ),
	'total'    => max( 1, $pages ),
	'type'     => 'plain',
	'add_args' => array(
		's'          => $search,
		'folder'     => $folder,
		'status'     => $status,
		'intent'     => $intent,
		'per_page'   => $per_page,
		'orderby'    => $orderby,
		'order'      => $order,
		'contact_id' => $contact_id,
	),
);

$extra_query_args = $extra_query_args ?? array();
if ( $contact_id ) {
	$extra_query_args = array_merge( array( 'contact_id' => $contact_id ), $extra_query_args );
}

// Get message counts for each folder
$db             = \ContactInbox\Core\DB::instance();
$count_main     = $db->get_total_messages( '', 'all', $contact_id );
$count_spam     = $db->get_total_messages( '', Config::STATUS_SPAM, $contact_id );
$count_archived = $db->get_total_messages( '', Config::STATUS_ARCHIVED, $contact_id );
?>

<div class="wrap cin-inbox-page">

	<!-- PAGE HEADER -->
	<div class="cin-page-header">
		<div>
			<h1><?php esc_html_e( 'Inbox', 'contactin' ); ?></h1>
			<span class="cin-header-count">
			<?php
			printf(
				__( '(%s messages)', 'contactin' ),
				number_format_i18n( $total_database_messages ?? $total_items )
			);
			?>
			</span>
		</div>
		<div>
			<button type="button" class="button button-secondary"
					data-cin-help-open="cin-inbox-help-modal"
					aria-haspopup="dialog"
					aria-controls="cin-inbox-help-modal">
				<?php _e( 'Help', 'contactin' ); ?>
			</button>
			<button type="button" class="button cin-icon-button"
					onclick="window.cinInboxKeyboardShortcuts && window.cinInboxKeyboardShortcuts()"
					title="<?php _e( 'Keyboard Shortcuts', 'contactin' ); ?>">
				⌨️
			</button>
		</div>
	</div>

	<div class="cin-inbox-shell">
		<!-- TAB NAVIGATION HEADER -->
		<div class="cin-consolidated-tabs-header">
			<nav class="cin-consolidated-tabs-nav" role="tablist">
				<!-- Main Inbox Tab -->
				<button class="cin-consolidated-tab-button <?php echo $folder === 'main' ? 'cin-tab-active' : ''; ?>" 
						role="tab" 
						aria-selected="<?php echo $folder === 'main' ? 'true' : 'false'; ?>" 
						aria-controls="cin-main-folder-panel"
						data-folder="main"
						onclick="window.cinConsolidatedInbox && window.cinConsolidatedInbox.switchFolder(this)">
					<span class="dashicons dashicons-email-alt"></span>
					<span><?php esc_html_e( 'Main', 'contactin' ); ?></span>
					<span class="cin-tab-badge"><?php echo number_format_i18n( $count_main ); ?></span>
				</button>

				<!-- Spam Tab -->
				<button class="cin-consolidated-tab-button <?php echo $folder === 'spam' ? 'cin-tab-active' : ''; ?>" 
						role="tab" 
						aria-selected="<?php echo $folder === 'spam' ? 'true' : 'false'; ?>" 
						aria-controls="cin-spam-folder-panel"
						data-folder="spam"
						onclick="window.cinConsolidatedInbox && window.cinConsolidatedInbox.switchFolder(this)">
					<span class="dashicons dashicons-shield-alt"></span>
					<span><?php esc_html_e( 'Spam', 'contactin' ); ?></span>
					<span class="cin-tab-badge"><?php echo number_format_i18n( $count_spam ); ?></span>
				</button>

				<!-- Archives Tab -->
				<button class="cin-consolidated-tab-button <?php echo $folder === 'archived' ? 'cin-tab-active' : ''; ?>" 
						role="tab" 
						aria-selected="<?php echo $folder === 'archived' ? 'true' : 'false'; ?>" 
						aria-controls="cin-archived-folder-panel"
						data-folder="archived"
						onclick="window.cinConsolidatedInbox && window.cinConsolidatedInbox.switchFolder(this)">
					<span class="dashicons dashicons-archive"></span>
					<span><?php esc_html_e( 'Archives', 'contactin' ); ?></span>
					<span class="cin-tab-badge"><?php echo number_format_i18n( $count_archived ); ?></span>
				</button>
			</nav>
		</div>

		<!-- MESSAGES TAB PANEL -->
		<div class="cin-messages-container">
			<!-- MAIN FORM -->
		<form method="get" id="messages-filter" class="cin-inbox-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( Config::MENU_INBOX_UNIFIED ); ?>">
				<input type="hidden" name="folder" value="<?php echo esc_attr( $folder ); ?>">
				<?php if ( $contact_id ) : ?>
					<input type="hidden" name="contact_id" value="<?php echo esc_attr( $contact_id ); ?>">
				<?php endif; ?>
				<input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
				<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">
				<input type="hidden" name="intent" value="<?php echo esc_attr( $intent ); ?>">

				<!-- SECTION 1: Search & Export -->
				<?php
				load_template(
					CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-search-filter.php',
					false,
					array(
						'search'         => $search,
						'current_status' => $folder === 'spam'
							? Config::STATUS_SPAM
							: ( $folder === 'archived' ? Config::STATUS_ARCHIVED : $status ),
						'base_url'       => $base_url,
						'contact_id'     => $contact_id,
					)
				);
				?>

				<!-- SECTION 2: Table Navigation (filters + bulk + pagination in one line) -->
				<div class="tablenav top cin-inbox-tablenav">
					<div class="alignleft actions">
						<!-- Status filter -->
						<?php if ( $folder === 'main' ) : ?>
							<label for="status-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'contactin' ); ?></label>
							<select id="status-filter" name="status" class="cin-status-filter">
								<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'contactin' ); ?></option>
								<option value="read" <?php selected( $status, 'read' ); ?>><?php esc_html_e( 'Read', 'contactin' ); ?></option>
								<option value="unread" <?php selected( $status, 'unread' ); ?>><?php esc_html_e( 'Unread', 'contactin' ); ?></option>
							</select>
						<?php else : ?>
							<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
							<span class="cin-status-pill">
								<?php echo $folder === 'spam' ? esc_html__( 'Spam', 'contactin' ) : esc_html__( 'Archived', 'contactin' ); ?>
							</span>
						<?php endif; ?>

						<!-- Intent filter (hidden on spam and archive tabs) -->
						<?php
						$settings = \ContactInbox\Core\Settings::get_settings();
						if ( ! empty( $settings['intent_enable'] ) && $folder === 'main' ) :
							$intent_categories = \ContactInbox\Core\IntentClassifier::get_categories();
							?>
						<label for="intent-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by intent', 'contactin' ); ?></label>
						<select id="intent-filter" name="intent" class="cin-intent-filter">
							<option value="all" <?php selected( $intent, 'all' ); ?>><?php esc_html_e( 'All Intents', 'contactin' ); ?></option>
							<?php
							foreach ( $intent_categories as $cat_key => $cat_label ) :
								// Skip spam category - use spam tab for spam handling
								if ( $cat_key === 'spam' ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $cat_key ); ?>" <?php selected( $intent, $cat_key ); ?>>
									<?php echo esc_html( $cat_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php endif; ?>

						<div id="filter-loading-indicator" class="cin-loading-indicator"></div>

						<!-- Bulk Actions -->
						<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Bulk actions', 'contactin' ); ?></label>
						<select name="action" id="bulk-action-selector-top" class="cin-bulk-action">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'contactin' ); ?></option>
							<?php
							// Determine current status from folder parameter
							$bulk_folder = $_REQUEST['folder'] ?? 'main';
							if ( $bulk_folder === 'spam' ) {
								$bulk_status = Config::STATUS_SPAM;
							} elseif ( $bulk_folder === 'archived' ) {
								$bulk_status = Config::STATUS_ARCHIVED;
							} else {
								$bulk_status = 'all';
							}
							$bulk_actions = \ContactInbox\Admin\Helpers\InboxActionHelper::get_bulk_actions( $bulk_status );
							foreach ( $bulk_actions as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<div id="bulk-loading-indicator" class="cin-loading-indicator"></div>

						<!-- Unread Count Badge -->
						<div class="cin-unread-count">
							<span class="cin-count-label"><?php esc_html_e( 'Unread:', 'contactin' ); ?></span>
							<span class="cin-count-value"><?php echo number_format_i18n( $unread_count ); ?></span>
						</div>
					</div>

					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', 'contactin' ); ?></span>

						<!-- Per page selector -->
						<label for="per-page" class="cin-per-page-label"><?php esc_html_e( 'Rows per page', 'contactin' ); ?></label>
						<select id="per-page" name="per_page" class="cin-per-page-select">
							<option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
							<option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
							<option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
						</select>

						<?php
						$pagination_top              = $pagination_args;
						$pagination_top['prev_text'] = __( 'Prev', 'contactin' );
						$pagination_top['next_text'] = __( 'Next', 'contactin' );
						echo paginate_links( $pagination_top );
						?>
					</div>
				</div>

				<!-- THE TABLE -->
				<div class="wp-list-table-container">
					<?php
					load_template(
						CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-table.php',
						false,
						array(
							'messages'         => $messages,
							'paged'            => $paged,
							'pages'            => $pages,
							'search'           => $search,
							'status'           => $status,
							'orderby'          => $orderby,
							'order'            => $order,
							'per_page'         => $per_page,
							'extra_query_args' => $extra_query_args,
						)
					);
					?>
				</div>

				<!-- Bottom pagination intentionally hidden to match log pages -->

		</form><!-- #messages-filter -->
		</div><!-- .cin-messages-container -->
	</div><!-- .cin-inbox-shell -->

	<!-- Export Modal -->
	<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'export-modal.php' ); ?>

	<!-- Classification Change Modal -->
	<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'classification-modal.php' ); ?>

	<!-- Support Boxes -->
	<?php \ContactInbox\Admin\SupportBoxesManager::render_support_boxes( 'inbox' ); ?>

	<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-help-modal.php' ); ?>

</div><!-- .wrap.cin-inbox-page -->

<script>
(function($) {
	'use strict';

	// Consolidated Inbox Tab Switching
	window.cinConsolidatedInbox = {
		switchFolder: function(button) {
			const folder = $(button).data('folder');
			const currentUrl = new URL(window.location.href);
			currentUrl.searchParams.set('folder', folder);
			currentUrl.searchParams.set('paged', '1'); // Reset to page 1
			window.location.href = currentUrl.toString();
		}
	};
	
	// Auto-submit form when status dropdown changes (reset to page 1)
	$(document).on('change', '#status-filter', function() {
		$('input[name="paged"]').val(1);
		$('#messages-filter').submit();
	});
	
	// Auto-submit form when intent dropdown changes (reset to page 1)
	$(document).on('change', '#intent-filter', function() {
		$('input[name="intent"]').val($(this).val());
		$('input[name="paged"]').val(1);
		$('#messages-filter').submit();
	});
	
	// Auto-submit form when per-page dropdown changes (reset to page 1)
	$(document).on('change', '.cin-per-page-select', function() {
		$('input[name="paged"]').val(1);
		$('#messages-filter').submit();
	});
	
	// Reset pagination when search button is clicked
	$(document).on('click', '#search-submit', function() {
		$('input[name="paged"]').val(1);
	});

	// Disable keyboard shortcuts when typing in search input
	$(document).on('focus', 'input[type="text"], input[type="search"], textarea, input[type="email"]', function() {
		if (window.keyboardShortcutsEnabled !== undefined) {
			window.keyboardShortcutsEnabled = false;
		}
	});
	
	$(document).on('blur', 'input[type="text"], input[type="search"], textarea, input[type="email"]', function() {
		if (window.keyboardShortcutsEnabled !== undefined) {
			window.keyboardShortcutsEnabled = true;
		}
	});
})(jQuery);
</script>
