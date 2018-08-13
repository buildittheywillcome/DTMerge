<?php
/**
 * Contains create, update and delete functions for posts, wrapping access to
 * the database
 *
 * @package  Disciple_Tools
 * @since    0.1.0
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

/**
 * Class Disciple_Tools_Posts
 * Functions for creating, finding, updating or deleting posts
 */
class Disciple_Tools_Posts
{

    public static $connection_types;

    /**
     * Disciple_Tools_Posts constructor.
     */
    public function __construct()
    {
        self::$connection_types = [
            "locations",
            "groups",
            "people_groups",
            "baptized_by",
            "baptized",
            "coached_by",
            "coaching",
            "subassigned",
            "leaders",
            "parent_groups",
            "child_groups",
        ];
    }

    /**
     * Permissions for interaction with contacts Custom Post Types
     * Example. Role permissions available on contacts:
     *  access_contacts
     *  create_contacts
     *  view_any_contacts
     *  assign_any_contacts  //assign contacts to others
     *  update_any_contacts  //update any contact
     *  delete_any_contacts  //delete any contact
     */

    /**
     * @param string $post_type
     *
     * @return bool
     */
    public static function can_access( string $post_type )
    {
        return current_user_can( "access_" . $post_type );
    }

    /**
     * @param string $post_type
     *
     * @return bool
     */
    public static function can_view_all( string $post_type )
    {
        return current_user_can( "view_any_" . $post_type );
    }

    /**
     * @param string $post_type
     *
     * @return bool
     */
    public static function can_create( string $post_type )
    {
        return current_user_can( 'create_' . $post_type );
    }

    /**
     * @param string $post_type
     *
     * @return bool
     */
    public static function can_delete( string $post_type )
    {
        return current_user_can( 'delete_any_' . $post_type );
    }

