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
function pmprorss_init()
{
	if(!empty($_REQUEST['memberkey']))
	{		
		global $wpdb;
		$key = $_REQUEST['memberkey'];
		global $pmpromrss_user_id;
		$pmpromrss_user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmpromrss_key' AND meta_value = '" . esc_sql($key) . "' LIMIT 1");				
	}	
}
add_action('init', 'pmprorss_init', 1);

//update has access filter
function pmpromrss_pmpro_has_membership_access_filter($hasaccess, $mypost, $myuser, $post_membership_levels)
{
	global $pmpromrss_user_id;		
	
	if(empty($pmpromrss_user_id))
		return $hasaccess;
	
	//we need to see if the user has access
	$post_membership_levels_ids = array();
	if(!empty($post_membership_levels))
	{
		foreach($post_membership_levels as $level)
			$post_membership_levels_ids[] = $level->id;
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