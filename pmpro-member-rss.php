<?php
/*
Plugin Name: Paid Memberships Pro - Member RSS Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-member-rss/
Description: Create Member-Specific RSS Feeds for Paid Memberships Pro
Version: .2
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/
/*
	1. Generate a random key for each user.
	2. Check for &memberkey param in RSS URL.
	3. If memberkey is present and valid, don't restrict content in RSS.
	
	To add more RSS URLs, use the pmpromrss_feeds filter.
*/

if ( defined( 'PMPRO_VERSION' ) && PMPRO_VERSION >= '2.9' ) {
	add_action( 'pmpro_membership_level_before_billing_information', 'pmprorss_level_settings' );
} else {
	add_action( 'pmpro_membership_level_after_other_settings', 'pmprorss_level_settings' );
}

function pmprorss_level_settings() {

	$level_id = $_REQUEST['edit'];

	$separate_member_key = pmpro_getOption( 'rss_member_key_'.$level_id );

	$section_visibility = 'shown';
	$section_activated = 'true';

	?>
	<div id="rss-settings" class="pmpro_section" data-visibility="<?php echo esc_attr( $section_visibility ); ?>" data-activated="<?php echo esc_attr( $section_activated ); ?>">
		<div class="pmpro_section_toggle">
			<button class="pmpro_section-toggle-button" type="button" aria-expanded="<?php echo $section_visibility === 'hidden' ? 'false' : 'true'; ?>">
				<span class="dashicons dashicons-arrow-<?php echo $section_visibility === 'hidden' ? 'down' : 'up'; ?>-alt2"></span>
				<?php esc_html_e( 'RSS Settings', 'pmpro-member-rss' ); ?>
			</button>
		</div>
		<div class="pmpro_section_inside" <?php echo $section_visibility === 'hidden' ? 'style="display: none"' : ''; ?>>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row" valign="top"><label for="rss_require_separate_key"><?php esc_html_e( 'Require a separate access key for this level?', 'pmpro-approvals' ); ?></label></th>
						<td>
							<input type='checkbox' name='pmprorss_level_key' value='1' <?php checked( 1, $separate_member_key ); ?> id='rss_require_separate_key'/>
							<label for="rss_require_separate_key"><?php esc_html_e( 'Should a separate member key be generated for this level to access content.', 'pmpro-member-rss' ); ?></label>
						</td>
					</tr>
				</tbody>
			</table>
		</div> <!-- end pmpro_section_inside -->
	</div> <!-- end pmpro_section -->
	<?php
}

function pmprorss_save_membership_level( $level_id ) {

	if ( isset( $_REQUEST['pmprorss_level_key'] ) ) {
		$use_separate_key = true;
	} else {
		$use_separate_key = false;
	}

	pmpro_setOption( 'rss_member_key_'.$level_id, $use_separate_key );

}
add_action( 'pmpro_save_membership_level', 'pmprorss_save_membership_level' );


/*
	Utility Functions
*/
//get a member key
function pmpromrss_getMemberKey( $user_id = NULL, $level_id = false ) {
	//default to current user
	if ( empty( $user_id ) ) {
		global $current_user;
		$user_id = $current_user->ID;
	}
	
	//make sure we have a user
	if ( empty( $user_id ) ) {
		return false;
	}
		
	$user = get_userdata($user_id);
// var_dump($level_id);
	if ( $level_id ) {

		//Should the level they're checking out with get a separate key?
		$separate_key = pmpro_getOption( 'rss_member_key_'.$level_id );

		if ( $separate_key ) {
			$key = get_user_meta( $user->ID, 'pmpromrss_key_'.$level_id, true );
			//create member key if they don't already have one
			if ( empty( $key ) ) {
				$key = md5(time() . $user->user_login . AUTH_KEY);
				update_user_meta( $user->ID, 'pmpromrss_key_'.$level_id, $key );
			}
		}

	} else {

		//get key
		$key = get_user_meta($user->ID, "pmpromrss_key", true);
		
		//create member key if they don't already have one
		if ( empty( $key ) ) {
			$key = md5(time() . $user->user_login . AUTH_KEY);
			update_user_meta($user->ID, "pmpromrss_key", $key);
		}

	}
	
	return $key;
}

//add the memberkey to a url
function pmpromrss_url($url, $user_id = NULL, $level = false ) {

	$key = pmpromrss_getMemberKey( $user_id, $level );

	if( $level ) {

		return add_query_arg( array(
			'memberkey' => $key,
			'level'		=> $level
		), $url );

	} else {
	
		return add_query_arg( 'memberkey', $key, $url );

	}
	
}
	
