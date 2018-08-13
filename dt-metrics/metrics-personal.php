<?php

Disciple_Tools_Metrics_Personal::instance();
class Disciple_Tools_Metrics_Personal extends Disciple_Tools_Metrics_Hooks_Base
{
    private static $_instance = null;
    public static function instance()
    {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        $url_path = dt_get_url_path();

        if ( 'metrics' === substr( $url_path, '0', 7 ) ) {

            add_filter( 'dt_metrics_menu', [ $this, 'add_overview_menu' ], 20 );

            if ( 'metrics' === $url_path ) {
                add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
            }
        }
    }

    public function add_overview_menu( $content ) {
        $content .= '
            <li><a href="'. site_url( '/metrics/' ) .'#my_stats" onclick="my_stats()">' .  esc_html__( 'Personal', 'disciple_tools' ) . '</a></li>
            ';
        return $content;
    }

    public function scripts() {
        wp_enqueue_script( 'dt_metrics_personal_script', get_stylesheet_directory_uri() . '/dt-metrics/metrics-personal.js', [
            'jquery',
            'jquery-ui-core',
        ], filemtime( get_theme_file_path() . '/dt-metrics/metrics-personal.js' ), true );

        wp_localize_script(
            'dt_metrics_personal_script', 'dtMetricsPersonal', [
                'root' => esc_url_raw( rest_url() ),
                'theme_uri' => get_stylesheet_directory_uri(),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'current_user_login' => wp_get_current_user()->user_login,
                'current_user_id' => get_current_user_id(),
                'map_key' => dt_get_option( 'map_key' ),
                'overview' => $this->overview(),
            ]
        );
    }

    public function overview() {
        $data = [
            'translations' => [
                'title'           => __( 'My Overview' ),
                'total_contacts'  => __( 'Total Contacts' ),
                'total_groups'    => __( 'Total Groups' ),
                'updates_needed'  => __( 'Updates Needed' ),
                'attempts_needed' => __( 'Attempts Needed' ),
            ],
            'hero_stats'        => self::chart_my_hero_stats(),
            'contacts_progress' => self::chart_contacts_progress( 'personal' ),
            'group_types'       => self::chart_group_types(),
            'group_health'      => self::chart_group_health(),
            'group_generations' => self::chart_group_generations(),
        ];

        return apply_filters( 'dt_my_metrics', $data );
    }
}
