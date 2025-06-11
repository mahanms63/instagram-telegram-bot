<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Add the plugin settings page to the WordPress admin menu.
 */
function aipw_add_settings_page_menu() {
    add_options_page(
        __( 'AI Product Writer Settings', 'ai-product-writer' ), // Page title
        __( 'AI Product Writer', 'ai-product-writer' ),        // Menu title
        'manage_options',                                     // Capability required
        'ai-product-writer-settings',                         // Menu slug
        'aipw_render_settings_page'                           // Function to display the page
    );
}
add_action( 'admin_menu', 'aipw_add_settings_page_menu' );

/**
 * Render the HTML for the plugin settings page.
 */
function aipw_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'aipw_settings_group' ); // Settings group name
            do_settings_sections( 'ai-product-writer-settings' ); // Page slug
            submit_button( __( 'Save Settings', 'ai-product-writer' ) );
            ?>
        </form>
    </div>
    <?php
}

/**
 * Register plugin settings, sections, and fields.
 */
function aipw_register_settings() {
    // Register a setting group
    register_setting(
        'aipw_settings_group',                 // Option group. This is used in settings_fields()
        'aipw_options',                        // Option name. This is where the data is stored in wp_options
        'aipw_sanitize_options'                // Sanitization callback
    );

    // Add a settings section
    add_settings_section(
        'aipw_general_settings_section',       // ID
        __( 'API Settings', 'ai-product-writer' ), // Title
        'aipw_general_settings_section_callback', // Callback function for the section description
        'ai-product-writer-settings'          // Page slug where this section will be displayed
    );

    // Add the API Key field
    add_settings_field(
        'aipw_api_key',                        // ID
        __( 'AI API Key', 'ai-product-writer' ), // Title
        'aipw_api_key_field_callback',         // Callback function to render the field
        'ai-product-writer-settings',          // Page slug
        'aipw_general_settings_section',       // Section ID
        array( 'label_for' => 'aipw_api_key_field' ) // Arguments array
    );
}
add_action( 'admin_init', 'aipw_register_settings' );

/**
 * Callback for the general settings section description.
 */
function aipw_general_settings_section_callback() {
    echo '<p>' . esc_html__( 'Enter your API key for the AI content generation service.', 'ai-product-writer' ) . '</p>';
}

/**
 * Callback to render the API Key input field.
 */
function aipw_api_key_field_callback() {
    $options = get_option( 'aipw_options' );
    $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
    ?>
    <input type="text" id="aipw_api_key_field" name="aipw_options[api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
    <p class="description"><?php esc_html_e( 'Your API key for accessing the AI service (e.g., OpenAI).', 'ai-product-writer' ); ?></p>
    <?php
}

/**
 * Sanitize the plugin options before saving.
 *
 * @param array $input The input options.
 * @return array The sanitized options.
 */
function aipw_sanitize_options( $input ) {
    $sanitized_input = array();
    if ( isset( $input['api_key'] ) ) {
        $sanitized_input['api_key'] = sanitize_text_field( $input['api_key'] );
    }
    // Add more sanitization for other options in the future
    return $sanitized_input;
}
?>
