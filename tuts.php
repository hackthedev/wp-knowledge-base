<?php
/*
Plugin Name: Shy's Tutorial & Handbook Plugin
Description: A simple plugin to create tutorials and handbooks with a table of contents and access control.
Version: 1.0
Author: Shy Devil
*/



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
    $is_paid = get_post_meta($post->ID, '_is_paid', true);
    ?>
    <label for="thp_is_paid">Post can only be accessed if paid</label>
    <input type="checkbox" name="thp_is_paid" id="thp_is_paid" value="1" <?php checked($is_paid, '1'); ?> />
    <?php
}

function thp_save_paid_meta_box($post_id) {
    if (isset($_POST['thp_is_paid'])) {
        update_post_meta($post_id, '_is_paid', '1');
    } else {
        update_post_meta($post_id, '_is_paid', '0');
    }
}
add_action('save_post', 'thp_save_paid_meta_box');

// Register Custom Post Type
function thp_register_custom_post_type() {
    $args = array(
        'public' => true,
        'label'  => 'Tutorials & Handbooks',
        'supports' => array('title', 'editor', 'thumbnail'),
        'has_archive' => true,
        'rewrite' => array('slug' => 'tutorials'),
        'publicly_queryable' => true,
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
    wp_enqueue_style('thp_toc_styles', plugin_dir_url(__FILE__) . 'style.css');
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
        echo '<li><label><input type="checkbox" name="assigned_users[]" value="' . $user->ID . '" ' . $checked . '> ' . $user->display_name . '</label></li>';
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
                        echo thp_render_single_article($post_id, $is_paid, true, $description);
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
                    echo thp_render_single_article($post_id, $is_paid, $user_has_access, $description);

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



// Function to generate the HTML structure for a single article
function thp_render_single_article($post_id, $is_paid, $user_has_access, $description) {
    ob_start();

    if ($user_has_access || !$is_paid) {
        // Get the full content of the post
        $post = get_post($post_id);
        if ($post) {
            // Apply 'the_content' filters, including the TOC
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
                <span class="lock-icon">&#x1f512;</span> <!-- Lock icon -->
            <?php endif; ?>
            <h2 class="article-title">
                <?php if (!$is_paid || $user_has_access) : ?>
                    <a href="<?php echo get_permalink($post_id); ?>"><?php echo get_the_title($post_id); ?></a>
                <?php else : ?>
                    <?php echo get_the_title($post_id); ?>
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

        <?php if ($user_has_access || !$is_paid) : ?>
            <div class="article-content">
                <?php //echo $content; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_paid && !$user_has_access) : ?>
            <div class="article-locked">
                <p>This post is locked. Please purchase access to view it.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
