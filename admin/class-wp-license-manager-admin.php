<?php

/**
 * The dashboard-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the dashboard-specific stylesheet and JavaScript.
 *
 * @since 1.0.0
 *
 * @package    Wp_License_Manager
 * @subpackage Wp_License_Manager/admin
 * @author     Jarkko Laine <jarkko@jarkkolaine.com>
 */
class Wp_License_Manager_Admin {

	/**
	 * The ID of this plugin.
	 *
     * @since 1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
     * @since 1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
     * @since 1.0.0
	 * @var      string    $plugin_name       The name of this plugin.
	 * @var      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the Dashboard.
     *
     * @since 1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-license-manager-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the dashboard.
     *
     * @since 1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-license-manager-admin.js', array( 'jquery' ), $this->version, false );
	}

    /**
     * Registers a meta box for entering product information. The meta box is
     * shown in the post editor for the "product" post type.
     *
     * @since 1.0.0
     * @param   $post   WP_Post The post object to apply the meta box to
     */
    public function add_product_information_meta_box( $post ) {
        add_meta_box(
            'product-information-meta-box',
            __( 'Product Information', $this->plugin_name ),
            array ( $this, 'render_product_information_meta_box' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Renders the product information meta box for the given post (product).
     *
     * @since 1.0.0
     * @param $post     WP_Post     The WordPress post object being rendered.
     */
    public function render_product_information_meta_box( $post ) {
        wp_nonce_field( $this->plugin_name . '_product_meta_box', $this->plugin_name . '_product_meta_box_nonce' );

        // The data for the meta box fields (rendered in the partial)
        $bucket = get_post_meta( $post->ID, '_product_file_bucket', true );
        $file_name = get_post_meta( $post->ID, '_product_file_name', true );
        $version = get_post_meta( $post->ID, '_product_version', true );
        $tested = get_post_meta( $post->ID, '_product_tested', true );
        $requires = get_post_meta( $post->ID, '_product_requires', true );
        $last_updated = get_post_meta( $post->ID, '_product_updated', true );

        $banner_low = get_post_meta( $post->ID, '_product_banner_low', true );
        $banner_high = get_post_meta( $post->ID, '_product_banner_high', true );

        // Display the form
        require( 'partials/product_meta_box.php' );
    }

    /**
     * Saves the product information meta box contents.
     *
     * @since 1.0.0
     * @param $post_id  int     The id of the post being saved.
     */
    public function save_product_information_meta_box( $post_id ) {
        // Check nonce
        if ( !$this->is_nonce_ok() ) {
            return $post_id;
        }

        // Ignore auto saves
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }

        // Check the user's permissions
        if ( !current_user_can( 'edit_posts', $post_id ) ) {
            return $post_id;
        }

        // Sanitize user input
        $bucket = sanitize_text_field( $_POST['wp_license_manager_product_bucket'] );
        $file_name = sanitize_text_field( $_POST['wp_license_manager_product_file_name'] );
        $version = sanitize_text_field( $_POST['wp_license_manager_product_version'] );

        $tested = sanitize_text_field( $_POST['wp_license_manager_product_tested'] );
        $requires = sanitize_text_field( $_POST['wp_license_manager_product_requires'] );
        $last_updated = sanitize_text_field( $_POST['wp_license_manager_product_updated'] );

        $banner_low = sanitize_text_field( $_POST['wp_license_manager_product_banner_low'] );
        $banner_high = sanitize_text_field( $_POST['wp_license_manager_product_banner_high'] );

        // Update the meta field
        update_post_meta( $post_id, '_product_file_bucket', $bucket );
        update_post_meta( $post_id, '_product_file_name', $file_name );
        update_post_meta( $post_id, '_product_version', $version );
        update_post_meta( $post_id, '_product_tested', $tested );
        update_post_meta( $post_id, '_product_requires', $requires );
        update_post_meta( $post_id, '_product_updated', $last_updated );
        update_post_meta( $post_id, '_product_banner_low', $banner_low );
        update_post_meta( $post_id, '_product_banner_high', $banner_high );
    }

    /**
     * A helper function for checking the product meta box nonce.
     *
     * @since 1.0.0
     * @return mixed False if nonce is not OK. 1 or 2 if nonce is OK (@see wp_verify_nonce)
     */
    private function is_nonce_ok() {
        $nonce_field_name = $this->plugin_name . '_product_meta_box_nonce';
        $nonce_name = $this->plugin_name . '_product_meta_box';

        if ( !isset( $_POST[ $nonce_field_name ] ) ) {
            return false;
        }

        $nonce = $_POST[ $nonce_field_name ];

        return wp_verify_nonce( $nonce, $nonce_name );
    }

    /**
     * Creates the settings menu and sub menus for adding and listing licenses.
     *
     * @since 1.0.0
     */
    public function add_licenses_menu_page() {
        add_menu_page(
            __( 'Licenses', $this->plugin_name ),
            __( 'Licenses', $this->plugin_name ),
            'edit_posts',
            'wp-licenses',
            array( $this, 'do_licenses_menu_list' ),
            'dashicons-lock',
            27 // position
        );

        add_submenu_page('wp-licenses',
            __( 'Licenses', $this->plugin_name ),
            __( 'Licenses', $this->plugin_name ),
            'edit_posts',
            'wp-licenses',
            array( $this, 'do_licenses_menu_list' )
        );

        // add new will be described in next part
        add_submenu_page(
            'wp-licenses',
            __( 'Add new', $this->plugin_name ),
            __( 'Add new', $this->plugin_name ),
            'edit_posts',
            'wp-licenses-new',
            array( $this, 'render_licenses_menu_new' )
        );
    }

    /**
     * Renders the list of licenses menu page using the "licenses_list.php" partial.
     *
     * @since 1.0.0
     */
    public function do_licenses_menu_list() {
        global $wpdb;

        $license_deleted = false;

        // Handle the delete action
        if ( isset($_REQUEST['action'] ) ) {
            if ($_REQUEST['action'] == 'delete') {
                if ( check_admin_referer( 'wp-license-manager-delete-license', 'wp-license-manager-delete-license-nonce' ) ) {

                    // Delete the license
                    $table_name = $wpdb->prefix . 'product_licenses';
                    $wpdb->delete( $table_name, array( 'id' => $_REQUEST['license'] ) );

                    $license_deleted = true;

                }
            }
        }

        $list_table = new Licenses_List_Table( $this->plugin_name );
        $list_table->prepare_items();

        require plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/licenses_list.php';
    }

    /**
     * Renders the list add new license menu page using the "licenses_new.php" partial.
     *
     * @since 1.0.0
     */
    public function render_licenses_menu_new() {
        // Used in the "Product" drop-down list in view
        $products = get_posts(
            array(
                'orderby' 		   => 'post_title',
                'order'            => 'ASC',
                'post_type'        => 'product',
                'post_status'      => 'publish',
                'nopaging'         => true,
                'suppress_filters' => true
            )
        );

        require plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/licenses_new.php';
    }


    /**
     * Handler for the add_license action (submitting the "Add License" form).
     *
     * @since 1.0.0
     */
    public function handle_add_license() {
        global $wpdb;

        if ( ! empty( $_POST )
            && check_admin_referer( 'wp-license-manager-add-license', 'wp-license-manager-add-license-nonce' ) ) {

            // Nonce valid, handle data

            $email = sanitize_text_field( $_POST['email'] );
            $valid_until = sanitize_text_field( $_POST['valid_until'] );
            $product_id = intval( $_POST['product'] );

            $license_key = wp_generate_password( 24, true, false );

            // Save data to database
            $table_name = $wpdb->prefix . 'product_licenses';
            $wpdb->insert(
                $table_name,
                array(
                    'product_id' => $product_id,
                    'email' => $email,
                    'license_key' => $license_key,
                    'valid_until' => $valid_until,
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' )
                ),
                array(
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                )
            );

            // Redirect to the list of licenses for displaying the new license
            wp_redirect( admin_url( 'admin.php?page=wp-licenses' ) );
        }
    }

    /**
     * Adds a link to the plugin settings page (see below) from the plugins page.
     *
     * @since 1.0.0
     *
     * @param $links    array   A list of existing links
     * @return array            Links with the new item added to it.
     */
    public function add_settings_link_to_plugin_list( $links ) {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=wp-license-settings' ) . '">'
            . __( 'Settings', $this->plugin_name ) . '</a>';

        array_push( $links, $settings_link );
        return $links;
    }

    /**
     * Adds an options page for plugin settings.
     *
     * @since 1.0.0
     */
    public function add_plugin_settings_page() {
        add_options_page(
            __('License Manager', $this->plugin_name ),
            __('License Manager Settings', $this->plugin_name ),
            'manage_options',
            'wp-license-settings',
            array ($this, 'render_settings_page' )
        );
    }

    /**
     * Creates the settings fields for the plugin options page.
     *
     * @since 1.0.0
     */
    public function add_plugin_settings_fields() {
        $settings_group_id = 'wp-license-manager-settings-group';
        $aws_settings_section_id = 'wp-license-manager-settings-section-aws';
        $settings_field_id = 'wp-license-manager-settings';

        register_setting( $settings_group_id, $settings_field_id );

        add_settings_section(
            $aws_settings_section_id,
            __( 'Amazon Web Services', $this->plugin_name ),
            array( $this, 'render_aws_settings_section' ),
            $settings_group_id
        );

        add_settings_field(
            'aws-key',
            __( 'AWS public key', $this->plugin_name ),
            array( $this, 'render_aws_key_settings_field' ),
            $settings_group_id,
            $aws_settings_section_id
        );

        add_settings_field(
            'aws-secret',
            __( 'AWS secret', $this->plugin_name ),
            array( $this, 'render_aws_secret_settings_field' ),
            $settings_group_id,
            $aws_settings_section_id
        );
    }

    /**
     * Renders the plugin's options page.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        $settings_group_id = 'wp-license-manager-settings-group';
        require plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/settings_page.php';
    }

    /**
     * Renders the description for the AWS settings section.
     *
     * @since 1.0.0
     */
    public function render_aws_settings_section() {
        // We use a partial here to make it easier to add more complex instructions
        require plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/aws_settings_group_instructions.php';
    }

    /**
     * Renders the settings field for the AWS key.
     *
     * @since 1.0.0
     */
    public function render_aws_key_settings_field() {
        $settings_field_id = 'wp-license-manager-settings';
        $options = get_option( $settings_field_id );
        ?>
            <input type='text' name='<?php echo $settings_field_id; ?>[aws_key]' value='<?php echo $options['aws_key']; ?>' class='regular-text'>
        <?php
    }

    /**
     * Renders the settings field for the AWS secret.
     *
     * @since 1.0.0
     */
    public function render_aws_secret_settings_field() {
        $settings_field_id = 'wp-license-manager-settings';
        $options = get_option( $settings_field_id );
        ?>
           <input type='text' name='<?php echo $settings_field_id; ?>[aws_secret]' value='<?php echo $options['aws_secret']; ?>' class='regular-text'>
        <?php
    }

    /**
     * If the plugin hasn't been configured properly, display a notice.
     *
     * @since 1.0.0
     */
    public function show_admin_notices() {
        $options = get_option('wp-license-manager-settings');

        if ( ! $options || ! isset( $options['aws_key'] ) || ! isset( $options['aws_secret'] ) ||
            $options['aws_key'] == '' || $options['aws_secret'] == '' ) {

            require plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/settings_nag.php';
        }
    }

}