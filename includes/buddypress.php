<?php
/**
 * BuddyPress integration with GDrive.
 *
 * @package GDrive
 */

/**
 * Loads our JS and markup on a user's BP settings page.
 */
function mexp_gdrive_bp_settings_loader() {
	if ( false === bp_is_user_settings_general() ) {
		return;
	}

	// load our needed JS
	add_action( 'wp_enqueue_scripts',  array( Media_Explorer::init()->services['gdrive'], 'enqueue_statics' ) );

	// add markup on "Settings > General" page
	add_action( 'bp_core_general_settings_after_submit', 'mexp_gdrive_bp_user_settings_content', 0 );
}
add_action( 'bp_actions', 'mexp_gdrive_bp_settings_loader' );

/**
 * BuddyPress user settings content.
 */
function mexp_gdrive_bp_user_settings_content() {
?>

	<div id="mexp-gdrive">
		<h3><?php _e( 'Google Drive', 'gdrive' ); ?></h3>

		<?php mexp_gdrive_user_settings_content(); ?>
	</div>

<?php
}