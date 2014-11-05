<?php

use Aws\S3\S3Client;

/**
 * The API handler for handling API requests from themes and plugins using
 * the license manager.
 *
 * @since      1.0.0
 * @package    Wp_License_Manager
 * @subpackage Wp_License_Manager/public
 * @author     Jarkko Laine <jarkko@jarkkolaine.com>
 */
class License_Manager_API {

    /**
     * The handler function that receives the API calls and passes them on to the
     * proper handlers.
     *
     * @since 1.0.0
     * @param $action   string  The name of the action
     * @param $params   array   Request parameters
     */
    public function handle_request( $action, $params ) {
        switch ( $action ) {
            case 'info':
                $response = $this->verify_license_and_execute( 'info', $params );
                break;

            case 'get':
                $response = $this->verify_license_and_execute( 'get', $params );
                break;

            default:
                $response = $this->error_response( 'No such API action' );
                break;
        }

        $this->send_response( $response );
    }

    /**
     * Returns a list of variables used by the API
     *
     * @since   1.0.0
     * @return  array    An array of query variable names.
     */
    public function get_api_vars() {
        return array( 'l',  'e', 'p' );
    }

    //
    // API HANDLER FUNCTIONS
    //

    /**
     * Checks the parameters and verifies the license, then forwards the request to the
     * actual API request handlers.
     *
     * @param $action       string  The API action to perform.
     * @param $params       array   The WordPress request parameters.
     * @return array        API response.
     */
    private function verify_license_and_execute( $action, $params ) {
        if ( ! isset( $params['p'] ) || ! isset( $params['e'] ) || ! isset( $params['l'] )) {
            return $this->error_response( 'Invalid request' );
        }

        $product_id = $params['p'];
        $email = $params['e'];
        $license_key = $params['l'];

        // Find product
        $posts = get_posts(
            array (
                'name' => $product_id,
                'post_type' => 'product',
                'post_status' => 'publish',
                'numberposts' => 1
            )
        );

        if ( ! isset( $posts[0] ) ) {
            return $this->error_response( 'Product not found.' );
        }

        // Verify license
        if ( !$this->verify_license( $posts[0]->ID, $email, $license_key ) ) {
            return $this->error_response( 'Invalid license or license expired.' );
        }

        // With parameters and license verified, it's time to move to the actual API handler
        $response = $this->error_response( 'Error executing API action.' );
        switch ( $action ) {
            case 'info':
                $response = $this->product_info( $product_id, $posts[0], $email, $license_key );
                break;

            case 'get':
                $response = $this->get_product( $posts[0] );
                break;

            default:
                break;
        }

        return $response;
    }

    /**
     * The handler for the "info" request. Checks the user's license information and
     * returns information about the product (latest version, name, update url).
     *
     * @since   1.0.0
     * @param   $product_id     string    The product id (slug)
     * @param   $product        WP_Post   The product object
     * @param   $email          string    The email address associated with the license
     * @param   $license_key    string  The license key associated with the license
     *
     * @return  array           The API response as an array.
     */
    private function product_info( $product_id, $product, $email, $license_key ) {
        // Collect all the metadata we have and return it to the caller
        $version = get_post_meta( $product->ID, '_product_version', true );
        $tested = get_post_meta( $product->ID, '_product_tested', true );
        $last_updated = get_post_meta( $product->ID, '_product_updated', true );
        $author = get_post_meta( $product->ID, '_product_author', true );
        $banner_low = get_post_meta( $product->ID, '_product_banner_low', true );
        $banner_high = get_post_meta( $product->ID, '_product_banner_high', true );

        return array(
            'name' => $product->post_title,
            'description' => $product->post_content,
            'version' => $version,
            'tested' => $tested,
            'author' => $author,
            'last_updated' => $last_updated,
            'banner_low' => $banner_low,
            'banner_high' => $banner_high,
            "package_url" => home_url( '/api/license-manager/get?p=' . $product_id . '&e=' . $email . '&l=' . urlencode( $license_key ) ),
            "description_url" => get_permalink( $product->ID ) . '#v=' . $version
        );
    }

    /**
     * The handler for the "get" request. Checks the user's license information and
     * redirects to a file download if all is OK.
     *
     * @since   1.0.0
     * @param   $product    WP_Post     The product object
     */
    private function get_product( $product ) {
        // Get the AWS data from post meta fields
        $bucket = get_post_meta( $product->ID, '_product_file_bucket', true);
        $file_name = get_post_meta( $product->ID, '_product_file_name', true);

        // Use the AWS API to set up the download
        require_once plugin_dir_path( dirname( __FILE__ ) ) .'lib/aws/aws-autoloader.php';

        // Instantiate the S3 client with stored AWS credentials
        $options = get_option( 'wp-license-manager-settings' );
        $s3_client = S3Client::factory(
            array(
                'key'    => $options['aws_key'],
                'secret' => $options['aws_secret']
            )
        );

        $s3_url = $s3_client->getObjectUrl($bucket, $file_name, '+10 minutes');

        // This API method is called directly by WordPress so we need to adhere to its
        // requirements and skip the JSON. WordPress expects to receive a ZIP file...

        wp_redirect( $s3_url, 302 );
    }

    //
    // HELPER FUNCTIONS
    //

    /**
     * Looks up a license that matches the given parameters.
     *
     * @since 1.0.0
     *
     * @param $product_id   int     The numeric ID of the product.
     * @param $email        string  The email address attached to the license.
     * @param $license_key  string  The license key
     * @return mixed                The license data if found. Otherwise false.
     */
    private function find_license( $product_id, $email, $license_key ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'product_licenses';

        $licenses = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table_name WHERE product_id = %d AND email = '%s' AND license_key = '%s'",
                $product_id, $email, $license_key ), ARRAY_A);

        if ( count( $licenses ) > 0 ) {
            return $licenses[0];
        }

        return false;
    }

    /**
     * Checks whether a license with the given parameters exists and is still valid.
     *
     * @since 1.0.0
     *
     * @param $product_id   int     The numeric ID of the product.
     * @param $email        string  The email address attached to the license.
     * @param $license_key  string  The license key.
     * @return bool                 true if license is valid. Otherwise false.
     */
    private function verify_license( $product_id, $email, $license_key ) {
        $license = $this->find_license( $product_id, $email, $license_key );
        if ( ! $license ) {
            return false;
        }

        $valid_until = strtotime( $license['valid_until'] );
        if ( $license['valid_until'] != '0000-00-00 00:00:00' && time() > $valid_until ) {
            return false;
        }

        return true;
    }

    /**
     * Generates and returns a simple error response. Used to make sure every error
     * message uses same formatting.
     *
     * @since 1.0.0
     *
     * @param $msg      string  The message to be included in the error response.
     * @return array    The error response as an array that can be passed to send_response.
     */
    private function error_response( $msg ) {
        return array( "error" => $msg );
    }

    /**
     * Prints out the JSON response for an API call.
     *
     * @since 1.0.0
     *
     * @param $response array   The response as associative array.
     */
    private function send_response( $response ) {
        echo json_encode($response) . '\n';
    }

}