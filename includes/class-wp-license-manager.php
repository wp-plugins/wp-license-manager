<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, dashboard-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @package    Wp_License_Manager
 * @subpackage Wp_License_Manager/includes
 * @author     Jarkko Laine <jarkko@jarkkolaine.com>
 */
class Wp_License_Manager {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @access   protected
	 * @var      Wp_License_Manager_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
     * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the Dashboard and
	 * the public-facing side of the site.
	 */
	public function __construct() {

		$this->plugin_name = 'wp-license-manager';
		$this->version = '0.5.5';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		// Initialize the data updater
		$this->loader->add_action( 'init', $this, 'update_plugin_data' );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wp_License_Manager_Loader. Orchestrates the hooks of the plugin.
	 * - Wp_License_Manager_i18n. Defines internationalization functionality.
	 * - Wp_License_Manager_Admin. Defines all hooks for the dashboard.
	 * - Wp_License_Manager_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-license-manager-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-license-manager-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the Dashboard.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wp-license-manager-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-license-manager-public.php';

        /**
         * The classes responsible for rendering the list of licenses.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-license-manager-list-table.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-licenses-list-table.php';

        /**
         * The class responsible for handling the incoming license manager API calls.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-license-manager-api.php';

        /**
         * A wrapper class for our Amazon S3 connectivity.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-license-manager-s3.php';

        $this->loader = new Wp_License_Manager_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wp_License_Manager_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Wp_License_Manager_i18n();
		$plugin_i18n->set_domain( $this->get_plugin_name() );

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the dashboard functionality
	 * of the plugin.
	 *
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Wp_License_Manager_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

        // Meta boxes
        $this->loader->add_action( 'add_meta_boxes_wplm_product', $plugin_admin, 'add_product_information_meta_box' );
        $this->loader->add_action( 'save_post', $plugin_admin, 'save_product_information_meta_box' );

        // Plugin settings menu
        $this->loader->add_action( 'admin_init', $plugin_admin, 'add_plugin_settings_fields' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_settings_page' );

        // Add licenses menu
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_licenses_menu_page' );
        $this->loader->add_action( 'admin_post_license_manager_add_license', $plugin_admin, 'handle_add_license' );

        // Add a link from plugins to settings
        $plugin_file_url = $this->plugin_name . '/wp-license-manager.php';
        $this->loader->add_filter( "plugin_action_links_" . $plugin_file_url, $plugin_admin, 'add_settings_link_to_plugin_list' );

        // Add the admin notice for displaying notification about missing plugin configuration
        $this->loader->add_action( 'admin_notices', $plugin_admin, 'show_admin_notices' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new Wp_License_Manager_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

        $this->loader->add_action( 'init', $plugin_public, 'add_products_post_type' );

        // The external API setup
        $this->loader->add_filter( 'query_vars', $plugin_public, 'add_api_query_vars' );
        $this->loader->add_action( 'init', $plugin_public, 'add_api_endpoint_rules' );
        $this->loader->add_action( 'parse_request', $plugin_public, 'sniff_api_requests' );
    }

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return    Wp_License_Manager_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Runs at plugin init. Checks the plugin's version and does updates
	 * as needed.
	 */
	public function update_plugin_data() {

		// Get the stored plugin version. If the version is not set, this is a clean installation.
		// If the version differs from current version, the plugin has been updated and we'll
		// run an update if necessary.
		$plugin_version = get_option( $this->plugin_name . '_plugin-version', false );

		if ( $plugin_version == $this->version ) {
			// Already at current version
			return false;
		} else {
			// The plugin was just updated. Update the version right away to prevent concurrency issues
			// with more than one thread running the updates at once.

			update_option( $this->plugin_name . '_plugin-version', $this->version );

			if ( $plugin_version === false ) {
				// This is a clean installation, no need to run updates.

				/*
				 * Exception: Because we added this update functionality only at version 0.5.5 of the plugin,
				 * we need to make an exception for people running that version.
				 *
				 * This is hacky and will lead to some people running the update unnecessarily, but since
				 * there are still only a small number of installs so far, it's better than not doing it.
				 */
				if ( $this->version == '0.5.5' ) {
					$plugin_version = '0.5.4';
				} else {
					return false;
				}
			}

			// Do the updates based on previous version

			if ( version_compare( $plugin_version, '0.5.5' ) < 0 ) {

				// Update product meta box and post type id for old products created before
				// the update.

				$posts = get_posts(
					array(
						'posts_per_page' => -1,
						'post_type' => 'product'
					)
				);

				foreach ( $posts as $post ) {
					if ( get_post_meta($post->ID, 'wp_license_manager_product_meta', true) == '' ) {
						$meta = array();
						$meta['file_bucket'] = get_post_meta($post->ID, '_product_file_bucket', true);
						$meta['file_name'] = get_post_meta($post->ID, '_product_file_name', true);
						$meta['version'] = get_post_meta($post->ID, '_product_version', true);
						$meta['tested'] = get_post_meta($post->ID, '_product_tested', true);
						$meta['requires'] = get_post_meta($post->ID, '_product_requires', true);
						$meta['updated'] = get_post_meta($post->ID, '_product_updated', true);
						$meta['banner_low'] = get_post_meta($post->ID, '_product_banner_low', true);
						$meta['banner_high'] = get_post_meta($post->ID, '_product_banner_high', true);

						update_post_meta( $post->ID, 'wp_license_manager_product_meta', $meta );
					}

					// Update post type to a better name
					set_post_type( $post->ID, 'wplm_product' );
				}

			}
		}
	}

}
