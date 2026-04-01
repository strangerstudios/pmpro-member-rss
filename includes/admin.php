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
		<li><a href="<?php echo esc_url( get_permalink( $feeds_page_id ) ); ?>"><?php esc_html_e( 'Manage your RSS feeds', 'pmpro-member-rss' ); ?> &rarr;</a></li>
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
 * Display the fields on the frontend profile page (Membership Account > Edit Profile)
 * 
 * @since 0.4
 *
 * @return void
 */
function pmpromrss_memberkeys_profile_frontend( $user ) {
	$args = array(
		'pmpromrss_regenerate_key' => 1,
		'user_id' => $user->ID,
		'_wpnonce' => wp_create_nonce( 'pmpromrss_regenerate' )
	);

	?>
	<div class="pmpro_spacer"></div>
	<fieldset id="pmpro_form_fieldset-member-rss-key" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fieldset', 'pmpro_form_fieldset-member-rss-key' ) ); ?>">
		<legend class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_legend' ) ); ?>">
			<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_heading pmpro_font-large' ) ); ?>"><?php esc_html_e( 'Member RSS Key', 'pmpro-member-rss' ); ?></h2>
		</legend>
		<div class="pmpro_form_fields">
			<div id="pmpromrss_key_div" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-text pmpro_form_field-pmpromrss_key', 'pmpromrss_key_div' ) ); ?>">
				<label class="pmpro_form_label" for="pmpromrss_profile_key"><?php esc_html_e( 'Your RSS Key', 'pmpro-member-rss' ); ?></label>
				<input type="text" name="pmpromrss_profile_key" id="pmpromrss_profile_key" readonly="readonly" value="<?php echo esc_attr( pmpromrss_getMemberKey( $user->ID ) ); ?>" class="<?php echo esc_attr( 'pmpro_form_input pmpro_form_input-text pmpro_form_input-pmpromrss_profile_key regular-text' ); ?>" />
				<a class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn', 'pmpromrss_profile_key' ) ); ?>" href="<?php echo esc_attr( "javascript:if(confirm('" . esc_js( __( "Regenerating your key will require you to update the feed URL in all connected RSS readers. Are you sure?", 'pmpro-member-rss' ) ) . "')){window.location.href='" . esc_url( add_query_arg( $args ) ) . "';}" ); ?>"><?php esc_html_e( 'Regenerate Key', 'pmpro-member-rss' ); ?></a>
				
			</div>
		</div>
		<?php if ( get_option( 'pmpro_pmpromrss_basic_auth' ) === 'Enabled' ) : ?>
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-info pmpro_form_field-pmpromrss_basic_auth_info' ) ); ?>">
				<p class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_hint', 'pmpro_form_field-pmpromrss_basic_auth_info' ) ); ?>"><?php
				// translators: %s is the query parameter to add to the feed URL for basic authentication.  
				printf( esc_html__( 'Note: Basic Authentication is enabled. You can access your member RSS feeds using either your member key or your WordPress username and application password. To authenticate with your application password, append %s to any feed URL and provide your credentials via Basic Authentication.', 'pmpro-member-rss' ), '<code>?pmpromrss_basic_auth=1</code>' ); 
				
				?></p>
			</div>
		<?php endif; ?>
	</fieldset>
	<?php
}
/**
 * Conditionally add the frontend profile fields.
 * If the feeds page is configured, suppress from Edit Profile (members use the feeds page instead).
 *
 * @since 0.5
 */
function pmpromrss_maybe_add_frontend_profile() {
	$feeds_page_id = pmpromrss_get_feeds_page_id();
	if ( empty( $feeds_page_id ) ) {
		add_action( 'pmpro_show_user_profile', 'pmpromrss_memberkeys_profile_frontend' );
	}
}
add_action( 'init', 'pmpromrss_maybe_add_frontend_profile' );

/**
 * Deletes the existing Member key, a new key will be generated when pmpromrss_getMemberKey is loaded.
 *
 * @since 0.3
 * @return void
 */
