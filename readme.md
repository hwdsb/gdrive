# Media Explorer - Google Drive #

This [WordPress](https://wordpress.org) plugin adds support for Google Drive to the [Media Explorer](https://github.com/automattic/media-explorer) plugin.

This plugin was developed for the [Hamilton-Wentworth District School Board Commons](http://commons.hwdsb.on.ca).  Licensed under the GPLv2 or later.

Currently in pre-release status.

***

### Installation
1. Install the [Media Explorer](https://github.com/automattic/media-explorer) plugin.
1. Install the [Google Docs Shortcode](https://github.com/cuny-academic-commons/google-docs-shortcode) plugin.
1. If you plan on developing, clone this repo and run `composer install` in your shell prompt.  This will install the latest version of Google's PHP API.  If you plan on testing, head over to the Github *Releases* section for a precompiled .ZIP file of the entire plugin.
1. Activate the plugin.  You should now see a notice asking you to define some Google constants.
1. For this, you will need to create a Google Drive application in Google Developers Console.
   1. Click on this link to create the app:<br> https://console.developers.google.com//start/api?id=drive&credential=client_key
   1. For help filling in the application, read "*Step 1*" of this page:<br> https://developers.google.com/identity/sign-in/web/server-side-flow#step_1_create_a_client_id_and_client_secret
   1. For your created app, enable oAuth 2.0.
      1. Under **APIs & Auth > Credentials**, click on **Add credentials > OAuth 2.0 client ID**.
      2. Under **Create client ID**, check **Web application** and click "Create".
      3. Under **Name**, type in an identifier of your choosing.
      4. Under **Authorized Javascript origins**, type in your site URL without a trailing slash. eg. `http://example.com`
      5. Under **Authorized redirect URIs**, type in your site URL with a trailing slash. eg. `http://example.com/`
      6. Note down your **Client ID** and **Client secret** located at the top of this page.
1. In `wp-config.php` or in a */wp-content/mu-plugins/* plugin, add the following:

        define( 'MEXP_GDRIVE_CLIENT_ID', 'YOUR_CLIENT_ID_FROM_GOOGLE' );
        define( 'MEXP_GDRIVE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET_FROM_GOOGLE' );
1. Finally, we are able to test the plugin!  Either create a new post or edit an existing one and click on the "**Add Media**" button.
    * There should be a "**Insert from Google Drive**" link in the sidebar.  Click on that.
    * You should now see a **Sign-in to Google** button.  Once you've authenticated, you should be able to see your Google Drive contents and you should be able to start embedding.


***

### Notes
* [You can view a walkthrough video](https://tv.hwdsb.on.ca/media/connect-google-drive-and-the-commons-to-streamline-sharing) showcasing the plugin's functionality on a WordPress multisite and BuddyPress instance.
* This plugin was originally created so each user on a WordPress site could access their own Google Drive contents.  If you plan on using this plugin in a shared, organizational context or would rather that the site share one Google Drive account, a developer should use:
 * the `'mexp_gdrive_get_refresh_token'` filter to override how the token is fetched
 * the `'mexp_gdrive_update_refresh_token'` action to update the token
 * the `'mexp_gdrive_delete_refresh_token'` action to delete token
 * the `'mexp_gdrive_enable_user_profile_page'` filter to return `false`