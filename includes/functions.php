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
 */
function mexp_gdrive_get_refresh_token() {
	return apply_filters( 'mexp_gdrive_get_refresh_token', get_user_meta( get_current_user_id(), 'gdu_refresh_token', true ) );
}
