<?php
/*
Plugin Name: Shy's Tutorials & Handbooks
Description: Create, manage, and restrict access to tutorials and handbooks, with features for manual user assignment and private content sharing.
Version: 1.2
License: GPLv2
Author: HackTheDev
*/

require_once plugin_dir_path(__FILE__) . 'PayPalLibrary.php';


// Add a meta box for marking a tutorial as paid/locked
function thp_add_paid_meta_box() {
    add_meta_box(
        'thp_paid_meta_box',
        'Paid Tutorial',
        'thp_paid_meta_box_callback',
        'tutorial_handbook',
        'side'
    );
}
add_action('add_meta_boxes', 'thp_add_paid_meta_box');

function thp_paid_meta_box_callback($post) {
    
    wp_nonce_field('thp_save_paid_meta_box', 'thp_paid_meta_nonce');
    $is_paid = get_post_meta($post->ID, '_is_paid', true);
    ?>
    <label for="thp_is_paid">Post can only be accessed if paid</label>
    <input type="checkbox" name="thp_is_paid" id="thp_is_paid" value="1" <?php checked($is_paid, '1'); ?> />
    <?php
}

function thp_save_paid_meta_box($post_id) {
    if (isset($_POST['thp_is_paid']) && check_admin_referer('thp_save_paid_meta_box', 'thp_paid_meta_nonce')) {
        update_post_meta($post_id, '_is_paid', '1');
    } else {
        update_post_meta($post_id, '_is_paid', '0');
    }
}
add_action('save_post', 'thp_save_paid_meta_box');

// Add a meta box for tutorial price
function thp_add_price_meta_box() {
    add_meta_box(
        'thp_price_meta_box',
        'Tutorial Price',
        'thp_price_meta_box_callback',
        'tutorial_handbook', // Ensure this matches your custom post type
        'side',
        'high' // Position it high up in the sidebar
    );
}
add_action('add_meta_boxes', 'thp_add_price_meta_box');

function thp_price_meta_box_callback($post) {
    wp_nonce_field('thp_save_price_meta_box', 'thp_price_meta_nonce');
    $price = get_post_meta($post->ID, '_thp_tutorial_price', true);
    ?>
    <label for="thp_tutorial_price">Price ($):</label>
    <input type="text" name="thp_tutorial_price" id="thp_tutorial_price" value="<?php echo esc_attr($price); ?>" />
    <?php
}

function thp_save_price_meta_box($post_id) {
    if (isset($_POST['thp_tutorial_price']) && check_admin_referer('thp_save_price_meta_box', 'thp_price_meta_nonce')) {
        update_post_meta($post_id, '_thp_tutorial_price', sanitize_text_field($_POST['thp_tutorial_price']));
    }
}
add_action('save_post', 'thp_save_price_meta_box');


// Register Custom Post Type
function thp_register_custom_post_type() {
    $args = array(
        'public' => true,
        'label'  => 'Tutorials & Handbooks',
        'supports' => array('title', 'editor', 'thumbnail'),
        'has_archive' => true,
        'rewrite' => array('slug' => 'tutorials'),
        'publicly_queryable' => true,
        'show_in_rest' => true
    );
    register_post_type('tutorial_handbook', $args);
}
add_action('init', 'thp_register_custom_post_type');

// Generate TOC if headings are present
function thp_generate_toc($content) {
    global $post;

    if ($post->post_type === 'tutorial_handbook') {
        $matches = array();
        preg_match_all('/<h([2-6])[^>]*>([^<]+)<\/h[2-6]>/', $content, $matches);

        if (count($matches[0]) > 0) {
            $toc = '<div class="toc-content-container">';
            $toc .= '<aside class="toc-widget">';
            $toc .= '<h2>Table of Contents</h2><ul class="toc-list">';
            foreach ($matches[2] as $key => $heading) {
                $anchor = sanitize_title($heading);
                $toc .= '<li><a href="#' . $anchor . '">' . $heading . '</a></li>';
                $content = str_replace($matches[0][$key], '<h' . $matches[1][$key] . ' id="' . $anchor . '">' . $heading . '</h' . $matches[1][$key] . '>', $content);
            }
            $toc .= '</ul></aside>';
            $toc .= '<div class="post-content">';
            $toc .= '<h1 class="tutorial_page_title">' . get_the_title($post->ID) . '</h1>'; // Include the post title in the content area
            $toc .= $content;

            // Author info
            $toc .= '<div class="post-meta">
                        <hr>
                        <div style="">
                            <div class="author-info">
                                ' . get_avatar(get_the_author_meta('ID'), 48) . '
                                <label><strong>Author:</strong> ' . get_the_author() . '</label>
                            </div>
                            <div style="float: right;">
                                <label><strong>Release Date:</strong> ' . get_the_date() . '</label><br>
                                <label><strong>Last Updated:</strong> ' . get_the_modified_date() . '</label>
                            </div>
                        </div>
                    </div>';
            $toc .= '</div></div>';

            return $toc;
        }
    }

    return '<div class="post-content"><h1 class="tutorial_page_title">' . get_the_title($post->ID) . '</h1>' . $content . '</div>';
}
add_filter('the_content', 'thp_generate_toc');