function pmpromrss_memberkeys_profile_regenerate() {
	global $pmpro_pages;

	if ( empty( $_REQUEST['user_id'] ) ) {
		return;
	}
  
    if ( empty( $_REQUEST['pmpromrss_regenerate_key'] ) ) {
    	return;
    }

	$feeds_page_id = pmpromrss_get_feeds_page_id();
	$pmpro_is_frontend_profile = is_page( $pmpro_pages['member_profile_edit'] ) || ( ! empty( $feeds_page_id ) && is_page( $feeds_page_id ) );
	$div_class_success = $pmpro_is_frontend_profile ? 'pmpro_message pmpro_success' : 'notice notice-success';
	
	if ( ! empty( $_REQUEST['_wpnonce'] ) && ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'pmpromrss_regenerate' ) ) {

		$div_class_errors = $pmpro_is_frontend_profile ? 'pmpro_message pmpro_error' : 'notice notice-error';
		?>
		<div class="<?php echo esc_attr( $div_class_errors ); ?>">
			<p><?php esc_html_e( 'Invalid nonce for regen. Try again.', 'pmpro-member-rss' ); ?></p>
		</div>
		<?php
	}

    $user_id = intval( $_REQUEST['user_id'] );
    
	if ( ! current_user_can( 'edit_user', $user_id ) ) { 
		return false; 
	}	

    delete_user_meta( $user_id, 'pmpromrss_key' );
	
	?>
	<div class="<?php echo esc_attr( $div_class_success ); ?>">
		<p><?php esc_html_e( 'A new key has been generated.', 'pmpro-member-rss' ); ?></p>
	</div>
	<?php

	// Move the messages to the top if we're on the frontend profile page.
	if ( $pmpro_is_frontend_profile ) {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var message = document.querySelector('.pmpro_message');
				var target = document.querySelector('#member-profile-edit .pmpro_card');
				if (message && target) {
					target.parentNode.insertBefore(message, target);
				}
			});
		</script>
		<?php
	}

}
add_action( 'wp', 'pmpromrss_memberkeys_profile_regenerate' ); // Needed for the frontend edit profile.
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

	// Add an option for basic authentication.
	$fields['pmpromrss_basic_auth'] = array(
		'field_name' => 'pmpromrss_basic_auth',
		'field_type' => 'select',
		'options' => array(
			0 => __( 'Disabled', 'pmpro-member-rss' ),
			1 => __( 'Enabled', 'pmpro-member-rss' ),
		),
		'label' => __( 'Enable Basic Authentication', 'pmpro-member-rss' ),
		'description' => __( 'Enable this option to allow users to authenticate to their member RSS feeds using Basic Authentication with their WordPress username and application password. This is an alternative to using the member key method for authentication. Note: Basic Authentication will only work for feed requests that include the pmpromrss_basic_auth=1 query parameter.', 'pmpro-member-rss' ),
	);

	// Add an option to allow memberkey as Basic Auth password.
	$fields['pmpromrss_memberkey_as_password'] = array(
		'field_name' => 'pmpromrss_memberkey_as_password',
		'field_type' => 'select',
		'options' => array(
			0 => __( 'Disabled', 'pmpro-member-rss' ),
			1 => __( 'Enabled', 'pmpro-member-rss' ),
		),
		'label' => __( 'Allow Memberkey as Basic Auth Password', 'pmpro-member-rss' ),
		'description' => __( 'Enable this option to allow members to use their memberkey as the password in Basic Authentication. The username can be any value. This allows RSS readers that support Basic Auth but not application passwords to still authenticate securely. Requires "Enable Basic Authentication" to also be enabled.', 'pmpro-member-rss' ),
	);

	// Add an option to disable memberkey in URL.
	$fields['pmpromrss_disable_url_key'] = array(
		'field_name' => 'pmpromrss_disable_url_key',
		'field_type' => 'select',
		'options' => array(
			0 => __( 'Disabled', 'pmpro-member-rss' ),
			1 => __( 'Enabled', 'pmpro-member-rss' ),
		),
		'label' => __( 'Disable Memberkey in URL', 'pmpro-member-rss' ),
		'description' => __( 'Enable this option to prevent feed authentication via the memberkey URL parameter. When active, members must use Basic Authentication instead. Feed URLs will no longer include the memberkey.', 'pmpro-member-rss' ),
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
	$pages['pmpromrss_feeds'] = array(
		'title'   => __( 'Member RSS Feeds', 'pmpro-member-rss' ),
		'content' => '[pmpromrss_feeds]',
		'hint'    => __( 'Include the [pmpromrss_feeds] shortcode.', 'pmpro-member-rss' ),
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
	if ( ! empty( $pmpro_pages['pmpromrss_feeds'] ) ) {
		return intval( $pmpro_pages['pmpromrss_feeds'] );
	}
	return 0;
}

/**
 * Shortcode: [pmpromrss_feeds]
 * Renders the member RSS feeds management page.
 *
 * @since 0.5
 *
 * @return string HTML output.
 */
function pmpromrss_feeds_shortcode() {
	// Must be logged in.
	if ( ! is_user_logged_in() ) {
		return '<p>' . esc_html__( 'You must be logged in to view your RSS feeds.', 'pmpro-member-rss' ) . '</p>';
	}

	$user = wp_get_current_user();

	// Must have a membership level.
	if ( function_exists( 'pmpro_hasMembershipLevel' ) && ! pmpro_hasMembershipLevel( null, $user->ID ) ) {
		return '<p>' . esc_html__( 'You must have an active membership to access RSS feeds.', 'pmpro-member-rss' ) . '</p>';
	}

	$disable_url_key       = get_option( 'pmpro_pmpromrss_disable_url_key' ) === 'Enabled';
	$basic_auth_enabled    = get_option( 'pmpro_pmpromrss_basic_auth' ) === 'Enabled';
	$memberkey_as_password = get_option( 'pmpro_pmpromrss_memberkey_as_password' ) === 'Enabled';

	$feeds = apply_filters( 'pmpromrss_feeds', array( 'Recent Posts Feed' => get_bloginfo( 'rss_url' ) ) );
	$key   = pmpromrss_getMemberKey( $user->ID );

	ob_start();
	?>
	<div id="pmpromrss_feeds_page" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
		<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>"><?php esc_html_e( 'Your RSS Feeds', 'pmpro-member-rss' ); ?></h2>
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
			<?php if ( ! $disable_url_key ) : ?>
				<h3 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_font-medium' ) ); ?>"><?php esc_html_e( 'Feed URLs', 'pmpro-member-rss' ); ?></h3>
				<p><?php esc_html_e( 'Copy and paste a feed URL below into your RSS reader to access your member content.', 'pmpro-member-rss' ); ?></p>
				<?php foreach ( $feeds as $title => $feed ) : ?>
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-text' ) ); ?>">
						<label class="pmpro_form_label"><?php echo esc_html( $title ); ?></label>
						<input type="text" readonly="readonly" value="<?php echo esc_attr( pmpromrss_url( $feed, $user->ID ) ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text regular-text' ) ); ?>" onclick="this.select();" />
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( $basic_auth_enabled ) : ?>
				<h3 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_font-medium' ) ); ?>"><?php esc_html_e( 'Basic Authentication', 'pmpro-member-rss' ); ?></h3>

				<?php if ( $memberkey_as_password ) : ?>
					<p><?php
						printf(
							/* translators: %s is the query parameter to add to the feed URL. */
							esc_html__( 'Your RSS reader can authenticate using Basic Auth. Use any username and your member key as the password. Append %s to the feed URL.', 'pmpro-member-rss' ),
							'<code>?pmpromrss_basic_auth=1</code>'
						);
					?></p>
				<?php else : ?>
					<p><?php
						printf(
							/* translators: %s is the query parameter to add to the feed URL. */
							esc_html__( 'Your RSS reader can authenticate using Basic Auth with your WordPress username and an application password. Append %s to the feed URL.', 'pmpro-member-rss' ),
							'<code>?pmpromrss_basic_auth=1</code>'
						);
					?></p>
					<p><?php
						printf(
							/* translators: %s is the URL to the application passwords section. */
							esc_html__( 'You can create an application password in your %s.', 'pmpro-member-rss' ),
							'<a href="' . esc_url( get_edit_profile_url( $user->ID ) . '#application-passwords-section' ) . '">' . esc_html__( 'WordPress profile', 'pmpro-member-rss' ) . '</a>'
						);
					?></p>
				<?php endif; ?>

				<?php if ( $disable_url_key ) : ?>
					<?php foreach ( $feeds as $title => $feed ) : ?>
						<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-text' ) ); ?>">
							<label class="pmpro_form_label"><?php echo esc_html( $title ); ?></label>
							<input type="text" readonly="readonly" value="<?php echo esc_attr( add_query_arg( 'pmpromrss_basic_auth', '1', $feed ) ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text regular-text' ) ); ?>" onclick="this.select();" />
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! $disable_url_key || $memberkey_as_password ) : ?>
				<div class="pmpro_spacer"></div>
				<h3 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_font-medium' ) ); ?>"><?php esc_html_e( 'Your Member Key', 'pmpro-member-rss' ); ?></h3>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-text' ) ); ?>">
					<input type="text" readonly="readonly" value="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text regular-text' ) ); ?>" onclick="this.select();" />
				</div>
				<?php
				$regen_args = array(
					'pmpromrss_regenerate_key' => 1,
					'user_id'                  => $user->ID,
					'_wpnonce'                 => wp_create_nonce( 'pmpromrss_regenerate' ),
				);
				?>
				<p>
					<a class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>" href="<?php echo esc_attr( "javascript:if(confirm('" . esc_js( __( "Regenerating your key will require you to update the feed URL in all connected RSS readers. Are you sure?", 'pmpro-member-rss' ) ) . "')){window.location.href='" . esc_url( add_query_arg( $regen_args ) ) . "';}" ); ?>"><?php esc_html_e( 'Regenerate Key', 'pmpro-member-rss' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'pmpromrss_feeds', 'pmpromrss_feeds_shortcode' );
