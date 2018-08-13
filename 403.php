<?php get_header(); ?>

    <div id="content">

        <div id="inner-content" class="grid-x grid-padding-x">

            <div class="cell bordered-box">
                <header class="article-header">
                    <h1><?php esc_html_e( 'Permission denied', 'disciple_tools' ); ?></h1>

                </header> <!-- end article header -->

                <section class="entry-content">
                    <p>
                        <?php
                        $type = "item";
                        if ( is_singular( "contacts" )){
                            $type = __( "contact", "disciple_tools" );
                        } elseif ( is_singular( "groups" )){
                            $type = __( "group", "disciple_tools" );
                        }
                        $id = GET_THE_ID();
                        echo sprintf( esc_html__( 'Sorry, you don\'t have permission to view the %1$s with id %2$s.', 'disciple_tools' ), esc_html( $type ), esc_html( $id ) ) . ' ';
                        echo esc_html__( 'Request permission from your administrator.', 'zume' );
                        // @todo Add a request for permission form here, which could leave a comment on the record, and notify the owner of the record to share the record.
                        echo '<p><a href="javascript:history.back(1);">'. esc_html__( 'Back', 'zume' ) .'</a></p>';
                        ?>
                    </p>
                </section> <!-- end article section -->
            </div>

        </div> <!-- end #inner-content -->

    </div> <!-- end #content -->

<?php get_footer(); ?>
