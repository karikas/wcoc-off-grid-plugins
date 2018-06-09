=== WP Plugin Update Server ===
Contributors: frogerme
Tags: plugins, themes, updates, license
Requires at least: 4.9.5
Tested up to: 4.9.5
Stable tag: trunk
Requires PHP: 7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Run your own update server for plugins and themes

== Description ==

WP Plugin Update Server allows developers to provide updates for plugins and themes packages not hosted on wordpress.org. It optionally integrates with Software License Manager for license checking. It is also useful to provide updates for plugins or themes not compliant with the GPLv2 (or later).
Packages may be either uploaded directly, or hosted in a remote repository, public or private. It supports Bitbucket, Github and Gitlab, as well as self-hosted installations of Gitlab.

### Special Thanks
A warm thank you to [Yahnis Elsts](https://github.com/YahnisElsts), the author of [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) and [WP Update Server](https://github.com/YahnisElsts/wp-update-server) libraries, without whom the creation of this plugin would not have been possible.  
Authorisation to use these libraries freely provided relevant licenses are included has been graciously granted [here](https://github.com/YahnisElsts/wp-update-server/issues/37#issuecomment-386814776).

### Overview

This plugin adds the following major features to WordPress:

* **WP Plugin Update Server admin page:** to manage the list of packages and configure the plugin.
* **Package management:** to manage update packages, showing a listing with Package Name, Version, Type, File Name, Size, Last Modified and License Status ; includes bulk operations to delete, download and change the license status, and the ability to delete all the packages.
* **Add Packages:** Upload update packages from a local machine to the server, or download them to the server from a remote repository.
* **General settings:** for archive files download size, cache, and logs, with force clean.
* **Packages licensing:** Prevent plugins and themes installed on remote WordPress installation from being updated without a valid license. Check the validity of licenses using Software License Manager, with an extra signature for stronger security. Possibility to use a remote installation of Software License Manager running on a separate WordPress installation.
* **Packages remote source:** WP Plugin Update Server can act as a proxy and will help you to connect your clients with your plugins and themes kept on a remote repository, so that they are always up to date. Supports Bitbucket, Github and Gitlab, as well as self-hosted installations of Gitlab. Packages will not be installed on your server, only transferred to the clients whenever they request them.

To connect their plugins or themes and WP WUpdate Plugin Server, developers can find integration examples in `wp-plugin-update-server/integration-examples`:
* **Dummy Plugin:** a folder `dummy-plugin` with a simple, empty plugin that includes the necessary code in the `dummy-plugin.php` main plugin file and the necessary libraries in a `lib` folder.
* **Dummy Theme:** a folder `dummy-theme` with a simple, empty child theme of Twenty Seventeen that includes the necessary code in the `functions.php` file and the necessary libraries in a `lib` folder.
* **Remote Software License Manager:** a file `remote-slm.php` demonstrating how a remote installation of Software License Manager can be put in place, with a little bit of extra code.

In addition, a [Must Use Plugin](https://codex.wordpress.org/Must_Use_Plugins) developers can add to the WordPress installation running WP Plugin Update Server is available in `wp-plugin-update-server/optimisation/wppus-endpoint-optimizer.php`.  
It allows to bypass all plugins execution when checking for updates (or keep some with a global whitelist in an array `$wppus_always_active_plugins`).  
It also provides a global variable `$wppus_doing_update_api_request` to test in themes and control if filters and actions should be added/removed.

For more information, see [the full documentation](https://github.com/froger-me/wp-plugin-update-server).

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin files to the `/wp-content/plugins/wp-plugin-update-server` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Edit plugin settings

== Changelog ==

= 1.0 =
* First version