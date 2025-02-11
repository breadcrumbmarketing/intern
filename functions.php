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


// Leo dasboard //
/**
 * Automate creation/update of Leo Custom Posts from database
 */

// Function to sync database entries with Custom Posts
function sync_leo_database_to_posts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'leo';
    
    // Get all records from leo table
    $leo_entries = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
    
    if (!empty($leo_entries)) {
        foreach ($leo_entries as $entry) {
            // Check if post already exists for this entry
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
                // Update ACF fields
                update_field('db_entry_id', $entry['id'], $post_id);
                update_field('leo_title', $entry['leo_title'], $post_id);
                update_field('leo_description', $entry['leo_description'], $post_id);
                update_field('start_time', $entry['start_time'], $post_id);
                update_field('end_time', $entry['end_time'], $post_id);
                update_field('leo_more_info', $entry['leo_moreinfo'], $post_id);
                
                // Handle image
                if (!empty($entry['leo_image'])) {
                    update_field('leo_image', $entry['leo_image'], $post_id);
                }
                
                // Handle video
                if (!empty($entry['leo_video'])) {
                    update_field('leo_video', $entry['leo_video'], $post_id);
                }
                
                // Handle document
                if (!empty($entry['leo_doc'])) {
                    update_field('leo_doc', $entry['leo_doc'], $post_id);
                }
            }
        }
    }
}

// Hook to run the sync when new entry is added to database
function sync_leo_on_db_insert($wpdb) {
    if ($wpdb->last_query && strpos($wpdb->last_query, $wpdb->prefix . 'leo') !== false) {
        sync_leo_database_to_posts();
    }
}
add_action('wpdb_query_after', 'sync_leo_on_db_insert');

// Add hourly cron job to sync
function schedule_leo_sync() {
    if (!wp_next_scheduled('leo_sync_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'leo_sync_cron_hook');
    }
}
add_action('wp', 'schedule_leo_sync');

// Hook for cron job
add_action('leo_sync_cron_hook', 'sync_leo_database_to_posts');

// Add manual sync button to admin
function add_leo_sync_button() {
    global $current_screen;
    if ($current_screen->post_type === 'leo') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.wrap h1').first().append('<a href="<?php echo admin_url('admin-post.php?action=sync_leo_posts'); ?>" class="page-title-action">Sync Database Entries</a>');
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

// Show success message after manual sync
function show_sync_success_message() {
    if (isset($_GET['sync']) && $_GET['sync'] === 'success') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Database entries have been successfully synced to Leo posts!</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'show_sync_success_message');

// Clean up cron job on deactivation
function leo_deactivation() {
    $timestamp = wp_next_scheduled('leo_sync_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'leo_sync_cron_hook');
    }
}
register_deactivation_hook(__FILE__, 'leo_deactivation');