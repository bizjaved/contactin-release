<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Inbox Table Template – Enterprise-Grade
 *
 * @package ContactIn\Admin
 */

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extract args safely if provided by load_template
if ( isset( $args ) && is_array( $args ) ) {
	$messages         = $args['messages'] ?? array();
	$paged            = (int) ( $args['paged'] ?? 1 );
	$pages            = (int) ( $args['pages'] ?? 1 );
	$search           = $args['search'] ?? '';
	$status           = $args['status'] ?? 'all';
	$orderby          = $args['orderby'] ?? 'submitted_at';
	$order            = $args['order'] ?? 'DESC';
	$per_page         = (int) ( $args['per_page'] ?? 20 );
	$extra_query_args = isset( $args['extra_query_args'] ) && is_array( $args['extra_query_args'] ) ? $args['extra_query_args'] : array();
}

// Normalize types
$messages = is_array( $messages ) ? $messages : array();
$paged    = max( 1, (int) $paged );
$pages    = max( 1, (int) $pages );

// Function to generate sortable column link
function ci_get_sortable_link( $column, $label, $orderby, $order, $search, $status, $per_page, array $extra_args = array() ) {
	$current_orderby = $orderby;
	$current_order   = $order;

	if ( $current_orderby === $column ) {
		$new_order = $current_order === 'ASC' ? 'DESC' : 'ASC';
	} else {
		$new_order = 'ASC'; // default for new column
	}

	$url = add_query_arg(
		array_merge(
			$extra_args,
			array(
				'orderby'  => $column,
				'order'    => $new_order,
				's'        => $search,
				'status'   => $status,
				'per_page' => $per_page,
				'paged'    => 1, // reset to first page on sort
			)
		)
	);

	$arrow = '';
	if ( $current_orderby === $column ) {
		$arrow = $current_order === 'ASC' ? ' ↑' : ' ↓';
	}

	return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a>';
}
?>

<table class="wp-list-table widefat fixed striped contactin-inbox-table">
	<thead>
		<tr>
			<td class="manage-column column-cb check-column">
				<input type="checkbox" id="cb-select-all-1">
			</td>
			<th><?php echo ci_get_sortable_link( 'name', __( 'From', 'contactin' ), $orderby, $order, $search, $status, $per_page, $extra_query_args ); ?></th>
			<th><?php echo ci_get_sortable_link( 'subject', __( 'Subject', 'contactin' ), $orderby, $order, $search, $status, $per_page, $extra_query_args ); ?></th>
			<th><?php echo ci_get_sortable_link( 'message', __( 'Message', 'contactin' ), $orderby, $order, $search, $status, $per_page, $extra_query_args ); ?></th>
			<th><?php echo ci_get_sortable_link( 'attachment', __( 'Attachment', 'contactin' ), $orderby, $order, $search, $status, $per_page, $extra_query_args ); ?></th>
			<th><?php echo ci_get_sortable_link( 'submitted_at', __( 'Date', 'contactin' ), $orderby, $order, $search, $status, $per_page, $extra_query_args ); ?></th>
			<th><?php esc_html_e( 'Email Sent', 'contactin' ); ?></th>
			<th><?php esc_html_e( 'CRM Sync', 'contactin' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'contactin' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $messages ) ) : ?>
			<tr class="no-items">
				<td colspan="9" class="cin-no-messages">
					<?php esc_html_e( 'No messages found.', 'contactin' ); ?>
				</td>
			</tr>
		<?php else : ?>
			<?php foreach ( $messages as $msg ) : ?>
				<?php
				load_template(
					CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-row.php',
					false,
					array(
						'msg'            => $msg,
						'search'         => $search,
						'current_status' => $status,
					)
				);
				?>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
	<tfoot>
		<tr>
			<td class="manage-column column-cb check-column">
				<input type="checkbox" id="cb-select-all-2">
			</td>
			<th><?php esc_html_e( 'From', 'contactin' ); ?></th>
			<th><?php esc_html_e( 'Subject', 'contactin' ); ?></th>
			<th><?php esc_html_e( 'Message', 'contactin' ); ?></th>
			<th><?php esc_html_e( 'Attachment', 'contactin' ); ?></th>
			<th><?php esc_html_e( 'Date', 'contactin' ); ?></th>
			<th><?php esc_html_e( 'Email Sync', 'contactin' ); ?></th>
			<th><?php esc_html_e( 'CRM Sync', 'contactin' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'contactin' ); ?></th>
		</tr>
	</tfoot>
</table>
