<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add a panel to the Edit Member dashboard page.
 *
 * @since 1.0.1
 *
 * @param array $panels Array of panels.
 * @return array
 */
function pmpromrss_pmpro_member_edit_panels( $panels ) {

	// If the class doesn't exist and the abstract class does, require the class.
	if ( ! class_exists( 'PMPROMRSS_Member_Edit_Panel' ) && class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		require_once( PMPROMRSS_DIR . '/classes/class-pmpromrss-member-edit-panel.php' );
	}

	// If the class exists, add a panel.
	if ( class_exists( 'PMPROMRSS_Member_Edit_Panel' ) ) {
		$panels[] = new PMPROMRSS_Member_Edit_Panel();
	}

	return $panels;
}

/**
 * Hook the correct function for admins editing a member's profile.
 *
 * @since 0.4
 */
function pmpromrss_hook_edit_member_profile() {
	// If the `pmpro_member_edit_get_panels()` function exists, add a panel.
	// Otherwise, use a legacy hook in the edit user.
	if ( function_exists( 'pmpro_member_edit_get_panels' ) ) {
		add_filter( 'pmpro_member_edit_panels', 'pmpromrss_pmpro_member_edit_panels' );
	} else {
		add_action( 'show_user_profile', 'pmpromrss_memberkeys_profile' );
		add_action( 'edit_user_profile', 'pmpromrss_memberkeys_profile' );
	}
}
add_action( 'admin_init', 'pmpromrss_hook_edit_member_profile', 0 );

/**
 *  Show RSS Feeds with Member Key on Membership Account Page
 * 
 * @since 0.1
 * 
 */
function pmpromrss_pmpro_member_links_bottom() {
	// If the feeds page is configured, show a link to that page instead of inline feed URLs.
	$feeds_page_id = pmpromrss_get_feeds_page_id();
	if ( ! empty( $feeds_page_id ) ) {
		?>
		<li><a href="<?php echo esc_url( get_permalink( $feeds_page_id ) ); ?>"><?php esc_html_e( 'Member RSS Feeds', 'pmpro-member-rss' ); ?></a></li>
		<?php
		return;
	}

	//show links to RSS feeds (format is title => url)
	$feeds = apply_filters("pmpromrss_feeds", array( "Recent Posts Feed" => get_bloginfo('rss_url') ) );

	//show URLs
	foreach( $feeds as $title => $feed ) {
	?>
		<li><a href="<?php echo esc_url( pmpromrss_url( $feed ) );?>"><?php echo esc_html( $title ); ?></a></li>
	<?php
	}
}
add_action('pmpro_member_links_bottom', 'pmpromrss_pmpro_member_links_bottom');

/**
 * Display the Member RSS Key and allow it to be regenerated in the Edit Member.
 * @since 0.3
 * 
 * @param  object $user The current user object that is being edited
 * @return mixed HTML content
 */
