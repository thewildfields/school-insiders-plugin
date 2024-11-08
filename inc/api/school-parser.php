<?php 

add_action('rest_api_init','___sip__register_api_endpoint_for_single_school_parser');

function ___sip__register_api_endpoint_for_single_school_parser(){
    register_rest_route( 'school-insiders/v1' , '/school' , array(
        'methods' => 'POST',
        'callback' => '___sip__single_scholl_parser',
        'permission_callback' => '__return_true',
    ));
}

function ___sip__single_scholl_parser( WP_REST_Request $request){

    $schoolData = wp_remote_post(
        'https://us-central1-school-insiders.cloudfunctions.net/app/school',
        array(
            'body' => array(
                'url' => $request['url']
            ),
            'timeout' => 30
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

    return new WP_REST_Response( wp_json_encode( $schoolData ) );

}