/*
	Show RSS Feeds with Member Key on Membership Account Page
*/
function pmpromrss_pmpro_member_links_bottom() {

	global $pmpro_levels;

	//show links to RSS feeds (format is title => url)
	$feed_array = array( 
		__( 'Recent Posts Feed', 'pmpro-member-rss' ) => get_bloginfo( 'rss_url' ) 
	);

	$levels_array = array();

	foreach( $pmpro_levels as $level ) {
		$requires_key = intval( pmpro_getOption( 'rss_member_key_'.$level->id ) );

		if( $requires_key ) {
			//Add a feed with that level's key
			$feed_array[sprintf( __( 'Recent Posts Feed - %s', 'pmpro-member-rss' ), $level->name )] = array( 'url' => get_bloginfo( 'rss_url' ), 'level' => $level->id );
		}
	}

	$feeds = apply_filters( "pmpromrss_feeds", $feed_array );	

	//show URLs
	foreach( $feeds as $title => $feed ) {

		if( is_array( $feed ) ) {
			
			?>
				<li><a href="<?php echo pmpromrss_url( $feed['url'], NULL, intval( $feed['level'] ) ); ?>"><?php echo $title; ?></a></li>
			<?php
		} else {
			//Backwards compatibility
			?>
				<li><a href="<?php echo pmpromrss_url( $feed ); ?>"><?php echo $title; ?></a></li>
			<?php
		}	
	}
}
add_action('pmpro_member_links_bottom', 'pmpromrss_pmpro_member_links_bottom');

/*
	Check for Member Key and Disable Content Filter in RSS Feed Items
*/
//only filter if a valid member key is present
function pmprorss_init() {

	if ( !empty( $_REQUEST['memberkey'] ) ) {		
		
		global $wpdb;
		$key = $_REQUEST['memberkey'];
		
		global $pmpromrss_user_id, $pmpromrss_level_id;

		if( ! empty( $_REQUEST['level'] ) ) {
			$pmpromrss_level_id = intval( $_REQUEST['level'] );

			$pmpromrss_user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmpromrss_key_".$pmpromrss_level_id."' AND meta_value = '" . esc_sql($key) . "' LIMIT 1");
		} else {

			$pmpromrss_user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmpromrss_key' AND meta_value = '" . esc_sql($key) . "' LIMIT 1");			
		}
		
		var_dump($pmpromrss_user_id);
	}	
}
add_action('init', 'pmprorss_init', 1);

//update has access filter
function pmpromrss_pmpro_has_membership_access_filter($hasaccess, $mypost, $myuser, $post_membership_levels)
{
	global $pmpromrss_user_id, $pmpromrss_level_id;		
	
	if(empty($pmpromrss_user_id))
		return $hasaccess;
	
	//we need to see if the user has access
	$post_membership_levels_ids = array();
	if( !empty( $post_membership_levels ) ) {
		foreach($post_membership_levels as $level) {
			if( ! empty( $pmpromrss_level_id ) ) {
				if( $pmpromrss_level_id == $level->id ) {
					$post_membership_levels_ids[] = $level->id;
				}
			} else {
				$post_membership_levels_ids[] = $level->id;
			}
		}
	}
		
	if(pmpro_hasMembershipLevel($post_membership_levels_ids, $pmpromrss_user_id))
		$hasaccess = true;
	
	return $hasaccess;
}
add_filter('pmpro_has_membership_access_filter', 'pmpromrss_pmpro_has_membership_access_filter', 10, 4);

//remove enclosures for member feeds
function pmprorss_rss_enclosure($enclosure)
{
	global $post;
	
	if(!pmpro_has_membership_access())
		$enclosure = "";
	
	return $enclosure;
}
add_filter('rss_enclosure', 'pmprorss_rss_enclosure', 20);

//better rss messages
function pmprorss_pmpro_rss_text_filter($text)
{
	global $post;
	
	$text = "Please visit " . get_permalink($post->ID) . " to access this member content.";
	
	return $text;
}
add_filter('pmpro_rss_text_filter', 'pmprorss_pmpro_rss_text_filter');

/*
Function to add links to the plugin row meta
*/
function pmprorss_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-member-rss.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprorss_plugin_row_meta', 10, 2);

/**
 * Generates the member key after the level has changed
 *
 * @param $level_id int Level ID being changed to
 * @param $user_id int User ID this relates to
 * @param $cancel_id int Level ID that is being cancelled
 * 
 * @since TBD
 * @return void
 */
function pmprorss_after_level_change_generate_key( $level_id, $user_id, $cancel ) {

	pmpromrss_getMemberKey( $user_id, $level_id );

}
add_action( 'pmpro_after_change_membership_level', 'pmprorss_after_level_change_generate_key', 10, 3 );