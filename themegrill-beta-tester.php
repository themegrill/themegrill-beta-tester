<?php
/**
 * Plugin Name: ThemeGrill Beta Tester
 * Plugin URI: https://github.com/themegrill/themegrill-beta-tester
 * Description: Run bleeding edge versions of ThemeGrill plugins from our Github repo.
 * Version: 1.0.0
 * Author: Shiva Poudel
 * Author URI: http://themegrill.com
 * Requires at least: 4.2
 * Tested up to: 4.8
 *
 * Based on WP_GitHub_Updater by Joachim Kudish.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . '/class-BetaTesterSettingsPage.php';


if ( ! defined( 'TG_BETA_TEST_GITHUB_REPO_OWNER' ) ) {
	define( 'TG_BETA_TEST_GITHUB_REPO_OWNER', get_option('tgbt_organization_field'));
}

if ( ! defined( 'TG_BETA_TEST_PLUGIN_SLUG' ) ) {
	define( 'TG_BETA_TEST_PLUGIN_SLUG', get_option('tgbt_plugin_field'));
}

if ( ! defined( 'TG_BETA_TEST_PLUGIN_BASENAME' ) ) {
	define( 'TG_BETA_TEST_PLUGIN_BASENAME', get_option('tgbt_plugin_field').'/'.get_option('tgbt-plugin-field').'.php' );
}

/**
 * Confirm ThemeGrill plugin is at least installed before doing anything
 * Curiously, developers are discouraged from using WP_PLUGIN_DIR and not given a
 * function with which to get the plugin directory, so this is what we have to do
 */

if ( ! file_exists( trailingslashit( dirname( dirname( __FILE__ ) ) ) . TG_BETA_TEST_PLUGIN_BASENAME ) ) :

	add_action( 'admin_notices', 'tgbt_plugin_not_installed' );

