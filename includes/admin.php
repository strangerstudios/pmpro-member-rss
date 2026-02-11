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
 * @since TBD
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
 * @since TBD
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
				<a class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn', 'pmpromrss_profile_key' ) ); ?>" href="<?php echo esc_attr( "javascript:if(confirm('" . esc_js( __( " This will invalidate all currently connected keys. Are you sure you want to regenerate your key?", 'pmpro-member-rss' ) ) . "')){window.location.href='" . esc_url( add_query_arg( $args ) ) . "';}" ); ?>"><?php esc_html_e( 'Regenerate Key', 'pmpro-member-rss' ); ?></a>
				
			</div>
		</div>
		<?php if ( get_option( 'pmpro_pmpromrss_basic_auth' ) ) : ?>
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
add_action( 'pmpro_show_user_profile', 'pmpromrss_memberkeys_profile_frontend' );

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

	$pmpro_is_frontend_profile = is_page( $pmpro_pages['member_profile_edit'] );
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
 * @since TBD
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
		'description' => __( 'Enable this option to allow users to authenticate to their member RSS feeds using Basic Authentication with their WordPress username and application password. This is an alternative to using the member key method for authentication. Note: Basic Authentication will only work for feed requests that include the <codepmpromrss_basic_auth=1</code> query parameter.', 'pmpro-member-rss' ),
	);
	
	return $fields;
}
add_filter( 'pmpro_custom_advanced_settings', 'pmpromrss_advanced_settings', 5 );
