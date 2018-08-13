<?php
// Numeric Page Navi (built into the theme by default)
function disciple_tools_page_navi() {
    global $wpdb, $wp_query;
    $request = $wp_query->request;
    $posts_per_page = intval( get_query_var( 'posts_per_page' ) );
    $paged = intval( get_query_var( 'paged' ) );
    $numposts = $wp_query->found_posts;
    $max_page = $wp_query->max_num_pages;
    if ( $numposts <= $posts_per_page ) { return; }
    if (empty( $paged ) || $paged == 0) {
        $paged = 1;
    }
    $pages_to_show = 7;
    $pages_to_show_minus_1 = $pages_to_show -1;
    $half_page_start = floor( $pages_to_show_minus_1 /2 );
    $half_page_end = ceil( $pages_to_show_minus_1 /2 );
    $start_page = $paged - $half_page_start;
    if ($start_page <= 0) {
        $start_page = 1;
    }
    $end_page = $paged + $half_page_end;
    if (( $end_page - $start_page ) != $pages_to_show_minus_1) {
        $end_page = $start_page + $pages_to_show_minus_1;
    }
    if ($end_page > $max_page) {
        $start_page = $max_page - $pages_to_show_minus_1;
        $end_page = $max_page;
    }
    if ($start_page <= 0) {
        $start_page = 1;
    }
    echo '<nav class="page-navigation"><ul class="pagination">'."";
    if ($start_page >= 2 && $pages_to_show < $max_page) {
        $first_page_text = __( 'First', 'disciple_tools' );
        echo '<li><a href="'. esc_url( get_pagenum_link() ).'" title="'. esc_attr( $first_page_text, 'disciple_tools' ).'">'. esc_html( $first_page_text, 'disciple_tools' ).'</a></li>';
    }
    echo '<li>';
    previous_posts_link( __( 'Previous', 'disciple_tools' ) );
    echo '</li>';
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $paged) {
            echo '<li class="current"> '. intval( $i ) .' </li>';
        } else {
            echo '<li><a href="'. esc_url( get_pagenum_link( $i ) ).'">'. intval( $i ) .'</a></li>';
        }
    }
    echo '<li>';
    next_posts_link( __( 'Next', 'disciple_tools' ), 0 );
    echo '</li>';
    if ($end_page < $max_page) {
        $last_page_text = __( 'Last', 'disciple_tools' );
        echo '<li><a href="'. esc_url( get_pagenum_link( $max_page ) ).'" title="'. esc_attr( $last_page_text, 'disciple_tools' ) .'">'. esc_html( $last_page_text, 'disciple_tools' ).'</a></li>';
    }
    echo '</ul></nav>';
} /* End page navi */
