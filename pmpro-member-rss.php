<?php
/*
Plugin Name: Paid Memberships Pro - Member RSS Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-member-rss/
Description: Create Member-Specific RSS Feeds for Paid Memberships Pro
Version: 0.3
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	1. Generate a random key for each user.
	2. Check for &memberkey param in RSS URL.
	3. If memberkey is present and valid, don't restrict content in RSS.
	
	To add more RSS URLs, use the pmpromrss_feeds filter.
*/

/**
 * Get a member's RSS key.
 *
 * @since 0.1
 * 
 * @param int $user_id
 * @return string Member RSS key, or false if no user found.
 */
function pmpromrss_getMemberKey( $user_id = NULL ) {
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

	//Get the member RSS key
	$key = get_user_meta( $user->ID, "pmpromrss_key", true );
	
	//create member key if they don't already have one
	if ( empty( $key ) ) {
		$key = md5(time() . $user->user_login . AUTH_KEY);
		update_user_meta($user->ID, "pmpromrss_key", $key);
	}
	
	return $key;
}

/**
 * Add the member key automatically to a URL.
 *
 * @since 0.1
 * 
 * @param string $url The URL to add the member key to.
 * @param int|null $user_id The WordPress user ID. If null, defaults to the current user.
 * @return string URL with member key added as a query parameter.
 */
function pmpromrss_url( $url, $user_id = NULL )  {
	$key = pmpromrss_getMemberKey( $user_id );
	return add_query_arg( "memberkey", $key, $url );
}
	
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
		<li><a href="<?php echo pmpromrss_url($feed);?>"><?php echo $title;?></a></li>
	<?php
	}
}
add_action('pmpro_member_links_bottom', 'pmpromrss_pmpro_member_links_bottom');

/**
 * Check for Member Key and Disable Content Filter in RSS Feed Items
 *
 * @since 0.1
 */
function pmprorss_init() {
	global $wpdb, $pmpromrss_user_id;

	if ( ! empty( $_REQUEST['memberkey'] ) ) {
		$key = preg_replace( '/[^0-9a-f]/', '', $_REQUEST['memberkey'] );
		$pmpromrss_user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmpromrss_key' AND meta_value = %s LIMIT 1",
				$key
			)
		);
	}
}
add_action( 'init', 'pmprorss_init', 1 );

/**
 * Filter feed queries to use the member key user's access.
 * 
 * @since TBD
 */
function pmprorss_pre_get_posts( $query ) {
	global $pmpromrss_user_id;

	// Only filter feed queries with a valid member key.
	if ( empty( $pmpromrss_user_id ) || ! $query->is_feed() ) {
		return;
	}

	// Remove PMPro's search filter for this feed query and add ours.
	if ( has_filter( 'pre_get_posts', 'pmpro_search_filter' ) ) {
		remove_filter( 'pre_get_posts', 'pmpro_search_filter' );
		add_filter( 'pre_get_posts', 'pmprorss_search_filter' );
	}
}
add_action( 'pre_get_posts', 'pmprorss_pre_get_posts', 0 );