// Add a meta box for hiding a tutorial from general listing
function thp_add_hide_meta_box() {
    add_meta_box(
        'thp_hide_meta_box',
        'Hide from General Listing',
        'thp_hide_meta_box_callback',
        'tutorial_handbook',
        'side'
    );
}
add_action('add_meta_boxes', 'thp_add_hide_meta_box');

function thp_hide_meta_box_callback($post) {
    $hide_from_general = get_post_meta($post->ID, '_hide_from_general', true);
    ?>
    <label for="thp_hide_from_general">Hide this post from general listing</label>
    <input type="checkbox" name="thp_hide_from_general" id="thp_hide_from_general" value="1" <?php checked($hide_from_general, '1'); ?> />
    <?php
}

function thp_save_hide_meta_box($post_id) {
    if (isset($_POST['thp_hide_from_general'])) {
        update_post_meta($post_id, '_hide_from_general', '1');
    } else {
        update_post_meta($post_id, '_hide_from_general', '0');
    }
}
add_action('save_post', 'thp_save_hide_meta_box');


// Add a meta box for the post description
function thp_add_description_meta_box() {
    add_meta_box(
        'thp_description_meta_box', // ID
        'Post Description', // Title
        'thp_description_meta_box_callback', // Callback
        'tutorial_handbook', // Post type
        'normal', // Context
        'high' // Priority
    );
}
add_action('add_meta_boxes', 'thp_add_description_meta_box');

// Meta box display callback
function thp_description_meta_box_callback($post) {
    // Add a nonce field so we can check for it later.
    wp_nonce_field('thp_save_description', 'thp_description_nonce');

    // Use get_post_meta to retrieve an existing value from the database.
    $value = get_post_meta($post->ID, '_thp_description', true);

    // Display the form, using the current value.
    echo '<textarea style="width:100%; height:100px;" id="thp_description" name="thp_description">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Enter a brief description of what this post will be about. This will be displayed on the listing page.</p>';
}

