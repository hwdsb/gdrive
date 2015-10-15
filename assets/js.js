/**
 * Disclaimer: My Backbone-fu isn't the greatest!  If you see any large
 * oversights, please let me know!
 */

var mexpView = wp.media.view.MEXP,
	mexpController = media.controller.MEXP,
	mexpAttachmentDisplayDetails,
	mexpAttachmentDisplaySettings;

mexpAttachmentDisplayDetails = wp.media.view.Attachment.Details.extend({
	template:  wp.template( 'gdrive-attachment-details' ),

	initialize: function() {
		wp.media.view.Attachment.Details.prototype.initialize.apply( this, arguments );
	}
});

mexpAttachmentDisplaySettings = wp.media.view.Settings.AttachmentDisplay.extend({
	template:  wp.template( 'gdrive-attachment-display-settings' ),

	initialize: function() {
		wp.media.view.Settings.AttachmentDisplay.prototype.initialize.apply( this, arguments );
	},

	/**
	 * @returns {wp.media.view.Settings.AttachmentDisplay} Returns itself to allow chaining
	 */
	render: function() {
		_.extend( this.options, {
			type: this.options.model.attributes.meta.file.type,
			nothumb: this.options.model.attributes.meta.file.nothumb,
		});

		/**
		 * call 'render' directly on the parent class
		 */
		wp.media.view.Settings.AttachmentDisplay.prototype.render.call( this );
		return this;
	},
});

