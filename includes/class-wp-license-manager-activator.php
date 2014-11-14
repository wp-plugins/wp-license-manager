<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    Wp_License_Manager
 * @subpackage Wp_License_Manager/includes
 * @author     Jarkko Laine <jarkko@jarkkolaine.com>
 */
class Wp_License_Manager_Activator {

    /**
     * The database version number. Update this every time you make a change to the database structure.
     *
     * @access   protected
     * @var      string    $db_version   The database version number
     */
    protected static $db_version = 1;

    /**
     * The version number for the product post type data. This was needed because we gave the post type
     * a bad name at first (easily colliding with other plugins). This should never go higher than 1.
     *
     * @access   protected
     * @var      string    $products_version   The version of the products post type
     */
    protected static $products_version = 1;

    /**
     * Code that is run at plugin activation.
     *
     * Creates or updates the database structure required by the plugin and does other
     * data initialization
	 */
	public static function activate() {
        // Get some version numbers
        $current_products_version = get_option( 'wp-license-manager-products-version' );
        if ( ! $current_products_version ) {
            $current_products_version = 0;
        }
        $current_products_version = intval( $current_products_version );

        $current_db_version = get_option( 'wp-license-manager-db-version' );
        if ( ! $current_db_version ) {
            $current_db_version = 0;
        }
        $current_db_version = intval( $current_db_version );

        // If the user has created products using an old meta box structure, update data
        if ( $current_products_version < Wp_License_Manager_Activator::$products_version ) {
            // If this is a clean installation (DB version still 0, don't do anything.
            // The user cannot have added products and therefore any product type posts are
            // from other plugins...
            if ( $current_db_version > 0 ) {
                $posts = get_posts(
                    array(
                        'posts_per_page' => -1,
                        'post_type' => 'product'
                    )
                );
                foreach ($posts as $post) {
                    if (get_post_meta($post->ID, 'wp_license_manager_product_meta', true) == '') {
                        $meta = array();
                        $meta['file_bucket'] = get_post_meta($post->ID, '_product_file_bucket', true);
                        $meta['file_name'] = get_post_meta($post->ID, '_product_file_name', true);
                        $meta['version'] = get_post_meta($post->ID, '_product_version', true);
                        $meta['tested'] = get_post_meta($post->ID, '_product_tested', true);
                        $meta['requires'] = get_post_meta($post->ID, '_product_requires', true);
                        $meta['updated'] = get_post_meta($post->ID, '_product_updated', true);
                        $meta['banner_low'] = get_post_meta($post->ID, '_product_banner_low', true);
                        $meta['banner_high'] = get_post_meta($post->ID, '_product_banner_high', true);

                        update_post_meta($post->ID, 'wp_license_manager_product_meta', $meta);
                    }

                    // Update post type to a better name
                    set_post_type($post->ID, 'wplm_product');
                }
            }

            update_option( 'wp-license-manager-products-version', Wp_License_Manager_Activator::$products_version );
        }

        // Update database if db version has increased
        if ( intval( $current_db_version ) < Wp_License_Manager_Activator::$db_version ) {
            if ( Wp_License_Manager_Activator::create_or_upgrade_db() ) {
                update_option( 'wp-license-manager-db-version', Wp_License_Manager_Activator::$db_version );
            }
        }
	}

    /**
     * Creates the database tables required for the plugin if
     * they don't exist. Otherwise updates them as needed.
     *
     * @return bool true if update was successful.
     */
    private static function create_or_upgrade_db() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'product_licenses';

        $charset_collate = '';
        if ( ! empty( $wpdb->charset ) ) {
            $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
        }
        if ( ! empty( $wpdb->collate ) ) {
            $charset_collate .= " COLLATE {$wpdb->collate}";
        }

        $sql = "CREATE TABLE " . $table_name . "("
		     . "id mediumint(9) NOT NULL AUTO_INCREMENT, "
             . "product_id mediumint(9) DEFAULT 0 NOT NULL,"
             . "license_key varchar(48) NOT NULL, "
             . "email varchar(48) NOT NULL, "
		     . "valid_until datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, "
             . "created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, "
             . "updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, "
		     . "UNIQUE KEY id (id)"
	         . ")" . $charset_collate. ";";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        return true;
    }

}
