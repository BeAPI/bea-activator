<?php
/*
Plugin Name: BEA - Activator
Version: 1.0.0
Plugin URI: https://github.com/BeAPI/bea-activator
Description: Quickly deactivate & reactivate a plugin.
Author: Be API
Author URI: https://beapi.fr
Contributors: Julien Maury, Maxime Culea & Amaury Balmer

--------

Copyright 2018-2019 Be API Technical team (human@beapi.fr)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! function_exists( 'add_filter' ) ) {
	die( 'No !' );
}

if ( ! is_admin() ) {
	return false;
}

add_action( 'plugins_loaded', function () {
	$i = Bea_Activator::getInstance();
	$i->hooks();
} );

class Bea_Activator {

	/**
	 * @var self
	 */
	protected static $instance;

	protected function __construct() {
	}

	final public static function getInstance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static;
		}

		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public function add_notice() {
		print __( '<div id="message" class="updated notice is-dismissible"><p>Plugin <strong>reactivated</strong>.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>', 'bea-activator' );
	}

	public function admin_init() {
		// Load the textdomain
		load_plugin_textdomain( 'bea-activator', false, basename( rtrim( dirname( __FILE__ ), '/' ) ) . '/lang' );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		$this->add_links_to_list();

		if ( empty( $_GET['action'] ) || empty( $_GET['plugin_file'] ) ) {
			return false;
		}

		if ( 'full_contact' !== $_GET['action'] ) {
			return false;
		}

		// nonce checking
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'full_contact' ) ) {
			wp_die( __( 'cheatin’ uh ' ) );
		}

		$plugin_file = urldecode( $_GET['plugin_file'] );

		// MU inside
		deactivate_plugins( $plugin_file );

		if ( is_multisite() ) {
			activate_plugin( $plugin_file, null, true );
			add_action( 'network_admin_notices', array( $this, 'add_notice' ) );
		} else {
			activate_plugin( $plugin_file );
			add_action( 'admin_notices', array( $this, 'add_notice' ) );
		}

		return true;
	}

	/**
	 * Activate-deactivate custom link
	 *
	 * @param $actions
	 * @param $plugin_file
	 *
	 * @return array
	 * @author Julien Maury
	 */
	public function action_links( $actions, $plugin_file ) {
		$url = add_query_arg( array(
			'action'      => 'full_contact',
			'plugin_file' => plugin_basename( $plugin_file ),
		), network_admin_url( 'plugins.php' ) // will be admin_url() on single installations
		);

		return array_merge( array( 'full_contact' => "<a href='" . wp_nonce_url( $url, 'full_contact' ) . "'>" . __( 'Deactivate & Reactivate', 'bea-activator' ) . "</a>" ), $actions );
	}

	/**
	 * Add link
	 *
	 * @return bool
	 * @author Julien Maury
	 */
	public function add_links_to_list() {
		if ( 'plugins.php' !== $GLOBALS['pagenow'] ) {
			return false;
		}

		$all_plugins = $this->get_plugins_list();
		foreach ( $all_plugins as $plugin ) {
			if ( ! is_plugin_active( $plugin ) ) {
				continue;
			}
			$this->add_link( $plugin );
		}

		return true;
	}

	/**
	 * Add link to extensions list
	 *
	 * @param $plugin
	 *
	 * @author Julien Maury
	 */
	protected function add_link( $plugin ) {
		if ( ! is_multisite() ) {
			add_filter( 'plugin_action_links_' . plugin_basename( $plugin ), array( $this, 'action_links' ), 10, 2 );
		} else {
			add_filter( 'network_admin_plugin_action_links_' . plugin_basename( $plugin ), array( $this, 'action_links' ), 10, 2 );
		}
	}

	/**
	 * Get all plugins
	 *
	 * @author Julien Maury
	 * @return array
	 */
	protected function get_plugins_list() {
		return array_keys( get_plugins() );
	}
}