wp.media.view.MEXP = mexpView.extend({
	events: function(){
		return _.extend({}, mexpView.prototype.events,{
			'click .mexp-toolbar #signinButton'    : 'onclickoAuth',
			'click .media-sidebar .embed-status a' : 'onclickAllowEmbed'
		});
	},

	initialize: function() {
		mexpView.prototype.initialize.apply( this, arguments );

		// add sidebar for our models only
		if ( 'gdrive' === this.service.id ) {
			this.createSidebar();

			// port over dynamic columns from wp.media.view.Attachments view
			this.$window = jQuery( window );
			this.resizeEvent = 'resize.media-modal-columns';
			_.bindAll( this, 'setColumns' );

			this.on( 'ready', this.bindEvents );
			this.controller.on( 'open', this.setColumns );

			// Call this.setColumns() after this view has been rendered in the DOM so
			// attachments get proper width applied.
			_.defer( this.setColumns, this );
		}
	},

	/**
	 * Ported from wp.media.view.Attachments.
	 */
	bindEvents: function() {
		this.$window.off( this.resizeEvent ).on( this.resizeEvent, _.debounce( this.setColumns, 50 ) );
	},

	/**
	 * Ported from wp.media.view.Attachments.
	 */
	setColumns: function() {
		var prev = this.columns,
			width = this.$el.width(),
			// this part here is modded by us
			idealColumnWidth = jQuery( window ).width() < 640 ? 300 : 315;

		if ( width ) {
			this.columns = Math.min( Math.round( width / idealColumnWidth ), 12 ) || 1;

			if ( ! prev || prev !== this.columns ) {
				this.$el.closest( '.media-frame-content' ).attr( 'data-columns', this.columns );
			}
		}
	},

	onclickoAuth: function( event ) {
		// kind of a hack
		var _this = this;

		auth2.grantOfflineAccess( {'redirect_uri': 'postmessage'} ).then( function( authResult ) {
			if ( authResult['code'] ) {
				// Hide the sign-in button now that the user is authorized, for example:
				jQuery('#gauth').attr( 'style', 'display: none' );
				jQuery('.mexp-content-gdrive-gauth .media-toolbar').attr( 'style', 'padding: 0' );
				jQuery('.mexp-content-gdrive-gauth span.description').toggle();

				// save the refresh token
				wp.ajax.post( 'mexp-gdrive-oauth', {
					code: authResult['code']
				} )
				.done( function( response ) {
					// switch the view on success
					_this.model.set( 'params', {} );
					_this.trigger( 'change:params' );
				} )
				.fail( function( response ) {
				} );
			}
		} );
	},

	onclickAllowEmbed: function( event ) {
		var model = this.getSelection().get( event.currentTarget.offsetParent.dataset.id ),
			service = this.service;

		// set the gdoc to share with anyone with link
		wp.ajax.post( 'mexp-gdrive-doc-allow-embed', {
			id: event.currentTarget.offsetParent.dataset.id
		} )
		.done( function( response ) {
			model.set( 'embeddable', 1 );
			model.set( 'embedType', 'share' );
			//console.log( model );

			jQuery( '.mexp-content-gdrive .embed-status' ).html( service.labels.embeddable );
			jQuery( '#mexp-button' ).prop( 'disabled', false );
		} )
		.fail( function( response ) {
		} );
	},

	/**
	 * This isn't implemented in the main MEXP plugin.
	 *
	 * We implement it here.
	 */
	moreEmpty: function( response ) {

		//this.$el.find( '.mexp-empty' ).text( this.service.labels.noresults ).show();
		jQuery( '#gdrive-loadmore' ).hide();

		this.trigger( 'loaded loaded:noresults', response );

	},

	/**
	 * Overrides parent function to do the following:
	 *   - Handle Google Drive folder clicks
	 *   - Adds a sidebar view
	 */
	toggleSelectionHandler: function( event ) {

		if ( 'gdrive' !== this.service.id ) {
			mexpView.prototype.toggleSelectionHandler.apply( this, arguments );
			return;
		}

		if ( event.target.href )
			return;

		var target = jQuery( '#' + event.currentTarget.id );
		var id     = target.attr( 'data-id' );
		var type   = target.attr( 'data-gdrive-type' );

		// folders have their own special syntax
		// @todo Load new view for folder
		if ( 'folder' === type ) {
			return;
		}

		// de-select current item
		if ( this.getSelection().get( id ) ) {
			this.removeFromSelection( target, id );

			// remove sidebar
			this.disposeSingle();

		// select another item
		} else {
			// reset the entire collection
			this.getSelection().reset();

			// remove all selected items
			jQuery( ".mexp-content-gdrive .mexp-item" ).removeClass('selected details');

			// add selection to collection
			this.addToSelection( target, id );

			// sidebar addition
			this.createSingle( this.getSelection().get( id ), id );
		}
	},

	createSidebar: function() {
		var sidebar = this.sidebar = new wp.media.view.Sidebar({
				controller: this.controller
			});

		this.views.add( sidebar );

		//var selection = this.getSelection();

		//selection.on( 'selection:single', this.createSingle, this );

		//if ( selection.single() ) {
		//	this.createSingle();
		//}
	},

	createSingle: function( model, id ) {
		var sidebar = this.sidebar,
			service = this.service,
			html = '';

		sidebar.set( 'details', new mexpAttachmentDisplayDetails({
			controller: this.controller,
			model:      model,
			priority:   80
		}) );

		sidebar.set( 'display', new mexpAttachmentDisplaySettings({
			controller:   this.controller,
			model:        model,
			priority:     160
		}) );

		// forms are already public
		if ( 'form' === model.attributes.meta.file.type ) {
			model.set( 'embeddable', 1 );
			model.set( 'embedType', 'public' );
		}

		// query for embed status with ajax
		if ( ! model.has( 'embeddable' ) ) {
			wp.ajax.post( 'mexp-gdrive-doc-embed-status', {
				id: id
			} )
			.done( function( response ) {
				// published publicly
				if ( response.published ) {
					model.set( 'embeddable', 1 );
					model.set( 'embedType', 'public' );

				} else {
					// shared with anyone
					if( 'share' in response ){
						model.set( 'embeddable', 1 );
						model.set( 'embedType', 'share' );

					// not embeddable
					} else {
						model.set( 'embeddable', 0 );
					}
				}

				if ( model.get( 'embeddable' ) ) {
					html = service.labels.embeddable;
				} else {
					html = service.labels.notembeddable;

					jQuery( '#mexp-button' ).prop( 'disabled', true );
				}

				jQuery( '.mexp-content-gdrive .embed-status' ).html( html );

				//console.log( response );
			} )
			.fail( function( response ) {
			} );

		// we already queried for embed status, so output
		} else {
			if ( model.get( 'embeddable' ) ) {
				html = service.labels.embeddable;
			} else {
				html = service.labels.notembeddable;

				jQuery( '#mexp-button' ).prop( 'disabled', true );
			}

			jQuery( '.mexp-content-gdrive .embed-status' ).html( html );
		}

		// Show the sidebar on mobile
		// @todo needs testing
		if ( this.model.id === 'insert' ) {
			sidebar.$el.addClass( 'visible' );
		}
	},

	disposeSingle: function() {
		var sidebar = this.sidebar;
		sidebar.unset('details');
		sidebar.unset('display');
		// Hide the sidebar on mobile
		sidebar.$el.removeClass( 'visible' );
	},

	/**
	 * Fix spinner.
	 *
	 * @link https://github.com/Automattic/media-explorer/pull/66
	 */
	loading: function() {

		// show spinner
		this.$el.find( '.spinner' ).addClass( 'is-active' );

		// hide messages
		this.$el.find( '.mexp-error' ).hide().text('');
		this.$el.find( '.mexp-empty' ).hide().text('');

		// disable 'load more' button
		jQuery( '#' + this.service.id + '-loadmore' ).attr( 'disabled', true );
	},
	loaded: function( response ) {

		// hide spinner
		this.$el.find( '.spinner' ).removeClass( 'is-active' );

	}
});

