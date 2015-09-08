<?php
defined( 'ABSPATH' ) or die();

/**
 * Google Drive service class for MEXP.
 */
class MEXP_GDrive_Service extends MEXP_Service {

	/**
	 * Google API client ID.
	 *
	 * @var string
	 */
	protected $client_id = '';

	/**
	 * Google API client secret.
	 *
	 * @var string
	 */
	protected $client_secret = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Includes
		$this->includes();

		// Properties
		$this->properties();

		// Hooks
		$this->hooks();
	}

	/**
	 * Includes.
	 */
	protected function includes() {
		require_once dirname( __FILE__ ) . '/functions.php';
		require_once dirname( __FILE__ ) . '/mexp-template.php';
	}

	/**
	 * Set up properties.
	 */
	protected function properties() {
		// Google API-related
		$this->client_id     = constant( 'MEXP_GDRIVE_CLIENT_ID' );
		$this->client_secret = constant( 'MEXP_GDRIVE_CLIENT_SECRET' );

		// Template
		$this->set_template( new MEXP_GDrive_Template );
	}

	/**
	 * Set up hooks.
	 */
	protected function hooks() {
		// oAuth AJAX hook
		add_action( 'wp_ajax_mexp-gdrive-oauth', array( $this, 'oauth_ajax_listener' ) );

		// doc embed status AJAX hooks
		add_action( 'wp_ajax_mexp-gdrive-doc-embed-status', array( $this, 'doc_embed_status_ajax_listener' ) );
		add_action( 'wp_ajax_mexp-gdrive-doc-allow-embed',  array( $this, 'doc_allow_embed_ajax_listener' ) );
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_statics() {

		wp_enqueue_script(
			'mexp-service-gdrive',
			MEXP_GDrive::$URL . '/assets/js.js',
			array( 'jquery', 'mexp' )
		);

		wp_enqueue_style(
			'mexp-service-gdrive-css',
			MEXP_GDrive::$URL . '/assets/style.css'
		);

		// only load Google Client JS API if we do not have an existing refresh token
		if ( '' === mexp_gdrive_get_refresh_token() ) {
			wp_enqueue_script(
				'mexp-service-gdrive-gapi',
				'https://apis.google.com/js/client:platform.js?onload=gdriveStart',
				array( 'jquery' ),
				null
			);

			add_filter( 'script_loader_tag', array( $this, 'gapi_js_async_defer' ), 10, 2 );

			add_action( 'admin_print_scripts', array( $this, 'gapi_inline_js_head' ) );
			add_action( 'wp_print_scripts',    array( $this, 'gapi_inline_js_head' ) );
		}
	}

	/**
	 * Google Client API JS requires 'async' and 'defer' elements.
	 *
	 * @param  string $retval The full <script> tag.
	 * @param  string $handle The current WP registered script handle.
	 * @return string
	 */
	public function gapi_js_async_defer( $retval, $handle ) {
		if ( 'mexp-service-gdrive-gapi' !== $handle ) {
			return $retval;
		}

		return str_replace( ' src', ' async defer src', $retval );
	}

	/** MEXP SERVICE API ***************************************************/

	/**
	 * Do stuff when our service is loaded.
	 *
	 * Extended class method API.
	 */
	public function load() {

		add_action( 'mexp_enqueue', array( $this, 'enqueue_statics' ) );

		add_action( 'print_media_templates', array( 'MEXP_GDrive_Template', 'extra' ) );

		add_filter( 'mexp_tabs', array( $this, 'tabs' ), 10, 1 );

		add_filter( 'mexp_labels', array( $this, 'labels' ), 10, 1 );
	}

	/**
	 * MEXP AJAX request hook.
	 *
	 * Extended class method API.
	 *
	 * @param array $request Request parameters.
	 */
	public function request( array $request ) {
		$load_gapi = $this->load_gapi( true );
		if ( is_wp_error( $load_gapi ) ) {
			return $load_gapi;
		}

		$params = $request['params'];
		$tab 	= $request['tab'];

		$query_params = array();

		if ( isset( $params['q'] ) ) {
			$q = $query_params['q'] = sanitize_title_with_dashes( $params['q'] );
		}

		switch ( $tab ) {
			case 'popular':
			default:
				$endpoint = 'media/popular';
		}

		if ( ! empty( $request['max_id'] ) ) {
			$query_params['max_id'] = $request['max_id'];
		}

		ray_log( 'query params: ' . print_r( $query_params, true ) );

		return $this->response( $query_params );
	}

	/**
	 * Calls the Google PHP API during AJAX request time.
	 *
	 * @param array $r Arguments.
	 */
	public function response( $r ) {
		// create Google Drive service
		$service = new Google_Service_Drive( $this->client );

		// Show all Google Drive files
		$files = $this->list_files( $service, $r );

		$response = new MEXP_Response;

		if ( isset( $r->search_metadata->next_results ) ) {
			$response->add_meta( 'max_id', self::get_max_id( $r->search_metadata->next_results ) );
		}

		// Pagination details
		if ( isset( $files['nextPageToken'] ) ) {
			$response->add_meta( 'page_token', $files['nextPageToken'] );
			unset( $files['nextPageToken'] );
		}

		if ( empty( $files ) ) {
			return;
		}
//ray_log( 'files: ' . print_r( $files, true ) );
		foreach( $files as $file ) {
			$item = new MEXP_Response_Item;

			$item->set_id( $file->getId() );
			$item->set_url( $file->getSelfLink() );

			$item->set_content( $file->getTitle() );
			$item->set_date( strtotime( $file->getModifiedDate() ) );
			$item->set_date_format( 'g:i A - j M y' );

			// use the same dimensions as drive.google.com for thumbs
			$thumb = str_replace( 's220', 'w200-h150-p-k-nu', $file->getThumbnailLink() );
			$item->set_thumbnail( $thumb );

			//$owners = $file->getOwnerNames();

			// set up file meta
			$file_meta = array(
				'icon' => $file->iconLink,
				'type' => substr( strrchr( $file->getMimeType(), '.' ), 1 ),
				'dateCreated' => date( $item->date_format, strtotime( $file->getCreatedDate() ) )
			);

			// add marker to tell that this is a gdoc
			$item->add_meta( 'gdoc', 1 );

			// this is the old way of passing data... aka. meta-stuffing
			$item->add_meta( 'file', $file_meta );

			$response->add_item( $item );

		}

		return $response;

	}

	/**
	 * Set up tabs for use in modal.
	 *
	 * Extended class method API.
	 */
	public function tabs( array $tabs ) {
		$tabs['gdrive'] = array();

		$refresh_token = mexp_gdrive_get_refresh_token();
		if ( ! empty( $refresh_token ) ) {
			$tabs['gdrive']['gmine'] = array(
				'text'       => _x( 'All', 'Tab title', 'gdrive' ),
				'defaultTab' => true,
				'fetchOnRender' => true,
			);
			$tabs['gdrive']['gsearch'] = array(
				'text'       => _x( 'Search', 'Tab title', 'gdrive' ),
			);

		} else {
			$tabs['gdrive']['gauth'] = array(
				'text'       => _x( 'Authentication Required', 'Tab title', 'gdrive'),
				'defaultTab' => true
			);
		}

		return $tabs;
	}
	/**
	 * Set up labels for use in modal.
	 *
	 * Extended class method API.
	 */
	public function labels( array $labels ) {
		$labels['gdrive'] = array(
			'title'     => __( 'Insert from Google Drive', 'gdrive' ),
			// @TODO the 'insert' button text gets reset when selecting items. find out why.
			'insert'    => __( 'Insert from Google Drive', 'gdrive' ),
			'noresults' => __( 'No documents matched your query', 'gdrive' ),
			'loadmore'  => __( 'Load more', 'gdrive' ),
			'embeddable' => '<span class="dashicons dashicons-yes"></span>' . __( 'Allowed', 'gdrive' ),
			'notembeddable' => '<span class="dashicons dashicons-warning"></span>' . __( 'Not allowed.  <a href="javascript:;">Click to allow access</a>.', 'gdrive' )
		);

		return $labels;
	}

	/** GOOGLE API *********************************************************/

	/**
	 * AJAX listener to save oAuth data.
	 *
	 * @see onclickoAuth() in /assets/js.js
	 */
	public function oauth_ajax_listener() {
		$load_gapi = $this->load_gapi();
		if ( is_wp_error( $load_gapi ) ) {
			wp_send_json_error( array(
				'type' => 'auth-error',
				'message' => $load_gapi->get_error_message(),
			) );
		}

		// this is tres important!
		$this->client->setRedirectUri( 'postmessage' );

		// authenticate
		$this->client->authenticate( $_POST['code'] );

		// save refresh token so we don't have to prompt user next time
		$refresh_token = $this->client->getRefreshToken();
		if ( ! empty( $refresh_token ) ) {
			update_user_meta( get_current_user_id(), 'gdu_refresh_token', $refresh_token );

			// for devs to override where refresh token is saved
			do_action( 'mexp_gdrive_update_refresh_token', $refresh_token );

			wp_send_json_success();
		}

		wp_send_json_error( array(
			'type' => 'auth-error',
			'message' => __( 'Auth error', 'gdrive' ),
		) );
	}

	/**
	 * AJAX listener to check the embed status of a Google Doc.
	 */
	public function doc_embed_status_ajax_listener() {
		$load_gapi = $this->load_gapi( true );
		if ( is_wp_error( $load_gapi ) ) {
			wp_send_json_error( array(
				'type' => 'auth-error',
				'message' => $load_gapi->get_error_message(),
			) );
		}

		// create Google Drive service
		$service = new Google_Service_Drive( $this->client );

		$data = array();

		// see if doc is published
		try {
			$revision = $service->revisions->get( $_POST['id'], 'head' );

			// doc is published publicly
			if ( $revision->getPublished() ) {
				$data['published'] = 1;
			} else {
				$data['published'] = 0;

				// check if doc is shared with anyone with link
				$perms = $service->permissions->listPermissions( $_POST['id'] );
				foreach ( (array) $perms->getItems() as $p ) {
					if ( 'anyoneWithLink' === $p->id ) {
						$data['share'] = 1;
						continue;
					}
				}
			}

			wp_send_json_success( $data );

		// this is a folder or some other doc that doesn't support revisions
		} catch ( Google_Service_Exception $e ) {
			//ray_log( 'revision error: ' . print_r( $e->getErrors(), true ) );
			wp_send_json_error( array(
				'type' => 'not-embeddable',
				'message' => 'Not an embeddable document',
			) );
		}
	}

	/**
	 * AJAX listener to share a Google Doc with anyone with the link.
	 *
	 * This will allow for Google Doc embedding.
	 */
	public function doc_allow_embed_ajax_listener() {
		$load_gapi = $this->load_gapi( true );
		if ( is_wp_error( $load_gapi ) ) {
			wp_send_json_error( array(
				'type' => 'auth-error',
				'message' => $load_gapi->get_error_message(),
			) );
		}

		// create Google Drive service
		$service = new Google_Service_Drive( $this->client );

		try {
			$this->share_doc_with_anyone( $service, $_POST['id'] );

			wp_send_json_success();

		} catch ( Exception $e ) {
			wp_send_json_error( array(
				'type' => 'share-failed',
				'message' => $e->getErrors(),
			) );
		}
	}

	/**
	 * Inline JS required for Google Client API oAuth.
	 */
	public function gapi_inline_js_head() {
	?>

		<script type="text/javascript">
		function gdriveStart() {
			gapi.load( 'auth2', function() {
				auth2 = gapi.auth2.init({
					client_id: '<?php esc_attr_e( $this->client_id ); ?>',
					fetch_basic_profile: false,
					scope: 'https://www.googleapis.com/auth/drive'
				});
			});
		}
		</script>

	<?php
	}

	/**
	 * Loads the Google PHP API.
	 *
	 * @param  bool $auth Should we attempt to authenticate as well?
	 * @return bool|WP_Error Boolean true on success. WP_Error object on failure.
	 */
	protected function load_gapi( $auth = false ) {
		if ( false === class_exists( 'Google_Client' ) ) {
			if ( file_exists( MEXP_GDrive::$PATH . '/vendor/autoload.php' ) ) {
				require_once MEXP_GDrive::$PATH . '/vendor/autoload.php';
			} else {
				return new WP_Error(
					'mexp_gdrive_lib_required',
					__( 'Please install the Google PHP API Client.  You can do this by running "composer install" in your console from the "gdrive" directory', 'gdrive' )
				);
			}
		}

		// set up the client
		$this->client = new Google_Client();
		$this->client->setClientId( $this->client_id );
		$this->client->setClientSecret( $this->client_secret );
		$this->client->addScope( 'https://www.googleapis.com/auth/drive' );

		// attempt to authorize
		if ( true === $auth ) {
			// user has already authorized app; so set access token
			$refresh_token = $this->get_refresh_token();
			if ( ! empty( $refresh_token ) ) {

				// set access token
				try {
					$this->client->refreshToken( $refresh_token );

					$access_token = $this->client->getAccessToken();

					// set access token
					if ( ! empty( $access_token ) ) {
						$this->client->setAccessToken( $access_token );
					}

				// user has revoked access
				} catch ( Google_Auth_Exception $e ) {
					delete_user_meta( get_current_user_id(), 'gdu_refresh_token' );

					do_action( 'mexp_gdrive_delete_refresh_token' );

					return new WP_Error(
						'mexp_gdrive_revoke',
						__( 'You have revoked access to Google Drive.  Please refresh the page to start the authentication process again.', 'gdrive' )
					);
				}
			}

		}

		return true;
	}

	/**
	 * Set Google Doc to share with anyone.
	 *
	 * @link https://developers.google.com/drive/web/manage-sharing
	 * @link https://developers.google.com/drive/v2/reference/permissions
	 * @link http://stackoverflow.com/questions/11155441/set-file-sharing-level-to-anyone-with-the-link-through-google-drive-api
	 *
	 * @param Google_Service_Drive $service Drive API service instance.
	 * @param string               $file_id File ID for Google Drive document.
	 */
	protected function share_doc_with_anyone( Google_Service_Drive $service, $file_id ) {
		$new_perm = new Google_Service_Drive_Permission();
		$new_perm->setRole( 'reader' );
		$new_perm->setType( 'anyone' );

		// unsure about this...
		$new_perm->setWithLink( true );

		// either id or value can be set; not both
		//$new_perm->setId( 'anyoneWithLink' );
		$new_perm->setValue( '' );

		// insert the permission
		$service->permissions->insert( $file_id, $new_perm );
	}

	/**
	 * Retrieve all Google Drive files by the owner.
	 *
	 * @todo Support pagination.
	 *
	 * @link https://developers.google.com/drive/v2/reference/files/list
	 * @link https://developers.google.com/drive/web/search-parameters
	 *
	 * @param  Google_Service_Drive $service Drive API service instance.
	 * @param  array                $args    Arguments to query against Google Drive API.
	 * @return array List of Google_Service_Drive_DriveFile resources.
	 */
	protected function list_files( Google_Service_Drive $service, $args = array() ) {
		$result = array();
		$pageToken = NULL;

		$parameters = array();

		// only allow files created by the user and not in trash
		$parameters['q'] = "'me' in owners and trashed = false";

		// Allow search queries
		if ( ! empty( $args['q'] ) ) {
			$parameters['q'] .= " and title contains '{$args['q']}'";
		}

		// omit folders
		$parameters['q'] .= " and mimeType != 'application/vnd.google-apps.folder'";

		// sort by modified date
		//$parameters['orderBy'] = 'modifiedDate';

		// only grab fields that we explicitly need
		// @todo revisit this for performance
		//$parameters['fields'] = "items(iconLink,id,title,embedLink)";

		//$parameters['corpus'] = 'DEFAULT';

		if ( $pageToken ) {
			$parameters['pageToken'] = $pageToken;
		}

		do {
			try {
				$files = $service->files->listFiles($parameters);

				$result = array_merge( $result, $files->getItems() );
				$pageToken = $files->getNextPageToken();

			} catch ( Exception $e ) {
				echo 'An error occurred: ' . $e->getMessage();
				$pageToken = NULL;
			}

		} while ( $pageToken );

		return $result;
	}
}
