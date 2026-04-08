<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Check for Member Key and Disable Content Filter in RSS Feed Items
 * Get the user ID from the member key and store it in a global variable for use in other filters.
 * 
 * @since 0.1
 */
function pmpromrss_init() {
	global $wpdb, $pmpromrss_user_id;

	if ( ! empty( $_REQUEST['memberkey'] ) ) {
		// If URL memberkey auth is disabled, don't process it.
		if ( get_option( 'pmpro_pmpromrss_disable_url_key' ) === 'Enabled' ) {
			return;
		}

		$key = preg_replace( '/[^0-9a-f]/', '', $_REQUEST['memberkey'] );
		
		// Try to get user_id from cache first
		$cache_key = 'pmpromrss_key_' . $key;
		$pmpromrss_user_id = wp_cache_get( $cache_key, 'pmpromrss_user_id' );
		
		// If not in cache, query database
		if ( false === $pmpromrss_user_id ) {
			$pmpromrss_user_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmpromrss_key' AND meta_value = %s LIMIT 1",
					$key
				)
			);
			
			// Cache the result (even if null, to avoid repeated lookups) for 12 hours.
			wp_cache_set( $cache_key, $pmpromrss_user_id, 'pmpromrss_user_id', 12 * HOUR_IN_SECONDS );
		}
	}

}
add_action( 'init', 'pmpromrss_init', 1 );

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
		
	$user = get_userdata( $user_id );

	// Make sure the user exists when trying to get the key. The $user_id passed in could be invalid.
	if ( ! $user || empty( $user->ID ) ) {
		return false;
	}

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
function pmpromrss_url( $url, $user_id = NULL ) {
	// Only switch to Basic Auth URL when the memberkey URL method is explicitly disabled.
	if ( get_option( 'pmpro_pmpromrss_disable_url_key' ) === 'Enabled' ) {
		return add_query_arg( 'pmpromrss_basic_auth', '1', $url );
	}

	$key = pmpromrss_getMemberKey( $user_id );
	return add_query_arg( 'memberkey', $key, $url );
}

/**
 * Whether PMPro spam protection is active and the required functions are available.
 *
 * @since 1.0
 * @return bool
 */
function pmpromrss_spam_protection_enabled() {
	return ! empty( get_option( 'pmpro_spamprotection' ) )
		&& function_exists( 'pmpro_is_spammer' )
		&& function_exists( 'pmpro_track_spam_activity' );
}

/**
 * Filter feed queries to use the member key user's access.
 * This ties into the memberkey method and not the basic authentication method.
 *
 * @since 0.4
 */
function pmpromrss_pre_get_posts( $query ) {
	global $wpdb, $pmpromrss_user_id;

	// Only filter feed queries with a valid member key.
	if ( ! $query->is_feed() ) {
		return;
	}

	if ( ! empty( $_REQUEST['memberkey'] ) ) {
		// Memberkey method.

	// If URL memberkey auth is disabled, reject with 403.
	if ( get_option( 'pmpro_pmpromrss_disable_url_key' ) === 'Enabled' ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
		esc_html_e( 'Memberkey URL authentication is disabled. Please use Basic Authentication instead.', 'pmpro-member-rss' );
		exit;
	}

	// They're a spammer, slow down!
	if ( pmpromrss_spam_protection_enabled() && pmpro_is_spammer() ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
		esc_html_e( 'Slow down. Access denied. Please try again later.', 'pmpro-member-rss' );
		exit;
	}

		// If the user ID is empty/0, user hasn't been authenticated.
		if ( empty( $pmpromrss_user_id ) ) {
			if ( pmpromrss_spam_protection_enabled() ) {
				pmpro_track_spam_activity(); // Someone trying a phony memberkey possibly, let's slow them down.
			}
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
			esc_html_e( 'Access denied. Please check your authentication credentials or reach out to the site administrator for assistance.', 'pmpro-member-rss' );
			exit;
		}

		wp_set_current_user( absint( $pmpromrss_user_id ) );

	} elseif ( ! empty( $_GET['pmpromrss_basic_auth'] ) ) {
		// Basic auth method — authenticate early so the filter swap happens before the query runs.
		// template_redirect will still handle issuing the 401/403 responses when auth fails.

		if ( get_option( 'pmpro_pmpromrss_basic_auth' ) !== 'Enabled' ) {
			return;
		}

		if ( ! defined( 'PMPRO_VERSION' ) ) {
			return;
		}

		if ( pmpromrss_spam_protection_enabled() && pmpro_is_spammer() ) {
			return;
		}

		// If already resolved (e.g. from a prior hook), just set the user.
		if ( ! empty( $pmpromrss_user_id ) ) {
			wp_set_current_user( absint( $pmpromrss_user_id ) );
		} else {
			$credentials = pmpromrss_get_auth_credentials();
			$username     = $credentials['username'];
			$password     = $credentials['password'];

			// No credentials yet — template_redirect will issue the 401 challenge.
			if ( empty( $username ) || empty( $password ) ) {
				return;
			}

			$user = wp_authenticate_application_password( null, $username, $password );

			if ( ! ( $user instanceof WP_User ) && get_option( 'pmpro_pmpromrss_memberkey_as_password' ) === 'Enabled' ) {
				$user = pmpromrss_authenticate_memberkey_password( $username, $password );
			}

			// Auth failed — template_redirect will issue the 403 response.
			if ( ! ( $user instanceof WP_User ) ) {
				return;
			}

			$pmpromrss_user_id = $user->ID;
			wp_set_current_user( absint( $pmpromrss_user_id ) );
		}
	} else {
		return;
	}

	// Remove PMPro's search filter for this feed query and add ours.
	if ( has_filter( 'pre_get_posts', 'pmpro_search_filter' ) ) {
		remove_filter( 'pre_get_posts', 'pmpro_search_filter' );
		add_filter( 'pre_get_posts', 'pmpromrss_search_filter' );
	}
}
add_action( 'pre_get_posts', 'pmpromrss_pre_get_posts', 0 );

