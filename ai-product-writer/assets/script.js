jQuery(document).ready(function($) {
    var $generateButton = $('#aipw-generate-button');

    // If the button doesn't exist on the page, do nothing further
    if (!$generateButton.length) {
        console.log('AI Product Writer: Generate button not found on this page.');
        return;
    }

    $generateButton.on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalButtonText = $button.html();
        $button.html('Generating...').prop('disabled', true);

        // Attempt to get content from TinyMCE editor (classic editor)
        var productContentValue = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            productContentValue = tinymce.get('content').getContent();
        } else {
            // Fallback for plain textarea (e.g., quick edit or other scenarios)
            productContentValue = $('#content').val();
        }

        // Gather product data
        var productData = {
            title: $('#title').val() || '', // Product title
            content: productContentValue,    // Main content (description)
            excerpt: $('#excerpt').val() || '', // Short description
            // Future: Collect category, tags, attributes, etc.
            // Example: category: $('#product_catchecklist :checked').first().parent().text().trim() || ''
            // For now, we'll keep it simple as the PHP mock doesn't use much more
            regular_price: $('#_regular_price').val() || '',
        };

     // Enhanced data collection for category and attributes
     var productAttributes = {};
     // Standard WooCommerce attributes section
     $('#product_attributes .woocommerce_attribute').each(function() {
         var attributeName = $(this).find('label').first().text().trim(); // Use .first() to avoid nested labels
         var attributeValues;

         // Check for select-based (taxonomy) attributes
         var $selectElement = $(this).find('select.attribute_values');
         if ($selectElement.length) {
             attributeValues = $selectElement.val(); // This can be an array if multi-select is enabled
         } else {
             // Check for text-based (custom) attributes
             var $textareaElement = $(this).find('textarea');
             if ($textareaElement.length) {
                 attributeValues = $textareaElement.val().split('|').map(s => s.trim()).filter(Boolean);
             }
         }

         if (attributeName && attributeValues && ( (Array.isArray(attributeValues) && attributeValues.length > 0) || (!Array.isArray(attributeValues) && attributeValues) ) ) {
             productAttributes[attributeName] = Array.isArray(attributeValues) ? attributeValues : [attributeValues];
         }
     });

    // If the above doesn't work (e.g. for attributes managed by "Product Add-ons Ultimate" or similar plugins)
    // let's try to get attributes from the "Attributes" tab for variable products specifically.
    if (Object.keys(productAttributes).length === 0) {
        $('div#variable_product_options div.woocommerce_variable_attributes div.variable_attribute').each(function() {
            var attributeName = $(this).find('h3 span.attribute_name').text().trim();
            var attributeValues = $(this).find('ul.select2-selection__rendered li.select2-selection__choice').map(function() {
                return $(this).attr('title');
            }).get();

            if (attributeName && attributeValues && attributeValues.length > 0) {
                 // Attempt to remove the "Remove this item" title from values if present (for some select2 versions)
                attributeValues = attributeValues.map(val => val.replace(/×$/, '').trim()).filter(Boolean);
                if (attributeValues.length > 0) {
                    productAttributes[attributeName] = attributeValues;
                }
            }
        });
    }


     productData.category = $('select#product_cat option:selected, input[name="tax_input[product_cat][]"]:checked').map(function() {
                              var term = $(this).text().trim();
                              // For hierarchical categories, the text might include parent categories like "Parent > Child"
                              // We might only want the most specific part, or the AI might handle it.
                              // For now, we take the full text provided by WP.
                              return term;
                           }).get().join(', ') || '';
     productData.attributes = productAttributes;

        console.log('AI Product Writer: Sending data to AJAX:', productData);

        $.ajax({
            url: aipw_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'aipw_generate_product_content', // This needs a corresponding wp_ajax_ hook in PHP
                _ajax_nonce: aipw_ajax_object.nonce,
                product_data: productData
            },
            success: function(response) {
    if(response && typeof response === 'object' && response.hasOwnProperty('success')) {
        if(response.success && response.data) {
            console.log('AI Product Writer: Populating fields with:', response.data);

            // 1. Main Product Description
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                tinymce.get('content').setContent(response.data.full_description || '');
            } else {
                $('#content').val(response.data.full_description || '');
            }

            // 2. Short Product Description
            $('#excerpt').val(response.data.short_description || '');

            // 3. SEO Meta Title (Attempt Yoast)
            var $yoastTitle = $('#yoast_wpseo_title');
            if ($yoastTitle.length) {
                $yoastTitle.val(response.data.seo_meta_title || '');
            } else {
                console.log('AI Product Writer: Yoast SEO title field not found. Suggested title:', response.data.seo_meta_title);
            }

            // 4. SEO Meta Description (Attempt Yoast)
            var $yoastDesc = $('#yoast_wpseo_metadesc');
            if ($yoastDesc.length) {
                $yoastDesc.val(response.data.seo_meta_description || '');
            } else {
                console.log('AI Product Writer: Yoast SEO description field not found. Suggested description:', response.data.seo_meta_description);
            }

            // 5. Product Tags
            if (response.data.tags && Array.isArray(response.data.tags) && response.data.tags.length > 0) {
                var $tagInput = $('#new-tag-post_tag');
                var $addButton = $tagInput.siblings('.button.tagadd, .button.button-secondary.tagadd, input.button.tagadd');

                if ($tagInput.length && $addButton.length) {
                    response.data.tags.forEach(function(tag) {
                        $tagInput.val(tag);
                        $addButton.click();
                    });
                    $tagInput.val('');
                } else {
                    console.warn('AI Product Writer: Product tag input field (#new-tag-post_tag) or add button not found. Suggested tags:', response.data.tags.join(', '));
                    alert('Product fields populated. Suggested tags for manual entry (see console): ' + response.data.tags.join(', '));
                }
            }

            alert('Product fields populated with AI-generated content! Please review and save your product.');

        } else {
            var errorMessage = 'Error: Content generation failed or no data received.';
            if (response.data && response.data.message) {
                errorMessage = 'Error: ' + response.data.message;
            } else if (response.data && typeof response.data === 'string') {
                errorMessage = 'Error: ' + response.data;
            }
            console.error('AI Product Writer: Server error or no data:', response);
            alert(errorMessage);
        }
    } else {
         console.error('AI Product Writer: Invalid AJAX response format:', response);
         alert('Error: Received an invalid response from the server. Check console.');
    }
},
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AI Product Writer: AJAX Error - Status:', textStatus, 'Error:', errorThrown);
                console.error('AI Product Writer: Response Text:', jqXHR.responseText); // Log the full response text
                alert('AJAX request failed: ' + textStatus + '. Check console for more details.');
            },
            complete: function() {
                $button.html(originalButtonText).prop('disabled', false);
            }
        });
    });
});
