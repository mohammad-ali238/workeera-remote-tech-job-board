<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Single-job template loader.
 *
 * Wraps the theme header/footer around the plugin's rendered job detail block,
 * served when WordPress resolves a `workeera_job` permalink.
 */

get_header();

while (have_posts()) : the_post();
    Workeera_Post_Detail::job_detail();
endwhile;

get_footer();
