var el = wp.element.createElement,
	Component = wp.element.Component,
	addButton;

addButton = wp.compose.createHigherOrderComponent( function( BlockEdit ) {
	return function( props ) {
		var attr = props.attributes,
			onSelect,
			sidebarControls;

		if ( 'ray/google-drive' !== props.name || attr.link ) {
			return el(
				BlockEdit,
				props
			);
		}

		onSelect = function( media ) {
			var width = media.width * 1,
				height = media.height * 1;

			// Set some defaults if dimensions aren't yet available.
			if ( ! width ) {
				media.width = 640;
			}
			if ( ! height ) {
				media.height = 360;
			}

			return props.setAttributes( media );
		};

        	return el(
			wp.element.Fragment,
			{},
			el(
				BlockEdit,
				props
			),
			el( GDrive, {
				onSelect: onSelect,
				render: function( obj ) {
					return el( 'div', {
							className: 'component-panel mexp-gdrive-gutenberg',
						},
						el(
							wp.components.Button, {
								className: 'button button-large is-primary',
								onClick: obj.open
							},
							wp.i18n.__( 'Or Select From Drive' )
						)
					);
				}
			} )
		);
	};
}, 'withInspectorControls' );

wp.hooks.addFilter( 'editor.BlockEdit', 'mexp/google-drive', addButton );

// Our media modal launcher, adapted from the MediaUpload component.
// @link https://github.com/WordPress/gutenberg/blob/master/edit-post/hooks/components/media-upload/index.js
class GDrive extends Component {
	constructor() {
		super( ...arguments );
		this.openModal = this.openModal.bind( this );
		this.onOpen = this.onOpen.bind( this );
		this.onClose = this.onClose.bind( this );
		this.onInsert = this.onInsert.bind( this );

		this.frame = wp.media(  {
			frame: 'post',

			// Default tab to select when modal is loaded.
			state: 'mexp-service-gdrive',

			// Disables plupload dropzone; we'll be handling uploading ourselves.
			uploader: false
		} );

		// When an image is selected in the media frame...
		this.frame.on( 'open', this.onOpen );
		this.frame.on( 'close', this.onClose );
		this.frame.on( 'gDrive:insert', this.onInsert );
	}

	componentWillUnmount() {
		this.frame.remove();
	}

	onInsert( shortcode ) {
		const { onSelect } = this.props;
		onSelect( shortcode );
	}

	onOpen() {
		jQuery( '.media-frame' ).addClass( 'hide-menu' );
		jQuery( '.media-modal' ).addClass( 'smaller' );
	}

	onClose() {
		jQuery( '.media-modal' ).removeClass( 'smaller' );
		const { onClose } = this.props;

		if ( onClose ) {
			onClose();
		}
	}

	openModal() {
		this.frame.open();
	}

	render() {
		return this.props.render( { open: this.openModal } );
	}
}
