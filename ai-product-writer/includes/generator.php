<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPW_Generator {

    private $api_key;

    public function __construct() {
        $options = get_option( 'aipw_options' );
        $this->api_key = isset( $options['api_key'] ) ? $options['api_key'] : null;
    }

    /**
     * Generates product content based on product data using OpenAI API.
     *
     * @param array $product_data Associative array of product information.
     *                            Expected keys: 'title', 'category', 'content' (current description), 'regular_price'.
     * @return array Associative array of generated content or an error array.
     *               Keys on success: 'full_description', 'short_description', 'seo_meta_title', 'seo_meta_description', 'tags'.
     *               Keys on error: 'error' (true), 'message' (string), optionally 'details'.
     */
    public function generate_content( $product_data ) {
        if ( empty( $this->api_key ) ) {
            return [
                'error'   => true,
                'message' => __( 'AI API Key is missing. Please configure it in the plugin settings.', 'ai-product-writer' ),
                // Fallback to mocked data for UI testing if API key is missing
                'full_description'    => "API KEY MISSING - This is a wonderfully mocked full product description for the product titled \"{$product_data['title']}\".",
                'short_description'   => "API KEY MISSING - A concise and mocked short description for \"{$product_data['title']}\".",
                'seo_meta_title'      => "API KEY MISSING - Mocked SEO Title: {$product_data['title']}",
                'seo_meta_description'=> "API KEY MISSING - This is a compelling, mocked SEO meta description for {$product_data['title']}.",
                'tags'                => ['mock_tag', 'api_key_missing']
            ];
        }

        $title = isset( $product_data['title'] ) && !empty($product_data['title']) ? $product_data['title'] : 'Untitled Product'; // Already sanitized in AJAX handler
        $current_description = isset( $product_data['content'] ) ? wp_strip_all_tags( $product_data['content'] ) : ''; // Strip tags for AI processing
        $price = isset( $product_data['regular_price'] ) && !empty($product_data['regular_price']) ? $product_data['regular_price'] : null; // Already sanitized

        $category = isset( $product_data['category'] ) && !empty($product_data['category']) ? $product_data['category'] : ''; // Already sanitized
        $attributes = isset( $product_data['attributes'] ) && is_array($product_data['attributes']) ? $product_data['attributes'] : []; // Already sanitized

        $prompt = "You are an expert WooCommerce product content writer. Based on the following product details:\n";
        $prompt .= "Product Title: " . $title . "\n";
        if (!empty($category)) $prompt .= "Category: " . $category . "\n";
        if (!empty($current_description)) $prompt .= "Current Description (for reference, improve or rewrite based on other details provided; keep it if it's good and just add other details): " . substr($current_description, 0, 300) . "...\n";
        if ($price) $prompt .= "Price: " . $price . "\n";

        if (!empty($attributes)) {
            $prompt .= "Product Attributes:\n";
            foreach ($attributes as $name => $values) {
                $prompt .= "- " . esc_html($name) . ": " . esc_html(implode(', ', $values)) . "\n";
            }
        }

        $prompt .= "\nPlease generate the following for this product:\n";
        $prompt .= "1. A compelling and detailed full product description (around 200-300 words, use HTML for paragraphs if appropriate).\n";
        $prompt .= "2. A concise short product description (around 25-40 words or 150-200 characters).\n";
        $prompt .= "3. An SEO-optimized meta title (around 50-60 characters).\n";
        $prompt .= "4. An SEO-optimized meta description (around 120-150 characters).\n";
        $prompt .= "5. A list of 3-5 relevant product tags (keywords).\n\n";
        $prompt .= "Return your response as a single, minified JSON object string with the following keys: 'full_description', 'short_description', 'seo_meta_title', 'seo_meta_description', 'tags' (where 'tags' is an array of strings). Example: {\"full_description\":\"...\",\"short_description\":\"...\",\"seo_meta_title\":\"...\",\"seo_meta_description\":\"...\",\"tags\":[\"tag1\",\"tag2\"]}";

        $api_url = 'https://api.openai.com/v1/chat/completions';
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type'  => 'application/json',
        ];
        $body = [
            'model'    => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant that provides complete product information in a single minified JSON object string as requested.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens'  => 2000, // Adjusted for potentially longer content
        ];

        $response = wp_remote_post( $api_url, [
            'method'    => 'POST',
            'headers'   => $headers,
            'body'      => json_encode( $body ),
            'timeout'   => 90, // Increased timeout for AI generation (was 60)
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            return ['error' => true, 'message' => 'API request failed: ' . $response->get_error_message()];
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $decoded_body = json_decode( $response_body, true );

        if ( $response_code !== 200 ) {
            $error_message = isset($decoded_body['error']['message']) ? $decoded_body['error']['message'] : 'Unknown API error.';
            return ['error' => true, 'message' => "API Error ({$response_code}): " . $error_message, 'details' => $decoded_body];
        }

        if ( !isset($decoded_body['choices'][0]['message']['content']) ) {
            return ['error' => true, 'message' => 'Unexpected API response format: Missing content.', 'details' => $decoded_body];
        }

        // The AI response is expected to be a JSON string, so we need to decode it.
        $ai_generated_json_string = $decoded_body['choices'][0]['message']['content'];
        // Sometimes the AI might wrap the JSON in backticks if it includes it in a sentence.
        $ai_generated_json_string = trim($ai_generated_json_string, " \n\r\t\v\0`");
        // Further attempt to clean if JSON is embedded within text, e.g. "Here is the JSON: {...}""
        if (strpos($ai_generated_json_string, '{') !== false && strrpos($ai_generated_json_string, '}') !== false) {
            $json_start = strpos($ai_generated_json_string, '{');
            $json_end = strrpos($ai_generated_json_string, '}');
            if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
                $ai_generated_json_string = substr($ai_generated_json_string, $json_start, ($json_end - $json_start + 1));
            }
        }


        $generated_data = json_decode( $ai_generated_json_string, true );

        if ( json_last_error() !== JSON_ERROR_NONE || !is_array($generated_data) ) {
            return [
                'error' => true,
                'message' => 'Failed to decode JSON from AI response or it is not an array. JSON Error: ' . json_last_error_msg(),
                'raw_response' => $ai_generated_json_string,
                'full_api_response_body' => $decoded_body // For debugging what the API returned overall
            ];
        }

        $required_keys = ['full_description', 'short_description', 'seo_meta_title', 'seo_meta_description', 'tags'];
        foreach ($required_keys as $key) {
            if (!isset($generated_data[$key])) {
                return [
                    'error' => true,
                    'message' => "AI response JSON missing expected key: '{$key}'.",
                    'decoded_json' => $generated_data, // Show what was decoded
                    'raw_response' => $ai_generated_json_string
                ];
            }
        }
        if (!is_array($generated_data['tags'])) {
             return [
                'error' => true,
                'message' => "AI response 'tags' key is not an array.",
                'decoded_json' => $generated_data,
                'raw_response' => $ai_generated_json_string
            ];
        }

        // Sanitize output from AI before returning
        $sanitized_generated_data = [];
        // For full_description, allow common HTML tags for product descriptions.
        $allowed_html_for_desc = array(
            'p' => array(), 'br' => array(), 'strong' => array(), 'em' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(), 'h2'=>array(), 'h3'=>array(), 'h4'=>array()
        );
        $sanitized_generated_data['full_description'] = isset($generated_data['full_description']) ? wp_kses($generated_data['full_description'], $allowed_html_for_desc) : '';
        $sanitized_generated_data['short_description'] = isset($generated_data['short_description']) ? sanitize_textarea_field($generated_data['short_description']) : '';
        $sanitized_generated_data['seo_meta_title'] = isset($generated_data['seo_meta_title']) ? sanitize_text_field($generated_data['seo_meta_title']) : '';
        $sanitized_generated_data['seo_meta_description'] = isset($generated_data['seo_meta_description']) ? sanitize_textarea_field($generated_data['seo_meta_description']) : '';
        $sanitized_generated_data['tags'] = isset($generated_data['tags']) && is_array($generated_data['tags']) ? array_map('sanitize_text_field', $generated_data['tags']) : [];


        return $sanitized_generated_data;
    }
}
?>
