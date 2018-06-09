<?php
/**
 * The remote host file to process update requests
 * Based on a haiku from https://github.com/omarabid/Self-Hosted-WordPress-Plugin-repository
 */
if ( ! isset( $_POST['action'] ) ) {
	echo 'Swab the deck ye lily-livered barnacle!';
	exit;
}

// Set current version
$current = '1.1.0';

// Set up the properties common to both requests
$obj              = new stdClass();
$obj->slug        = 'arrmatey';
$obj->name        = 'ARR Matey!';
$obj->plugin_name = 'arrmatey/arrmatey.php';
$obj->url         = 'https://arrmatey.neutrinoinc.com/'; // plugin homepage
$obj->new_version = $current;
// Download location for the plugin zip file (can exist anywhere - aws, geocities, this host, etc)
$obj->package = "https://updates.arrmatey.neutrinoinc.com/arrmatey-{$current}.zip";

switch ( $_POST['action'] ) {

	case 'version':
		echo serialize( $obj );
		break;

	case 'info':
		$obj->requires      = '4.0';
		$obj->tested        = '4.9.6';
		$obj->last_updated  = date( 'Y-m-d', filectime( __DIR__ . "/arrmatey-{$current}.zip" ) ); // like 2018-06-09
		$obj->sections      = array(
			'description' => 'Make your website piratical by converting the letter R into a seaworthy RRRRRRR.',
			'changelog'   => <<<EOF
= 1.1.0 =<br>
* More variety in text replacement<br>
* Bugfix: Text replacement skips HTML tags and attributes with post contents<br>
<br>
= 1.0.0 =<br>
* This plugin surfaced from the depths of Neptune's domain
EOF
		);
		$obj->download_link = $obj->package;
		echo serialize( $obj );

	case 'license':
		echo serialize( $obj );
		break;
}
