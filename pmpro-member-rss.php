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

/*
	Utility Functions
*/
//get a member key
function pmpromrss_getMemberKey($user_id = NULL)
{
	//default to current user
	if(empty($user_id))
	{
		global $current_user;
		$user_id = $current_user->ID;
	}
	
	//make sure we have a user
	if(empty($user_id))
		return false;
		
	$user = get_userdata($user_id);

	//get key
	$key = get_user_meta($user->ID, "pmpromrss_key", true);
	
	//create member key if they don't already have one
	if(empty($key))
	{
		$key = md5(time() . $user->user_login . AUTH_KEY);
		update_user_meta($user->ID, "pmpromrss_key", $key);
	}
	
	return $key;
}

//add the memberkey to a url
function pmpromrss_url($url, $user_id = NULL)
{
	$key = pmpromrss_getMemberKey($user_id);
	
	return add_query_arg("memberkey", $key, $url);
}
	
/*
	Show RSS Feeds with Member Key on Membership Account Page
*/
function pmpromrss_pmpro_member_links_bottom()
{	
	//show links to RSS feeds (format is title => url)
	$feeds = apply_filters("pmpromrss_feeds", array("Recent Posts Feed" => get_bloginfo('rss_url')));
	
	//show URLs
	foreach($feeds as $title => $feed)
	{
	?>
		<li><a href="<?php echo pmpromrss_url($feed);?>"><?php echo $title;?></a></li>
	<?php
	}
}
add_action('pmpro_member_links_bottom', 'pmpromrss_pmpro_member_links_bottom');

/*
	Check for Member Key and Disable Content Filter in RSS Feed Items
*/
//only filter if a valid member key is present
function pmprorss_init() {	
	global $wpdb, $pmpromrss_user_id, $wp_query;
	if( ! empty( $_REQUEST['memberkey'] ) ) {		
		$key = preg_replace( '[0-9a-f]', '', $_REQUEST['memberkey'] );
		$pmpromrss_user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmpromrss_key' AND meta_value = '" . esc_sql($key) . "' LIMIT 1");

		// Use our search filter if PMPro set one up.
		if ( $wp_query->is_feed && has_filter( 'pre_get_posts', 'pmpro_search_filter' ) ) {
			remove_filter( 'pre_get_posts', 'pmpro_search_filter' );
			add_filter( 'pre_get_posts', 'pmprorss_search_filter' );	
		}
	}	
}
add_action('init', 'pmprorss_init', 1);

/**
 * Override the current user when running search queries.
 * @since 0.3
 */
function pmprorss_search_filter( $query ) {
	global $current_user, $pmpromrss_user_id;

	$current_user_backup = $current_user;
	$current_user = wp_set_current_user( $pmpromrss_user_id );

	$query = pmpro_search_filter( $query );

	$current_user = $current_user_backup;
}

//update has access filter
function pmpromrss_pmpro_has_membership_access_filter( $hasaccess, $mypost, $myuser, $post_membership_levels ) {
	global $pmpromrss_user_id, $wp_query;		
	
	if( empty( $pmpromrss_user_id ) ) {
		return $hasaccess;
	}	
	
	// Remove this filter itself to avoid loops.
	remove_filter( 'pmpro_has_membership_access_filter', 'pmpromrss_pmpro_has_membership_access_filter', 10, 4 );

	// Mask is_feed flag, since PMPro hides all member content from feeds.
	$is_feed = $wp_query->is_feed;
	$wp_query->is_feed = false;

	// Now check again with the RSS user.
	if ( ! empty( $mypost ) && ! empty( $mypost->ID ) ) {
		$mypost_id = $mypost->ID;
	} else {
		$mypost_id = null;
	}
	$hasaccess = pmpro_has_membership_access( $mypost_id, $pmpromrss_user_id );

	// Add the filter back.
	add_filter( 'pmpro_has_membership_access_filter', 'pmpromrss_pmpro_has_membership_access_filter', 10, 4 );

	// Reset is_feed
	$wp_query->is_feed = $is_feed;
	
	return $hasaccess;	
}
add_filter( 'pmpro_has_membership_access_filter', 'pmpromrss_pmpro_has_membership_access_filter', 10, 4 );

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
	
	$text = sprintf( __( 'Please visit %s to access this member content.', 'pmpro-member-rss' ), get_permalink( $post->ID ) );
	
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
 * Display the Member RSS Key and allow it to be regenerated
 *
 * @since TBD
 * @param  object $user The current user object that is being edited
 * @return mixed HTML content
 */
function pmprorss_memberkeys_profile( $user ) { 

	global $pmpro_levels;

	$args = array(
		'pmpromrss_regenerate_key' => 1,
		'user_id' => $user->ID
	);
	?>

    <h3><?php esc_html_e( 'Member RSS Key', 'pmpro-member-rss' ); ?></h3>

    <table class="form-table">

	    <tr id='pmpromrss_key'>
	        <th><label for="address"><?php esc_html_e( 'Recent Posts Feed', 'pmpro-member-rss' ); ?></label></th>
	        <td>
	            <input type="text" name="pmpromrss_profile_key" id="pmpromrss_profile_key" readonly="readonly" value="<?php echo pmpromrss_getMemberKey( $user->ID ); ?>" class="regular-text" />&nbsp;<a href='<?php echo esc_html( add_query_arg( $args, get_edit_profile_url() ).'#pmpromrss_key' ); ?>' class='button button-primary'><?php esc_html_e( 'Regenerate Key', 'pmpro-member-rss' ); ?></a>
	        </td>
	    </tr>
    	
    </table>
<?php }
add_action( 'show_user_profile', 'pmprorss_memberkeys_profile' );
add_action( 'edit_user_profile', 'pmprorss_memberkeys_profile' );

/**
 * Deletes the existing Member key and generates a new one
 *
 * @since TBD
 * @return void
 */
function pmprorss_memberkeys_profile_regenerate() {

	if ( empty( $_REQUEST['user_id'] ) ) {
		return;
	}
  
    if ( empty( $_REQUEST['pmpromrss_regenerate_key'] ) ) {
    	return;
    }

    $user_id = intval( $_REQUEST['user_id'] );
    
	if ( !current_user_can( 'edit_user', $user_id ) ) { 
		return false; 
	}	

    delete_user_meta( $user_id, 'pmpromrss_key' );
    	
   	pmpromrss_getMemberKey( $user_id );		

}
add_action( 'admin_init', 'pmprorss_memberkeys_profile_regenerate' );

/*
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

	pmpromrss_getMemberKey( $user_id );

}
add_action( 'pmpro_after_change_membership_level', 'pmprorss_after_level_change_generate_key', 10, 3 );