// change inserted URL to shortcode
media.controller.MEXP = mexpController.extend({
	initialize: function() {
		mexpController.prototype.initialize.apply( this, arguments );
	},

	mexpInsert: function() {

		var selection = this.frame.content.get().getSelection(),
			model = false,
			shortcode = {},
			gdoc = true,
			type,
			url,
			retval = '';

		selection.each( function( item ) {
			// we check for our special meta.file property to determine if this is a gdoc
			if( item.attributes.meta.gdoc ) {
				model = item;
			}
		}, this );

		// stop the madness for non-gdocs!
		if ( false === model ) {
			mexpController.prototype.mexpInsert.apply( this, arguments );
			return;
		}

		type = model.attributes.meta.file.type;
		if ( 'spreadsheet' === type || 'form' === type ) {
			type += 's';
		}

		switch ( type ) {
			case 'document' :
			case 'forms' :
			case 'spreadsheets' :
			case 'presentation' :
				gdoc = true;
				url = 'https://docs.google.com/' + type + '/d/' + model.id + '/';

				if ( 'share' === model.get( 'embedType' ) ) {
					url += 'edit?usp=sharing';
				}

				break;

			default :
				if ( 'audio' !== type ) {
					type = 'other';
				}

				url = 'https://drive.google.com/file/d/' + model.id + '/view?usp=sharing';
				shortcode.type = type;
				break;
		}

		if ( 'public' === model.get( 'embedType' ) ) {
			switch ( type ) {
				case 'document' :
					url += 'pub';
					break;

				case 'forms' :
					url += 'viewform';
					break;

				case 'spreadsheets' :
					url += 'pubhtml';
					break;

				case 'presentation' :
					url += 'pub?start=false&loop=false&delayms=3000';
					break;

			}
		}

		// set gdoc url
		shortcode.link = url;

		// set gdoc width
		// @todo proportions!
		if ( model.get( 'width' ) ) {
			shortcode.width = model.get( 'width' );
		} else if ( 'forms' === type ) {
			shortcode.width = 760;
		}

		// set gdoc height
		// @todo proportions!
		if ( model.get( 'height' ) ) {
			shortcode.height = model.get( 'height' );
		} else if ( 'forms' === type ) {
			shortcode.height = 500;
		}

		// presentation - size
		if ( model.get( 'size' ) ) {
			shortcode.size = model.get( 'size' );
		}

		// document - header/footer
		if ( model.get( 'seamless' ) ) {
			shortcode.seamless = 0;
		}

		// download link
		if ( model.get( 'downloadlink' ) ) {
			shortcode.downloadlink = 'true';
		}

		//console.log( model );

		// non-Google Doc - filename
		if ( model.get( 'filename' ) ) {
			retval += '<div class="wp-caption">';
		}

		retval += wp.shortcode.string({
			tag:  'gdoc',
			type: 'single',
			attrs: shortcode
		});

		// non-Google Doc - filename
		if ( model.get( 'filename' ) ) {
			retval += '<p class="wp-caption-text">' + model.attributes.content + '</p></div>';
		}

		media.editor.insert( retval );

		selection.reset();
		this.frame.close();
	}

});