/**
 * Check for Basic Auth on Feed Requests Without Member Key
 * 
 * @since 0.4
 */
function pmpromrss_basic_auth_challenge() {
	global $pmpromrss_user_id;

	// Only proceed if this is a feed request
	if ( ! is_feed() ) {
		return;
	}

	// If already authenticated (memberkey or early basic auth in pre_get_posts), nothing to do.
	if ( ! empty( $pmpromrss_user_id ) ) {
		return;
	}

	// Check for our parameter to prompt Basic Auth login.
	if ( empty( $_GET['pmpromrss_basic_auth'] ) ) {
		return;
	}

	// The option is not enabled, bail.
	if ( get_option( 'pmpro_pmpromrss_basic_auth' ) !== 'Enabled' ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
		esc_html_e( 'Authentication method not allowed.', 'pmpro-member-rss' );
		exit;
	}

	// PMPro may not be installed, let's not try to log in or anything.
	if ( ! defined( 'PMPRO_VERSION' ) ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
		esc_html_e( 'Paid Memberships Pro not activated. Please ensure you have it installed and activated.', 'pmpro-member-rss' );
		exit;
	}

	// No spammers allowed.
	if ( pmpromrss_spam_protection_enabled() && pmpro_is_spammer() ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
		esc_html_e( 'Slow down. Access denied. Please try again later.', 'pmpro-member-rss' );
		exit;
	}

	$credentials = pmpromrss_get_auth_credentials();
	$username = $credentials['username'];
	$password = $credentials['password'];

	// This is mainly for RSS app readers, people testing this in their browser will get flagged on first page load - which is okay.
	if ( empty( $username ) || empty( $password ) ) {
		if ( pmpromrss_spam_protection_enabled() ) {
			pmpro_track_spam_activity();
		}
		status_header( 401 );
		header( 'WWW-Authenticate: Basic realm="Private Feed - Member Login Required"' );
		esc_html_e( 'Authentication required. Please provide your WordPress username and application password.', 'pmpro-member-rss' );
		exit;
	}

	// Only use application passwords for authentication, not regular passwords.
	// Note: wp_authenticate_application_password() returns null (not WP_Error) when it bails early
	// (e.g. application passwords unavailable), so we treat anything non-WP_User as a failure.
	$user = wp_authenticate_application_password( null, $username, $password );

	// If application password auth failed or returned null, try memberkey-as-password fallback.
	if ( ! ( $user instanceof WP_User ) && get_option( 'pmpro_pmpromrss_memberkey_as_password' ) === 'Enabled' ) {
		$user = pmpromrss_authenticate_memberkey_password( $username, $password );
	}

	// There was an error authenticating the user (WP_Error or still null).
	if ( ! ( $user instanceof WP_User ) ) {
		if ( pmpromrss_spam_protection_enabled() ) {
			pmpro_track_spam_activity();
		}
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
		esc_html_e( 'Access denied. Please check your authentication credentials or reach out to the site administrator for assistance.', 'pmpro-member-rss' );
		exit;
	}

	// Authentication successful. User and filter swap were already handled in pmpromrss_pre_get_posts.
	$pmpromrss_user_id = $user->ID;
}
add_action( 'template_redirect', 'pmpromrss_basic_auth_challenge' );

