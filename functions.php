<?php
/**
 * Theme functions and definitions.
 *
 * For additional information on potential customization options,
 * read the developers' documentation:
 *
 * https://developers.elementor.com/docs/hello-elementor-theme/
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 */
function hello_elementor_child_scripts_styles() {

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		HELLO_ELEMENTOR_CHILD_VERSION
	);

}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );



/**
 * Automate creation/update of Leo Custom Posts from database
 */

// Main sync function
function sync_leo_database_to_posts($entry_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'leo';
    
    // If entry_id is provided, only sync that specific entry
    if ($entry_id) {
        $leo_entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $entry_id
        ), ARRAY_A);
    } else {
        // Get all records
        $leo_entries = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
    }
    
    if (!empty($leo_entries)) {
        foreach ($leo_entries as $entry) {
            // Check if post exists
            $existing_posts = get_posts(array(
                'post_type' => 'leo',
                'meta_query' => array(
                    array(
                        'key' => 'db_entry_id',
                        'value' => $entry['id'],
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            ));

            // Prepare post data
            $post_data = array(
                'post_title'   => $entry['leo_title'],
                'post_content' => $entry['leo_description'],
                'post_status'  => 'publish',
                'post_type'    => 'leo'
            );

            if (empty($existing_posts)) {
                // Create new post
                $post_id = wp_insert_post($post_data);
            } else {
                // Update existing post
                $post_id = $existing_posts[0]->ID;
                $post_data['ID'] = $post_id;
                wp_update_post($post_data);
            }

            if ($post_id && !is_wp_error($post_id)) {
                // Store database ID
                update_post_meta($post_id, 'db_entry_id', $entry['id']);
                
                // Update all ACF fields with error checking
                $fields_mapping = array(
                    'leo_title' => 'leo_title',
                    'leo_description' => 'leo_description',
                    'start_time' => 'start_time',
                    'end_time' => 'end_time',
                    'leo_moreinfo' => 'leo_moreinfo',
                    'leo_image' => 'leo_image',
                    'leo_video' => 'leo_video',
                    'leo_doc' => 'leo_doc'
                );

                foreach ($fields_mapping as $db_field => $acf_field) {
                    if (isset($entry[$db_field]) && !empty($entry[$db_field])) {
                        // For file fields, ensure they're properly handled
                        if (in_array($db_field, ['leo_image', 'leo_video', 'leo_doc'])) {
                            // If it's a URL, try to get the attachment ID
                            $attachment_id = attachment_url_to_postid($entry[$db_field]);
                            if ($attachment_id) {
                                update_field($acf_field, $attachment_id, $post_id);
                            } else {
                                update_field($acf_field, $entry[$db_field], $post_id);
                            }
                        } else {
                            update_field($acf_field, $entry[$db_field], $post_id);
                        }
                    }
                }
                
                // Log successful sync
                error_log("Leo Post ID {$post_id} synced successfully with database ID {$entry['id']}");
            }
        }
    }
}

// Trigger sync on database insert/update
function leo_db_sync_trigger($query) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'leo';
    
    // Check if the query is for our table
    if (strpos($query, $table_name) !== false) {
        // Extract ID from INSERT query
        if (preg_match("/INSERT INTO/i", $query)) {
            $entry_id = $wpdb->insert_id;
            sync_leo_database_to_posts($entry_id);
        }
        // Extract ID from UPDATE query
        elseif (preg_match("/UPDATE/i", $query)) {
            preg_match("/WHERE.*?id\s*=\s*(\d+)/i", $query, $matches);
            if (!empty($matches[1])) {
                $entry_id = $matches[1];
                sync_leo_database_to_posts($entry_id);
            }
        }
    }
}
add_action('query', 'leo_db_sync_trigger');

// Keep the manual sync button for backup
function add_leo_sync_button() {
    global $current_screen;
    if ($current_screen->post_type === 'leo') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.wrap h1').first().append('<a href="<?php echo admin_url('admin-post.php?action=sync_leo_posts'); ?>" class="page-title-action">Manual Sync</a>');
            });
        </script>
        <?php
    }
}
add_action('admin_head', 'add_leo_sync_button');

// Handle manual sync
function handle_manual_sync() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    sync_leo_database_to_posts();
    
    wp_redirect(admin_url('edit.php?post_type=leo&sync=success'));
    exit;
}
add_action('admin_post_sync_leo_posts', 'handle_manual_sync');

// Show sync messages
function show_sync_messages() {
    if (isset($_GET['sync']) && $_GET['sync'] === 'success') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Leo posts have been synchronized with the database!</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'show_sync_messages');

// Debug function - you can remove this in production
function leo_debug_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}