<?php
/**
 * Contact Detail Admin Page
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\PhoneUtils;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$back_url   = admin_url( 'admin.php?page=' . Config::MENU_CONTACTS );
$detail_url = add_query_arg(
	array(
		'page'       => Config::MENU_CONTACTS,
		'contact_id' => $contact_item->id,
	),
	admin_url( 'admin.php' )
);

$messages               = is_array( $messages_list ?? null ) ? $messages_list : array();
$contact_deletion_nonce = wp_create_nonce( 'ci_contact_deletion' );
$search                 = $search ?? '';
$status                 = $status ?? 'all';
$paged                  = (int) ( $paged ?? 1 );
$per_page               = (int) ( $per_page ?? 20 );
$pages_count            = (int) ( $pages_count ?? 1 );
$total_items            = (int) ( $total_items ?? 0 );
$orderby                = $orderby ?? 'submitted_at';
$order                  = $order ?? 'DESC';
$extra_query_args       = array( 'contact_id' => $contact_item->id );
$pagination_args        = array(
	'base'     => add_query_arg( 'paged', '%#%', $detail_url ),
	'format'   => '',
	'current'  => max( 1, $paged ),
	'total'    => max( 1, $pages_count ),
	'add_args' => array(
		'per_page' => $per_page,
		's'        => $search,
		'status'   => $status,
		'orderby'  => $orderby,
		'order'    => $order,
	),
);

// Collect phone values with labels
$phone_fields = array(
	__( 'Mobile', 'contactin' )  => $contact_item->mobile_phone,
	__( 'Primary', 'contactin' ) => $contact_item->primary_phone,
	__( 'Home', 'contactin' )    => $contact_item->home_phone,
	__( 'Other', 'contactin' )   => $contact_item->other_phone,
);
$phones       = array();
foreach ( $phone_fields as $label => $value ) {
	if ( empty( $value ) ) {
		continue;
	}
	$phones[] = array(
		'label'   => $label,
		'value'   => $value,
		'display' => PhoneUtils::format( $value, 'international' ),
	);
}
?>

<div class="wrap cin-contact-detail">
	<!-- PAGE HEADER -->
	<div class="cin-page-header">
		<div>
			<h1><?php esc_html_e( 'Contact', 'contactin' ); ?>: <?php echo esc_html( $contact_item->name ?: __( '(No name)', 'contactin' ) ); ?></h1>
			<span class="cin-header-count">
			<?php
			printf(
				__( '(%s messages, %s unread)', 'contactin' ),
				number_format_i18n( $total_items ),
				number_format_i18n( $unread_count ?? 0 )
			);
			?>
			</span>
		</div>
		<div>
			<button type="button" class="button button-secondary cin-download-csv disabled"
					data-upgrade-only="1"
					aria-disabled="true"
					title="<?php esc_attr_e( 'GDPR Link', 'contactin' ); ?>">
				<span class="dashicons dashicons-privacy"></span>
				<?php esc_html_e( 'GDPR Link', 'contactin' ); ?>
				<span class="cin-pro-badge cin-pro-badge--button"><?php esc_html_e( 'PRO', 'contactin' ); ?></span>
			</button>
			<button type="button" class="button button-danger cin-delete-contact-btn"
					data-contact-id="<?php echo esc_attr( $contact_item->id ); ?>"
					title="<?php esc_attr_e( 'Delete this contact', 'contactin' ); ?>">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Delete Contact', 'contactin' ); ?>
			</button>
			<a class="button button-secondary" href="<?php echo esc_url( $back_url ); ?>">
				<?php esc_html_e( 'Back to contacts', 'contactin' ); ?>
			</a>
		</div>
	</div>

	<!-- Hidden nonce field for contact deletion -->
	<input type="hidden" name="ci_contact_deletion_nonce" value="<?php echo esc_attr( $contact_deletion_nonce ); ?>">

	<div class="cin-contact-detail-shell">
		<!-- TABS NAVIGATION -->
		<div class="cin-detail-tabs-header">
			<nav class="cin-detail-tabs-nav" role="tablist">
				<button class="cin-detail-tab-button cin-detail-tab-active" 
						role="tab" 
						aria-selected="true" 
						aria-controls="cin-details-panel"
						data-tab="details">
					<span class="dashicons dashicons-admin-users"></span>
					<span><?php esc_html_e( 'Contact Details', 'contactin' ); ?></span>
				</button>
				<button class="cin-detail-tab-button" 
						role="tab" 
						aria-selected="false" 
						aria-controls="cin-messages-panel"
						data-tab="messages">
					<span class="dashicons dashicons-email-alt"></span>
					<span><?php esc_html_e( 'Messages', 'contactin' ); ?></span>
					<span class="cin-tab-badge"><?php echo number_format_i18n( $total_items ); ?></span>
				</button>
			</nav>
		</div>

		<!-- DETAILS TAB PANEL -->
		<div class="cin-detail-tab-panel cin-detail-tab-active" id="cin-details-panel" role="tabpanel">
			<div class="cin-contact-details-container">
				<!-- Contact Card -->
				<div class="cin-contact-card-full">
					<div class="cin-card-header">
						<div>
							<h2><?php esc_html_e( 'About', 'contactin' ); ?></h2>
						</div>
						<button type="button" class="cin-btn cin-btn-primary cin-edit-contact-btn" 
								data-contact-id="<?php echo esc_attr( $contact_item->id ); ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'ci_update_contact' ) ); ?>"
								title="<?php esc_attr_e( 'Edit Contact', 'contactin' ); ?>">
							<span class="dashicons dashicons-edit"></span>
							<?php esc_html_e( 'Edit Contact', 'contactin' ); ?>
						</button>
					</div>

					<div class="cin-card-content-full">
						<!-- Avatar/Icon Section -->
						<div class="cin-card-avatar-full">
							<div class="cin-avatar-placeholder-lg">
								<span class="dashicons dashicons-admin-users"></span>
							</div>
							<div class="cin-contact-name-block-lg">
								<h3><?php echo esc_html( $contact_item->name ?: __( '(No name)', 'contactin' ) ); ?></h3>
								<p class="cin-contact-source-lg"><?php echo $contact_item->source ? esc_html( $contact_item->source ) : '<em>' . esc_html__( 'No source', 'contactin' ) . '</em>'; ?></p>
							</div>
						</div>

						<!-- Contact Info Grid - Full Width -->
						<div class="cin-contact-info-grid-full">
							<!-- Primary Contact Info -->
							<div class="cin-info-section-full">
								<h4><?php esc_html_e( 'Primary Contact Information', 'contactin' ); ?></h4>
								<div class="cin-info-rows-full">
									<div class="cin-info-row-full">
										<span class="cin-info-icon dashicons dashicons-email-alt"></span>
										<div class="cin-info-content-full">
											<span class="cin-row-label-full"><?php esc_html_e( 'Email', 'contactin' ); ?></span>
											<span class="cin-row-value-full">
												<?php echo $contact_item->email ? '<a href="mailto:' . esc_attr( $contact_item->email ) . '">' . esc_html( $contact_item->email ) . '</a>' : '<em>' . esc_html__( 'Not provided', 'contactin' ) . '</em>'; ?>
											</span>
										</div>
									</div>
									<div class="cin-info-row-full">
										<span class="cin-info-icon dashicons dashicons-building"></span>
										<div class="cin-info-content-full">
											<span class="cin-row-label-full"><?php esc_html_e( 'Source', 'contactin' ); ?></span>
											<span class="cin-row-value-full"><?php echo $contact_item->source ? esc_html( $contact_item->source ) : '<em>' . esc_html__( 'Not available', 'contactin' ) . '</em>'; ?></span>
										</div>
									</div>
								</div>
							</div>

							<!-- Phone Numbers -->
							<div class="cin-info-section-full">
								<h4><?php esc_html_e( 'Phone Numbers', 'contactin' ); ?></h4>
								<div class="cin-info-rows-full">
									<?php if ( empty( $phones ) ) : ?>
										<div class="cin-info-row-full">
											<span class="cin-info-icon dashicons dashicons-phone"></span>
											<div class="cin-info-content-full">
												<span class="cin-row-value-full"><em><?php esc_html_e( 'No phone numbers on file', 'contactin' ); ?></em></span>
											</div>
										</div>
									<?php else : ?>
										<?php foreach ( $phones as $phone ) : ?>
											<div class="cin-info-row-full">
												<span class="cin-info-icon dashicons dashicons-phone"></span>
												<div class="cin-info-content-full">
													<span class="cin-row-label-full"><?php echo esc_html( $phone['label'] ); ?></span>
													<span class="cin-row-value-full cin-phone-value-full"><?php echo esc_html( $phone['display'] ); ?></span>
												</div>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</div>

							<!-- Record Information -->
							<div class="cin-info-section-full">
								<h4><?php esc_html_e( 'Record Information', 'contactin' ); ?></h4>
								<div class="cin-info-rows-full">
									<div class="cin-info-row-full">
										<span class="cin-info-icon dashicons dashicons-calendar-alt"></span>
										<div class="cin-info-content-full">
											<span class="cin-row-label-full"><?php esc_html_e( 'Created', 'contactin' ); ?></span>
											<span class="cin-row-value-full"><?php echo $contact_item->created_at ? esc_html( date_i18n( 'M j, Y g:i A', strtotime( $contact_item->created_at ) ) ) : '&mdash;'; ?></span>
										</div>
									</div>
									<div class="cin-info-row-full">
										<span class="cin-info-icon dashicons dashicons-update"></span>
										<div class="cin-info-content-full">
											<span class="cin-row-label-full"><?php esc_html_e( 'Updated', 'contactin' ); ?></span>
											<span class="cin-row-value-full"><?php echo $contact_item->updated_at ? esc_html( date_i18n( 'M j, Y g:i A', strtotime( $contact_item->updated_at ) ) ) : '&mdash;'; ?></span>
										</div>
									</div>
									<div class="cin-info-row-full">
										<span class="cin-info-icon dashicons dashicons-history"></span>
										<div class="cin-info-content-full">
											<span class="cin-row-label-full"><?php esc_html_e( 'Last Activity', 'contactin' ); ?></span>
											<span class="cin-row-value-full"><?php echo $contact_item->last_message_at ? esc_html( date_i18n( 'M j, Y g:i A', strtotime( $contact_item->last_message_at ) ) ) : '<em>' . esc_html__( 'Never', 'contactin' ) . '</em>'; ?></span>
										</div>
									</div>
									<div class="cin-info-row-full">
										<span class="cin-info-icon dashicons dashicons-tag"></span>
										<div class="cin-info-content-full">
											<span class="cin-row-label-full"><?php esc_html_e( 'Contact ID', 'contactin' ); ?></span>
											<span class="cin-row-value-full cin-id-badge-full"><?php echo esc_html( $contact_item->id ); ?></span>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- MESSAGES TAB PANEL -->
		<div class="cin-detail-tab-panel" id="cin-messages-panel" role="tabpanel">
			<div class="cin-messages-container">

	<div class="cin-inbox-shell">
		<form method="get" id="messages-filter" class="cin-inbox-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( Config::MENU_CONTACTS ); ?>" />
			<input type="hidden" name="contact_id" value="<?php echo esc_attr( $contact_item->id ); ?>" />
			<input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>" />
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
			<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />

			<?php
			load_template(
				CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-search-filter.php',
				false,
				array(
					'search'         => $search,
					'current_status' => $status,
					'base_url'       => $detail_url,
					'contact_id'     => $contact_item->id,
				)
			);
			?>

			<div class="tablenav top cin-inbox-tablenav">
				<div class="alignleft actions">
					<!-- Status filter -->
					<label for="status-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'contactin' ); ?></label>
					<select id="status-filter" name="status" class="cin-status-filter">
						<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'contactin' ); ?></option>
						<option value="read" <?php selected( $status, 'read' ); ?>><?php esc_html_e( 'Read', 'contactin' ); ?></option>
						<option value="unread" <?php selected( $status, 'unread' ); ?>><?php esc_html_e( 'Unread', 'contactin' ); ?></option>
					</select>
					<div id="filter-loading-indicator" class="cin-loading-indicator"></div>

					<?php if ( ! empty( $search ) || $status !== 'all' ) : ?>
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									's'     => '',
									'paged' => '',
								),
								$detail_url
							)
						);
						?>
									" class="button">
							<?php esc_html_e( 'Clear', 'contactin' ); ?>
						</a>
					<?php endif; ?>

					<!-- Bulk Actions -->
					<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Bulk actions', 'contactin' ); ?></label>
					<select name="action" id="bulk-action-selector-top" class="cin-bulk-action">
						<option value="-1"><?php esc_html_e( 'Bulk actions', 'contactin' ); ?></option>
						<option value="read"><?php esc_html_e( 'Mark as Read', 'contactin' ); ?></option>
						<option value="unread"><?php esc_html_e( 'Mark as Unread', 'contactin' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete Permanently', 'contactin' ); ?></option>
					</select>
					<div id="bulk-loading-indicator" class="cin-loading-indicator"></div>
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

			<div class="wp-list-table-container">
				<?php
				load_template(
					CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-table.php',
					false,
					array(
						'messages'         => $messages,
						'paged'            => $paged,
						'pages'            => $pages_count,
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
			</div><!-- end cin-messages-container -->
		</div><!-- end cin-messages-panel tab panel -->
	</div><!-- end cin-contact-detail-shell -->
</div><!-- end wrap -->

<!-- Edit Contact Modal -->
<?php
load_template(
	CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'contact-edit-modal.php',
	false
);
?>

<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'export-modal.php' ); ?>

<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'classification-modal.php' ); ?>
		</div>
	</div>
</div>