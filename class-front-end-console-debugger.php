<?php

/**
 * Plugin Name: Front Page Console Debugger
 * Plugin URI:  https://github.com/mrxkon/
 * Description: Mu-plugin for some easy debugging stats produced on console for live viewing. Use ?console_debug=true on any page to enable the console debugging.
 * Author:      Konstantinos 'xkon' Xenos
 * Author URI:  https://github.com/mrxkon/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Props:
 * Takis @ Nevma (https://github.com/nevma)
 * Clorith (https://github.com/Clorith)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Front_End_Console_Debugger' ) ) {
	class Front_End_Console_Debugger {

		private static $_instance = null;

		public static function get_instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new Front_End_Console_Debugger();
			}

			return self::$_instance;
		}

		private function __construct() {
			add_action( 'shutdown', array( $this, 'run_debugger' ) );
		}

		public function run_debugger() {
			if ( isset( $_GET['console_debug'] ) && 'true' === esc_attr( $_GET['console_debug'] ) ) {
				global $wp_filter;
				global $wp;

				// PHP Execution
				$time = str_replace( ',', '.', timer_stop( 0, 3 ) );

				// Max memory usage
				$max_ram = ceil( memory_get_peak_usage( true ) / 1024 / 1024 );

				// Number of database queries
				$num_queries = get_num_queries();

				// WordPress Hooks
				$hook = $wp_filter;

				$hook_array = array();

				foreach ( $hook as $tag => $priority ) {
					foreach ( $priority as $priority => $function ) {
						foreach ( $function as $name => $properties ) {
							$hook_array[ $tag ][ $priority ] = $name;
						}
					}
				}

				// Plugins
				if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_mu_plugins' ) || ! function_exists( 'get_dropins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				if ( ! function_exists( 'get_plugin_updates' ) ) {
					require_once ABSPATH . 'wp-admin/includes/update.php';
				}

				$plugins        = get_plugins();
				$mu_plugins     = count( get_mu_plugins() );
				$dropins        = count( get_dropins() );
				$plugin_updates = get_plugin_updates();
				$plugins_active = 0;
				$plugins_total  = 0;

				foreach ( $plugins as $plugin_path => $plugin ) {
					$plugins_total++;

					if ( is_plugin_active( $plugin_path ) ) {
						$plugins_active++;
					}
				}

				if ( $plugins_total > $plugins_active && ! is_multisite() ) {
					$unused_plugins = $plugins_total - $plugins_active;
				}

				// Themes
				if ( ! function_exists( 'wp_get_themes' ) ) {
					require_once ABSPATH . 'wp-admin/includes/theme.php';
				}

				$themes = count( wp_get_themes() );

				// Headers
				$response = wp_remote_get( get_the_permalink() );
				$headers  = $this->headers_to_array( wp_remote_retrieve_headers( $response ), 'data' );

				// Output
				?>
				<script>
					window.setTimeout( function() {
						var performance          = window.performance || window.mozPerformance || window.msPerformance || window.webkitPerformance,
							responseStart        = ( ( performance.timing.responseStart            - performance.timing.requestStart ) / 1000 ).toFixed( 2 ),
							domContentLoaded     = ( ( performance.timing.domContentLoadedEventEnd - performance.timing.requestStart ) / 1000 ).toFixed( 2 ),
							load                 = ( ( performance.timing.loadEventEnd             - performance.timing.requestStart ) / 1000 ).toFixed( 2 );

						console.log( 'Debug info - Plugins: <?php echo $plugins_total; ?> Total / <?php echo $plugins_active; ?>  Active / <?php echo $unused_plugins; ?> Inactive / <?php echo $mu_plugins; ?> Must-Use / <?php echo $dropins; ?> Drop-in' );
						console.log( 'Debug info - Themes: <?php echo $themes; ?> Total' );
						console.log( 'Debug info - PHP Time: <?php echo $time; ?> ' );
						console.log( 'Debug info - PHP RAM Usage: <?php echo $max_ram; ?> MB' );
						console.log( 'Debug info - MySQL Queries: <?php echo $num_queries; ?> ' );
						console.log( 'Debug info - Headers:' );
						console.log( <?php echo wp_json_encode( $headers ); ?> );
						console.log( 'Debug info - TTFB: ' + responseStart );
						console.log( 'Debug info - DOMContentLoaded: ' + domContentLoaded );
						console.log( 'Debug info - Load: ' + load );
						console.log( 'Debug info - WordPress Hooks:' );
						console.log( <?php echo wp_json_encode( $hook_array ); ?> );
					}, 1000 );
				</script>
				<?php
			}
		}

		public function headers_to_array( $obj, $prop ) {
			$array  = (array) $obj;
			$prefix = chr( 0 ) . '*' . chr( 0 );

			return $array[ $prefix . $prop ];
		}
	}

	if ( ! function_exists( 'front_end_console_debugger' ) ) {
		function front_end_console_debugger() {
			return Front_End_Console_Debugger::get_instance();
		}

		add_action( 'plugins_loaded', 'front_end_console_debugger', 10 );
	}
}
