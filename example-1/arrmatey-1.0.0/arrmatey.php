<?php

/**
 * ARR Matey! Plugin
 * Add a little more pirate to your WordPress website by turning R into RRRRRRR and the like.
 *
 * @wordpress-plugin
 * Plugin Name:       ARR Matey!
 * Plugin URI:        https://arrmatey.neutrinoinc.com
 * Description:       Make your website piratical by converting the letter R into a seaworthy RRRRRRR.
 * Version:           1.0.0
 * Author:            Neutrino, Inc.
 * Author URI:        https://www.neutrinoinc.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class ARRMatey {

	/**
	 * The current version of the plugin.
	 * @var string $version
	 */
	protected $version;

	/**
	 * Text strings to search for during plugin execution
	 * @var array $text_search
	 */
	protected $text_search = [];

	/**
	 * Text strings to replace during plugin execution (in order to match $this->text_search)
	 * @var array $text_replace
	 */
	protected $text_replace = [];

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 */
	public function __construct() {
		// Version: Start at version 1.0.0 and use SemVer - https://semver.org
		$this->version      = '1.0.0';
		$this->text_search  = [ 'r', 'R' ];
		$this->text_replace = [ 'rrrrr', 'RRRRR' ];

		// Plugin Updates Example 1: Hand-coded
		// Using code from https://github.com/omarabid/Self-Hosted-WordPress-Plugin-repository
		include( plugin_dir_path( __FILE__ ) . 'wp_autoupdate.php' );
		// Syntax: new WP_AutoUpdate ($plugin_current_version, $plugin_remote_path, $plugin_slug, $license_user, $license_key);
		new WP_AutoUpdate ( $this->version, 'https://updates.arrmatey.neutrinoinc.com', plugin_basename( __FILE__ ) );
	}

	/**
	 * Do the thing that this plugin does - plugin execution
	 */
	public function run() {
		// Set filters for any time the title or content are displayed
		add_filter( 'the_title', [ $this, 'pirate_title' ], 10, 2 );
		add_filter( 'the_content', [ $this, 'pirate_content' ], 10, 1 );
	}

	/**
	 * Replace post titles with piratical RRRs
	 *
	 * @param $title string
	 * @param $id int
	 *
	 * @return string
	 */
	function pirate_title( $title, $id ) {
		$title = str_replace( $this->text_search, $this->text_replace, $title );

		return $title;
	}

	/**
	 * Replace post content with piratical RRRs
	 *
	 * @param $content string
	 *
	 * @return string
	 */
	function pirate_content( $content ) {
		$content = str_replace( $this->text_search, $this->text_replace, $content );

		return $content;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_arrmatey() {
	$plugin = new ARRMatey();
	$plugin->run();
}

run_arrmatey();