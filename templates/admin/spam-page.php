<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Spam Admin Page Template
 *
 * @package ContactIn\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.MissingTranslatorsComment

$messages    = $messages ?? array();
$paged       = (int) ( $paged ?? 1 );
$pages       = (int) ( $pages ?? 1 );
$search      = $search ?? '';
$status      = Config::STATUS_SPAM;
$total_items = (int) ( $total_items ?? 0 );
$spam_count  = (int) ( $spam_count ?? $total_items );
$orderby     = $orderby ?? 'submitted_at';
$order       = $order ?? 'DESC';
$per_page    = (int) ( $per_page ?? Config::INBOX_PER_PAGE );
$contact_id  = isset( $contact_id ) ? (int) $contact_id : 0;

$base_url = $base_url ?? admin_url( 'admin.php?page=' . Config::MENU_SPAM );
$base_url = add_query_arg( 'status', $status, $base_url );
if ( $contact_id ) {
	$base_url = add_query_arg( 'contact_id', $contact_id, $base_url );
}

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
		'status'     => $status,
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
	<div class="cin-page-header">
		<div>
			<h1><?php esc_html_e( 'Spam', 'contactin' ); ?></h1>
			<span class="cin-header-count">
			<?php
			printf(
				esc_html__( '(%s spam messages)', 'contactin' ),
				number_format_i18n( $spam_count )
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
			<button type="button" class="button button-secondary" id="cin-clear-spam"
					data-spam-count="<?php echo esc_attr( $spam_count ); ?>">
				<?php esc_html_e( 'Clear All Spam', 'contactin' ); ?>
			</button>
		</div>
	</div>

	<div class="cin-inbox-shell cin-px-md">
		<form method="get" id="messages-filter" class="cin-inbox-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( Config::MENU_SPAM ); ?>">
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
			<?php if ( $contact_id ) : ?>
				<input type="hidden" name="contact_id" value="<?php echo esc_attr( $contact_id ); ?>">
			<?php endif; ?>
			<input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
			<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">

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

			<div class="tablenav top cin-inbox-tablenav">
				<div class="alignleft actions">

					<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Bulk actions', 'contactin' ); ?></label>
					<select name="action" id="bulk-action-selector-top" class="cin-bulk-action">
						<option value="-1"><?php esc_html_e( 'Bulk actions', 'contactin' ); ?></option>
						<option value="not_spam"><?php esc_html_e( 'Not Spam (Move to Inbox)', 'contactin' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete Permanently', 'contactin' ); ?></option>
					</select>
				<div id="bulk-loading-indicator" class="cin-loading-indicator"></div>
					<span class="cin-unread-badge">
						<?php
						printf(
							__( 'Unread: %s', 'contactin' ),
							number_format_i18n( $spam_count )
						);
						?>
					</span>
			</div>

				<div class="tablenav-pages">
					<span class="displaying-num"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', 'contactin' ); ?></span>

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
		</form>
	</div>
</div>

