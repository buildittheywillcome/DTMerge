<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Contacts_Endpoints
 */
class Disciple_Tools_Contacts_Endpoints
{

    /**
     * @var object Public_Hooks instance variable
     */
    private static $_instance = null;

    /**
     * Public_Hooks. Ensures only one instance of Public_Hooks is loaded or can be loaded.
     *
     * @return Disciple_Tools_Contacts_Endpoints instance
     */
    public static function instance()
    {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    } // End instance()

    /**
     * The Public_Hooks rest api variables
     */
    private $version = 1;
    private $context = "dt";
    private $namespace;
    private $contacts_instance;
    private $api_keys_controller;

    /**
     * Disciple_Tools_Contacts_Endpoints constructor.
     */
    public function __construct()
    {
        $this->namespace = $this->context . "/v" . intval( $this->version );
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );

        require_once( 'contacts.php' );
        $this->contacts_instance = new Disciple_Tools_Contacts();

        $this->api_keys_controller = Disciple_Tools_Api_Keys::instance();
    }

    /**
     * Add the api routes
     */
    public function add_api_routes()
    {
        register_rest_route(
            $this->namespace, '/dt-public/contact/create', [
                'methods'  => 'POST',
                'callback' => [ $this, 'public_create_contact' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/create', [
                "methods"  => "POST",
                "callback" => [ $this, 'create_contact' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)', [
                "methods"  => "GET",
                "callback" => [ $this, 'get_contact' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)', [
                "methods"  => "POST",
                "callback" => [ $this, 'update_contact' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/dt-public/contact/update', [
                'methods'  => 'POST',
                'callback' => [ $this, 'public_update_contact' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/user/(?P<user_id>\d+)/contacts', [
                "methods"  => "GET",
                "callback" => [ $this, 'get_user_contacts' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contacts', [
                "methods"  => "GET",
                "callback" => [ $this, 'get_viewable_contacts' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contacts/search', [
                "methods"  => "GET",
                "callback" => [ $this, 'search_viewable_contacts' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contacts/compact', [
                "methods"  => "GET",
                "callback" => [ $this, 'get_contacts_compact' ],
            ]
        );
//        register_rest_route(
//            $this->namespace, '/user/(?P<user_id>\d+)/team/contacts', [
//                "methods"  => "GET",
//                "callback" => [ $this, 'get_team_contacts' ],
//            ]
//        );
        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/comments', [
                "methods"  => "GET",
                "callback" => [ $this, 'get_comments' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/comment', [
                "methods"  => "POST",
                "callback" => [ $this, 'post_comment' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/comment/update', [
                "methods"  => "POST",
                "callback" => [ $this, 'update_comment' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/comment', [
                "methods"  => "DELETE",
                "callback" => [ $this, 'delete_comment' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/activity', [
                "methods"  => "GET",
                "callback" => [ $this, 'get_activity' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/activity/(?P<activity_id>\d+)', [
                "methods"  => "GET",
                "callback" => [ $this, 'get_single_activity' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/revert/(?P<activity_id>\d+)', [
                "methods"  => "GET",
                "callback" => [ $this, 'revert_activity' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/accept', [
                "methods"  => "POST",
                "callback" => [ $this, 'accept_contact' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/shared-with', [
                "methods"  => "GET",
                "callback" => [ $this, 'shared_with' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/remove-shared', [
                "methods"  => "POST",
                "callback" => [ $this, 'remove_shared' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/contact/(?P<id>\d+)/add-shared', [
                "methods"  => "POST",
                "callback" => [ $this, 'add_shared' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/contact/tags', [
                "methods" => "GET",
                "callback" => [ $this, 'get_tag_options' ]
            ]
        );
        register_rest_route(
            $this->namespace, '/contacts/mergedetails', [
                "methods" => "GET",
                "callback" => [ $this, 'get_viewable_contacts' ]
            ]
        );
    }

    /**
     * Check to see if the client_id and the client_token are set and see if they are valid
     *
     * @param  $query_params
     *
     * @access private
     * @since  0.1.0
     * @return bool
     */
    private function check_api_token( $query_params )
    {
        if ( isset( $query_params['client_id'] ) && isset( $query_params['client_token'] ) ) {
            return $this->api_keys_controller->check_api_key( $query_params['client_id'], $query_params['client_token'] );
        }
    }

    /**
     * Create a contact from the PUBLIC api.
     *
     * @param  WP_REST_Request $request as application/json
     *
     * @access public
     * @since  0.1.0
     * @return array|WP_Error The new contact Id on success, an error on failure
     */
    public function public_create_contact( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $site_key = Site_Link_System::verify_transfer_token( $params['transfer_token'] );
        if ( !$site_key ){
            return new WP_Error(
                "contact_creation_error",
                "Invalid or missing transfer_token", [ 'status' => 401 ]
            );
        }

        if ( isset( $params["fields"] ) ) {
            $result = Disciple_Tools_Contacts::create_contact( $params["fields"], false );
            return $result; // Could be permission WP_Error
        } else {
            return new WP_Error(
                "contact_creation_error",
                "missing fields param", [ 'status' => 401 ]
            );
        }
    }

    /**
     * Create a contact
     *
     * @param  WP_REST_Request $request
     *
     * @access public
     * @since  0.1.0
     * @return string|array The contact on success
     */
    public function create_contact( WP_REST_Request $request )
    {
        $fields = $request->get_json_params();
        $result = Disciple_Tools_Contacts::create_contact( $fields, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            "post_id"   => (int) $result,
            "permalink" => get_post_permalink( $result ),
        ];
    }

    /**
     * Get a single contact by ID
     *
     * @param  WP_REST_Request $request
     *
     * @access public
     * @since  0.1.0
     * @return array|WP_Error The contact on success
     */
    public function get_contact( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['id'] ) ) {
            $result = Disciple_Tools_Contacts::get_contact( $params['id'], true );

            return $result; // Could be permission WP_Error
        } else {
            return new WP_Error( "get_contact_error", "Please provide a valid id", [ 'status' => 400 ] );
        }
    }

    /**
     * Update a single contact by ID
     *
     * @param  WP_REST_Request $request
     *
     * @access public
     * @since  0.1.0
     * @return WP_REST_Response|WP_Error Contact_id on success
     */
    public function update_contact( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $body = $request->get_json_params();
        if ( isset( $params['id'] ) ) {
            $result = Disciple_Tools_Contacts::update_contact( $params['id'], $body, true );
            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( "update_contact", "Missing a valid contact id", [ 'status' => 400 ] );
        }
    }

    /**
     * Update a contact from the PUBLIC api.
     *
     * @param  WP_REST_Request $request as application/json
     *
     * @access public
     * @since  0.1.0
     * @return array|WP_Error The new contact Id on success, an error on failure
     */
    public function public_update_contact( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $site_key = Site_Link_System::verify_transfer_token( $params['transfer_token'] );
        if ( !$site_key ){
            return new WP_Error(
                "contact_creation_error",
                "Invalid or missing transfer_token", [ 'status' => 401 ]
            );
        }
        if ( isset( $params["fields"] ) && isset( $params["contact_id"] ) ) {
            $result = Disciple_Tools_Contacts::update_contact( $params["contact_id"], $params["fields"], false );

            return $result; // Could be permission WP_Error
        } else {
            return new WP_Error(
                "contact_creation_error",
                "Invalid or missing fields or contact_id", [ 'status' => 401 ]
            );
        }
    }


    /**
     * @param array $contacts
     *
     * @return array
     */
    private function add_related_info_to_contacts( array $contacts ): array
    {
        p2p_type( 'contacts_to_locations' )->each_connected( $contacts, [], 'locations' );
        p2p_type( 'contacts_to_groups' )->each_connected( $contacts, [], 'groups' );
        $rv = [];
        foreach ( $contacts as $contact ) {
            $meta_fields = get_post_custom( $contact->ID );
            $contact_array = [];
            $contact_array["ID"] = $contact->ID;
            $contact_array["post_title"] = $contact->post_title;
            $contact_array["is_team_contact"] = $contact->is_team_contact ?? false;
            $contact_array['permalink'] = get_post_permalink( $contact->ID );
            $contact_array['overall_status'] = get_post_meta( $contact->ID, 'overall_status', true );
            $contact_array['locations'] = [];
            foreach ( $contact->locations as $location ) {
                $contact_array['locations'][] = $location->post_title;
            }
            $contact_array['groups'] = [];
            foreach ( $contact->groups as $group ) {
                $contact_array['groups'][] = [
                    'id'         => $group->ID,
                    'post_title' => $group->post_title,
                    'permalink'  => get_permalink( $group->ID ),
                ];
            }
            $contact_array['phone_numbers'] = [];
            $contact_array['requires_update'] = false;
            foreach ( $meta_fields as $meta_key => $meta_value ) {
                if ( strpos( $meta_key, "contact_phone" ) === 0 && strpos( $meta_key, "details" ) === false ) {
                    $contact_array['phone_numbers'] = array_merge( $contact_array['phone_numbers'], $meta_value );
                } elseif ( strpos( $meta_key, "milestone_" ) === 0 ) {
                    $contact_array[ $meta_key ] = $this->yes_no_to_boolean( $meta_value[0] );
                } elseif ( $meta_key === "seeker_path" ) {
                    $contact_array[ $meta_key ] = $meta_value[0] ? $meta_value[0] : "none";
                } elseif ( $meta_key == "assigned_to" ) {
                    $type_and_id = explode( '-', $meta_value[0] );
                    if ( $type_and_id[0] == 'user' && isset( $type_and_id[1] ) ) {
                        $user = get_user_by( 'id', (int) $type_and_id[1] );
                        $contact_array["assigned_to"] = [
                            "id" => $type_and_id[1],
                            "type" => $type_and_id[0],
                            "name" => ( $user ? $user->display_name : "Nobody" ),
                            'user_login' => ( $user ? $user->user_login : "nobody" )
                        ];
                    }
                } elseif ( $meta_key == "requires_update" ) {
                    $contact_array[ $meta_key ] = $this->yes_no_to_boolean( $meta_value[0] );
                } elseif ( $meta_key == 'last_modified' ) {
                    $contact_array[ $meta_key ] = (int) $meta_value[0];
                }
            }

            $user_id = get_current_user_id();
            if ( isset( $contact_array["overall_status"] ) && isset( $contact_array["assigned_to"]["id"] ) &&
                 $contact_array["overall_status"] === "assigned" && $contact_array["assigned_to"]["id"] == $user_id){
                $contact_array["requires_update"] = true;
            }
            $rv[] = $contact_array;
        }
        if (get_current_user_id()) {
            $contacts_shared_with_user = Disciple_Tools_Contacts::get_posts_shared_with_user(
                "contacts", get_current_user_id()
            );
            $ids_shared_with_user = [];
            foreach ( $contacts_shared_with_user as $contact ) {
                $ids_shared_with_user[$contact->ID] = true;
            }
            foreach ($rv as $index => $_) {
                $rv[$index]["shared_with_user"] = isset( $ids_shared_with_user[$rv[$index]["ID"]] );
            }
        }
        return $rv;
    }

    /**
     * @param string $yes_no
     *
     * @return bool
     * @throws \Error|bool 'Expected yes or no'.
     */
    private static function yes_no_to_boolean( string $yes_no )
    {
        if ( $yes_no === 'yes' ) {
            return true;
        } elseif ( $yes_no === 'no' ) {
            return false;
        } else {
            return false;
//            @todo move error to saving
//            throw new Error( "Expected yes or no, instead got $yes_no" );
        }
    }


    /**
     * Get Contacts assigned to a user
     *
     * @param  WP_REST_Request $request
     *
     * @access public
     * @since  0.1.0
     * @return array|WP_Error return the user's contacts
     */
    public function get_user_contacts( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['user_id'] ) ) {
            $contacts = Disciple_Tools_Contacts::get_user_contacts( (int) $params['user_id'], true );
            if ( is_wp_error( $contacts ) ) {
                return $contacts;
            }

            return $this->add_related_info_to_contacts( $contacts->posts );
        } else {
            return new WP_Error( "get_user_contacts", "Missing a valid user id", [ 'status' => 400 ] );
        }
    }

    /**
     * Get Contacts viewable by a user
     *
     * @param  WP_REST_Request $request
     *
     * @access public
     * @since  0.1.0
     * @return array|WP_Error return the user's contacts
     */
    public function get_viewable_contacts( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $most_recent = isset( $params["most_recent"] ) ? $params["most_recent"] : 0;
        $result = Disciple_Tools_Contacts::get_viewable_contacts( (int) $most_recent, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            "contacts" => $this->add_related_info_to_contacts( $result["contacts"] ),
            "total" => $result["total"],
            "deleted" => $result["deleted"]
        ];
    }

    public function search_viewable_contacts( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $result = Disciple_Tools_Contacts::search_viewable_contacts( $params, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            "contacts" => $this->add_related_info_to_contacts( $result["contacts"] ),
            "total" => $result["total"],
        ];
    }

    /**
     * Get Contact assigned to a user's team
     *
     * @param  WP_REST_Request $request
     *
     * @access public
     * @since  0.1.0
     * @return array|WP_Error return the user's team's contacts
     */
    public function get_team_contacts( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['user_id'] ) ) {
            $result = Disciple_Tools_Contacts::get_team_contacts( $params['user_id'], true );

            return $result; // Could be permission WP_Error
        } else {
            return new WP_Error( "get_team_contacts", "Missing a valid user id", [ 'status' => 400 ] );
        }
    }


    /**
     * @param \WP_REST_Request $request
     *
     * @return false|int|\WP_Error|\WP_REST_Response
     */
    public function post_comment( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $body = $request->get_json_params();
        if ( isset( $params['id'] ) && isset( $body['comment'] ) ) {
            $result = Disciple_Tools_Contacts::add_comment( $params['id'], $body["comment"], true );

            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                $comment = get_comment( $result );

                return new WP_REST_Response( [
                    "comment_id" => $result,
                    "comment" => $comment
                ] );
            }
        } else {
            return new WP_Error( "post_comment", "Missing a valid contact id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return false|int|\WP_Error|\WP_REST_Response
     */
    public function update_comment( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $body = $request->get_json_params();
        if ( isset( $params['id'] ) && isset( $body['comment_ID'] ) && isset( $body['comment_content'] ) ) {
            return Disciple_Tools_Contacts::update_comment( $params['id'], $body["comment_ID"], $body["comment_content"], true );
        } else {
            return new WP_Error( "post_comment", "Missing a valid contact id, comment id or missing new comment.", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return false|int|\WP_Error|\WP_REST_Response
     */
    public function delete_comment( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $body = $request->get_json_params();
        if ( isset( $params['id'] ) && isset( $body['comment_ID'] ) ) {
            return Disciple_Tools_Contacts::delete_comment( $params['id'], $body["comment_ID"], true );
        } else {
            return new WP_Error( "post_comment", "Missing a valid contact id or comment id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return array|int|\WP_Error|\WP_REST_Response
     */
    public function get_comments( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['id'] ) ) {
            $result = Disciple_Tools_Contacts::get_comments( $params['id'], true );

            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( "get_comments", "Missing a valid contact id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return array|null|object|\WP_Error|\WP_REST_Response
     */
    public function get_activity( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['id'] ) ) {
            $result = Disciple_Tools_Contacts::get_activity( $params['id'] );
            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( "get_activity", "Missing a valid contact id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return array|null|object|\WP_Error|\WP_REST_Response
     */
    public function get_single_activity( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['id'] ) && isset( $params["activity_id"] ) ) {
            $result = Disciple_Tools_Contacts::get_single_activity( $params['id'], $params["activity_id"] );
            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( "get_activity", "Missing a valid contact id or activity id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return array|null|object|\WP_Error|\WP_REST_Response
     */
    public function revert_activity( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['id'] ) && isset( $params["activity_id"] ) ) {
            $result = Disciple_Tools_Contacts::revert_activity( $params['id'], $params["activity_id"] );
            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( "get_activity", "Missing a valid contact id or activity id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return array|\WP_Error|\WP_REST_Response
     */
    public function accept_contact( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $body = $request->get_json_params();
        if ( isset( $params['id'] ) ) {
            $result = Disciple_Tools_Contacts::accept_contact( $params['id'], $body["accept"], true );

            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( "accept_contact", "Missing a valid contact id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return array|mixed|\WP_Error|\WP_REST_Response
     */
    public function shared_with( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['id'] ) ) {
            $result = Disciple_Tools_Contacts::get_shared_with_on_contact( $params['id'] );

            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( 'shared_with', "Missing a valid contact id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return false|int|\WP_Error|\WP_REST_Response
     */
    public function remove_shared( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['id'] ) ) {
            $result = Disciple_Tools_Contacts::remove_shared_on_contact( $params['id'], $params['user_id'] );

            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( 'remove_shared', "Missing a valid contact id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return false|int|\WP_Error|\WP_REST_Response
     */
    public function add_shared( WP_REST_Request $request )
    {
        $params = $request->get_params();
        if ( isset( $params['id'] ) && isset( $params['user_id'] ) ) {
            $result = Disciple_Tools_Contacts::add_shared_on_contact( (int) $params['id'], (int) $params['user_id'] );

            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( 'add_shared', "Missing a valid contact or user id", [ 'status' => 400 ] );
        }
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return array|\WP_Query
     */
    public function get_contacts_compact( WP_REST_Request $request )
    {
        $params = $request->get_params();
        $search = "";
        if ( isset( $params['s'] ) ) {
            $search = $params['s'];
        }
        $contacts = Disciple_Tools_Contacts::get_viewable_contacts_compact( $search );

        return $contacts;
    }


    public function get_tag_options( WP_REST_Request $request ){
        $params = $request->get_params();
        return Disciple_Tools_Contacts::get_tag_options();
    }
}