/*
	Check for Basic Auth on Feed Requests Without Member Key
*/
function pmprorss_basic_auth_challenge() {
	global $pmpromrss_user_id, $wp_query;

	// Only proceed if this is a feed request
	if ( ! is_feed() ) {
		return;
	}

	// If we already have a valid member key, don't challenge for Basic Auth
	if ( ! empty( $pmpromrss_user_id ) ) {
		return;
	}

	// Check for our parameter to prompt Basic Auth login.
	if ( empty( $_GET['pmpromrss_basic_auth'] ) ) {
		return;
	}

	// PMPro may not be installed, let's not try to log in or anything.
	if ( ! function_exists( 'pmpro_is_spammer' ) || ! function_exists( 'pmpro_track_spam_activity' ) ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
		esc_html_e( 'Paid Memberships Pro not activated. Please ensure you have it installed and activated.', 'pmpro-member-rss' );
		exit;
	}

	// No spammers allowed.
	if ( pmpro_is_spammer() ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
		esc_html_e( 'Slow down. Access denied. Please try again later', 'pmpro-member-rss' );
		exit;
	}

	$credentials = pmprorss_get_auth_credentials();
	$username = $credentials['username'];
	$password = $credentials['password'];

	if ( empty( $username ) || empty( $password ) ) {
		pmpro_track_spam_activity(); // Count the activity here now.
		status_header( 401 );
		header( 'WWW-Authenticate: Basic realm="Private Feed - Member Login Required"' );
		esc_html_e( 'Authentication required. Please provide your WordPress username and application password.', 'pmpro-member-rss' );
		exit;
	}

	// Only use application passwords for authentication, not regular passwords.
	$user = wp_authenticate_application_password( null, $username, $password );

	// There was an error authenticating the user.
	if ( is_wp_error( $user ) ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
		esc_html_e( 'Access denied. Invalid username or application password.', 'pmpro-member-rss' );
		exit;
	}

	// Authentication successful - set the RSS user ID
	// This allows the existing membership access filter to work
	$pmpromrss_user_id = $user->ID;

	// Use our search filter if PMPro set one up.
	if ( $wp_query->is_feed && has_filter( 'pre_get_posts', 'pmpro_search_filter' ) ) {
		remove_filter( 'pre_get_posts', 'pmpro_search_filter' );
		add_filter( 'pre_get_posts', 'pmprorss_search_filter' );
	}
}
add_action( 'template_redirect', 'pmprorss_basic_auth_challenge' );

/**
 * Enable Application Password authentication for Feed Requets.
 * 
 * @since TBD
 * 
 * @is_api_request boolean Is this an API request, we can spoof this to enable it for our requests.
 */
function pmprorss_allow_application_passwords( $is_api_request ) {
	// Treat our feed request as an "API request" so application passwords are allowed.
	if ( ! empty( $_GET['pmpromrss_basic_auth'] ) ) {
		return true;
	}
	return $is_api_request;
}
add_filter( 'application_password_is_api_request', 'pmprorss_allow_application_passwords', 10, 1 );


/**
 * Get the authorization headers from the request for various servers.
 * 
 * @since TBD
 */
function pmprorss_get_auth_credentials() {
    $username = '';
    $password = '';
    $auth_header = null;

    // Try different methods to get the Authorization header
    if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
        $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif ( function_exists( 'apache_request_headers' ) ) {
        $headers = apache_request_headers();
        if ( isset( $headers['Authorization'] ) ) {
            $auth_header = $headers['Authorization'];
        } elseif ( isset( $headers['authorization'] ) ) {
            $auth_header = $headers['authorization'];
        }
    }

    // Parse Basic auth from the header
    if ( $auth_header && stripos( $auth_header, 'Basic ' ) === 0 ) {
        $credentials = base64_decode( substr( $auth_header, 6 ) );
        if ( $credentials && strpos( $credentials, ':' ) !== false ) {
            list( $username, $password ) = explode( ':', $credentials, 2 );
        }
    }

    // Fallback to PHP_AUTH_USER/PHP_AUTH_PW (may not work on all servers)
    if ( empty( $username ) && empty( $password ) ) {
        $username = $_SERVER['PHP_AUTH_USER'] ?? '';
        $password = $_SERVER['PHP_AUTH_PW'] ?? '';
    }

    // Sanitize username but leave password as-is as it needs to match.
    $username = sanitize_user( $username );

    return array(
        'username' => $username,
        'password' => $password,
    );
}

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

/**
 * Filter the has_membership_access_filter when processing RSS feeds.
 *
 * @since 0.1
 */
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

/**
 * Remove enclosures for member feeds when the person does not have access to the content.
 *
 * @since 0.1
 * 
 * @param string $enclosure 
 * @return string $modified_enclosure
 */
function pmprorss_rss_enclosure( $enclosure ) {
	global $post;
	
	if ( ! function_exists( 'pmpro_has_membership_access' ) ) {
		return $enclosure;
	}

	// Remove the enclosure.
	if ( ! pmpro_has_membership_access() ) {
		$enclosure = "";
	}
	
	return $enclosure;
}
add_filter( 'rss_enclosure', 'pmprorss_rss_enclosure', 20 );

/**
 * Improve the RSS text message when viewing the feed.
 * 
 * @since 0.1
 * 
 * @return string $text The modified RSS text.
 */
