<?php
/**
 * Plugin Name: AI Product Writer
 * Plugin URI: https://example.com/plugins/ai-product-writer/
 * Description: Automatically generates product descriptions, SEO meta tags, and keywords using AI for WooCommerce products.
 * Version: 0.1.0
 * Author: Your Name / Your Company
 * Author URI: https://example.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-product-writer
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'AIPW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIPW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIPW_VERSION', '0.1.0' );

require_once AIPW_PLUGIN_DIR . 'includes/generator.php';
require_once AIPW_PLUGIN_DIR . 'includes/settings-page.php';

/**
 * The code that runs during plugin activation.
 */
function activate_ai_product_writer() {
    // Activation code here.
}
register_activation_hook( __FILE__, 'activate_ai_product_writer' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ai_product_writer() {
    // Deactivation code here.
}
register_deactivation_hook( __FILE__, 'deactivate_ai_product_writer' );

/**
 * Add "Generate Content with AI" button to product edit page.
 */
function aipw_add_generate_button() {
    global $post;
    // Check if the current post is a product
    if ( $post && $post->post_type == 'product' ) {
        ?>
        <div class="misc-pub-section">
            <button type="button" id="aipw-generate-button" class="button">
                <?php esc_html_e( 'Generate Content with AI', 'ai-product-writer' ); ?>
            </button>
        </div>
        <?php
    }
}
add_action( 'post_submitbox_misc_actions', 'aipw_add_generate_button' );

/**
 * Enqueue scripts and styles for the admin product page.
 */
function aipw_admin_enqueue_scripts( $hook_suffix ) {
    global $post_type;

    // Only load on product edit screens (post.php for editing, post-new.php for adding new)
    if ( ( $hook_suffix == 'post.php' || $hook_suffix == 'post-new.php' ) && $post_type == 'product' ) {
        wp_enqueue_style(
            'aipw-admin-styles',
            AIPW_PLUGIN_URL . 'assets/style.css',
            array(),
            AIPW_VERSION
        );

        wp_enqueue_script(
            'aipw-admin-script',
            AIPW_PLUGIN_URL . 'assets/script.js',
            array( 'jquery' ), // Add dependencies like jQuery if needed
            AIPW_VERSION,
            true // Load in footer
        );

        wp_localize_script(
            'aipw-admin-script',
            'aipw_ajax_object',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'aipw_generate_content_nonce' )
            )
        );
    }
}
add_action( 'admin_enqueue_scripts', 'aipw_admin_enqueue_scripts' );

/**
 * Handles the AJAX request for generating product content.
 */
function aipw_handle_generate_content_ajax() {
    // 1. Verify the nonce
    check_ajax_referer( 'aipw_generate_content_nonce', '_ajax_nonce' );

    // 2. Get and sanitize product data
    $raw_product_data = isset( $_POST['product_data'] ) ? (array) $_POST['product_data'] : array();
    $sanitized_product_data = array();

    $sanitized_product_data['title'] = isset( $raw_product_data['title'] ) ? sanitize_text_field( $raw_product_data['title'] ) : '';
    // For content, wp_kses_post could be used if it's expected to be HTML.
    // However, since it's input to an AI, an un-kses'd version might be better,
    // with sanitization happening on output. For now, let's use sanitize_textarea_field for safety.
    $sanitized_product_data['content'] = isset( $raw_product_data['content'] ) ? sanitize_textarea_field( $raw_product_data['content'] ) : '';
    $sanitized_product_data['excerpt'] = isset( $raw_product_data['excerpt'] ) ? sanitize_textarea_field( $raw_product_data['excerpt'] ) : '';
    $sanitized_product_data['regular_price'] = isset( $raw_product_data['regular_price'] ) ? sanitize_text_field( $raw_product_data['regular_price'] ) : '';

    $sanitized_product_data['category'] = isset( $raw_product_data['category'] ) ? sanitize_text_field( $raw_product_data['category'] ) : '';

    $sanitized_product_data['attributes'] = array();
    if ( isset( $raw_product_data['attributes'] ) && is_array( $raw_product_data['attributes'] ) ) {
        foreach ( $raw_product_data['attributes'] as $attr_name => $attr_values ) {
            $sanitized_name = sanitize_text_field( $attr_name );
            $sanitized_values = array();
            if (is_array($attr_values)) {
                foreach ($attr_values as $value) {
                    $sanitized_values[] = sanitize_text_field( $value );
                }
            }
            if (!empty($sanitized_name) && !empty($sanitized_values)) {
                $sanitized_product_data['attributes'][ $sanitized_name ] = $sanitized_values;
            }
        }
    }

    // Basic check if we have at least a title or content to work with
    if ( empty( $sanitized_product_data['title'] ) && empty( $sanitized_product_data['content'] ) ) {
        wp_send_json_error( ['message' => 'Product title or content is required to generate content.'] );
    }

    // 3. Instantiate generator and generate content
    // Ensure AIPW_Generator class is available (it should be due to the require_once at the top)
    if ( ! class_exists( 'AIPW_Generator' ) ) {
        wp_send_json_error( ['message' => 'Content generator class (AIPW_Generator) not found. Plugin file structure issue?'] );
    }
    $generator = new AIPW_Generator();
    $generated_content = $generator->generate_content( $sanitized_product_data );

    // 4. Send response
    if ( !empty($generated_content) && is_array($generated_content) ) {
        wp_send_json_success( $generated_content );
    } else {
        wp_send_json_error( ['message' => 'Failed to generate content or content was empty.'] );
    }

    // wp_send_json_success and wp_send_json_error automatically call wp_die()
}
add_action( 'wp_ajax_aipw_generate_product_content', 'aipw_handle_generate_content_ajax' );
// For admin-only functionality, 'wp_ajax_nopriv_' is not strictly needed.


/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
// require plugin_dir_path( __FILE__ ) . 'includes/class-ai-product-writer.php';

/**
 * Begins execution of the plugin.
 */
// function run_ai_product_writer() {
//     $plugin = new Ai_Product_Writer();
//     $plugin->run();
// }
// run_ai_product_writer();

?>