elseif ( ! class_exists( 'TG_Beta_Tester' ) ) :

	/**
	 * TG_Beta_Tester Main Class
	 */
	final class TG_Beta_Tester {

		/** Github Data */
		private $config = array();

		/** @var TG_Beta_Tester The single instance of the class */
		protected static $_instance = null;

		/**
		 * Main Instance
		 */
		public static function instance() {
			return self::$_instance = is_null( self::$_instance ) ? new self() : self::$_instance;
		}

		/**
		 * Run on activation to flush update cache
		 */
		public static function activate() {
			delete_site_transient( 'update_plugins' );
			delete_site_transient( TG_BETA_TEST_PLUGIN_SLUG . '_latest_tag' );
		}

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->config = array(
				'plugin_file'        => TG_BETA_TEST_PLUGIN_BASENAME,
				'slug'               => TG_BETA_TEST_PLUGIN_SLUG,
				'proper_folder_name' => TG_BETA_TEST_PLUGIN_SLUG,
				'github_owner'       => TG_BETA_TEST_GITHUB_REPO_OWNER,
				'api_url'            => 'https://api.github.com/repos/' . TG_BETA_TEST_GITHUB_REPO_OWNER . '/' . TG_BETA_TEST_PLUGIN_SLUG,
				'github_url'         => 'https://github.com/' . TG_BETA_TEST_GITHUB_REPO_OWNER . '/' . TG_BETA_TEST_PLUGIN_SLUG,
				'requires'           => '4.2',
				'tested'             => '4.8'
			);
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'api_check' ) );
			add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );
			add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );
		}

		/**
		 * Update args
		 * @return array
		 */
		public function set_update_args() {
			$plugin_data                    = $this->get_plugin_data();
			$this->config[ 'plugin_name' ]  = $plugin_data['Name'];
			$this->config[ 'version' ]      = $plugin_data['Version'];
			$this->config[ 'author' ]       = $plugin_data['Author'];
			$this->config[ 'homepage' ]     = $plugin_data['PluginURI'];
			$this->config[ 'new_version' ]  = $this->get_latest_prerelease();
			$this->config[ 'last_updated' ] = $this->get_date();
			$this->config[ 'description' ]  = $this->get_description();
			$this->config[ 'zip_url' ]      = "https://github.com/{$this->config['github_owner']}/{$this->config['slug']}/zipball/{$this->config[ 'new_version' ]}";
		}

		/**
		 * Check whether or not the transients need to be overruled and API needs to be called for every single page load
		 *
		 * @return bool overrule or not
		 */
		public function overrule_transients() {
			return ( defined( 'TG_BETA_TESTER_FORCE_UPDATE' ) && TG_BETA_TESTER_FORCE_UPDATE );
		}

		/**
		 * Get New Version from GitHub
		 *
		 * @since  1.0
		 * @return int $version the version number
		 */
		public function get_latest_prerelease() {
			$tagged_version = get_site_transient( md5( $this->config['slug'] ) . '_latest_tag' );

			if ( $this->overrule_transients() || empty( $tagged_version ) ) {

				$raw_response = wp_remote_get( trailingslashit( $this->config['api_url'] ) . 'releases' );

				if ( is_wp_error( $raw_response ) ) {
					return false;
				}

				$releases       = json_decode( $raw_response['body'] );
				$tagged_version = false;

				if ( is_array( $releases ) ) {
					foreach ( $releases as $release ) {
						if ( $release->prerelease ) {
							$tagged_version = $release->tag_name;
							break;
						}
					}
				}

				// Refresh every 6 hours
				if ( ! empty( $tagged_version ) ) {
					set_site_transient( md5( $this->config['slug'] ) . '_latest_tag', $tagged_version, 60*60*6 );
				}
			}

			return $tagged_version;
		}

		/**
		 * Get GitHub Data from the specified repository
		 *
		 * @since  1.0
		 * @return array $github_data the data
		 */
		public function get_github_data() {
			if ( ! empty( $this->github_data ) ) {
				$github_data = $this->github_data;
			} else {
				$github_data = get_site_transient( md5( $this->config['slug'] ) . '_github_data' );

				if ( $this->overrule_transients() || ( ! isset( $github_data ) || ! $github_data || '' == $github_data ) ) {
					$github_data = wp_remote_get( $this->config['api_url'] );

					if ( is_wp_error( $github_data ) ) {
						return false;
					}

					$github_data = json_decode( $github_data['body'] );

					// Refresh every 6 hours
					set_site_transient( md5( $this->config['slug'] ) . '_github_data', $github_data, 60*60*6 );
				}

				// Store the data in this class instance for future calls
				$this->github_data = $github_data;
			}

			return $github_data;
		}

		/**
		 * Get update date
		 *
		 * @since  1.0
		 * @return string $date the date
		 */
		public function get_date() {
			$_date = $this->get_github_data();
			return ! empty( $_date->updated_at ) ? date( 'Y-m-d', strtotime( $_date->updated_at ) ) : false;
		}

		/**
		 * Get plugin description
		 *
		 * @since  1.0
		 * @return string $description the description
		 */
		public function get_description() {
			$_description = $this->get_github_data();
			return ! empty( $_description->description ) ? $_description->description : false;
		}

		/**
		 * Get Plugin data
		 *
		 * @since  1.0
		 * @return object $data the data
		 */
		public function get_plugin_data() {
			return get_plugin_data( WP_PLUGIN_DIR . '/' . $this->config['plugin_file'] );
		}

		/**
		 * Hook into the plugin update check and connect to GitHub
		 *
		 * @since  1.0
		 * @param  object $transient the plugin data transient
		 * @return object $transient updated plugin data transient
		 */
		public function api_check( $transient ) {
			// Check if the transient contains the 'checked' information
			// If not, just return its value without hacking it
			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			// Clear our transient
			delete_site_transient( md5( $this->config['slug'] ) . '_latest_tag' );

			// Update tags
			$this->set_update_args();

			// Check the version and decide if it's new
			$update = version_compare( $this->config['new_version'], $this->config['version'], '>' );

			if ( $update ) {
				$response              = new stdClass;
				$response->plugin      = $this->config['slug'];
				$response->new_version = $this->config['new_version'];
				$response->slug        = $this->config['slug'];
				$response->url         = $this->config['github_url'];
				$response->package     = $this->config['zip_url'];

				// If response is false, don't alter the transient
				if ( false !== $response ) {
					$transient->response[ $this->config['plugin_file'] ] = $response;
				}
			}

			return $transient;
		}

		/**
		 * Get Plugin info
		 *
		 * @since  1.0
		 * @param  bool   $false  always false
		 * @param  string $action the API function being performed
		 * @param  object $args   plugin arguments
		 * @return object $response the plugin info
		 */
		public function get_plugin_info( $false, $action, $response ) {
			// Check if this call API is for the right plugin
			if ( ! isset( $response->slug ) || $response->slug != $this->config['slug'] ) {
				return false;
			}

			// Update tags
			$this->set_update_args();

			$response->slug          = $this->config['slug'];
			$response->plugin        = $this->config['slug'];
			$response->name          = $this->config['plugin_name'];
			$response->plugin_name   = $this->config['plugin_name'];
			$response->version       = $this->config['new_version'];
			$response->author        = $this->config['author'];
			$response->homepage      = $this->config['homepage'];
			$response->requires      = $this->config['requires'];
			$response->tested        = $this->config['tested'];
			$response->downloaded    = 0;
			$response->last_updated  = $this->config['last_updated'];
			$response->sections      = array( 'description' => $this->config['description'] );
			$response->download_link = $this->config['zip_url'];

			return $response;
		}

		/**
		 * Rename the downloaded zip
		 */
		public function upgrader_source_selection( $source, $remote_source, $upgrader ) {
			global $wp_filesystem;

			if ( strstr( $source, "/{$this->config['github_owner']}-{$this->config['slug']}-" ) ) {
				$corrected_source = trailingslashit( $remote_source ) . trailingslashit( $this->config[ 'proper_folder_name' ] );

				if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
					return $corrected_source;
				} else {
					return new WP_Error();
				}
			}

			return $source;
		}
	}

	register_activation_hook( __FILE__, array( 'TG_Beta_Tester', 'activate' ) );

	add_action( 'admin_init', array( 'TG_Beta_Tester', 'instance' ) );

endif;

/**
 * ThemeGrill Plugin Not Installed Notice.
 */
if ( ! function_exists( 'tgbt_plugin_not_installed' ) ) {
	function tgbt_plugin_not_installed() {
		echo '<div class="error"><p>' . sprintf( __( 'ThemeGrill Beta Tester requires any one %s to be installed.', 'themegrill-beta-tester' ), '<a href="https://themegrill.com/plugins/" target="_blank">ThemeGrill plugins</a>' ) . '</p></div>';
	}
}