function pmprorss_pmpro_rss_text_filter( $text ) {
	global $post;
	
	$text = sprintf( esc_html__( 'Please visit %s to access this member content.', 'pmpro-member-rss' ), esc_url( get_permalink( $post->ID ) ) );
	
	return $text;
}
add_filter( 'pmpro_rss_text_filter', 'pmprorss_pmpro_rss_text_filter' );

/**
 * Function to add links to the plugin row meta
 * 
 * @since 0.1 
 */
function pmprorss_plugin_row_meta( $links, $file ) {
	if( strpos( $file, 'pmpro-member-rss.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/member-rss/' ) . '" title="' . esc_attr__( 'View Documentation', 'pmpro-member-rss' ) . '">' . esc_html__( 'Docs', 'pmpro-member-rss' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'pmpro-member-rss' ) . '">' . esc_html__( 'Support', 'pmpro-member-rss' ) . '</a>',
		);
		$links = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmprorss_plugin_row_meta', 10, 2 );

/**
 * Display the Member RSS Key and allow it to be regenerated
 *
 * @since 0.3
 * @param  object $user The current user object that is being edited
 * @return mixed HTML content
 */
function pmprorss_memberkeys_profile( $user ) { 

	global $pmpro_levels;

	$args = array(
		'pmpromrss_regenerate_key' => 1,
		'user_id' => $user->ID,
		'_wpnonce' => wp_create_nonce( 'pmpromrss_regenerate' )
	);
	?>

    <h3><?php esc_html_e( 'Member RSS', 'pmpro-member-rss' ); ?></h3>

    <table class="form-table">

	    <tr id='pmpromrss_key'>
	        <th><label for="address"><?php esc_html_e( 'Key', 'pmpro-member-rss' ); ?></label></th>
	        <td>
	            <input type="text" name="pmpromrss_profile_key" id="pmpromrss_profile_key" readonly="readonly" value="<?php echo pmpromrss_getMemberKey( $user->ID ); ?>" class="regular-text" />&nbsp;<a href='javascript:pmpro_askfirst("<?php esc_attr_e( "Are you sure you want to regenerate this user's key?", 'pmpro-member-rss' ); ?>", "<?php echo esc_url( add_query_arg( $args, get_edit_profile_url() ) ); ?>");' class="button button-primary"><?php esc_html_e( 'Regenerate Key', 'pmpro-member-rss' ); ?></a>
	        </td>
	    </tr>
    	
    </table>
<?php }
add_action( 'show_user_profile', 'pmprorss_memberkeys_profile' );
add_action( 'edit_user_profile', 'pmprorss_memberkeys_profile' );

/**
 * Deletes the existing Member key and generates a new one
 *
 * @since 0.3
 * @return void
 */
function pmprorss_memberkeys_profile_regenerate() {	
	if ( empty( $_REQUEST['user_id'] ) ) {
		return;
	}
  
    if ( empty( $_REQUEST['pmpromrss_regenerate_key'] ) ) {
    	return;
    }
	
	if ( ! empty( $_REQUEST['_wpnonce'] ) && ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'pmpromrss_regenerate' ) ) {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Invalid nonce for regen. Try again.', 'pmpro-member-rss' ); ?></p>
		</div>
		<?php
	}

    $user_id = intval( $_REQUEST['user_id'] );
    
	if ( !current_user_can( 'edit_user', $user_id ) ) { 
		return false; 
	}	

    delete_user_meta( $user_id, 'pmpromrss_key' );
    	
   	pmpromrss_getMemberKey( $user_id );
	
	?>
	<div class="notice notice-success">
		<p><?php esc_html_e( 'A new key has been generated.', 'pmpro-member-rss' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_init', 'pmprorss_memberkeys_profile_regenerate' );

/*
 * Generates the member key after the level has changed
 *
 * @param $level_id int Level ID being changed to
 * @param $user_id int User ID this relates to
 * @param $cancel_id int Level ID that is being cancelled
 * 
 * @since 0.3
 * @return void
 */
function pmprorss_after_level_change_generate_key( $level_id, $user_id, $cancel ) {
	pmpromrss_getMemberKey( $user_id );
}
add_action( 'pmpro_after_change_membership_level', 'pmprorss_after_level_change_generate_key', 10, 3 );
