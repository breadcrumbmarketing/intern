<?php
/**
 * Template Name: Auto Refresh Page with Elementor Support
 * Description: A page template that auto-refreshes every 5 minutes and supports Elementor editing.
 */

get_header(); // Include the header of your WordPress theme
?>

<div class="content">
    <!-- Your page content goes here -->

    <h1>Live Content Page</h1>
    <p>The content on this page refreshes every 5 minutes.</p>

    <!-- This will display Elementor content -->
    <div class="elementor-page-content">
        <?php
        // WordPress Loop for displaying page content created by Elementor
        while (have_posts()) : the_post();
            the_content(); // Displays the content created in Elementor
        endwhile;
        ?>
    </div>

</div>

<script type="text/javascript">
// Auto refresh the page every 5 minutes (300000 ms)
setTimeout(function(){
    location.reload();
}, 300000); // 300000 ms = 5 minutes
</script>

<?php
get_footer(); // Include the footer of your WordPress theme
?>
