<?php 

/**
 * Plugin Name: School Insiders
 * Author: The Wild Fields
 * Author URI: https://thewildfields.com
 */

define('PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );

require_once PLUGIN_DIR_PATH . 'inc/api/school-parser.php';
require_once PLUGIN_DIR_PATH . 'inc/api/canada-school-parser.php';

add_action('init', '___sip__register_school_cpt');

function ___sip__register_school_cpt(){
    register_post_type( 'school', array(
        'labels' => array(
            'name' => 'Schools',
            'menu_name' => 'Schools'
        ),
        'public' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'revisions', 'author'),
        'show_in_rest' => true,
        'can_export' => true
    ) );
    register_taxonomy( 'school-type', 'school', array(
        'labels' => array(
            'menu_name' => 'School Types'
        ),
        'public' => true,
        'show_in_rest' => true
    ) );
}

add_action('admin_menu', '___sip__add_school_import_subpage');

function ___sip__add_school_import_subpage(){

    add_submenu_page(
        $parent_slug = 'edit.php?post_type=school',
        $page_title = 'Single School Import',
        $menu_title = 'Single School Import',
        $capability = 'administrator',
        $menu_slug = 'single-school-import',
        $callback = '___sip__single_school_import_menu_page_callback'
    );

    add_submenu_page(
        $parent_slug = 'edit.php?post_type=school',
        $page_title = 'Batch School Import',
        $menu_title = 'Batch School Import',
        $capability = 'administrator',
        $menu_slug = 'batch-school-import',
        $callback = '___sip__batch_school_import_menu_page_callback'
    );

}

function ___sip__single_school_import_menu_page_callback(){
    load_template( plugin_dir_path( __FILE__ ) . 'inc/template-parts/single-school-import-menu-page.php', true );
}

function ___sip__batch_school_import_menu_page_callback(){
    load_template( plugin_dir_path( __FILE__ ) . 'inc/template-parts/batch-school-import-menu-page.php', true );
}

add_action('admin_enqueue_scripts', function(){
    wp_enqueue_script( 'parser', plugin_dir_url( __FILE__ ) . 'assets/dist/bundle.js', null, null, true );
    wp_enqueue_script( 'axios', 'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js', null, null, true);
});

add_action( 'current_screen', 'check_admin_page' );

function createCollegeFromCSVUrl($url){
    $postExists = get_posts(array(
        'tax_query' => array(
            'source_url' => $url
        )
    ));

}

function mapSchoolUrls ($item) {
    return $item['school_url'];
}

function check_admin_page() {
    $screen = get_current_screen();
    if ( $screen && 'school_page_batch-school-import' === $screen->id ) {
        if( isset( $_POST['csv_file'] ) ){
            $csvFile = $_POST['csv_file'];
            $uniqueData = array();
            if (($handle = fopen($csvFile, 'r')) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    foreach ($data as $cell) {
                        $url = trim($cell);
                        if( strlen($url) > 0){
                            array_push($uniqueData, $url);
                        }
                    }
                }
                fclose($handle);
            } else {
                echo 'Unable to open the file.';
            }

            $schoolsInQueueDB = get_field('schools_to_import','option') ? get_field('schools_to_import','option') : array();

            $schoolsInQueue = array_map('mapSchoolUrls', $schoolsInQueueDB );
            $uniqueData = array_unique($uniqueData);
            foreach ($uniqueData as $url) {
                if( !in_array($url, $schoolsInQueue) ){
                    add_row(
                        'schools_to_import',
                        array('school_url' => $url),
                        'option'
                    );
                }
            }
        }
    }
}


function my_enqueue_acf_scripts() {
    if (isset($_GET['page']) && $_GET['page'] === 'batch-import-settings') {
        acf_enqueue_scripts();
    }
}
add_action('admin_enqueue_scripts', 'my_enqueue_acf_scripts');

add_filter('cron_schedules', 'custom_cron_schedules');
function custom_cron_schedules($schedules) {
    $schedules['every_two_minutes'] = array(
        'interval' => 120,
        'display'  => __('Every Two Minutes'),
    );
    return $schedules;
}

function ___sip__schedule_batch_school_import() {
    if (!wp_next_scheduled('batch_school_import')) {
        wp_schedule_event(time(), 'every_two_minutes', 'batch_school_import');
    }
}
add_action('wp', '___sip__schedule_batch_school_import');

function ___sip__handle_batch_school_import() {

    // Get first element of import schools repeater

    $repeater = get_field('schools_to_import','option');
    if( !$repeater ){
        return;
    }
    $schoolUrl = $repeater[0]['school_url'];

    // Check if the school with this URL already exists

    if( !str_contains( $schoolUrl, 'https://collegedunia.com' )){
        return;
    }

    $schoolAlreadyExists = get_posts( array(
        'post_type' => 'school',
        'post_status' => 'any',
        'meta_query' => [ [
            'key' => 'source_url',
            'value' => $schoolUrl
            ] ],
    ));

    // Import school

    if( !$schoolAlreadyExists ){
        $schoolData = wp_remote_post(
            'https://us-central1-school-insiders.cloudfunctions.net/app/canada-school',
            array(
                'body' => array(
                    'url' => $schoolUrl,
                ),
                'timeout' => 120
            )
        );

        
        if( !is_wp_error( $schoolData ) ){
            $responseBody = json_decode( $schoolData['body'] );
            $schoolPost = wp_insert_post( wp_slash( array(
                'post_type' => 'school',
                'post_title' => $responseBody->schoolName,
                'author' => get_current_user_id(),
                'post_status' => 'draft',
                'tax_input' => array(
                    'school-type' => $responseBody->schoolType
                ),
                'meta_input' => array(
                    'page_title' => $responseBody->pageTitle,
                    'source_url' => $responseBody->url,
                    'year_established' => $responseBody->establishedYear,
                    'summary' => $responseBody->summary,
                )
            )));

            if( !is_wp_error( $schoolPost ) ){
                // Likes Repeater
                foreach ($responseBody->likes as $like ) {
                    add_row(
                        'likes', 
                        array( 'like_content' => $like ),
                        $schoolPost
                    );
                }
                // Dislikes Repeater
                foreach ($responseBody->dislikes as $dislike ) {
                    add_row(
                        'dislikes', 
                        array( 'dislike_content' => $dislike ),
                        $schoolPost
                    );
                }
                // Ranking Repeater
                foreach ($responseBody->ranking as $ranking ) {
                    add_row(
                        'ranking', 
                        array(
                            'agency_name' => $ranking->title,
                            'summary' => $ranking->summary,
                        ),
                        $schoolPost
                    );
                }
                // Courses Repeater
                foreach ($responseBody->courses as $course ) {
                    add_row(
                        'courses',
                        array(
                            'title' => $course->title,
                            'source_url' => $course->url,
                            'price' => $course->price
                        ),
                        $schoolPost
                    );
                }
                // Costs Repeater
                foreach ($responseBody->costs as $cost ) {
                    add_row(
                        'costs',
                        array(
                            'program' => $cost->title,
                            'price' => $cost->price,
                            'price_hint' => $cost->priceHint
                        ),
                        $schoolPost
                    );
                }
            }
        }
    }

    // Delete URL from import list

    if( !is_wp_error($schoolData) ){
        $schools = get_field('schools_to_import', 'option');
        unset($schools[0]);
        update_field('schools_to_import', $schools, 'option');
    }

}
add_action('batch_school_import', '___sip__handle_batch_school_import');