    /**
     * A user can view the record if they have the global permission or
     * if the post if assigned or shared with them
     *
     * @param string $post_type
     * @param int    $post_id
     *
     * @return bool
     */
    public static function can_view( string $post_type, int $post_id )
    {
        global $wpdb;
        if ( current_user_can( 'view_any_' . $post_type ) ) {
            return true;
        } else {
            $user = wp_get_current_user();
            $assigned_to = get_post_meta( $post_id, "assigned_to", true );
            if ( $assigned_to && $assigned_to === "user-" . $user->ID ) {
                return true;
            } else {
                $shares = $wpdb->get_results( $wpdb->prepare(
                    "SELECT
                        *
                    FROM
                        `$wpdb->dt_share`
                    WHERE
                        post_id = %s",
                    $post_id
                ), ARRAY_A );
                foreach ( $shares as $share ) {
                    if ( (int) $share['user_id'] === $user->ID ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * A user can update the record if they have the global permission or
     * if the post if assigned or shared with them
     *
     * @param string $post_type
     * @param int    $post_id
     *
     * @return bool
     */
    public static function can_update( string $post_type, int $post_id )
    {
        global $wpdb;
        if ( current_user_can( 'update_any_' . $post_type ) ) {
            return true;
        } else {
            $user = wp_get_current_user();
            $assigned_to = get_post_meta( $post_id, "assigned_to", true );
            if ( isset( $assigned_to ) && $assigned_to === "user-" . $user->ID ) {
                return true;
            } else {
                $shares = $wpdb->get_results( $wpdb->prepare(
                    "SELECT
                        *
                    FROM
                        `$wpdb->dt_share`
                    WHERE
                        post_id = %s",
                    $post_id
                ), ARRAY_A );
                foreach ( $shares as $share ) {
                    if ( (int) $share['user_id'] === $user->ID ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function get_label_for_post_type( $post_type, $singular ){
        switch ( $post_type ) {
            case "contacts":
            case "contact":
                return $singular ? Disciple_Tools_Contact_Post_Type::instance()->singular : Disciple_Tools_Contact_Post_Type::instance()->plural;
                break;
            case "groups":
            case "group":
                return $singular ? Disciple_Tools_Groups_Post_Type::instance()->singular : Disciple_Tools_Groups_Post_Type::instance()->plural;
                break;
            default:
                return $post_type;
        }
    }

    /**
     * @param string $post_type
     * @param int    $user_id
     *
     * @return array
     */
    public static function get_posts_shared_with_user( string $post_type, int $user_id )
    {
        global $wpdb;
        $shares = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $wpdb->dt_share as shares
                INNER JOIN $wpdb->posts as posts
                WHERE user_id = %d
                AND shares.post_id = posts.ID
                AND posts.post_type = %s",
                $user_id,
                $post_type
            ),
            ARRAY_A
        );
        $list = [];
        foreach ( $shares as $share ) {
            $post = get_post( $share["post_id"] );
            if ( isset( $post->post_type ) && $post->post_type === $post_type ) {
                $list[] = $post;
            }
        }

        return $list;
    }

    /**
     * @param string $post_type
     * @param int    $group_id
     * @param string $comment
     *
     * @return false|int|\WP_Error
     */
    public static function add_post_comment( string $post_type, int $group_id, string $comment )
    {
        if ( !self::can_update( $post_type, $group_id ) ) {
            return new WP_Error( __FUNCTION__, __( "You do not have permission for this" ), [ 'status' => 403 ] );
        }
        $user = wp_get_current_user();
        $user_id = get_current_user_id();
        $comment_data = [
            'comment_post_ID'      => $group_id,
            'comment_content'      => $comment,
            'user_id'              => $user_id,
            'comment_author'       => $user->display_name,
            'comment_author_url'   => $user->user_url,
            'comment_author_email' => $user->user_email,
            'comment_type'         => 'comment',
        ];

        return wp_new_comment( $comment_data );
    }

    public static function format_connection_message( $p2p_id, $action = 'connected to', $activity ){
        // Get p2p record
        $p2p_record = p2p_get_connection( (int) $p2p_id ); // returns object

        if ( !$p2p_record ){
            if ($activity->field_type === "connection from"){
                $from_title = get_the_title( $activity->object_id );
                $to_title = get_the_title( $activity->meta_value );
            } elseif ( $activity->field_type === "connection to"){
                $from_title = get_the_title( $activity->meta_value );
                $to_title = get_the_title( $activity->object_id );
            } else {
                return "CONNECTION DESTROYED";
            }
        } else {
            $p2p_from = get_post( $p2p_record->p2p_from, ARRAY_A );
            $p2p_to = get_post( $p2p_record->p2p_to, ARRAY_A );
            $from_title = $p2p_from["post_title"];
            $to_title = $p2p_to["post_title"];
        }
        $object_note_from = '';
        $object_note_to = '';

        // Build variables
        $p2p_type = $activity->meta_key;
        if ($p2p_type === "baptizer_to_baptized"){
            if ($action === "connected to"){
                $object_note_to = __( 'Baptized', 'disciple_tools' ) . ' ' . $from_title;
                $object_note_from = __( 'Baptized by', 'disciple_tools' ) . ' ' . $to_title;
            } else {
                $object_note_to = __( 'Did not baptize', 'disciple_tools' ) . ' ' . $from_title;
                $object_note_from = __( 'Not baptized by', 'disciple_tools' ) . ' ' . $to_title;
            }
        } else if ($p2p_type === "contacts_to_groups"){
            if ($action == "connected to"){
                $object_note_to = __( 'Added to group', 'disciple_tools' ) . ' ' . $to_title;
                $object_note_from = __( 'Added to group', 'disciple_tools' ) . ' ' . $to_title;
            } else {
                $object_note_to = __( 'Removed from group', 'disciple_tools' ) . ' ' . $to_title;
                $object_note_from = __( 'Removed from group', 'disciple_tools' ) . ' ' . $to_title;
            }
        }
        else if ($p2p_type === "contacts_to_peoplegroups"){
            if ($action == "connected to"){
                $object_note_to = __( 'Added to people group:', 'disciple_tools' ) . ' ' . $to_title;
                $object_note_from = __( 'Added to people group:', 'disciple_tools' ) . ' ' . $to_title;
            } else {
                $object_note_to = __( 'Removed from people group:', 'disciple_tools' ) . ' ' . $to_title;
                $object_note_from = __( 'Removed from people group:', 'disciple_tools' ) . ' ' . $to_title;
            }
        }
        else if ( $p2p_type === "contacts_to_contacts"){
            if ($action === "connected to"){
                $object_note_to = __( 'Coaching', 'disciple_tools' ) . ' ' . $from_title;
                $object_note_from = __( 'Coached by', 'disciple_tools' ) . ' ' . $to_title;
            } else {
                $object_note_to = __( 'No longer coaching', 'disciple_tools' ) . ' ' . $from_title;
                $object_note_from = __( 'No longed coached by', 'disciple_tools' ) . ' ' . $to_title;
            }
        } else if ( $p2p_type === "contacts_to_subassigned"){
            if ($action === "connected to"){
                $object_note_to = __( 'Sub-assigned', 'disciple_tools' ) . ' ' . $from_title;
                $object_note_from = __( 'Sub-assigned on', 'disciple_tools' ) . ' ' . $to_title;
            } else {
                $object_note_to = __( 'Removed sub-assigned', 'disciple_tools' ) . ' ' . $from_title;
                $object_note_from = __( 'No longed sub-assigned on', 'disciple_tools' ) . ' ' . $to_title;
            }
        } else if ( $p2p_type === "contacts_to_locations"){
            if ($action == "connected to"){
                $object_note_to = __( 'Added to location', 'disciple_tools' ) . ' ' . $to_title;
                $object_note_from = __( 'Added to location', 'disciple_tools' ) . ' ' . $to_title;
            } else {
                $object_note_to = __( 'Removed from location', 'disciple_tools' ) . ' ' . $to_title;
                $object_note_from = __( 'Removed from location', 'disciple_tools' ) . ' ' . $to_title;
            }
        } else if ( $p2p_type === "groups_to_leaders"){
            if ($action == "connected to"){
                $object_note_to = __( 'Added to leaders in group', 'disciple_tools' ) . ': ' . $from_title;
                $object_note_from = $to_title . ' ' . __( 'added to leaders', 'disciple_tools' );
            } else {
                $object_note_to = __( 'Removed from leaders in group', 'disciple_tools' ) . ': ' . $from_title;
                $object_note_from = $to_title . ' ' . __( 'Removed from leaders', 'disciple_tools' );
            }
        } else {
            if ($action == "connected to"){
                $object_note_to = __( 'Connected to', 'disciple_tools' ) . ' ' . $to_title;
                $object_note_from = __( 'Connected on', 'disciple_tools' ) . ' ' . $to_title;
            } else {
                $object_note_to = __( 'Removed from', 'disciple_tools' ) . ' ' . $to_title;
                $object_note_from = __( 'Removed from', 'disciple_tools' ) . ' ' . $to_title;
            }
        }

        if ( $activity->field_type === "connection from" ){
            return $object_note_from;
        } else {
            return $object_note_to;
        }
    }

    public static function format_activity_message( $activity, $fields) {
        $message = "";
        if ( $activity->action == "field_update" ){
            if ( isset( $fields[$activity->meta_key] ) ){
                if ( $activity->meta_key === "assigned_to"){
                    $meta_array = explode( '-', $activity->meta_value ); // Separate the type and id
                    if ( isset( $meta_array[1] ) ) {
                        $user = get_user_by( "ID", $meta_array[1] );
                        $message = __( 'Assigned to', 'disciple_tools' ) . ": " . ( $user ? $user->display_name : __( "Nobody", 'disciple_tools' ) );
                    }
                }
                if ( $fields[$activity->meta_key]["type"] === "text"){
                    $message = $fields[$activity->meta_key]["name"] . " " . __( "changed to", 'disciple_tools' ) . ": " .$activity->meta_value;
                }
                if ( $fields[$activity->meta_key]["type"] === "multi_select" ){
                    $message = "";
                    $value = $activity->meta_value;
                    if ( $activity->meta_value == "value_deleted" ){
                        $message .= __( "Removed from", 'disciple_tools' );
                        $value = $activity->old_value;
                    } else {
                        $message .= __( "Added to", 'disciple_tools' );
                    }

                    if ( isset( $fields[$activity->meta_key]["default"][$value] ) ){
                        $message .= " " . $fields[$activity->meta_key]["name"] . ": " . $fields[$activity->meta_key]["default"][$value] ?? $value;
                    } else {
                        $message .= " " . $fields[$activity->meta_key]["name"] . ": " . $value;
                    }
                }
                if ( $fields[$activity->meta_key]["type"] === "key_select" ){
                    if ( isset( $fields[$activity->meta_key]["default"][$activity->meta_value] ) ){
                        $message = $fields[$activity->meta_key]["name"] . ": " . $fields[$activity->meta_key]["default"][$activity->meta_value] ?? $activity->meta_value;
                    } else {
                        $message = $fields[$activity->meta_key]["name"] . ": " . $activity->meta_value;
                    }
                }
                if ($fields[$activity->meta_key]["type"] === "number"){
                    $message = $fields[$activity->meta_key]["name"] . ": " . $activity->meta_value;
                }
                if ($fields[$activity->meta_key]["type"] === "date" ){
                    $message = $fields[$activity->meta_key]["name"] . ": " . $activity->meta_value;
                }
            } else {
                if (strpos( $activity->meta_key, "_details" ) !== false ) {
                    $meta_value = maybe_unserialize( $activity->meta_value );
                    $original_key = str_replace( "_details", "", $activity->meta_key );
                    $original = get_post_meta( $activity->object_id, $original_key, true );
                    if ( !is_string( $original ) ){
                        $original = "Not a string";
                    }
                    $name = $fields[ $activity->meta_key ]['name'] ?? "";
                    $object_note = $name . ' "'. $original .'" ';
                    if ( is_array( $meta_value ) ){
                        foreach ($meta_value as $k => $v){
                            $prev_value = $activity->old_value;
                            if (is_array( $prev_value ) && isset( $prev_value[ $k ] ) && $prev_value[ $k ] == $v){
                                continue;
                            }
                            if ($k === "verified") {
                                $object_note .= $v ? __( "verified", 'disciple_tools' ) : __( "not verified", 'disciple_tools' );
                            }
                            if ($k === "invalid") {
                                $object_note .= $v ? __( "invalidated", 'disciple_tools' ) : __( "not invalidated", 'disciple_tools' );
                            }
                            $object_note .= ', ';
                        }
                    } else {
                        $object_note = $meta_value;
                    }
                    $object_note = chop( $object_note, ', ' );
                    $message = $object_note;
                } else if ( $activity->meta_key == "title" ){
                    $message = __( "Name changed to:", 'disciple_tools' ) . ' ' . $activity->meta_value;
                } else if ( $activity->meta_key === "_sample"){
                    $message = __( "Created from Demo Plugin", "disciple_tools" );
                } else {
                    $message = "Deleted field";
                }
            }
        }
        if ( $activity->object_subtype === "p2p" ){
            $message = self::format_connection_message( $activity->meta_id, $activity->action, $activity );
        }
        if ( $activity->object_subtype === "share" ){
            if ($activity->action === "share"){
                $message = __( "Shared with", "disciple_tools" ) . ' ' . dt_get_user_display_name( $activity->meta_value );
            } else if ( $activity->action === "remove" ){
                $message = __( "Unshared with", "disciple_tools" ) . ' ' . dt_get_user_display_name( $activity->meta_value );
            }
        }

        return $message;
    }

    /**
     * @param string $post_type
     * @param int    $post_id
     *
     * @return array|null|object|\WP_Error
     */
    public static function get_post_activity( string $post_type, int $post_id, array $fields )
    {
        global $wpdb;
        if ( !self::can_view( $post_type, $post_id ) ) {
            return new WP_Error( __FUNCTION__, __( "No permissions to read:" ) . $post_type, [ 'status' => 403 ] );
        }
        $activity = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                *
            FROM
                `$wpdb->dt_activity_log`
            WHERE
                `object_type` = %s
                AND `object_id` = %s",
            $post_type,
            $post_id
        ) );
        $activity_simple = [];
        foreach ( $activity as $a ) {
            $a->object_note = self::format_activity_message( $a, $fields );
            if ( isset( $a->user_id ) && $a->user_id > 0 ) {
                $user = get_user_by( "id", $a->user_id );
                if ( $user ){
                    $a->name =$user->display_name;
                    $a->gravatar = get_avatar_url( $user->ID, [ 'size' => '16' ] );
                }
            }
            $activity_simple[] = [
                "meta_key" => $a->meta_key,
                "gravatar" => isset( $a->gravatar ) ? $a->gravatar : "",
                "name" => isset( $a->name ) ? $a->name : "",
                "object_note" => $a->object_note,
                "hist_time" => $a->hist_time,
                "meta_id" => $a->meta_id,
                "histid" => $a->histid,
            ];
        }

        return $activity_simple;
    }

    public static function get_post_single_activity( string $post_type, int $post_id, array $fields, int $activity_id ){
        global $wpdb;
        if ( !self::can_view( $post_type, $post_id ) ) {
            return new WP_Error( __FUNCTION__, __( "No permissions to read group" ), [ 'status' => 403 ] );
        }
        $activity = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                *
            FROM
                `$wpdb->dt_activity_log`
            WHERE
                `object_type` = %s
                AND `object_id` = %s
                AND `histid` = %s",
            $post_type,
            $post_id,
            $activity_id
        ) );
        foreach ( $activity as $a ) {
            $a->object_note = self::format_activity_message( $a, $fields );
            if ( isset( $a->user_id ) && $a->user_id > 0 ) {
                $user = get_user_by( "id", $a->user_id );
                if ( $user ) {
                    $a->name = $user->display_name;
                }
            }
        }
        if ( isset( $activity[0] ) ){
            return $activity[0];
        }
        return $activity;
    }

    /**
     * Get post comments
     *
     * @param string $post_type
     * @param int    $post_id
     *
     * @return array|int|\WP_Error
     */
    public static function get_post_comments( string $post_type, int $post_id )
    {
        if ( !self::can_view( $post_type, $post_id ) ) {
            return new WP_Error( __FUNCTION__, __( "No permissions to read group" ), [ 'status' => 403 ] );
        }
        $comments = get_comments( [ 'post_id' => $post_id ] );

        return $comments;
    }

    /**
     * Get viewable in compact form
     *
     * @param string $post_type
     * @param string $search_string
     *
     * @return array|\WP_Error|\WP_Query
     */
    public static function get_viewable_compact( string $post_type, string $search_string )
    {
        if ( !self::can_access( $post_type ) ) {
            return new WP_Error( __FUNCTION__, sprintf( __( "You do not have access to these %s" ), $post_type ), [ 'status' => 403 ] );
        }
        global $wpdb;
        $current_user = wp_get_current_user();
        $compact = [];
        $search_string = esc_sql( sanitize_text_field( $search_string ) );
        $shared_with_user = [];
        $users_interacted_with =[];
        $posts = [];
        if ( !self::can_view_all( $post_type ) ) {
//            @todo better way to get the contact records for users my contacts are shared with
            $users_interacted_with = Disciple_Tools_Users::get_assignable_users_compact( $search_string );
            $shared_with_user = self::get_posts_shared_with_user( $post_type, $current_user->ID );
            $query_args['meta_key'] = 'assigned_to';
            $query_args['meta_value'] = "user-" . $current_user->ID;
            $posts = $wpdb->get_results( $wpdb->prepare( "
                SELECT * FROM $wpdb->posts 
                INNER JOIN $wpdb->postmeta as assigned_to ON ( $wpdb->posts.ID = assigned_to.post_id AND assigned_to.meta_key = 'assigned_to')
                WHERE assigned_to.meta_value = %s
                AND INSTR( $wpdb->posts.post_title, %s ) > 0
                AND $wpdb->posts.post_type = %s AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_status = 'private')
                ORDER BY CASE
                    WHEN INSTR( $wpdb->posts.post_title, %s ) = 1 then 1
                    ELSE 2  
                END, CHAR_LENGTH($wpdb->posts.post_title), $wpdb->posts.post_title
                LIMIT 0, 30
            ", "user-". $current_user->ID, $search_string, $post_type, $search_string
            ), OBJECT );
        } else {
            $posts = $wpdb->get_results( $wpdb->prepare( "
                SELECT * FROM $wpdb->posts WHERE 1=1 AND
                INSTR( $wpdb->posts.post_title, %s ) > 0
                AND $wpdb->posts.post_type = %s AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_status = 'private')
                ORDER BY  CASE
                    WHEN INSTR( $wpdb->posts.post_title, %s ) = 1 then 1
                    ELSE 2  
                END, CHAR_LENGTH($wpdb->posts.post_title), $wpdb->posts.post_title
                LIMIT 0, 30
            ", $search_string, $post_type, $search_string
            ), OBJECT );
        }
        if ( is_wp_error( $posts ) ) {
            return $posts;
        }

        $post_ids = array_map(
            function( $post ) {
                return $post->ID;
            },
            $posts
        );
        foreach ( $shared_with_user as $shared ) {
            if ( !in_array( $shared->ID, $post_ids ) ) {
                $compact[] = [
                "ID" => $shared->ID,
                "name" => $shared->post_title
                ];
            }
        }
        foreach ( $users_interacted_with as $user ) {
            $contact = Disciple_Tools_Users::get_contact_for_user( $user["ID"] );
            if ( $contact ){
                if ( !in_array( $contact->ID, $post_ids ) ) {
                    $compact[] = [
                        "ID" => $contact->ID,
                        "name" => $user["name"]
                    ];
                }
            }
        }
        foreach ( $posts as $post ) {
            $compact[] = [
                "ID" => $post->ID,
                "name" => $post->post_title
            ];
        }

        return [
        "total" => $wpdb->get_var( "SELECT found_rows();" ),
        "posts" => $compact
        ];
    }

    /**
     * @param string $post_type
     *
     * @param int $most_recent
     *
     * @return array|\WP_Error|\WP_Query
     */
    public static function get_viewable( string $post_type, int $most_recent = 0 )
    {
        if ( !self::can_access( $post_type ) ) {
            return new WP_Error( __FUNCTION__, sprintf( __( "You do not have access to these %s" ), $post_type ), [ 'status' => 403 ] );
        }
        $current_user = wp_get_current_user();

        $query_args = [
            'post_type' => $post_type,
            'meta_query' => [
                'relation' => "AND",
                [
                    'key' => "last_modified",
                    'value' => $most_recent,
                    'compare' => '>'
                ]
            ],
            'orderby' => 'meta_value_num',
            'meta_key' => "last_modified",
            'order' => 'ASC',
            'posts_per_page' => 1000 // @codingStandardsIgnoreLine
        ];
        $posts_shared_with_user = [];
        if ( !self::can_view_all( $post_type ) ) {
            $posts_shared_with_user = self::get_posts_shared_with_user( $post_type, $current_user->ID );

            $query_args['meta_key'] = 'assigned_to';
            $query_args['meta_value'] = "user-" . $current_user->ID;
        }
        $queried_posts = new WP_Query( $query_args );
        if ( is_wp_error( $queried_posts ) ) {
            return $queried_posts;
        }
        $posts = $queried_posts->posts;
        $post_ids = array_map(
            function( $post ) {
                return $post->ID;
            },
            $posts
        );
        //add shared posts to the list avoiding duplicates
        foreach ( $posts_shared_with_user as $shared ) {
            if ( !in_array( $shared->ID, $post_ids ) ) {
                $posts[] = $shared;
            }
        }

        $delete_posts = [];
        if ($most_recent){
            global $wpdb;
            $deleted_query = $wpdb->get_results( $wpdb->prepare(
                "SELECT object_id 
                FROM `$wpdb->dt_activity_log`
                WHERE 
                    ( `action` = 'deleted' || `action` = 'trashed' )
                    AND `object_subtype` = %s
                    AND hist_time > %d
                ",
                $post_type,
                $most_recent
            ), ARRAY_A);
            foreach ( $deleted_query as $deleted ){
                $delete_posts[] = $deleted["object_id"];
            }
        }

        return [
            $post_type => $posts,
            "total" => $queried_posts->found_posts,
            "deleted" => $delete_posts
        ];
    }


    public static function search_viewable_post( string $post_type, array $query, bool $check_permissions = true ){
        if ( $check_permissions && !self::can_access( $post_type ) ) {
            return new WP_Error( __FUNCTION__, __( "You do not have access to these" ), [ 'status' => 403 ] );
        }
        global $wpdb;
        $current_user = wp_get_current_user();

        $include = [];
        if ( isset( $query["include"] ) ){
            $include = $query["include"];
            unset( $query["include"] );
        }
        $search = "";
        if ( isset( $query["text"] )){
            $search = sanitize_text_field( $query["text"] );
            unset( $query["text"] );
        }
        $offset = 0;
        if ( isset( $query["offset"] )){
            $offset = esc_sql( sanitize_text_field( $query["offset"] ) );
            unset( $query["offset"] );
        }
        $sort = "post_title";
        $sort_dir = "asc";
        if ( isset( $query["sort"] )){
            $sort = esc_sql( sanitize_text_field( $query["sort"] ) );
            if ( strpos( $sort, "-" ) === 0 ){
                $sort_dir = "desc";
                $sort = str_replace( "-", "", $sort );
            }
            unset( $query["sort"] );
        }

        $inner_joins = "";
        $connections_sql_to = "";
        $connections_sql_from = "";

        foreach ( $query as $query_key => $query_value ) {
            if ( in_array( $query_key, self::$connection_types ) ) {
                if ( $query_key === "locations" ) {
                    $location_sql = "";
                    foreach ( $query_value as $location ) {
                        $l = get_post( $location );
                        if ( $l && $l->post_type === "locations" ){
                            $location_sql .= empty( $location_sql ) ? $l->ID : ( ",".$l->ID );
                        }
                    }
                    if ( !empty( $location_sql ) ){
                        $connections_sql_to .= "AND ( to_p2p.p2p_type = 'contacts_to_locations' AND to_p2p.p2p_to in (" . esc_sql( $location_sql ) .") )";
                    }
                }
                if ( $query_key === "subassigned" ) {
                    $subassigned_sql = "";
                    foreach ( $query_value as $subassigned ) {
                        $l = get_post( $subassigned );
                        if ( $l && $l->post_type === "contacts" ){
                            $subassigned_sql .= empty( $subassigned_sql ) ? $l->ID : ( ",".$l->ID );
                        }
                    }
                    if ( !empty( $subassigned_sql ) ){
                        $connections_sql_from .= "AND ( from_p2p.p2p_type = 'contacts_to_subassigned' AND from_p2p.p2p_from in (" . esc_sql( $subassigned_sql ) .") )";
                    }
                }
            }
        }
        if ( !empty( $connections_sql_to )){
            $inner_joins .= " INNER JOIN $wpdb->p2p as to_p2p ON ( to_p2p.p2p_from = $wpdb->posts.ID )";
        }
        if ( !empty( $connections_sql_from )){
            $inner_joins .= " INNER JOIN $wpdb->p2p as from_p2p ON ( from_p2p.p2p_to = $wpdb->posts.ID )";
        }


        $meta_query = "";
        $includes_query = "";
        $share_joins = "";
        $access_joins = "";
        $access_query = "";
        if ( !isset( $query["assigned_to"] ) || in_array( "all", $query["assigned_to"] ) ){
            $query["assigned_to"] = [ "all" ];
            if ( !self::can_view_all( 'contacts' ) ){
                $query["assigned_to"] = [ "me" ];
                if ( !in_array( "shared", $include )){
                    $include[] = "shared";
                }
            };
        }
        foreach ( $include as $i ){
            if ( $i === "shared" ){
                $share_joins = "LEFT JOIN $wpdb->dt_share AS shares ON ( shares.post_id = $wpdb->posts.ID ) ";
                $access_query = "shares.user_id = $current_user->ID ";
            }
        }
        if ( in_array( "shared", $query["assigned_to"] ) ){
            $share_joins = "LEFT JOIN $wpdb->dt_share AS shares ON ( shares.post_id = $wpdb->posts.ID ) ";
            $access_query = ( !empty( $access_query ) ? "OR" : "" ) ." shares.user_id = $current_user->ID ";
        }
        foreach ( $query as $query_key => $query_value ){
            $meta_field_sql = "";
            if ( !is_array( $query_value )){
                return new WP_Error( __FUNCTION__, __( "Filter queries must be arrays" ), [ 'status' => 403 ] );
            }
            if ( !in_array( $query_key, self::$connection_types ) && strpos( $query_key, "contact_" ) !== 0 ){
                if ( $query_key == "assigned_to" ){
                    foreach ( $query_value as $assigned_to ){
                        $connector = "OR";
                        if ( $assigned_to == "me" ){
                            $assigned_to = "user-" . $current_user->ID;
                        } else if ( $assigned_to != "all" && $assigned_to != "shared" ) {
                            if ( self::can_view_all( 'contacts' ) ){
                                $assigned_to = "user-" . $assigned_to;
                            } else {
                                $assigned_to = "user-" . $assigned_to;
                                if ( !$share_joins ){
                                    $share_joins = "INNER JOIN $wpdb->dt_share AS shares ON ( shares.post_id = $wpdb->posts.ID ) ";
                                    $access_query = "shares.user_id = $current_user->ID ";
                                    $connector = "AND";
                                }
                            }
                        } else {
                            break;
                        }
                        $access_joins = "INNER JOIN $wpdb->postmeta AS assigned_to ON ( $wpdb->posts.ID = assigned_to.post_id ) ";
                        $access_query .= ( !empty( $access_query ) ? $connector : "" ) . ( $connector == "AND" ? " ( " : "" ) . " ( " . esc_sql( $query_key ) . ".meta_key = '" . esc_sql( $query_key ) ."' AND " . esc_sql( $query_key ) . ".meta_value = '" . esc_sql( $assigned_to ) . "' ) " . ( $connector == "AND" ? " ) " : "" );

                    }
                } else {
                    foreach ( $query_value as $value ){
                        if ( !empty( $meta_field_sql ) ){
                            $meta_field_sql .= " OR ";
                        }
                        $meta_field_sql .= " ( " . esc_sql( $query_key ) . ".meta_key = '" . esc_sql( $query_key ) ."' AND " . esc_sql( $query_key ) . ".meta_value = '" . esc_sql( $value ) . "' ) ";
                    }
                }
                if ( $meta_field_sql ){
                    $inner_joins .= "INNER JOIN $wpdb->postmeta AS " . esc_sql( $query_key ) . " ON ( $wpdb->posts.ID = " . esc_sql( $query_key ) . ".post_id ) ";
                    $meta_query .= "AND ( " .$meta_field_sql . ") ";
                }
            }
        }

        if ( !empty( $search )){
            $inner_joins .= "INNER JOIN $wpdb->postmeta AS search ON ( $wpdb->posts.ID = search.post_id ) ";
            $meta_query .= "AND ( ( INSTR( $wpdb->posts.post_title ,'" . esc_sql( $search ) . "' ) > 0 ) OR ( search.meta_key LIKE 'contact_%' AND INSTR( search.meta_value, '" . esc_sql( $search ) . "' ) > 0 ) ) ";

        }

        $access_query = $access_query ? ( "AND ( " . $access_query . " ) " ) : "";

        $sort_sql = "$wpdb->posts.post_date asc";
        $sort_join = "";
        $post_type_check = "";
        if ( $post_type == "contacts" ){
            $inner_joins .= "LEFT JOIN $wpdb->postmeta as contact_type ON ( $wpdb->posts.ID = contact_type.post_id AND contact_type.meta_key = 'type' ) ";
            $post_type_check = " AND (
                ( contact_type.meta_key = 'type' AND contact_type.meta_value = 'media' )
                OR
                ( contact_type.meta_key = 'type' AND contact_type.meta_value = 'next_gen' )
                OR ( contact_type.meta_key IS NULL )
            ) ";
            $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
            if ( $sort === "overall_status" || $sort === "seeker_path" ) {
                $keys = array_keys( $contact_fields[$sort]["default"] );
                $sort_join = "INNER JOIN $wpdb->postmeta as sort ON ( $wpdb->posts.ID = sort.post_id AND sort.meta_key = '$sort')";
                $sort_sql  = "CASE ";
                foreach ( $keys as $index => $key ) {
                    $i        = $key == "closed" ? 99 : $index;
                    $sort_sql .= "WHEN ( sort.meta_value = '" . esc_sql( $key ) . "' ) THEN $i ";
                }
                $sort_sql .= "else 98 end ";
                $sort_sql .= $sort_dir;
            } elseif ( $sort === "faith_milestones" ){
                $all_field_keys = array_keys( $contact_fields );
                $sort_sql = "CASE ";
                $sort_join = "";
                foreach ( array_reverse( $all_field_keys ) as $field_index => $field_key ){
                    if ( strpos( $field_key, "milestone_" ) === 0 ){
                        $alias = 'faith_' . esc_sql( $field_key );
                        $sort_join .= "LEFT JOIN $wpdb->postmeta as $alias ON 
                    ( $wpdb->posts.ID = $alias.post_id AND $alias.meta_key = '" . esc_sql( $field_key ) . "' AND $alias.meta_value = 'yes') ";
                        $sort_sql .= "WHEN ( $alias.meta_key = '" . esc_sql( $field_key ) . "' ) THEN $field_index ";
                    }
                }
                $sort_sql .= "else 1000 end ";
                $sort_sql .= $sort_dir;
            }
        } elseif ( $post_type === "groups" ){
            $group_fields = Disciple_Tools_Groups_Post_Type::instance()->get_custom_fields_settings();
            if ( $sort === "group_status" || $sort === "group_type" ) {
                $keys      = array_keys( $group_fields[ $sort ]["default"] );
                $sort_join = "INNER JOIN $wpdb->postmeta as sort ON ( $wpdb->posts.ID = sort.post_id AND sort.meta_key = '$sort')";
                $sort_sql  = "CASE ";
                foreach ( $keys as $index => $key ) {
                    $sort_sql .= "WHEN ( sort.meta_value = '" . esc_sql( $key ) . "' ) THEN $index ";
                }
                $sort_sql .= "else 98 end ";
                $sort_sql .= $sort_dir;
            } elseif ( $sort === "members" ){
                $sort_join = "LEFT JOIN $wpdb->p2p as sort ON ( sort.p2p_to = $wpdb->posts.ID AND sort.p2p_type = 'contacts_to_groups' )";
                $sort_sql = "COUNT(sort.p2p_id) $sort_dir";
            }
        }
        if ( $sort === "name" ){
            $sort_sql = "$wpdb->posts.post_title  " . $sort_dir;
        } elseif ( $sort === "assigned_to" || $sort === "last_modified" ){
            $sort_join = "INNER JOIN $wpdb->postmeta as sort ON ( $wpdb->posts.ID = sort.post_id AND sort.meta_key = '$sort')";
            $sort_sql = "sort.meta_value $sort_dir";
        } elseif ( $sort === "locations" || $sort === "groups" || $sort === "leaders" ){
            $sort_join = "LEFT JOIN $wpdb->p2p as sort ON ( sort.p2p_from = $wpdb->posts.ID AND sort.p2p_type = '" . $post_type . "_to_$sort' ) 
            LEFT JOIN $wpdb->posts as p2p_post ON (p2p_post.ID = sort.p2p_to)";
            $sort_sql = "ISNULL(p2p_post.post_name), p2p_post.post_name $sort_dir";
        } elseif ( $sort === "post_date" ){
            $sort_sql = "$wpdb->posts.post_date  " . $sort_dir;
        }


        // phpcs:disable
        $prepared_sql = $wpdb->prepare("
            SELECT SQL_CALC_FOUND_ROWS $wpdb->posts.ID, $wpdb->posts.post_title, $wpdb->posts.post_type FROM $wpdb->posts
            " . $sort_join . " " . $inner_joins . " " . $share_joins . " " . $access_joins . "
            WHERE 1=1 
            " . $post_type_check . " " . $connections_sql_to . " ". $connections_sql_from . " " . $meta_query . " " . $includes_query . " " . $access_query . "
            AND $wpdb->posts.post_type = %s
            AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_status = 'private')
            GROUP BY $wpdb->posts.ID 
            ORDER BY " . $sort_sql . "
            LIMIT %d, 100
            ",
            esc_sql( $post_type ),
            $offset
        );
        $posts = $wpdb->get_results( $prepared_sql, OBJECT );
        // phpcs:enable
        $total_rows = $wpdb->get_var( "SELECT found_rows();" );

        return [
            "posts" => $posts,
            "total" => $total_rows,
        ];
    }