/**
 * Enable Application Password authentication for Feed Requests.
 * 
 * @since 0.4
 * 
 * @param boolean $is_api_request Is this an API request, we can spoof this to enable it for our requests.
 * @return $boolean True if we're trying to authenticate a feed request, otherwise return the original value.
 */
function pmpromrss_allow_application_passwords( $is_api_request ) {
	// Treat our feed request as an "API request" so application passwords are allowed.
	if ( ! empty( $_GET['pmpromrss_basic_auth'] ) ) {
		return true;
	}
	return $is_api_request;
}
add_filter( 'application_password_is_api_request', 'pmpromrss_allow_application_passwords', 10, 1 );


/**
 * Get the authorization headers from the request for various servers.
 * 
 * @since 0.4
 * @return array Array containing 'username' and 'password' keys.  
 */
function pmpromrss_get_auth_credentials() {
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
 * Authenticate a user by matching the Basic Auth password against their memberkey.
 * The username is used to look up the user, then the password is compared timing-safely.
 *
 * @since 1.0
 *
 * @param string $username The username provided via Basic Auth.
 * @param string $password The password provided via Basic Auth.
 * @return WP_User|WP_Error User object on success, WP_Error on failure.
 */
function pmpromrss_authenticate_memberkey_password( $username, $password ) {
	$user = get_user_by( 'login', $username );
	if ( ! $user ) {
		return new WP_Error( 'pmpromrss_invalid_key', __( 'Invalid username or member key.', 'pmpro-member-rss' ) );
	}

	$stored_key = get_user_meta( $user->ID, 'pmpromrss_key', true );
	if ( empty( $stored_key ) || ! hash_equals( $stored_key, $password ) ) {
		return new WP_Error( 'pmpromrss_invalid_key', __( 'Invalid username or member key.', 'pmpro-member-rss' ) );
	}

	return $user;
}

/**
 * Override the current user when running search queries.
 * @since 0.3
 */
function pmpromrss_search_filter( $query ) {
	global $current_user, $pmpromrss_user_id;

	$current_user_backup = $current_user;
	$current_user = wp_set_current_user( $pmpromrss_user_id );

	$query = pmpro_search_filter( $query );

	$current_user = $current_user_backup;

	return $query;
}

/**
 * Filter the has_membership_access_filter when processing RSS feeds.
 *
 * @since 0.1
 *  
 * @param bool $hasaccess Whether the user has access  
 * @param WP_Post|null $mypost The post object being checked  
 * @param WP_User|null $myuser The user being checked  
 * @param array $post_membership_levels Required membership levels  
 * @return bool Whether the user has access to the content  
 */
function pmpromrss_pmpro_has_membership_access_filter( $hasaccess, $mypost, $myuser, $post_membership_levels ) {
	global $pmpromrss_user_id, $wp_query;		
		
	if ( empty( $pmpromrss_user_id ) ) {
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
 * @param string $enclosure  The default enclosure for the RSS item.
 * @return string $enclosure The modified enclosure, or empty string if the user does not have access to the content.
 */
function pmpromrss_rss_enclosure( $enclosure ) {
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
add_filter( 'rss_enclosure', 'pmpromrss_rss_enclosure', 20 );

/**
 * Improve the RSS text message when viewing the feed.
 * 
 * @since 0.1
 * 
 * @param string $text The original RSS text to filter.  
 * @return string $text The modified RSS text.
 */
function pmpromrss_pmpro_rss_text_filter( $text ) {
	global $post;
	
	$text = sprintf( 
		/* Translators: %s is the permalink to the member content. */
		esc_html__( 'Please visit %s to access this member content.', 'pmpro-member-rss' ), esc_url( get_permalink( $post->ID ) ) 
		);
	
	return $text;
}
add_filter( 'pmpro_rss_text_filter', 'pmpromrss_pmpro_rss_text_filter' );

/**
 * Clear cached user ID when the member key is updated
 * 
 * @since 0.4
 */
function pmpromrss_clear_key_cache( $meta_id, $user_id, $meta_key, $meta_value ) {
	if ( 'pmpromrss_key' === $meta_key ) {
		$cache_key = 'pmpromrss_key_' . $meta_value;
		wp_cache_delete( $cache_key, 'pmpromrss_user_id' );
	}
}
add_action( 'added_user_meta', 'pmpromrss_clear_key_cache', 10, 4 );
add_action( 'updated_user_meta', 'pmpromrss_clear_key_cache', 10, 4 );
add_action( 'deleted_user_meta', 'pmpromrss_clear_key_cache', 10, 4 );

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
function pmpromrss_after_level_change_generate_key( $level_id, $user_id, $cancel ) {
	pmpromrss_getMemberKey( $user_id );
}
add_action( 'pmpro_after_change_membership_level', 'pmpromrss_after_level_change_generate_key', 10, 3 );