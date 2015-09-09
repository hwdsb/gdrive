<?php
defined( 'ABSPATH' ) or die();

/**
 * Google Drive template class for MEXP.
 */
class MEXP_GDrive_Template extends MEXP_Template {

	/**
	 * Loop item template.
	 *
	 * @param string $id  The file ID.
	 * @param string $tab The MEXP tab.
	 */
	public function item( $id, $tab ) {
		?>
		<div id="mexp-item-gdrive-<?php echo esc_attr( $tab ); ?>-{{ data.id }}" class="mexp-item-area mexp-item-gdrive" data-id="{{ data.id }}" data-gdrive-type="{{ data.meta.file.type }}">
			<div class="mexp-item-container clearfix">
				<div class="mexp-item-thumb">
					<img width="150" height="112" src="{{ data.thumbnail }}">
				</div>
				<div class="mexp-item-main">
					<div class="mexp-item-content">
						<img src="{{ data.meta.file.icon }}">
						<strong>{{ data.content }}</strong>
					</div>
				</div>
			</div>
		</div>
		<a href="#" id="mexp-check-{{ data.id }}" data-id="{{ data.id }}" class="check" title="<?php esc_attr_e( 'Deselect', 'mexp' ); ?>">
			<div class="media-modal-icon"></div>
		</a>
		<?php
	}

	/**
	 * Search template.
	 *
	 * Kind of abused here to handle other stuff :)
	 *
	 * @param string $id  The file ID.
	 * @param string $tab The MEXP tab.
	 */
	public function search( $id, $tab ) {

		switch ( $tab ) {

			case 'gauth' :
				?>
				<form action="#" class="mexp-toolbar-container clearfix">
					<div id="gauth">
						<?php if ( is_main_site() || mexp_gdrive_is_subdomain_auth_supported() ) : ?>
							<button id="signinButton"><img src="https://developers.google.com/identity/images/btn_google_signin_dark_normal_web.png" alt="<?php _e( 'Sign in with Google', 'gdrive' ); ?>" /></button>
							<p><?php _e( "We'll open a new page to help you connect to your Google Drive account.", 'gdrive' ); ?></p>

						<?php elseif ( mexp_gdrive_is_user_profile_page_enabled() ) : ?>

							<a href="<?php echo esc_url( mexp_gdrive_get_user_profile_url() ); ?>" class="button-primary button-larger" target="_blank"><?php _e( 'Set up Google Drive', 'gdrive' ); ?></a>

							<p><?php _e( 'Before you can embed items from your Google Drive, you need to authenticate with Google.', 'gdrive' ); ?></p>
							<p><?php _e( 'Click on the button above to connect your Google Drive account to this site and then reload this page.', 'gdrive' ); ?></p>

						<?php endif; ?>

					</div>
					<span class="description" style="display:none;"><?php _e( 'Listing contents of your Google Drive.', 'gdrive' ) ?></span>
				</form>

				<?php
			break;

			case 'gmine':
				?>
				<form action="#" class="mexp-toolbar-container clearfix">
					<span class="description"><?php _e( 'Listing contents of your Google Drive.', 'gdrive' ) ?></span>
					<input type="hidden" name="type" value="mine">
					<div class="spinner"></div>
				</form>
				<?php
			break;

			case 'gsearch':
				?>
				<form action="#" class="mexp-toolbar-container clearfix">
					<input
						type="text"
						name="q"
						value="{{ data.params.q }}"
						class="mexp-input-text mexp-input-search"
						size="40"
						placeholder="<?php esc_attr_e( 'Search', 'gdrive' ); ?>"
					>
					<input class="button button-large" type="submit" value="<?php esc_attr_e( 'Search', 'gdrive') ?>">
					<div class="spinner"></div>
				</form>
				<?php
			break;
		}
	}

	/**
	 * Unused, but necessary to avoid fatal error.
	 */
	public function thumbnail( $id ) {}

	/**
	 * Extra method for extra templates!
	 */
	public static function extra() {
		// presentation sizes
		$sizes = array();
		$sizes['small'] = array(
			'name'   => __( 'Small', 'gdrive' ),
			'width'  => 480,
			'height' => 299
		);
		$sizes['medium'] = array(
			'name'   => __( 'Medium', 'gdrive' ),
			'width'  => 960,
			'height' => 559
		);
		$sizes['large'] = array(
			'name'   => __( 'Large', 'gdrive' ),
			'width'  => 1440,
			'height' => 839
		);
	?>

	<script type="text/html" id="tmpl-gdrive-attachment-details">
		<h3>
			<?php _e( 'Document Details', 'gdrive' ); ?>

			<span class="settings-save-status">
				<span class="spinner"></span>
				<span class="saved"><?php esc_html_e( 'Saved.', 'gdrive' ); ?></span>
			</span>
		</h3>

		<div class="attachment-info">
			<div class="thumbnail thumbnail-{{ data.meta.file.type }}">
				<# if ( data.uploading ) { #>
					<div class="media-progress-bar"><div></div></div>
				<# } else if ( 'image' === data.type && data.sizes ) { #>
					<img src="{{ data.size.url }}" draggable="false" />
				<# } else { #>
					<img src="{{ data.meta.file.icon }}" class="icon" draggable="false" />
				<# } #>
			</div>
			<div class="details">
				<div class="filename">{{ data.content }}</div>
				<div class="uploaded"><strong><?php _e( 'Modified:', 'gdrive' ); ?></strong> {{ data.date }}</div>
				<div class="uploaded"><strong><?php _e( 'Created:', 'gdrive' ); ?></strong> {{ data.meta.file.dateCreated }}</div>
			</div>
		</div>

		<h3><?php _e( 'Embed Status', 'gdrive' ); ?></h3>
		<div class="attachment-info embed-status">
			<img width="20" height="20" alt="<?php esc_attr_e( 'Loading...', 'gdrive' ); ?>" src="<?php esc_attr_e( includes_url( 'images/spinner.gif' ) ); ?>" />
		</div>
	</script>

	<script type="text/html" id="tmpl-gdrive-attachment-display-settings">
		<h3><?php _e('Document Display Settings'); ?></h3>

		<# if ( 'presentation' === data.type  ) { #>

			<label class="setting">
				<span><?php _e( 'Preset Size', 'gdrive' ); ?></span>
				<select class="size" name="size" data-setting="size">
					<?php foreach ( $sizes as $preset => $s ) : ?>
						<option value="<?php echo esc_attr( $preset ); ?>" <?php selected( $preset, 'small' ); ?>>
							<?php echo esc_html( $s['name'] ); ?> &ndash; <?php echo $s['width']; ?> &times; <?php echo $s['height']; ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

		<# } else { #>

			<label class="setting width">
				<span><?php _e( 'Width', 'gdrive' ); ?></span>
				<input type="text" class="alignment" data-setting="width" />
			</label>

			<label class="setting height">
				<span><?php _e( 'Height', 'gdrive' ); ?></span>
				<input type="text" class="alignment" data-setting="height" />
			</label>

		<# } #>

		<# if ( 'document' === data.type  ) { #>

			<label class="setting">
				<span><?php _e( 'Show Docs Header/Footer', 'gdrive' ); ?></span>
				<input type="checkbox" data-setting="seamless" />
			</label>

		<# } #>

	</script>

	<?php
	}
}
