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
function sync_leo_entry_to_post($entry_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'leo';
    
    // Get specific entry
    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $entry_id
    ), ARRAY_A);
    
    if ($entry) {
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
            'post_title'   => wp_strip_all_tags($entry['leo_title']),
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
            
            // Update ACF fields
            if (function_exists('update_field')) {
                update_field('leo_title', $entry['leo_title'], $post_id);
                update_field('leo_description', $entry['leo_description'], $post_id);
                update_field('start_time', $entry['start_time'], $post_id);
                update_field('end_time', $entry['end_time'], $post_id);
                update_field('leo_moreinfo', $entry['leo_moreinfo'], $post_id);
                
                // Handle media fields
                if (!empty($entry['leo_image'])) {
                    update_field('leo_image', $entry['leo_image'], $post_id);
                }
                if (!empty($entry['leo_video'])) {
                    update_field('leo_video', $entry['leo_video'], $post_id);
                }
                if (!empty($entry['leo_doc'])) {
                    update_field('leo_doc', $entry['leo_doc'], $post_id);
                }
            }
        }
    }
}

// Trigger sync on database operations
function leo_after_db_operation($wpdb) {
    if (strpos($wpdb->last_query, $wpdb->prefix . 'leo') !== false) {
        $entry_id = $wpdb->insert_id ?: null;
        if ($entry_id) {
            sync_leo_entry_to_post($entry_id);
        }
    }
}
add_action('wpdb_query_after', 'leo_after_db_operation');

// Add manual sync button to admin
function add_leo_sync_button() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'leo') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.wrap h1').first().append(' <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=sync_leo_posts'), 'sync_leo_posts'); ?>" class="page-title-action">Sync Leo Posts</a>');
            });
        </script>
        <?php
    }
}
add_action('admin_head', 'add_leo_sync_button');

// Handle manual sync
function handle_manual_leo_sync() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'sync_leo_posts')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'leo';
    $entries = $wpdb->get_results("SELECT id FROM {$table_name}", ARRAY_A);
    
    foreach ($entries as $entry) {
        sync_leo_entry_to_post($entry['id']);
    }
    
    wp_redirect(add_query_arg('sync', 'success', admin_url('edit.php?post_type=leo')));
    exit;
}
add_action('admin_post_sync_leo_posts', 'handle_manual_leo_sync');

// Show sync success message
function show_leo_sync_message() {
    if (isset($_GET['sync']) && $_GET['sync'] === 'success' && get_current_screen()->post_type === 'leo') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Leo posts have been synchronized successfully!</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'show_leo_sync_message');