<?php
/**
 * Common functions used by GDrive.
 *
 * @package GDrive
 */

/**
 * Get refresh token for Google Drive.
 *
 * Our implementation requires that every user is authenticated to their own
 * Google Drive.
 *
 * If you're using this in a shared organizational context, use:
 *  - the 'mexp_gdrive_get_refresh_token' filter to override fetching token
 *  - the 'mexp_gdrive_update_refresh_token' action to update token
 *  - the 'mexp_gdrive_delete_refresh_token' action to delete token
 *  - the 'mexp_gdrive_enable_user_profile_page' filter to return false
 */
function mexp_gdrive_get_refresh_token() {
	return apply_filters( 'mexp_gdrive_get_refresh_token', get_user_meta( get_current_user_id(), 'gdu_refresh_token', true ) );
}

/**
 * Can we authenticate to Google from any multisite subdomain?
 *
 * In Google's API, if you attempt to authorize from a subdomain, you must
 * list the subdomain manually in Google under "APIs and Auth > Credentials".
 * Otherwise, the authentication will fail:
 * {@link }
 *
 * On a multisite install with tons of sites, this is simply madness!  By
 * default, this function will return false.  This means that if a user is
 * attempting to authenticate to Google Drive from a sub-site, the user will
 * be redirected to the main site to authenticate to Google Drive.
 *
 * If you want users to authenticate to the subdomain directly, you must list
 * the subdomain in Google and return true for this function using the filter
 * below.
 *
 * @return bool
 */
function mexp_gdrive_is_subdomain_auth_supported() {
	return apply_filters( 'mexp_gdrive_is_subdomain_auth_supported', false );
}

/**
 * Enable users to authenticate to Google Drive from their user profile page.
 *
 * This also allows users to disconnect access to Google Drive.
 */
function mexp_gdrive_is_user_profile_page_enabled() {
	return apply_filters( 'mexp_gdrive_enable_user_profile_page', true );
}

/**
 * Get user profile URL to add our Google Drive options.
 *
 * If BuddyPress is enabled and the BP Settings component is enabled, we'll
 * use the user settings URL.  Otherwise, we'll use the admin dashboard
 * profile link from the main site.
 *
 * @return string
 */
function mexp_gdrive_get_user_profile_url() {
	if ( function_exists( 'buddypress' ) && bp_is_active( 'settings' ) ) {
		$url = trailingslashit( bp_loggedin_user_domain() . bp_get_settings_slug() );

	} else {
		$url = get_admin_url( $GLOBALS['current_site']->blog_id, 'profile.php' );
	}

	return apply_filters( 'mexp_gdrive_get_user_profile_url', $url . '#gauth' );
}

/**
 * User settings content.
 *
 * Displayed on:
 *  1. A user's BuddyPress settings page - "Settings > General"
 *  2. A user's admin dashboard page - "Users > My Profile"
 */
function mexp_gdrive_user_settings_content() {
?>

	<?php if ( '' === mexp_gdrive_get_refresh_token() ) : ?>

		<div id="gauth">
			<button id="signinButton"><img src="https://developers.google.com/identity/images/btn_google_signin_dark_normal_web.png" alt="<?php _e( 'Sign in with Google', 'gdrive' ); ?>" /></button>
			<p class="description"><?php _e( "We'll open a new page to help you connect to your Google Drive account.", 'gdrive' ); ?></p>

			<p class="description"><?php printf( __( 'Authenticating will allow you to easily embed items from your Google Drive via the familiar "%sAdd Media%s" button when writing new posts.', 'gdrive' ), '<strong>', '</strong>' ); ?></p>
		</div>

		<script type="text/javascript">
		jQuery('#signinButton').click( function() {
			e.preventDefault();

			auth2.grantOfflineAccess( {'redirect_uri': 'postmessage'} ).then( function( authResult ) {
				if ( authResult['code'] ) {
					// save the refresh token
					jQuery.post( ajaxurl, {
						action: 'mexp-gdrive-oauth',
						type: 'not-media',
						code: authResult['code']
					}, function( response ) {
						jQuery( '#gauth' ).html( response.data.message );
					} );
				}
			} );
		} );
		</script>

	<?php else : ?>

		<div id="gauth">
			<p><?php _e( 'You have allowed us to access your Google Drive.', 'gdrive' ); ?></p>

			<p><?php printf( __( 'To easily embed items from your Google Drive, click on the %s button when you are creating a new post.  Next, click on the %s link and proceed from there.', 'gdrive' ), '<strong>' . __( 'Add Media', 'gdrive' ) . '</strong>', '<strong>' . __( 'Insert from Google Drive', 'gdrive' ) . '</strong>' ); ?></p>

			<p><?php _e( 'To disallow access to your Google Drive, click on the button below:', 'gdrive' ); ?></p>

			<button type="button" class="button button-secondary" id="gauth-revoke" data-nonce="<?php echo wp_create_nonce( 'mexp-gdrive-revoke' ); ?>"><?php _e( 'Disallow access', 'gdrive' ); ?></button>
		</div>

		<script type="text/javascript">
		jQuery('#gauth-revoke').click( function( e ) {
			e.preventDefault();

			jQuery.post( ajaxurl, {
				action: 'mexp-gdrive-revoke',
				'_ajax_nonce' : e.currentTarget.dataset.nonce
			}, function( response ) {
				jQuery( '#gauth' ).html( response.data.message );
			} );
		} );
		</script>

	<?php endif; ?>

<?php
}