    /**
     * Gets an array of users whom the post is shared with.
     *
     * @param string $post_type
     * @param int $post_id
     *
     * @param bool $check_permissions
     *
     * @return array|mixed
     */
    public static function get_shared_with( string $post_type, int $post_id, bool $check_permissions = false )
    {
        global $wpdb;

        if ( $check_permissions && !self::can_update( $post_type, $post_id ) ) {
            return new WP_Error( 'no_permission', __( "You do not have permission for this" ), [ 'status' => 403 ] );
        }

        $shared_with_list = [];
        $shares = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                *
            FROM
                `$wpdb->dt_share`
            WHERE
                post_id = %s",
            $post_id
        ), ARRAY_A );

        // adds display name to the array
        foreach ( $shares as $share ) {
            $display_name = dt_get_user_display_name( $share['user_id'] );
            if ( is_wp_error( $display_name ) ) {
                $display_name = 'Not Found';
            }
            $share['display_name'] = $display_name;
            $shared_with_list[] = $share;
        }

        return $shared_with_list;
    }

    /**
     * Removes share record
     *
     * @param string $post_type
     * @param int    $post_id
     * @param int    $user_id
     *
     * @return false|int|WP_Error
     */
    public static function remove_shared( string $post_type, int $post_id, int $user_id )
    {
        global $wpdb;

        if ( !self::can_update( $post_type, $post_id ) ) {
            $name = dt_get_user_display_name( $user_id );
            return new WP_Error( __FUNCTION__, __( "You do not have permission to unshare with" ) . " " . $name, [ 'status' => 403 ] );
        }

        $assigned_to_meta = get_post_meta( $post_id, "assigned_to", true );
        if ( !( current_user_can( 'update_any_' . $post_type ) ||
             get_current_user_id() === $user_id ||
            dt_get_user_id_from_assigned_to( $assigned_to_meta ) === get_current_user_id() )
        ){
            $name = dt_get_user_display_name( $user_id );
            return new WP_Error( __FUNCTION__, __( "You do not have permission to unshare with" ) . " " . $name, [ 'status' => 403 ] );
        }


        $table = $wpdb->dt_share;
        $where = [
        'user_id' => $user_id,
        'post_id' => $post_id
        ];
        $result = $wpdb->delete( $table, $where );

        if ( $result == false ) {
            return new WP_Error( 'remove_shared', __( "Record not deleted." ), [ 'status' => 418 ] );
        } else {

            // log share activity
            dt_activity_insert(
                [
                    'action'         => 'remove',
                    'object_type'    => get_post_type( $post_id ),
                    'object_subtype' => 'share',
                    'object_name'    => get_the_title( $post_id ),
                    'object_id'      => $post_id,
                    'meta_id'        => '', // id of the comment
                    'meta_key'       => '',
                    'meta_value'     => $user_id,
                    'meta_parent'    => '',
                    'object_note'    => 'Sharing of ' . get_the_title( $post_id ) . ' was removed for ' . dt_get_user_display_name( $user_id ),
                ]
            );

            return $result;
        }
    }

    /**
     * Adds a share record
     *
     * @param string $post_type
     * @param int $post_id
     * @param int $user_id
     * @param array $meta
     * @param bool $send_notifications
     *
     * @param bool $check_permissions
     *
     * @return false|int|WP_Error
     */
    public static function add_shared( string $post_type, int $post_id, int $user_id, $meta = null, bool $send_notifications = true, $check_permissions = true )
    {
        global $wpdb;

        if ( $check_permissions && !self::can_update( $post_type, $post_id ) ) {
            return new WP_Error( __FUNCTION__, __( "You do not have permission for this" ), [ 'status' => 403 ] );
        }

        $table = $wpdb->dt_share;
        $data = [
            'user_id' => $user_id,
            'post_id' => $post_id,
            'meta'    => $meta,
        ];
        $format = [
            '%d',
            '%d',
            '%s',
        ];

        $duplicate_check = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                id
            FROM
                `$wpdb->dt_share`
            WHERE
                post_id = %s
                AND user_id = %s",
            $post_id,
            $user_id
        ), ARRAY_A );

        if ( is_null( $duplicate_check ) ) {

            // insert share record
            $results = $wpdb->insert( $table, $data, $format );

            // log share activity
            dt_activity_insert(
                [
                    'action'         => 'share',
                    'object_type'    => get_post_type( $post_id ),
                    'object_subtype' => 'share',
                    'object_name'    => get_the_title( $post_id ),
                    'object_id'      => $post_id,
                    'meta_id'        => '', // id of the comment
                    'meta_key'       => '',
                    'meta_value'     => $user_id,
                    'meta_parent'    => '',
                    'object_note'    => strip_tags( get_the_title( $post_id ) ) . ' was shared with ' . dt_get_user_display_name( $user_id ),
                ]
            );

            // Add share notification
            if ( $send_notifications ){
                Disciple_Tools_Notifications::insert_notification_for_share( $user_id, $post_id );
            }

            return $results;
        } else {
            return new WP_Error( 'add_shared', __( "Post already shared with user." ), [ 'status' => 418 ] );
        }
    }

    /**
     * Get most recent activity for the field
     *
     * @param $post_id
     * @param $field_key
     *
     * @return mixed
     */
    public static function get_most_recent_activity_for_field( $post_id, $field_key ){
        global $wpdb;
        $most_recent_activity = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                *
            FROM
                `$wpdb->dt_activity_log`
            WHERE
                `object_id` = %s
                AND `meta_key` = %s
            ORDER BY
                `hist_time` DESC
            LIMIT
                0,1;",
            $post_id,
            $field_key
        ) );
        return $most_recent_activity[0];
    }

    /**
     * Cached version of get_page_by_title so that we're not making unnecessary SQL all the time
     *
     * @param string $title Page title
     * @param string $output Optional. Output type; OBJECT*, ARRAY_N, or ARRAY_A.
     * @param string|array $post_type Optional. Post type; default is 'page'.
     * @param $connection_type
     *
     * @return WP_Post|null WP_Post on success or null on failure
     * @link http://vip.wordpress.com/documentation/uncached-functions/ Uncached Functions
     */
    public static function get_post_by_title_cached( $title, $output = OBJECT, $post_type = 'page', $connection_type ) {
        $cache_key = $connection_type . '_' . sanitize_key( $title );
        $page_id = wp_cache_get( $cache_key, 'get_page_by_title' );
        if ( $page_id === false ) {
            $page = get_page_by_title( $title, OBJECT, $post_type );
            $page_id = $page ? $page->ID : 0;
            wp_cache_set( $cache_key, $page_id, 'get_page_by_title', 3 * HOUR_IN_SECONDS ); // We only store the ID to keep our footprint small
        }
        if ( $page_id ){
            return get_post( $page_id, $output );
        }
        return null;
    }


    public static function get_subassigned_users( $post_id ){
        $users = [];
        $subassigned = get_posts(
            [
                'connected_type'      => 'contacts_to_subassigned',
                'connected_direction' => 'to',
                'connected_items'     => $post_id,
                'nopaging'            => true,
                'suppress_filters'    => false,
                'meta_key'            => "type",
                'meta_value'          => "user"
            ]
        );
        foreach ( $subassigned as $c ) {
            $user_id = get_post_meta( $c->ID, "corresponds_to_user", true );
            if ( $user_id ){
                $users[] = $user_id;
            }
        }
        return $users;
    }

    public static function get_users_following_post( $post_type, $post_id ){
        $users = [];
        $assigned_to_meta = get_post_meta( $post_id, "assigned_to", true );
        $assigned_to = dt_get_user_id_from_assigned_to( $assigned_to_meta );
        if ( $post_type === "contacts" ){
            array_merge( $users, self::get_subassigned_users( $post_id ) );
        }
        $shared_with = self::get_shared_with( $post_type, $post_id, false );
        foreach ( $shared_with as $shared ){
            $users[] = $shared["user_id"];
        }
        $users_follow = get_post_meta( $post_id, "follow", false );
        foreach ( $users_follow as $follow ){
            if ( !in_array( $follow, $users ) && user_can( $follow, "view_any_". $post_type ) ){
                $users[] = $follow;
            }
        }
        $users_unfollow = get_post_meta( $post_id, "unfollow", false );
        foreach ( $users_unfollow as $unfollower ){
            if ( ( $key = array_search( $unfollower, $users ) ) !== false ){
                unset( $users[$key] );
            }
        }
        //you always follow a post if you are assigned to it.
        if ( $assigned_to ){
            $users[] = $assigned_to;
        }
        return array_unique( $users );
    }
}
