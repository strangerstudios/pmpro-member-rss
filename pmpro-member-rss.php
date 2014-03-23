<?php
/*
Plugin Name: PMPro Member RSS Feeds
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-member-rss/
Description: Create Member-Specific RSS Feeds for Paid Memberships Pro
Version: .1
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
	Show RSS Feeds with Member Key on Membership Account Page
*/
function pmpromrss_pmpro_member_links_bottom()
{
	global $current_user;
	
	//get member key
	$key = get_user_meta($current_user->ID, "pmpromrss_key", true);
	
	//create member key if they don't already have one
	if(empty($key))
	{
		$key = md5(time() . $current_user->user_login . AUTH_KEY);
		update_user_meta($current_user->ID, "pmpromrss_key", $key);
	}
	
	//show links to RSS feeds (format is title => url)
	$feeds = apply_filters("pmpromrss_feeds", array("Recent Posts Feed" => get_bloginfo('rss_url')));
	
	//show URLs
	foreach($feeds as $title => $feed)
	{
	?>
		<li><a href="<?php echo esc_attr(add_query_arg("memberkey", $key, $feed));?>"><?php echo $title;?></a></li>
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