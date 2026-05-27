<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Inbox Admin Page Template – Bulletproof Structure
 *
 * @package ContactIn\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.MissingTranslatorsComment

// Variables passed from display_page()
$messages     = $messages ?? array();
$paged        = (int) ( $paged ?? 1 );
$pages        = (int) ( $pages ?? 1 );
$search       = $search ?? '';
$status       = $status ?? 'all';
$intent       = $intent ?? 'all';
$total_items  = (int) ( $total_items ?? 0 );
$unread_count = (int) ( $unread_count ?? 0 );
$orderby      = $orderby ?? 'submitted_at';
$order        = $order ?? 'DESC';
$per_page     = (int) ( $per_page ?? Config::INBOX_PER_PAGE );
$contact_id   = isset( $contact_id ) ? (int) $contact_id : 0;

$base_url = $base_url ?? admin_url( 'admin.php?page=' . Config::MENU_INBOX );
if ( $contact_id ) {
	$base_url = add_query_arg( 'contact_id', $contact_id, $base_url );
}

// Safeguard: recompute total pages based on total_items and per_page
$per_page_safe = max( 1, $per_page );
$pages         = max( 1, (int) ceil( $total_items / $per_page_safe ) );

$pagination_args  = array(
	'base'     => add_query_arg( 'paged', '%#%', $base_url ),
	'format'   => '',
	'current'  => max( 1, $paged ),
	'total'    => max( 1, $pages ),
	'type'     => 'plain',
	'add_args' => array(
		's'          => $search,
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
?>

<div class="wrap cin-inbox-page">

	<!-- PAGE HEADER -->
	<div class="cin-page-header">
		<div>
			<h1><?php esc_html_e( 'Inbox', 'contactin' ); ?></h1>
			<span class="cin-header-count">
			<?php
			printf(
				esc_html__( '(%s messages)', 'contactin' ),
				number_format_i18n( $unread_count )
			);
			?>
			</span>
		</div>
		<div>
			<button type="button" class="button button-secondary"
					data-cin-help-open="cin-inbox-help-modal"
					aria-haspopup="dialog"
					aria-controls="cin-inbox-help-modal">
				<?php esc_html_e( 'Help', 'contactin' ); ?>
			</button>
			<button type="button" class="button cin-icon-button cin-inbox-shortcuts-btn"
					title="<?php esc_attr_e( 'Keyboard Shortcuts', 'contactin' ); ?>">
				⌨️
			</button>
		</div>
	</div>

	<div class="cin-inbox-shell">
		<!-- MAIN FORM -->
		<form method="get" id="messages-filter" class="cin-inbox-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( Config::MENU_INBOX ); ?>">
			<?php if ( $contact_id ) : ?>
				<input type="hidden" name="contact_id" value="<?php echo esc_attr( $contact_id ); ?>">
			<?php endif; ?>
			<input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
			<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">

			<!-- SECTION 1: Search & Export -->
			<?php
			load_template(
				CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-search-filter.php',
				false,
				array(
					'search'         => $search,
					'current_status' => $status,
					'base_url'       => $base_url,
					'contact_id'     => $contact_id,
				)
			);
			?>

			<!-- SECTION 2: Table Navigation (filters + bulk + pagination in one line) -->
			<div class="tablenav top cin-inbox-tablenav">
				<div class="alignleft actions">
					<!-- Status filter -->
					<label for="status-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'contactin' ); ?></label>
					<select id="status-filter" name="status" class="cin-status-filter">
						<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'contactin' ); ?></option>
						<option value="read" <?php selected( $status, 'read' ); ?>><?php esc_html_e( 'Read', 'contactin' ); ?></option>
						<option value="unread" <?php selected( $status, 'unread' ); ?>><?php esc_html_e( 'Unread', 'contactin' ); ?></option>
					</select>

					<!-- Intent filter -->
					<?php
					$settings = \ContactInbox\Core\Settings::get_settings();
					if ( ! empty( $settings['intent_enable'] ) ) :
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

					<?php if ( ! empty( $search ) || $status !== 'all' || $intent !== 'all' ) : ?>
						<a href="<?php echo esc_url( remove_query_arg( array( 's', 'status', 'intent', 'paged' ), $base_url ) ); ?>" class="button">
							<?php esc_html_e( 'Clear', 'contactin' ); ?>
						</a>
					<?php endif; ?>

					<!-- Bulk Actions -->
					<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Bulk actions', 'contactin' ); ?></label>
					<select name="action" id="bulk-action-selector-top" class="cin-bulk-action">
						<option value="-1"><?php esc_html_e( 'Bulk actions', 'contactin' ); ?></option>
						<option value="read"><?php esc_html_e( 'Mark as Read', 'contactin' ); ?></option>
						<option value="unread"><?php esc_html_e( 'Mark as Unread', 'contactin' ); ?></option>
						<option value="archive"><?php esc_html_e( 'Archive', 'contactin' ); ?></option>
						<option value="spam"><?php esc_html_e( 'Move to Spam', 'contactin' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete Permanently', 'contactin' ); ?></option>
					</select>
					<div id="bulk-loading-indicator" class="cin-loading-indicator"></div>

					<!-- Unread Count Badge -->
					<span class="cin-unread-badge">
						<?php
						printf(
							__( 'Unread: %s', 'contactin' ),
							number_format_i18n( $unread_count )
						);
						?>
					</span>
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
					$pagination_top['prev_text'] = esc_html__( 'Prev', 'contactin' );
					$pagination_top['next_text'] = esc_html__( 'Next', 'contactin' );
					echo wp_kses_post( paginate_links( $pagination_top ) );
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
	</div><!-- .cin-inbox-shell -->

	<!-- MODALS & EXTRAS -->

	<!-- Support Boxes -->
	<?php \ContactInbox\Admin\SupportBoxesManager::render_support_boxes( 'inbox' ); ?>

	<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-help-modal.php' ); ?>

</div><!-- .wrap.cin-inbox-page -->