// Save the meta box data
function thp_save_description_meta_box($post_id) {
    // Check if our nonce is set.
    if (!isset($_POST['thp_description_nonce'])) {
        return;
    }

    // Verify that the nonce is valid.
    if (!wp_verify_nonce($_POST['thp_description_nonce'], 'thp_save_description')) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions.
    if (isset($_POST['post_type']) && $_POST['post_type'] === 'tutorial_handbook') {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    // Sanitize user input.
    if (isset($_POST['thp_description'])) {
        $description_data = sanitize_text_field($_POST['thp_description']);

        // Update the meta field in the database.
        update_post_meta($post_id, '_thp_description', $description_data);
    }
}
add_action('save_post', 'thp_save_description_meta_box');

// Add a meta box for internal memo
function thp_add_memo_meta_box() {
    add_meta_box(
        'thp_memo_meta_box', // ID
        'Internal Memo', // Title
        'thp_memo_meta_box_callback', // Callback
        'tutorial_handbook', // Post type
        'normal', // Context
        'high' // Priority
    );
}
add_action('add_meta_boxes', 'thp_add_memo_meta_box');

// Meta box display callback
function thp_memo_meta_box_callback($post) {
    // Add a nonce field so we can check for it later.
    wp_nonce_field('thp_save_memo', 'thp_memo_nonce');

    // Use get_post_meta to retrieve an existing value from the database.
    $memo_value = get_post_meta($post->ID, '_thp_memo', true);

    // Display the form, using the current value.
    echo '<textarea style="width:100%; height:100px;" id="thp_memo" name="thp_memo">' . esc_textarea($memo_value) . '</textarea>';
    echo '<p class="description">This memo is for internal use only and will not be visible to the public.</p>';
}

// Save the meta box data
function thp_save_memo_meta_box($post_id) {
    // Check if our nonce is set.
    if (!isset($_POST['thp_memo_nonce'])) {
        return;
    }

    // Verify that the nonce is valid.
    if (!wp_verify_nonce($_POST['thp_memo_nonce'], 'thp_save_memo')) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions.
    if (isset($_POST['post_type']) && $_POST['post_type'] === 'tutorial_handbook') {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    // Sanitize user input.
    if (isset($_POST['thp_memo'])) {
        $memo_data = sanitize_text_field($_POST['thp_memo']);

        // Update the meta field in the database.
        update_post_meta($post_id, '_thp_memo', $memo_data);
    }
}
add_action('save_post', 'thp_save_memo_meta_box');

// Enqueue styles for TOC
function thp_enqueue_toc_styles() {
    global $post;

    // Check if the post is of type 'tutorial_handbook' or if it contains the shortcodes
    if (
        is_singular('tutorial_handbook') ||
        (isset($post->post_content) && (has_shortcode($post->post_content, 'shy_tutorials') || has_shortcode($post->post_content, 'shy_private_tutorials')))
    ) {
        wp_enqueue_style('thp_toc_styles', plugin_dir_url(__FILE__) . 'style.css', array(), filemtime(plugin_dir_path(__FILE__) . 'style.css'));
    }
}
add_action('wp_enqueue_scripts', 'thp_enqueue_toc_styles');

// Access Control - Assign users to specific tutorials (simplified example)
function thp_add_meta_boxes() {
    add_meta_box('thp_access_control', 'Access Control', 'thp_access_control_callback', 'tutorial_handbook', 'side');
}
add_action('add_meta_boxes', 'thp_add_meta_boxes');

function thp_access_control_callback($post) {
    $users = get_users();
    $assigned_users = get_post_meta($post->ID, '_assigned_users', true) ?: array();

    echo '<ul>';
    foreach ($users as $user) {
        $checked = in_array($user->ID, $assigned_users) ? 'checked' : '';
        echo '<li><label><input type="checkbox" name="assigned_users[]" value="' . esc_attr($user->ID) . '" ' . esc_attr($checked) . '> ' . esc_attr($user->display_name) . '</label></li>';
    }
    echo '</ul>';
}

function thp_save_post($post_id) {
    if (isset($_POST['assigned_users'])) {
        update_post_meta($post_id, '_assigned_users', $_POST['assigned_users']);
    } else {
        delete_post_meta($post_id, '_assigned_users');
    }
}
add_action('save_post', 'thp_save_post');

function thp_restrict_access($content) {
    if (get_post_type() === 'tutorial_handbook') {
        $is_paid = get_post_meta(get_the_ID(), '_is_paid', true); // Check if the post is marked as paid

        if ($is_paid) {
            $assigned_users = get_post_meta(get_the_ID(), '_assigned_users', true);

            // Ensure $assigned_users is an array
            if (!is_array($assigned_users)) {
                $assigned_users = []; // Convert to an empty array if it's not an array
            }

            if (!in_array(get_current_user_id(), $assigned_users)) {
                return 'You do not have permission to view this content.';
            }
        }
    }
    return $content;
}
add_filter('the_content', 'thp_restrict_access');

// Register the shortcode
function shy_assigned_articles_shortcode($atts) {
    ob_start(); // Start output buffering

    ?>
    <div class="assigned-articles-container">
        <h2 class="assigned-articles-title" style="text-align: center;"><?php echo esc_html(get_the_title()); ?></h2>
        <div class="articles-list">

            <style>
                .post-content h1 {
                    display: none !important;
                }
            </style>

            <?php
            // Query for all posts in the 'tutorial_handbook' post type
            $args = array('post_type' => 'tutorial_handbook', 'posts_per_page' => -1);
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                $user_id = get_current_user_id();
                $found_articles = false;

                while ($query->have_posts()) : $query->the_post();
                    $post_id = get_the_ID();
                    $assigned_users = get_post_meta($post_id, '_assigned_users', true); // Check who has access

                    // Ensure $assigned_users is an array
                    if (!is_array($assigned_users)) {
                        $assigned_users = []; // Convert to an empty array if it's not an array
                    }

                    // Only show posts assigned to the current user
                    if (in_array($user_id, $assigned_users)) {
                        $found_articles = true;
                        $is_paid = get_post_meta($post_id, '_is_paid', true); // Check if the article is paid
                        $description = get_post_meta($post_id, '_thp_description', true); // Get the custom description
                        
                        // Use the common rendering function
                        echo wp_kses_post(thp_render_single_article($post_id, $is_paid, true, $description));
                    }

                endwhile;

                if (!$found_articles) :
                    echo '<p>No articles assigned to you.</p>';
                endif;

            } 
            else {
                echo "<p>No articles found</p>";
            
            } // <-- Correctly closes the if statement
            wp_reset_postdata(); ?>
        </div>
    </div>
    <?php

    return ob_get_clean(); // Return the buffered content
}
add_shortcode('shy_private_tutorials', 'shy_assigned_articles_shortcode');
                      




// Register the shortcode
function shy_knowledge_base_shortcode($atts) {
    ob_start(); // Start output buffering

    ?>
    <div class="knowledge-base-container">
        <h2 class="knowledge-base-title"><?php echo esc_html(get_the_title()); ?></h2>
        <div class="articles-list">

            <style>
                .post-content h1 {
                    display: none !important;
                }
            </style>

            <?php
            // Query for all posts in the 'tutorial_handbook' post type
            $args = array('post_type' => 'tutorial_handbook', 'posts_per_page' => -1);
            $query = new WP_Query($args);

            if ($query->have_posts()) :
                while ($query->have_posts()) : $query->the_post();
                    $post_id = get_the_ID();
                    $is_paid = get_post_meta($post_id, '_is_paid', true); // Check if the article is paid
                    $hide_from_general = get_post_meta($post_id, '_hide_from_general', true); // Check if the article is hidden
                    $assigned_users = get_post_meta($post_id, '_assigned_users', true); // Check who has access
                    $description = get_post_meta($post_id, '_thp_description', true); // Get the custom description

                    // Ensure $assigned_users is an array
                    if (!is_array($assigned_users)) {
                        $assigned_users = []; // Convert to an empty array if it's not an array
                    }

                    // Skip posts that are marked as hidden from general listing
                    if ($hide_from_general) {
                        continue;
                    }

                    $user_has_access = in_array(get_current_user_id(), $assigned_users);

                    // Use the common rendering function
                    echo wp_kses_post(thp_render_single_article($post_id, $is_paid, $user_has_access, $description));

                endwhile;
            else : ?>
                <p>No articles found</p>
            <?php 
            endif;
            wp_reset_postdata(); ?>
        </div>
    </div>
    <?php

    return ob_get_clean(); // Return the buffered content
}
add_shortcode('shy_tutorials', 'shy_knowledge_base_shortcode');


// Add PayPal Settings submenu
function thp_add_paypal_settings_submenu() {
    add_submenu_page(
        'edit.php?post_type=tutorial_handbook', // Parent slug
        'PayPal Settings',                      // Page title
        'PayPal Settings',                      // Menu title
        'manage_options',                       // Capability
        'thp_paypal_settings',                  // Menu slug
        'thp_paypal_settings_page_callback'     // Callback function
    );
}
add_action('admin_menu', 'thp_add_paypal_settings_submenu');

// Register PayPal settings
function thp_register_paypal_settings() {
    register_setting('thp_paypal_settings_group', 'thp_paypal_client_id');
    register_setting('thp_paypal_settings_group', 'thp_paypal_secret');
    register_setting('thp_paypal_settings_group', 'thp_paypal_currency');

    add_settings_section(
        'thp_paypal_main_section',
        'PayPal Configuration',
        'thp_paypal_main_section_callback',
        'thp_paypal_settings'
    );

    add_settings_field(
        'thp_paypal_client_id',
        'PayPal Client ID',
        'thp_paypal_client_id_callback',
        'thp_paypal_settings',
        'thp_paypal_main_section'
    );

    add_settings_field(
        'thp_paypal_secret',
        'PayPal Secret',
        'thp_paypal_secret_callback',
        'thp_paypal_settings',
        'thp_paypal_main_section'
    );

    add_settings_field(
        'thp_paypal_currency',
        'Currency',
        'thp_paypal_currency_callback',
        'thp_paypal_settings',
        'thp_paypal_main_section'
    );
}
add_action('admin_init', 'thp_register_paypal_settings');
// Section description callback
function thp_paypal_main_section_callback() {
    echo '<p>Enter your PayPal API credentials below.</p>';
}

// Client ID field callback
function thp_paypal_client_id_callback() {
    $client_id = get_option('thp_paypal_client_id');
    echo '<input type="text" name="thp_paypal_client_id" value="' . esc_attr($client_id) . '" style="width: 100%;" />';
}

// Secret field callback
function thp_paypal_secret_callback() {
    $secret = get_option('thp_paypal_secret');
    echo '<input type="password" name="thp_paypal_secret" value="' . esc_attr($secret) . '" style="width: 100%;" />';
}

// Currency field callback
function thp_paypal_currency_callback() {
    $currency = get_option('thp_paypal_currency', 'USD'); // Default to USD
    echo '<input type="text" name="thp_paypal_currency" value="' . esc_attr($currency) . '" style="width: 100%;" />';
    echo '<p class="description">Enter the currency code (e.g., USD, EUR).</p>';
}
// Settings page display callback
function thp_paypal_settings_page_callback() {
    ?>
    <div class="wrap">
        <h1>PayPal Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('thp_paypal_settings_group');
                do_settings_sections('thp_paypal_settings');
                submit_button();
            ?>
        </form>
    </div>
    <?php
}



// Modify the single article rendering to include the PayPal purchase link
function thp_render_single_article($post_id, $is_paid, $user_has_access, $description) {
    ob_start();

    if ($user_has_access || !$is_paid) {
        // Get the full content of the post
        $post = get_post($post_id);
        if ($post) {
            $content = apply_filters('the_content', $post->post_content);
        }
    } else {
        // If the user doesn't have access, we won't generate the TOC, just show the description.
        $content = '<p>This content is locked. Please purchase access to view it.</p>';
    }

    ?>
    <div class="article-item <?php echo $is_paid ? 'locked' : ''; ?>">
        <div class="article-header">
            <?php if ($is_paid && !$user_has_access) : ?>
                <span class="lock-icon">&#x1f512;</span>
            <?php endif; ?>
            <h2 class="article-title">
                <?php if (!$is_paid || $user_has_access) : ?>
                    <a href="<?php echo esc_html(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a>
                <?php else : ?>
                    <?php echo esc_html(get_the_title($post_id)); ?>
                <?php endif; ?>
            </h2>
        </div>
        <div class="article-meta">
            <span class="article-date">Published on <?php echo get_the_date('', $post_id); ?></span>
            <?php if ($is_paid && !$user_has_access) : ?>
                <span class="article-status">Locked</span>
            <?php endif; ?>
        </div>
        <div class="article-description">
            <p><?php echo esc_html($description); ?></p>
        </div>

        <?php if ($is_paid && !$user_has_access) : ?>
            <div class="article-locked">
                <?php if (is_user_logged_in()): ?>
                    <p>This post is locked. Please purchase access to view it.</p>
                    <?php 
                        $paypal_link = thp_generate_paypal_link($post_id); 
                        if ($paypal_link): ?>
                        <a href="<?php echo esc_url($paypal_link); ?>" class="button">Purchase for $<?php echo esc_html(get_post_meta($post_id, '_thp_tutorial_price', true)); ?></a>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Please <a href="<?php echo wp_login_url(get_permalink($post_id)); ?>">login</a> or <a href="<?php echo wp_registration_url(); ?>">register</a> to purchase access.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}







// Generate a PayPal payment link using the PayPalLibrary
function thp_generate_paypal_link($post_id) {
    $client_id = get_option('thp_paypal_client_id');
    $secret = get_option('thp_paypal_secret');
    $currency = get_option('thp_currency');
    $price = get_post_meta($post_id, '_thp_tutorial_price', true);

    if (!$client_id || !$secret || !$price || !$currency) {
        return false;
    }

    $paypal = new PayPalLibrary($client_id, $secret, $currency);
    $description = get_the_title($post_id);
    $return_url = get_permalink($post_id);
    $cancel_url = get_permalink($post_id);

    return $paypal->generatePurchase($price, $description, $return_url, $cancel_url);
}


function thp_paypal_webhook_listener() {
    $client_id = get_option('thp_paypal_client_id');
    $secret = get_option('thp_paypal_secret');
    $webhook_id = 'YOUR_WEBHOOK_ID'; // Replace with your actual webhook ID

    $paypal = new PayPalLibrary($client_id, $secret, 'USD', $webhook_id);

    $paypal->handleWebhook(function ($event) {
        if ($event['event_type'] == 'PAYMENT.SALE.COMPLETED') {
            $sale_id = $event['resource']['id'];
            $payer_email = $event['resource']['payer']['email_address'];
            $amount = $event['resource']['amount']['total'];
            $currency = $event['resource']['amount']['currency'];

            // Assuming you're storing the user ID and post ID in the custom field during purchase creation
            $custom = json_decode($event['resource']['invoice_number'], true);
            $user_id = $custom['user_id'];
            $post_id = $custom['post_id'];

            // Save the transaction and update user access
            thp_save_transaction($user_id, $post_id, $sale_id, $amount, $currency, $payer_email);
            thp_mark_tutorial_as_purchased($user_id, $post_id);
        }
    });
}


function thp_save_transaction($user_id, $post_id, $transaction_id, $amount, $currency, $payer_email) {
    $transaction_data = array(
        'user_id' => $user_id,
        'post_id' => $post_id,
        'transaction_id' => $transaction_id,
        'amount' => $amount,
        'currency' => $currency,
        'payer_email' => $payer_email,
        'date' => current_time('mysql')
    );

    // Create a new transaction post
    $transaction_post_id = wp_insert_post(array(
        'post_type' => 'transaction',
        'post_title' => 'Transaction for ' . get_the_title($post_id),
        'post_status' => 'publish',
        'meta_input' => $transaction_data,
    ));
}

function thp_mark_tutorial_as_purchased($user_id, $post_id) {
    // Add the post ID to the user's purchased tutorials list (stored as user meta)
    $purchased_tutorials = get_user_meta($user_id, '_purchased_tutorials', true);

    if (!is_array($purchased_tutorials)) {
        $purchased_tutorials = array();
    }

    if (!in_array($post_id, $purchased_tutorials)) {
        $purchased_tutorials[] = $post_id;
        update_user_meta($user_id, '_purchased_tutorials', $purchased_tutorials);
    }
}



function thp_init_webhook_listener() {
    add_rewrite_rule('paypal-webhook/?$', 'index.php?paypal_webhook=1', 'top');
}
add_action('init', 'thp_init_webhook_listener');

function thp_add_paypal_query_vars($vars) {
    $vars[] = 'paypal_webhook';
    return $vars;
}
add_filter('query_vars', 'thp_add_paypal_query_vars');

function thp_handle_paypal_webhook() {
    global $wp_query;

    if (isset($wp_query->query_vars['paypal_webhook'])) {
        thp_paypal_webhook_listener();
    }
}
add_action('template_redirect', 'thp_handle_paypal_webhook');



// Register the Transactions custom post type under Tutorials & Handbooks menu
function thp_register_transaction_post_type() {
    $labels = array(
        'name'               => 'Transactions',
        'singular_name'      => 'Transaction',
        'menu_name'          => 'Transactions',
        'name_admin_bar'     => 'Transaction',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Transaction',
        'new_item'           => 'New Transaction',
        'edit_item'          => 'Edit Transaction',
        'view_item'          => 'View Transaction',
        'all_items'          => 'All Transactions',
        'search_items'       => 'Search Transactions',
        'parent_item_colon'  => 'Parent Transactions:',
        'not_found'          => 'No transactions found.',
        'not_found_in_trash' => 'No transactions found in Trash.',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => 'edit.php?post_type=tutorial_handbook',  // Make it a sub-menu
        'query_var'          => true,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 20,
        'supports'           => array('title', 'custom-fields'),
    );

    register_post_type('transaction', $args);
}
add_action('init', 'thp_register_transaction_post_type');
