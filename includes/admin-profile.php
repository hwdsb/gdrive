<?php
/**
 * Admin profile integration with GDrive.
 *
 * @package GDrive
 */

/**
 * Shows the GDrive markup on the "Users > My Profile" admin page.
 */
function mexp_gdrive_admin_user_profile_content() {
?>

	<table class="form-table">
	<tbody>
		<tr>
			<th><?php _e( 'Google Drive', 'gdrive' ); ?></th>
			<td>
				<?php mexp_gdrive_user_settings_content(); ?>
			</td>
		</tr>
	
	</tbody>
	</table>

<?php
}
add_action( 'show_user_profile', 'mexp_gdrive_admin_user_profile_content', 0 );

/**
 * Loads the GDrive JS on the "Users > My Profile" admin page.
 */
function mexp_gdrive_admin_user_profile_load_js( $hook = '' ) {
	if ( 'profile.php' !== $hook ) {
		return;
	}

	Media_Explorer::init()->services['gdrive']->enqueue_statics();
}
add_action( 'admin_enqueue_scripts', 'mexp_gdrive_admin_user_profile_load_js' );