function pmpromrss_memberkeys_profile( $user ) { 
	$args = array(
		'pmpromrss_regenerate_key' => 1,
		'user_id' => $user->ID,
		'_wpnonce' => wp_create_nonce( 'pmpromrss_regenerate' )
	);

	// If the request comes from the member edit panel, let's keep it on the same tab.
	if ( ! empty( $_REQUEST['pmpro_member_edit_panel'] ) ) {
		$args['pmpro_member_edit_panel'] = sanitize_text_field( $_REQUEST['pmpro_member_edit_panel'] );
	}
	?>
	<table class="form-table">
		<tr id='pmpromrss_key'>
			<th><label for="address"><?php esc_html_e( 'Member RSS Key', 'pmpro-member-rss' ); ?></label></th>
			<td>
				<input type="text" name="pmpromrss_profile_key" id="pmpromrss_profile_key" readonly="readonly" value="<?php echo esc_attr( pmpromrss_getMemberKey( $user->ID ) ); ?>" class="regular-text" /> <a href="<?php echo "javascript:pmpro_askfirst('" . esc_js( __( "Are you sure you want to regenerate this user's key?", 'pmpro-member-rss' ) ) . "', '" . esc_url( add_query_arg( $args ) ) . "');"; ?>" class="<?php echo esc_attr( 'button button-primary' ); ?>"><?php esc_html_e( 'Regenerate Key', 'pmpro-member-rss' ); ?></a>
				<p class="description"><?php esc_html_e( "You may regenerate the member's RSS key at any time. Regenerating it will invalidate the previous key.", 'pmpro-member-rss' ); ?></p>
			</td>
		</tr>
		
	</table>
<?php 
}

/**
 * Deletes the existing Member key, a new key will be generated when pmpromrss_getMemberKey is loaded.
 *
 * @since 0.3
 * @return void
 */
function pmpromrss_memberkeys_profile_regenerate() {
	if ( empty( $_REQUEST['user_id'] ) || empty( $_REQUEST['pmpromrss_regenerate_key'] ) ) {
		return;
	}

	if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'pmpromrss_regenerate' ) ) {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Invalid nonce for regen. Try again.', 'pmpro-member-rss' ); ?></p>
		</div>
		<?php
		return;
	}

	$user_id = intval( $_REQUEST['user_id'] );

	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	delete_user_meta( $user_id, 'pmpromrss_key' );

	?>
	<div class="notice notice-success">
		<p><?php esc_html_e( 'A new key has been generated.', 'pmpro-member-rss' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_init', 'pmpromrss_memberkeys_profile_regenerate' ); // Needed for the Edit Member screen.

/**
 * Add an option to allow basic authentication functionality.
 *
 * @since 0.4
 * 
 * @param array $fields 
 * @return array $fields The modified fields array with the Google Maps API key setting added.
 */
function pmpromrss_advanced_settings( $fields ) {

	// Basic Authentication via WordPress Application Passwords.
	$fields['pmpromrss_basic_auth'] = array(
		'field_name'  => 'pmpromrss_basic_auth',
		'field_type'  => 'select',
		'options'     => array(
			'Disabled' => __( 'No - members use the memberkey URL to access their feeds (default)', 'pmpro-member-rss' ),
			'Enabled'  => __( 'Yes - allow members to authenticate using HTTP Basic Authentication', 'pmpro-member-rss' ),
		),
		'label'       => __( 'Enable Basic Auth for RSS Feeds', 'pmpro-member-rss' ),
		'description' => sprintf( __( 'Allow members to authenticate their RSS reader when the %s parameter is set.', 'pmpro-member-rss' ), '<code>' . esc_html( '?pmpromrss_basic_auth=1' ) . '</code>' ),
	);

	// Allow the member RSS key to be used as the Basic Auth password instead of an Application Password.
	$fields['pmpromrss_memberkey_as_password'] = array(
		'field_name'  => 'pmpromrss_memberkey_as_password',
		'field_type'  => 'select',
		'options'     => array(
			'Disabled' => __( 'No - members must use a WordPress Application Password (default)', 'pmpro-member-rss' ),
			'Enabled'  => __( 'Yes - allow members to use their member RSS key as the Basic Auth password', 'pmpro-member-rss' ),
		),
		'label'       => sprintf( __( 'Allow memberkey as Basic Auth Password', 'pmpro-member-rss' ), '<code>' . esc_html( 'memberkey' ) . '</code>' ),
		'description' => sprintf( __( 'When enabled, members can enter their username and member RSS key (%s) as the Basic Auth password', 'pmpro-member-rss' ), '<code>' . esc_html( 'memberkey' ) . '</code>' ),
	);

	// Prevent the memberkey from being used as a URL parameter entirely.
	$fields['pmpromrss_disable_url_key'] = array(
		'field_name'  => 'pmpromrss_disable_url_key',
		'field_type'  => 'select',
		'options'     => array(
			'Disabled' => __( 'No - allow feed access via the memberkey in URL (default)', 'pmpro-member-rss' ),
			'Enabled'  => __( 'Yes - require Basic Auth for all feed access', 'pmpro-member-rss' ),
		),
		'label'       => __( 'Disable memberkey in URL', 'pmpro-member-rss' ),
		'description' => __( 'When enabled, members must use Basic Authentication to access their RSS feeds. The memberkey URL parameter will no longer work. Requires "Enable Basic Auth for RSS Feeds" to be enabled.', 'pmpro-member-rss' ),
	);

	return $fields;
}
add_filter( 'pmpro_custom_advanced_settings', 'pmpromrss_advanced_settings', 5 );

/**
 * Add "Member RSS Feeds" to the PMPro Extra Pages list.
 *
 * @since 0.5
 *
 * @param array $pages Array of extra page settings.
 * @return array
 */
function pmpromrss_extra_page_settings( $pages ) {
	$pages['pmpromrss_member_rss'] = array(
		'title'   => __( 'Member RSS Feeds', 'pmpro-member-rss' ),
		'content' => '[pmpro_member_rss]',
		'hint'    => __( 'Include the [pmpro_member_rss] shortcode.', 'pmpro-member-rss' ),
	);
	return $pages;
}
add_filter( 'pmpro_extra_page_settings', 'pmpromrss_extra_page_settings' );

/**
 * Get the configured feeds page ID.
 *
 * @since 0.5
 *
 * @return int Page ID or 0 if not set.
 */
function pmpromrss_get_feeds_page_id() {
	global $pmpro_pages;
	if ( ! empty( $pmpro_pages['pmpromrss_member_rss'] ) ) {
		return intval( $pmpro_pages['pmpromrss_member_rss'] );
	}
	return 0;
}

/**
 * Shortcode: [pmpro_member_rss]
 * Renders the member RSS feeds management page.
 *
 * @since 0.5
 *
 * @return string HTML output.
 */
function pmpromrss_member_rss_shortcode() {
	// Must be logged in.
	if ( ! is_user_logged_in() ) {
		return '<p>' . esc_html__( 'You must be logged in to view your RSS feeds.', 'pmpro-member-rss' ) . '</p>';
	}

	$user = wp_get_current_user();

	// Must have a membership level.
	if ( function_exists( 'pmpro_hasMembershipLevel' ) && ! pmpro_hasMembershipLevel( null, $user->ID ) ) {
		return '<p>' . esc_html__( 'You must have an active membership to access RSS feeds.', 'pmpro-member-rss' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Process regenerate key form submission (group-accounts pattern).
	// -------------------------------------------------------------------------
	$message = '';
	if ( ! empty( $_REQUEST['pmpromrss_regenerate_key'] ) && ! empty( $_REQUEST['user_id'] ) ) {
		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'pmpromrss_regenerate' ) ) {
			$message = '<div class="' . esc_attr( pmpro_get_element_class( 'pmpro_message pmpro_error' ) ) . '"><p>' . esc_html__( 'Invalid request. Please try again.', 'pmpro-member-rss' ) . '</p></div>';
		} else {
			$regen_user_id = intval( $_REQUEST['user_id'] );
			if ( ! current_user_can( 'edit_user', $regen_user_id ) ) {
				$message = '<div class="' . esc_attr( pmpro_get_element_class( 'pmpro_message pmpro_error' ) ) . '"><p>' . esc_html__( 'You do not have permission to regenerate this key.', 'pmpro-member-rss' ) . '</p></div>';
			} else {
				delete_user_meta( $regen_user_id, 'pmpromrss_key' );
				$message = '<div class="' . esc_attr( pmpro_get_element_class( 'pmpro_message pmpro_success' ) ) . '"><p>' . esc_html__( 'A new RSS key has been generated. Update any RSS readers that were using the old feed URL.', 'pmpro-member-rss' ) . '</p></div>';
			}
		}
	}

	// -------------------------------------------------------------------------
	// Determine active auth mode.
	// -------------------------------------------------------------------------
	$basic_auth_enabled    = get_option( 'pmpro_pmpromrss_basic_auth' ) === 'Enabled';
	$memberkey_as_password = get_option( 'pmpro_pmpromrss_memberkey_as_password' ) === 'Enabled';
	$disable_url_key       = get_option( 'pmpro_pmpromrss_disable_url_key' ) === 'Enabled';
	$block_dashboard       = get_option( 'pmpro_block_dashboard' ) === 'yes';

	$feeds = apply_filters( 'pmpromrss_feeds', array( __( 'Recent Posts Feed', 'pmpro-member-rss' ) => get_bloginfo( 'rss_url' ) ) );

	$regen_args = array(
		'pmpromrss_regenerate_key' => 1,
		'user_id'                  => $user->ID,
		'_wpnonce'                 => wp_create_nonce( 'pmpromrss_regenerate' ),
	);

	ob_start();
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro' ) ); ?>">
		<section id="pmpromrss" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section', 'pmpromrss_section' ) ); ?>">

			<?php echo wp_kses_post( $message ); ?>

			<?php // ---- Card 1: Feed URLs ---- ?>
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card', 'pmpromrss_feeds_card' ) ); ?>">
				<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>"><?php esc_html_e( 'Your RSS Feeds', 'pmpro-member-rss' ); ?></h2>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>">
						<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields-description' ) ); ?>"><?php esc_html_e( 'Copy a feed URL into your RSS reader to access your member content.', 'pmpro-member-rss' ); ?></div>
						<?php foreach ( $feeds as $title => $feed ) :
							$feed_url = pmpromrss_url( $feed, $user->ID );
						?>
							<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-text pmpro_form_field-pmpromrss_feed_url' ) ); ?>">
								<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>"><?php echo esc_html( $title ); ?></label>
								<input type="text" readonly="readonly" value="<?php echo esc_attr( $feed_url ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text pmpro_form_input-pmpromrss_feed_url' ) ); ?>" onclick="this.select();" />
							</div>
						<?php endforeach; ?>
					</div> <!-- end pmpro_form_fields -->
				</div> <!-- end pmpro_card_content -->
			</div> <!-- end pmpromrss_feeds_card -->

			<?php if ( ! $basic_auth_enabled || ! $disable_url_key || $memberkey_as_password ) : ?>
				<?php // ---- Card 2: RSS key display + regen ---- ?>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card', 'pmpromrss_auth_card' ) ); ?>">
					<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>"><?php esc_html_e( 'Feed Authentication', 'pmpro-member-rss' ); ?></h2>
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
						<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>">
							<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-text pmpro_form_field-pmpromrss_key' ) ); ?>">
								<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>" for="pmpromrss_profile_key"><?php esc_html_e( 'Your RSS Key', 'pmpro-member-rss' ); ?></label>
								<input type="text" id="pmpromrss_profile_key" readonly="readonly" value="<?php echo esc_attr( pmpromrss_getMemberKey( $user->ID ) ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text pmpro_form_input-pmpromrss_key' ) ); ?>" onclick="this.select();" />
								<p class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_hint' ) ); ?>">
									<?php if ( ! $disable_url_key ) : ?>
										<?php esc_html_e( 'Your RSS key is embedded in the feed URLs above.', 'pmpro-member-rss' ); ?>
									<?php endif; ?>

									<?php if ( $basic_auth_enabled && $memberkey_as_password ) : ?>
										<?php esc_html_e( 'Use your username and this RSS key as the password when authenticating with Basic Auth.', 'pmpro-member-rss' ); ?>
									<?php endif; ?>

									<?php esc_html_e( 'You can regenerate your key at any time. Regenerating it will invalidate the previous key.', 'pmpro-member-rss' ); ?>
								</p>
							</div>
							<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_submit' ) ); ?>">
								<a
									class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>"
									href="<?php echo esc_url( add_query_arg( $regen_args ) ); ?>"
									onclick="return confirm( <?php echo wp_json_encode( __( "Regenerating your key will immediately break any RSS readers currently using the key-based URL. You'll need to update those readers with your new feed URL. Continue?", 'pmpro-member-rss' ) ); ?> );"
								><?php esc_html_e( 'Regenerate Key', 'pmpro-member-rss' ); ?></a>
							</div> <!-- end pmpro_form_submit -->
						</div> <!-- end pmpro_form_fields -->
					</div> <!-- end pmpro_card_content -->
				</div> <!-- end pmpromrss_auth_card -->

			<?php else : ?>
				<?php // ---- Card 2: Application Passwords only (basic auth ON, URL key disabled, memberkey-as-password disabled) ---- ?>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card', 'pmpromrss_auth_card' ) ); ?>">
					<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>"><?php esc_html_e( 'Feed Authentication', 'pmpro-member-rss' ); ?></h2>
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
						<?php if ( ! $block_dashboard ) : ?>
							<p><?php
								printf(
									/* translators: %s is a link to the WP admin profile page */
									esc_html__( 'To access these feeds, you need a WordPress Application Password. %s to create one, then use your WordPress username and the application password in your RSS reader.', 'pmpro-member-rss' ),
									'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'Visit your profile', 'pmpro-member-rss' ) . '</a>'
								);
							?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Contact the site administrator to receive your RSS feed authentication credentials.', 'pmpro-member-rss' ); ?></p>
						<?php endif; ?>
					</div> <!-- end pmpro_card_content -->
				</div> <!-- end pmpromrss_auth_card -->
			<?php endif; ?>

		</section> <!-- end pmpromrss_section -->
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_actions_nav' ) ); ?>">
			<span class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_actions_nav-right' ) ); ?>"><a href="<?php echo esc_url( pmpro_url( "account" ) ) ?>"><?php esc_html_e('View Your Membership Account &rarr;', 'pmpro-member-rss' );?></a></span>
		</div> <!-- end pmpro_actions_nav -->
	</div> <!-- end pmpro -->
	<?php
	return ob_get_clean();
}
add_shortcode( 'pmpro_member_rss', 'pmpromrss_member_rss_shortcode' );

/**
 * On the PMPro Advanced Settings page, hide the two dependent RSS settings
 * ("Allow Memberkey as Basic Auth Password" and "Disable Memberkey in URL")
 * unless "Enable Basic Authentication for RSS Feeds" is set to Yes.
 *
 * @since 0.5
 */
function pmpromrss_advanced_settings_js() {
	if ( ! is_admin() || empty( $_GET['page'] ) || $_GET['page'] !== 'pmpro-advancedsettings' ) {
		return;
	}
	?>
	<script>
	jQuery( function( $ ) {
		function pmpromrssToggleDependentSettings() {
			var enabled = $( '#pmpromrss_basic_auth' ).val() === 'Enabled';
			$( '#pmpromrss_memberkey_as_password' ).prop( 'disabled', ! enabled );
			$( '#pmpromrss_disable_url_key' ).prop( 'disabled', ! enabled );
		}
		pmpromrssToggleDependentSettings();
		$( '#pmpromrss_basic_auth' ).on( 'change', pmpromrssToggleDependentSettings );
	} );
	</script>
	<?php
}
add_action( 'admin_footer', 'pmpromrss_advanced_settings_js' );
