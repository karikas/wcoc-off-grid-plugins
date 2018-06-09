<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<div class="wrap">
	<h1><?php esc_html_e( 'WP Plugin Update Server', 'wppus' ); ?></h1>
	<?php if ( is_string( $updated ) && ! empty( $updated ) ) : ?>
		<div class="updated notice notice-success is-dismissible">
			<p>
				<?php echo esc_html( $updated ); ?>
			</p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.' ); ?></span>
			</button>
		</div>
	<?php elseif ( is_array( $updated ) && ! empty( $updated ) ) : ?>
		<div class="error notice notice-error is-dismissible">
			<ul>
				<?php foreach ( $updated as $option_name => $message ) : ?>
					<li><?php echo esc_html( $message ); ?></li>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.' ); ?></span>
			</button>
		</div>
	<?php endif; ?>
	<?php if ( is_string( $action_error ) && ! empty( $action_error ) ) : ?>
		<div class="error notice notice-error is-dismissible">
			<p>
				<?php echo esc_html( $action_error ); ?>
			</p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.' ); ?></span>
			</button>
		</div>
	<?php endif; ?>
	<h2 class="nav-tab-wrapper">
		<a href="admin.php?page=wppus-options&tab=general-options" class="nav-tab<?php print ( 'general-options' === $active_tab ) ? ' nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'General', 'wppus' ); ?>
		</a>
		<a href="admin.php?page=wppus-options&tab=package-licensing" class="nav-tab<?php print ( 'package-licensing' === $active_tab ) ? ' nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Packages licensing', 'wppus' ); ?>
		</a>
		<a href="admin.php?page=wppus-options&tab=package-source" class="nav-tab<?php print ( 'package-source' === $active_tab ) ? ' nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Packages remote source', 'wppus' ); ?>
		</a>
		<a href="admin.php?page=wppus-options&tab=help" class="nav-tab<?php print ( 'help' === $active_tab ) ? ' nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Help', 'wppus' ); ?>
		</a>
	</h2>
	<?php if ( 'general-options' === $active_tab ) : ?>
		<form action="" method="post">
			<h3><?php esc_html_e( 'Packages', 'wppus' ); ?></h3>
			<?php $packages_table->display(); ?>
		</form>
		<br>
		<hr>
		<form action="" method="post" class="files">
			<h3><?php esc_html_e( 'Add packages', 'wppus' ); ?></h3>
			<table class="form-table general-options">
				<?php if ( get_option( 'wppus_use_remote_repository', false ) ) : ?>
				<tr>
					<th>
						<label for="wppus_manual_download_slug"><?php esc_html_e( 'Add a package from the remote repository (recommended)', 'wppus' ); ?></label>
					</th>
					<td>
						<select name="wppus_manual_download_type" id="wppus_manual_download_type" style="vertical-align: top;line-height: 25px;height: 25px;"><option value="Plugin"><?php esc_html_e( 'Plugin', 'wppus' ); ?></option><option value="Theme"><?php esc_html_e( 'Theme', 'wppus' ); ?></option></select><input class="regular-text" type="text" id="wppus_manual_download_slug" placeholder="<?php esc_attr_e( 'repository-name-aka-theme-or-plugin-slug' ); ?>" name="wppus_manual_download_slug" value=""> <input type="button" value="<?php print esc_attr_e( 'Get remote package', 'wppus' ); ?>" class="button button-primary manual-download-slug-trigger" />
						<p class="description">
							<?php echo sprintf( __( 'Get an archive of a package from a remote repository in the <code>%s</code> directory by entering the package slug. Within the repository, the package files must be contained in a single directory <code>repository-name-aka-plugin-slug</code>, and in the case of plugins, the main plugin file must follow the pattern <code>repository-name-aka-plugin-slug.php</code>.', 'wppus' ), WP_PUS_PLUGIN_PATH . 'packages' ); ?><?php // @codingStandardsIgnoreLine ?>
							<br>
							<?php esc_html_e( 'Adds the package to the list or force downloads an update from the remote repository and overwrites an existing package.', 'wppus' ); ?>
							<br>
							<?php esc_html_e( 'Note: packages will be overwritten automatically regularly with their counterpart in the remote repository if a newer version exists.', 'wppus' ); ?>
						</p>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th>
						<label for="wppus_manual_package_upload"><?php esc_html_e( 'Upload a package', 'wppus' ); ?>
						<?php if ( get_option( 'wppus_use_remote_repository', false ) ) : ?>
							<?php esc_html_e( ' (discouraged)', 'wppus' ); ?>
						<?php endif; ?>
						</label>
					</th>
					<td>
						<input type="button" value="<?php print esc_attr_e( 'Upload package', 'wppus' ); ?>" class="button button-primary manual-package-upload-trigger" /> <input class="input-file" type="file" id="wppus_manual_package_upload" name="wppus_manual_package_upload" value="">
						<p class="description">
							<?php echo sprintf( __( 'Add a package zip archive in the <code>%s</code> directory. The archive needs to be a valid WordPress plugin or theme package, and in the case of a plugin the main plugin file must have the same name as the zip archive. For example, the main plugin file in <code>package-name.zip</code> would be <code>package-name.php</code>.', 'wppus' ), WP_PUS_PLUGIN_PATH . 'packages' ); ?><?php // @codingStandardsIgnoreLine ?>
							<br>
							<?php esc_html_e( 'Adds the package to the list or overwrites an existing package.', 'wppus' ); ?>
							<?php if ( get_option( 'wppus_use_remote_repository', false ) ) : ?>
							<br>
							<?php esc_html_e( 'Note: it is not recommended to keep manually uploaded packages that do not have their counterpart in the remote repository, because updates will be checked regularly for all the packages.', 'wppus' ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
			</table>
			<hr>
			<h3><?php esc_html_e( 'General Settings', 'wppus' ); ?></h3>
			<table class="form-table general-options">
				<tr>
					<th>
						<label for="wppus_archive_max_size"><?php esc_html_e( 'Archive max size (in MB)', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="number" id="wppus_archive_max_size" name="wppus_archive_max_size" value="<?php echo esc_attr( get_option( 'wppus_archive_max_size', $default_archive_size ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Maximum file size when uploading or downloading packages.', 'wppus' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_cache_max_size"><?php esc_html_e( 'Cache max size (in MB)', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="number" id="wppus_cache_max_size" name="wppus_cache_max_size" value="<?php echo esc_attr( get_option( 'wppus_cache_max_size', $default_cache_size ) ); ?>"> <input type="button" value="<?php print esc_attr_e( 'Force Clean', 'wppus' ); ?> (<?php print esc_attr( $cache_size ); ?>)" class="button clean-trigger" data-type="cache" />
						<p class="description">
							<?php echo sprintf( __( 'Maximum size in MB for the <code>%s</code> directory. If the size of the directory grows larger, its content will be deleted at next cron run (checked hourly). The size indicated in the "Force Clean" button is the real current size.', 'wppus' ), WP_PUS_PLUGIN_PATH . 'cache' ); ?><?php // @codingStandardsIgnoreLine ?>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_logs_max_size"><?php esc_html_e( 'Logs max size (in MB)', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="number" id="wppus_logs_max_size" name="wppus_logs_max_size" value="<?php echo esc_attr( get_option( 'wppus_logs_max_size', $default_logs_size ) ); ?>"> <input type="button" value="<?php print esc_attr_e( 'Force Clean', 'wppus' ); ?> (<?php print esc_attr( $logs_size ); ?>)" class="button clean-trigger" data-type="logs" />
						<p class="description">
							<?php echo sprintf( __( 'Maximum size in MB for the <code>%s</code> directory. If the size of the directory grows larger, its content will be deleted at next cron run (checked hourly). The size indicated in the "Force Clean" button is the real current size.', 'wppus' ), WP_PUS_PLUGIN_PATH . 'logs' ); ?><?php // @codingStandardsIgnoreLine ?>
						</p>
					</td>
				</tr>
			</table>
			<br>
			<hr>
			<input type="hidden" name="wppus_settings_section" value="general-options">
			<?php wp_nonce_field( 'wppus_plugin_options', 'wppus_plugin_options_handler_nonce' ); ?>
			<p class="submit">
				<input type="submit" name="wppus_options_save" value="<?php esc_attr_e( 'Save', 'wppus' ); ?>" class="button button-primary" />
			</p>
		</form>
	<?php elseif ( 'package-licensing' === $active_tab ) : ?>
		<form action="" method="post">
			<table class="form-table package-licensing">
				<tr>
					<th>
						<label for="wppus_use_license_server"><?php esc_html_e( 'Software License Manager integration', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="checkbox" id="wppus_use_license_server" name="wppus_use_license_server" value="1" <?php checked( get_option( 'wppus_use_license_server', 0 ), 1 ); ?>>
						<p class="description">
							<?php esc_html_e( 'Enables this server manages to deliver license-enabled plugins and themes using Software License Manager licenses.', 'wppus' ); ?>
							<br>
							<strong><?php esc_html_e( 'It affects all the packages with a "Requires License" license status delivered by this installation of WP Plugin Update Server.', 'wppus' ); ?></strong>
							<br>
							<strong><?php esc_html_e( 'Settings of the "Packages licensing" section will be saved only if this option is checked.', 'wppus' ); ?></strong>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_hmac_key"><?php esc_html_e( 'HMAC Key', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="text" id="wppus_hmac_key" name="wppus_hmac_key" value="<?php echo esc_attr( get_option( 'wppus_hmac_key', 'hmac' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Ideally a random string, used to authenticate license signatures.', 'wppus' ); ?>
							<br>
							<?php esc_html_e( 'WARNING: Changing this value will invalidate all the licence signatures for current remote installations.', 'wppus' ); ?>
							<br>
							<?php esc_html_e( 'You may grant a grace period and let webmasters deactivate and re-activate their license(s) by unchecking "Check License signature?" below.', 'wppus' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_crypto_key"><?php esc_html_e( 'Encryption Key', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="text" id="wppus_crypto_key" name="wppus_crypto_key" value="<?php echo esc_attr( get_option( 'wppus_crypto_key', 'crypto' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Ideally a random string, used to encrypt license signatures.', 'wppus' ); ?>
							<br>
							<?php esc_html_e( 'WARNING: Changing this value will invalidate all the licence signatures for current remote installations.', 'wppus' ); ?>
							<br>
							<?php esc_html_e( 'You may grant a grace period and let webmasters deactivate and re-activate their license(s) by unchecking "Check License signature?" below.', 'wppus' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_license_server_url"><?php esc_html_e( 'License server URL (Software License Manager plugin)', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="text" id="wppus_license_server_url" name="wppus_license_server_url" value="<?php echo esc_attr( get_option( 'wppus_license_server_url', home_url( '/' ) ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'The URL of the server where Software License Manager plugin is installed. Must include the protocol ("http://" or "https://").', 'wppus' ); ?>
							<br>
							<?php esc_html_e( 'If using a remote install of Software License Manager plugin, the validity of the server URL will be checked regularly.', 'wppus' ); ?>
							<br>
							<?php echo sprintf( __( 'If using a remote install of Software License Manager plugin, see <code>%s</code>.', 'wppus' ), WP_PUS_PLUGIN_PATH . 'integration-examples/remote-slm.php' ); ?><?php // @codingStandardsIgnoreLine ?>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_license_check_signature"><?php esc_html_e( 'Check License signature?', 'wppus' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="wppus_license_check_signature" name="wppus_license_check_signature" value="1" <?php checked( get_option( 'wppus_license_check_signature', 1 ), 1 ); ?>>
						<p class="description">
							<?php esc_html_e( 'Check signatures - can be deactivated if the HMAC Key or the Encryption Key has been recently changed and remote installations have active licenses.', 'wppus' ); ?>
							<br>
							<?php esc_html_e( 'Typically, all webmasters would have to deactivate and re-activate their license(s) to re-build their signatures, and this could take time ; it allows to grant a grace period during which license checking is less strict to avoid conflicts.', 'wppus' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<br>
			<hr>
			<input type="hidden" name="wppus_settings_section" value="package-licensing">
			<?php wp_nonce_field( 'wppus_plugin_options', 'wppus_plugin_options_handler_nonce' ); ?>
			<p class="submit">
				<input type="submit" name="wppus_options_save" value="<?php esc_attr_e( 'Save', 'wppus' ); ?>" class="button button-primary" />
			</p>
		</form>
	<?php elseif ( 'package-source' === $active_tab ) : ?>
		<form action="" method="post">
			<table class="form-table package-source">
				<tr>
					<th>
						<label for="wppus_use_remote_repository"><?php esc_html_e( 'Use remote repository service', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="checkbox" id="wppus_use_remote_repository" name="wppus_use_remote_repository" value="1" <?php checked( get_option( 'wppus_use_remote_repository', 0 ), 1 ); ?>>
						<p class="description">
							<?php esc_html_e( 'Enables this server to download plugins and themes from a remote repository before delivering updates.', 'wppus' ); ?>
							<br>
							<?php esc_html_e( 'Supports Bitbucket, Github and Gitlab.', 'wppus' ); ?>
							<br>
							<?php echo sprintf( __( 'If left unchecked, zip packages need to be manually uploaded to <code>%s</code>.', 'wppus' ), WP_PUS_PLUGIN_PATH . 'packages' ); ?><?php // @codingStandardsIgnoreLine ?>
							<br>
							<strong><?php esc_html_e( 'It affects all the packages delivered by this installation of WP Plugin Update Server if they have a corresponding repository in the remote repository service.', 'wppus' ); ?></strong>
							<br>
							<strong><?php esc_html_e( 'Settings of the "Packages source" section will be saved only if this option is checked.', 'wppus' ); ?></strong>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_remote_repository_url"><?php esc_html_e( 'Remote repository service URL', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="text" id="wppus_remote_repository_url" name="wppus_remote_repository_url" value="<?php echo esc_attr( get_option( 'wppus_remote_repository_url' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'The URL of the remote repository service where packages are hosted.', 'wppus' ); ?>
							<br>
							<?php _e( 'Must follow the following pattern: <code>https://repository-service.tld/username</code> where <code>https://repository-service.tld</code> may be a self-hosted instance of Gitlab.', 'wppus' ); ?><?php // @codingStandardsIgnoreLine ?>
							<br>
							<?php _e( 'Each package repository URL must follow the following pattern: <code>https://repository-service.tld/username/package-name/</code> ; it must contain a single <code>package-name</code> directory, and in the case of plugins the main plugin file must follow the pattern <code>package-name.php</code>.', 'wppus' ); ?><?php // @codingStandardsIgnoreLine ?>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_remote_repository_self_hosted"><?php esc_html_e( 'Self-hosted remote repository service', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="checkbox" id="wppus_remote_repository_self_hosted" name="wppus_remote_repository_self_hosted" value="1" <?php checked( get_option( 'wppus_remote_repository_self_hosted', 0 ), 1 ); ?>>
						<p class="description">
							<?php esc_html_e( 'Check this only if the remote repository service is a self-hosted instance of Gitlab.', 'wppus' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_remote_repository_branch"><?php esc_html_e( 'Packages branch name', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="text" id="wppus_remote_repository_branch" name="wppus_remote_repository_branch" value="<?php echo esc_attr( get_option( 'wppus_remote_repository_branch', 'master' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'The branch to download when getting remote packages from the remote repository service.', 'wppus' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_remote_repository_credentials"><?php esc_html_e( 'Remote repository service credentials', 'wppus' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="text" id="wppus_remote_repository_credentials" name="wppus_remote_repository_credentials" value="<?php echo esc_attr( get_option( 'wppus_remote_repository_credentials' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Credentials for non-publicly accessible repositories.', 'wppus' ); ?>
							<br>
							<?php _e( 'In the case of Github and Gitlab, an access token (<code>token</code>).', 'wppus' ); ?><?php // @codingStandardsIgnoreLine ?>
							<br>
							<?php _e( 'In the case of Bitbucket, the Consumer key and secret separated by a pipe (<code>consumer_key|consumer_secret</code>). IMPORTANT: when creating the consumer, "This is a private consumer" must be checked.', 'wppus' ); ?><?php // @codingStandardsIgnoreLine ?>
							<br>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="wppus_remote_repository_check_frequency"><?php esc_html_e( 'Remote update check frequency', 'wppus' ); ?></label>
					</th>
					<td>
						<select name="wppus_remote_repository_check_frequency" id="wppus_remote_repository_check_frequency">
							<?php foreach ( $schedules as $schedule_slug => $schedule ) : ?>
								<option value="<?php echo esc_attr( $schedule_slug ); ?>" <?php selected( get_option( 'wppus_remote_repository_check_frequency', 'daily' ), $schedule_slug ); ?>><?php echo esc_html( $schedule['display'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'How often WP Plugin Update Server will poll each remote repository for package updates - checking too often may slow down the server (recommended "Once Daily").', 'wppus' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<br>
			<hr>
			<input type="hidden" name="wppus_settings_section" value="package-source">
			<?php wp_nonce_field( 'wppus_plugin_options', 'wppus_plugin_options_handler_nonce' ); ?>
			<p class="submit">
				<input type="submit" name="wppus_options_save" value="<?php esc_attr_e( 'Save', 'wppus' ); ?>" class="button button-primary" />
			</p>
		</form>
	<?php elseif ( 'help' === $active_tab ) : ?>
		<h2><?php esc_html_e( 'Add update and possibly provide license support to plugins and themes', 'wppus' ); ?></h2>
		<p>
			<?php _e( "To link your packages to WP Plugin Update Server, and maybe to prevent webmasters from getting updates of your plugins and themes unless they have a license, your plugins and themes need to include some extra code. It is a simple matter of adding a few lines in the main plugin file (for plugins) or in the functions.php file (for themes), and add a few libraries in a <code>lib</code> directory." ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<p>
			<?php echo sprintf( __( "An example of plugin is available in <code>%s</code>, and an example of theme is available in <code>%s</code>.", 'wppus' ), WP_PUS_PLUGIN_PATH . 'integration-examples/dummy-plugin', WP_PUS_PLUGIN_PATH . 'integration-examples/dummy-theme' ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<p>
			<?php echo sprintf( __( 'Unless "Use remote repository service" is checked in "Packages remote source", you need to manually upload the packages zip archives (and subsequent updates) in <code>%s</code>. Packages need to be valid WordPress plugin or theme packages, and in the case of a plugin the main plugin file must have the same name as the zip archive. For example, the main plugin file in <code>package-name.zip</code> would be <code>package-name.php</code>.', 'wppus' ), WP_PUS_PLUGIN_PATH . 'packages' ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<p>
			<?php _e( 'When adding package licenses in Software License Manager, each license must have its "Product Reference" field set to <code>package-name/package-name.php</code> for a plugin, or <code>package-name/functions.php</code> for a theme.' ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<hr>
		<h2><?php esc_html_e( 'Requests optimisation', 'wppus' ); ?></h2>
		<p>
			<?php _e( "When the remote clients where your plugins and themes are installed send a request to check for updates or download a package, this server's WordPress installation is loaded, with its own plugins and themes. This is not optimised because unnecessary action and filter hooks that execute before <code>parse_request</code> action hook are also triggered, even though the request is not designed to produce any output.", 'wppus' ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<p>
			<?php echo sprintf( __( "To solve this for plugins, you can place <code>%s</code> in <code>%s</code>. This will effectively create a Must Use Plugin that runs before everything else and prevents other plugins from being executed when a request is received by WP Plugin Update Server." ), WP_PUS_PLUGIN_PATH . 'optimisation/wppus-endpoint-optimiser.php', dirname( dirname( WP_PUS_PLUGIN_PATH ) ) . 'mu-plugin/wppus-endpoint-optimiser.php' ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<p>
			<?php _e( 'You may edit the variable <code>$wppus_always_active_plugins</code> of the MU Plugin file to allow some plugins to run anyway.' ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<p>
			<?php _e( "<strong>IMPORTANT - This MU Plugin does not prevent theme hooks registered before</strong> <code>parse_request</code>  <strong>action hook from being fired.</strong>" ); ?><br/><?php // @codingStandardsIgnoreLine ?>
			<?php _e( "To solve this for themes, a few code changes are necessary." ); ?><br/><?php // @codingStandardsIgnoreLine ?>
			<?php _e( 'The MU Plugin provides a global variable <code>$wppus_doing_update_api_request</code> that can be tested when adding hooks and filters:' ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<ul>
			<li><?php _e( "- Use the global variable in a <strong>main theme's</strong> <code>functions.php</code> <strong>to test if current theme's hooks should be added</strong>." ); ?><?php // @codingStandardsIgnoreLine ?></li>
			<li><?php _e( "- Use the global variable in a <strong>child theme's</strong> <code>functions.php</code> <strong>to remove action and filter hooks from the parent theme AND test if current theme's hooks should be added</strong>." ); ?><?php // @codingStandardsIgnoreLine ?></li>
		</ul>
		<hr>
		<h2><?php esc_html_e( 'Remote license server integration', 'wppus' ); ?></h2>
		<p>
			<?php _e( "WP Plugin Update Server can work with Software License Manager running on a separate installation of WordPress."); ?><?php // @codingStandardsIgnoreLine ?><br>
			<?php _e( "WP Plugin Update Server uses an extra parameter <code>license_signature</code> containing license information, in particular the registered domain, encrypted with Open SSL for extra security when checking licenses." ); ?><br><?php // @codingStandardsIgnoreLine ?>
			<?php _e( "When running on the same installation, a filter <code>slm_ap_response_args</code> is added, but it cannot run if Software License Manager is installed remotely ; this means the remote installation needs to take care of adding and running this filter." ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<p>
			<?php echo sprintf( __( "An example of filter implementation is available in <code>%s</code> for you to add in the code base of the remote WordPress installation running the Software License Manager plugin. You may add your code in a theme's functions.php file or build an extra plugin around it.", 'wppus' ), WP_PUS_PLUGIN_PATH . 'integration-examples/remote-slm.php' ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<hr>
		<h2><?php esc_html_e( 'More help...', 'wppus' ); ?></h2>
		<p>
			<?php _e( 'For more help on how to use WP Plugin Update Server, please <a target="_blank" href="https://github.com/froger-me/wp-plugin-update-server/issues">open an issue on Github</a> or contact <a href="mailto:wppus-help@froger.me">wppus-help@froger.me</a>.', 'wppus' ); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
		<p>
			<?php _e( 'Depending on the nature of the request, a fee may apply.'); ?><?php // @codingStandardsIgnoreLine ?>
		</p>
	<?php endif; ?>
</div>
