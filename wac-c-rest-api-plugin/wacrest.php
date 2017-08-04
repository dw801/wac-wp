<?php
/*
Plugin Name: WAC REST API Plugin
Description: REST stuff.
Version: 1.4.0
Author: Dave Wilson

For shared hosting, requires the following in .htaccess.  NOTE: .htaccess will likely need to be updated every time Wordpress is upgraded!!

	# WP REST API 
	RewriteEngine on
	RewriteCond %{HTTP:Authorization} ^(.*)
	RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
	SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
	# END WP REST API
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* WAC REST controller class for groups */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

class Wac_groups_REST_Controller {
	private $wpdb;
	
    /***
     * groups : Initialize namespace, resource, etc.
     */
    public function __construct() {
		// DB reference
		global $wpdb;
		$this->wpdb = &$wpdb;
		
		// Namespace.
        $this->namespace     = '/wac/v1';
		
		// Route.
        $this->resource_name = 'groups';
		
		// Pagination defaults.
		$this->page = 1;
		$this->per_page = 100;
		$this->offset = 0;
		
		// Parm error structure
		$parmerror = array( "code" => "rest_invalid_param",
							"message" => "Invalid parameter(s): ",
							"data" => array ( 	"status" => 400,
												"params" => array () ) );	
    }
 
    /***
     * groups : Register routes.
     */
    public function register_routes() {
		// Register a route to return a list of groups
        register_rest_route( $this->namespace, '/' . $this->resource_name, array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_results' ),
                'permission_callback' => array( $this, 'get_permissions_check' ),
            ),
            // Register our schema callback.
           'schema' => array( $this, 'get_schema' ),
        ) );
    }
 
    /***
     * Check permissions: groups, @param WP_REST_Request $request Current request.
     */
    public function get_permissions_check( $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the groups resource.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    /***
     * groups : Prepare results, @param WP_REST_Request $request Current request.
     */
    public function get_results( $request ) {
		// Set default parms and check for overrides
		$page = $this->page;
		$per_page = $this->per_page;
		$offset = $this->offset;
		// Capture and validate override parms
		if ( isset( $request['page'] ) ) { 
			$page = (int) $request['page'];
			if ( $page == 0 || $page > 1000 ) { // Validate override parm 
				$pe = $parmerror;
				$pe ['message'] .= "page";
				$pe ['data']['params']['page'] = "page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['per_page'] ) ) { 
			$per_page = (int) $request['per_page'];
			if ( $per_page == 0 || $page > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "per_page";
				$pe ['data']['params']['per_page'] = "per_page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['offset'] ) ) { 
			$offset = (int) $request['offset'];
			if ( $offset > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "offset";
				$pe ['data']['params']['offset'] = "offset must be an integer between 0-1000";
				return rest_ensure_response ($pe);	
			}
		}
		$qrylimit = $per_page;
		$qryoffset = ( $per_page * ($page - 1) ) + $offset;				
		
		$tblprefix = trim($this->wpdb->prefix);
		$qry  = "";
		$qry .= "SELECT gg0.group_id group_id ";
		$qry .= "	,REPLACE (gg0.name,' ','-') group_name";
		$qry .= "	,gg0.description group_description ";
		$qry .= "FROM " . $tblprefix . "groups_group gg0 ";
		$qry .= "LIMIT " . $qrylimit . " OFFSET " . $qryoffset . " ";
		
		// Run the query and put the results in an associative array
		$results = $this->wpdb->get_results( $qry, ARRAY_A );		

		return rest_ensure_response( $results );
    }
   
    /***
     * groups : Schema. @param WP_REST_Request $request Current request.
     */
    public function get_schema( $request ) {
        $schema = array(
            // This tells the spec of JSON Schema we are using which is draft 4.
            '$schema'              => 'http://json-schema.org/draft-04/schema#',
            // The title property marks the identity of the resource.
            'title'                => 'groups',
            'type'                 => 'object',
            // In JSON Schema you can specify object properties in the properties attribute.
            'properties'           => array(
                'group_id' => array(
                    'description'  => esc_html__( 'Unique identifier for the group.', 'my-textdomain' ),
                    'type'         => 'integer',
                ),
                'group_name' => array(
                    'description'  => esc_html__( 'Name of the group. Used as route to group members.', 'my-textdomain' ),
                    'type'         => 'string',
                ),
                'group_description' => array(
                    'description'  => esc_html__( 'Description of what the group is for.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
            ),
        );
 
        return $schema;
    }
  
    // Sets up the proper HTTP status code for authorization.
    public function authorization_status_code() {
 
        $status = 401;
 
        if ( is_user_logged_in() ) {
            $status = 403;
        }
 
        return $status;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* WAC REST controller class for groupmembers */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

class Wac_groupmembers_REST_Controller {
	private $wpdb;
	
    /***
     * groupmembers : Initialize namespace, resource, etc.
     */
    public function __construct() {
		// DB reference
		global $wpdb;
		$this->wpdb = &$wpdb;
		
		// Namespace.
        $this->namespace     = '/wac/v1';
		
		// Route.
		$this->resource_name = 'groupmembers';
		
		// Pagination defaults.
		$this->page = 1;
		$this->per_page = 100;
		$this->offset = 0;
		
		// Parm error structure
		$parmerror = array( "code" => "rest_invalid_param",
							"message" => "Invalid parameter(s): ",
							"data" => array ( 	"status" => 400,
												"params" => array () ) );	
    }
 
    /***
     * groupmembers : Register routes.
     */
    public function register_routes() {
		// Register a route which returns members of a group - group name as route
        register_rest_route( $this->namespace, '/' . $this->resource_name . '/(?P<grp>([\da-zA-Z\- _]{1,30}))', array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_results' ),
                'permission_callback' => array( $this, 'get_permissions_check' ),
            ),
            // Register our schema callback.
            'schema' => array( $this, 'get_schema' ),
        ) );
		// Register a route which returns members of a group - this registration enables schema results when no group route provided.
		register_rest_route( $this->namespace, '/' . $this->resource_name, array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_results' ),
                'permission_callback' => array( $this, 'get_permissions_check' ),
            ),
            // Register our schema callback.
            'schema' => array( $this, 'get_schema' ),
        ) );
    }

    /***
     * groupmembers : Check permissions, @param WP_REST_Request $request Current request.
     */
    public function get_permissions_check( $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the item resource.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    /**
     * groupmembers : Prepare results, @param WP_REST_Request $request Current request.
     */
    public function get_results ( $request ) {
        $grp = $request['grp'];
		
		// Set default parms and check for overrides
		$page = $this->page;
		$per_page = $this->per_page;
		$offset = $this->offset;
		// Capture and validate override parms
		if ( isset( $request['page'] ) ) { 
			$page = (int) $request['page'];
			if ( $page == 0 || $page > 1000 ) { // Validate override parm 
				$pe = $parmerror;
				$pe ['message'] .= "page";
				$pe ['data']['params']['page'] = "page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['per_page'] ) ) { 
			$per_page = (int) $request['per_page'];
			if ( $per_page == 0 || $page > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "per_page";
				$pe ['data']['params']['per_page'] = "per_page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['offset'] ) ) { 
			$offset = (int) $request['offset'];
			if ( $offset > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "offset";
				$pe ['data']['params']['offset'] = "offset must be an integer between 0-1000";
				return rest_ensure_response ($pe);	
			}
		}
		$qrylimit = $per_page;
		$qryoffset = ( $per_page * ($page - 1) ) + $offset;				
		
        $tblprefix = trim($this->wpdb->prefix);
		$qry  = "";
		$qry .= "SELECT u0.ID user_id ";
		$qry .= "	,u0.user_login user_login ";
		$qry .= "	,u0.user_nicename user_nicename ";
		$qry .= "	,u0.display_name user_display_name ";
		$qry .= "	,u0.user_email user_email ";
		$qry .= "	,REPLACE (gs0.group_name,' ','-') group_name ";
		$qry .= "FROM " . $tblprefix . "users u0 ";
		$qry .= "INNER JOIN ( ";	
		$qry .= "	SELECT gug0.user_id user_id ";
		$qry .= "		,gg0.group_id group_id ";
		$qry .= "		,gg0.name group_name ";
		$qry .= "	FROM " . $tblprefix . "groups_group gg0 ";
		$qry .= "	INNER JOIN " . $tblprefix . "groups_user_group gug0 ";
		$qry .= "		ON gg0.group_id = gug0.group_id ";
		$qry .= "	WHERE gg0.name = '" . $grp . "' ";
		$qry .= "		OR gg0.name = REPLACE ('" . $grp . "','-',' ') ";
		$qry .= "	) gs0 ";
		$qry .= "WHERE u0.ID = gs0.user_id ";	
		$qry .= "LIMIT " . $qrylimit . " OFFSET " . $qryoffset . " ";
		
		// Run the query and put the results in an associative array
		$results = $this->wpdb->get_results( $qry, ARRAY_A );		

		return rest_ensure_response( $results );
    }

    /***
     * groupmembers : Schema, @param WP_REST_Request $request Current request.
     */
    public function get_schema( $request ) {
        $schema = array(
            // This tells the spec of JSON Schema we are using which is draft 4.
            '$schema'              => 'http://json-schema.org/draft-04/schema#',
            // The title property marks the identity of the resource.
            'title'                => 'groupmembers',
            'type'                 => 'object',
            'properties'           => array(
                'user_id' => array(
                    'description'  => esc_html__( 'Unique identifier for the user.', 'my-textdomain' ),
                    'type'         => 'integer',
                ),
                'user_login' => array(
                    'description'  => esc_html__( 'User login.', 'my-textdomain' ),
                    'type'         => 'string',
                ),
                'user_nicename' => array(
                    'description'  => esc_html__( 'User nicename.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
                'user_display_name' => array(
                    'description'  => esc_html__( 'User display name.', 'my-textdomain' ),
                    'type'         => 'string',
                ),                 
				'user_email' => array(
                    'description'  => esc_html__( 'User email address.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'group_name' => array(
                    'description'  => esc_html__( 'Group name.  User may also belong to other groups.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
            ),
        );
 
        return $schema;
    }
  
    // Sets up the proper HTTP status code for authorization.
    public function authorization_status_code() {
 
        $status = 401;
 
        if ( is_user_logged_in() ) {
            $status = 403;
        }
 
        return $status;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* WAC REST controller class for groupsbyuser */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

class Wac_groupsbyuser_REST_Controller {
	private $wpdb;
	
    /***
     * groupsbyuser : Initialize namespace, resource, etc.
     */
    public function __construct() { 
		// DB reference
		global $wpdb;
		$this->wpdb = &$wpdb;
		
		// Namespace.
        $this->namespace     = '/wac/v1';
		
		// Route.
		$this->resource_name = 'groupsbyuser'; 
		
		// Pagination defaults.
		$this->page = 1;
		$this->per_page = 100;
		$this->offset = 0;
		
		// Parm error structure
		$parmerror = array( "code" => "rest_invalid_param",
							"message" => "Invalid parameter(s): ",
							"data" => array ( 	"status" => 400,
												"params" => array () ) );	
    }
 
    /***
     * groupsbyuser : Register routes.
     */
    public function register_routes() {
		// Register a route which returns the groups that a member belongs to - user ID as route.
		register_rest_route( $this->namespace, '/' . $this->resource_name . '/(?P<id>[\d]+)', array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_results' ),
                'permission_callback' => array( $this, 'get_permissions_check' ),
            ),
            // Register our schema callback.
            'schema' => array( $this, 'get_schema' ),
        ) );
		// Register a route which returns the groups that a member belongs to - this registration enables schema results when no group route provided.
		register_rest_route( $this->namespace, '/' . $this->resource_name, array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_results' ),
                'permission_callback' => array( $this, 'get_permissions_check' ),
            ),
            // Register our schema callback.
            'schema' => array( $this, 'get_schema' ),
        ) );
    }

    /***
     * groupsbyuser : Check permissions, @param WP_REST_Request $request Current request.
     */
    public function get_permissions_check( $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the item resource.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    /***
     * groupsbyuser : Pepare results, @param WP_REST_Request $request Current request.
     */
    public function get_results( $request ) {
        $id = (int) $request['id'];
		
		// Set default parms and check for overrides
		$page = $this->page;
		$per_page = $this->per_page;
		$offset = $this->offset;
		// Capture and validate override parms
		if ( isset( $request['page'] ) ) { 
			$page = (int) $request['page'];
			if ( $page == 0 || $page > 1000 ) { // Validate override parm 
				$pe = $parmerror;
				$pe ['message'] .= "page";
				$pe ['data']['params']['page'] = "page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['per_page'] ) ) { 
			$per_page = (int) $request['per_page'];
			if ( $per_page == 0 || $page > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "per_page";
				$pe ['data']['params']['per_page'] = "per_page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['offset'] ) ) { 
			$offset = (int) $request['offset'];
			if ( $offset > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "offset";
				$pe ['data']['params']['offset'] = "offset must be an integer between 0-1000";
				return rest_ensure_response ($pe);	
			}
		}
		$qrylimit = $per_page;
		$qryoffset = ( $per_page * ($page - 1) ) + $offset;				
		
        $tblprefix = trim($this->wpdb->prefix);
		$qry  = "";
		$qry .= "SELECT gg0.group_id ";
		$qry .= "	,REPLACE (gg0.name,' ','-') group_name ";
		$qry .= "	,gug0.user_id user_id ";
		$qry .= "FROM " . $tblprefix . "groups_group gg0 ";
		$qry .= "INNER JOIN " . $tblprefix . "groups_user_group gug0 ";
		$qry .= "ON gg0.group_id = gug0.group_id ";
		$qry .= "WHERE gug0.user_id = " . $id . " ";
		$qry .= "LIMIT " . $qrylimit . " OFFSET " . $qryoffset . " ";
	
		// Run the query and put the results in an associative array
		$results = $this->wpdb->get_results( $qry, ARRAY_A );		

		return rest_ensure_response( $results );
    }
  
    /***
     * groupsbyuser : Schema, @param WP_REST_Request $request Current request.
     */
    public function get_schema( $request ) {
        $schema = array(
            // This tells the spec of JSON Schema we are using which is draft 4.
            '$schema'              => 'http://json-schema.org/draft-04/schema#',
            // The title property marks the identity of the resource.
            'title'                => 'groupsbyuser',
            'type'                 => 'object',
            // In JSON Schema you can specify object properties in the properties attribute.
            'properties'           => array(
                'group_id' => array(
                    'description'  => esc_html__( 'Unique identifier for the group.', 'my-textdomain' ),
                    'type'         => 'integer',
                ),
                'group_name' => array(
                    'description'  => esc_html__( 'Name of the group.', 'my-textdomain' ),
                    'type'         => 'string',
                ),
                'user_id' => array(
                    'description'  => esc_html__( 'User_id of the user belonging to the group.', 'my-textdomain' ),
                    'type'         => 'integer',
                ), 
            ),
        );
 
        return $schema;
    }
   
    // Sets up the proper HTTP status code for authorization.
    public function authorization_status_code() {
 
        $status = 401;
 
        if ( is_user_logged_in() ) {
            $status = 403;
        }
 
        return $status;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* WAC REST controller class for expdates*/
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

class Wac_expdates_REST_Controller {
	private $wpdb;
	
    /***
     * expdates : Initialize namespace, resource, etc.
     */
    public function __construct() {
		// DB reference
		global $wpdb;
		$this->wpdb = &$wpdb;
		
		// Namespace.
        $this->namespace     = '/wac/v1';
		
		// Route.
		$this->resource_name = 'expdates';
		
		// Pagination defaults.
		$this->page = 1;
		$this->per_page = 100;
		$this->offset = 0;
		
		// Parm error structure
		$parmerror = array( "code" => "rest_invalid_param",
							"message" => "Invalid parameter(s): ",
							"data" => array ( 	"status" => 400,
												"params" => array () ) );	
    }
 
    /***
     * expdates : Register routes.
     */
    public function register_routes() {
		// Register a route which returns member expiration dates.
		register_rest_route( $this->namespace, '/' . $this->resource_name, array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_results' ),
                'permission_callback' => array( $this, 'get_permissions_check' ),
            ),
            // Register our schema callback.
            'schema' => array( $this, 'get_schema' ),
        ) );
		// Register a route which returns member expiration dates for a specific member - user_id provided in path.
		register_rest_route( $this->namespace, '/' . $this->resource_name . '/(?P<id>[\d]+)', array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_results' ),
                'permission_callback' => array( $this, 'get_permissions_check' ),
            ),
            // Register our schema callback. 
            'schema' => array( $this, 'get_schema' ),
        ) );		
    }
	
    /***
     * expdates : Check permissions, @param WP_REST_Request $request Current request.
     */
    public function get_permissions_check( $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the item resource.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }	
  
    /***
     * expdates : Prepare results, @param WP_REST_Request $request Current request.
     */
    public function get_results( $request ) {
        $id = (int) $request['id'];
		
		// Set default parms and check for overrides
		$page = $this->page;
		$per_page = $this->per_page;
		$offset = $this->offset;
		// Capture and validate override parms
		if ( isset( $request['page'] ) ) { 
			$page = (int) $request['page'];
			if ( $page == 0 || $page > 1000 ) { // Validate override parm 
				$pe = $parmerror;
				$pe ['message'] .= "page";
				$pe ['data']['params']['page'] = "page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['per_page'] ) ) { 
			$per_page = (int) $request['per_page'];
			if ( $per_page == 0 || $page > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "per_page";
				$pe ['data']['params']['per_page'] = "per_page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['offset'] ) ) { 
			$offset = (int) $request['offset'];
			if ( $offset > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "offset";
				$pe ['data']['params']['offset'] = "offset must be an integer between 0-1000";
				return rest_ensure_response ($pe);	
			}
		}
		$qrylimit = $per_page;
		$qryoffset = ( $per_page * ($page - 1) ) + $offset;		
		
        $tblprefix = trim($this->wpdb->prefix);
		$qry  = "";
		$qry .= "SELECT ";
		$qry .= "	 u0.ID user_id ";
		$qry .= "	,u0.user_login user_login ";
		$qry .= "	,u0.display_name display_name ";
		$qry .= "	,u0.user_email user_email ";
		$qry .= "	,(SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'address') address ";
		$qry .= "	,(SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'city') city ";
		$qry .= "	,(SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'state') state ";
		$qry .= "	,(SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'zip') zip		 ";
		$qry .= "	,date(u0.user_registered) user_registered ";
		$qry .= "	,date ( ";
		$qry .= "		GREATEST ( ";
		$qry .= "			 COALESCE ( DATE_SUB(s0.end_date,INTERVAL '11:30' HOUR_MINUTE),0) ";
		$qry .= "			,COALESCE ( STR_TO_DATE ((SELECT meta_value FROM " . $tblprefix . "usermeta WHERE meta_key = 'membership_expiration' AND user_id = u0.id),'%m-%d-%Y'),0) ";
		$qry .= "		) ";
		$qry .= "	) expiration_date ";
		$qry .= "	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND 	gg0.name = 'Active Members') 'Active Members' ";
		$qry .= "	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'Members') 'Members' ";
		$qry .= "	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'Annual Cabin User') 'Annual Cabin User' ";
		$qry .= "	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'CWaiver') 'CWaiver' ";
		$qry .= "	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'NWaiver') 'NWaiver' ";
		$qry .= "	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'RWaiver') 'RWaiver' ";	
		$qry .= "	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'MWaiver') 'MWaiver' ";
		$qry .= "FROM " . $tblprefix . "users u0 ";
		$qry .= "LEFT OUTER JOIN	 ";
		$qry .= "	( ";
		$qry .= "	SELECT pm0.meta_value user_id ";
		$qry .= "	,woi0.order_item_name subscription_type ";
		$qry .= "	,max(pm1.meta_value) end_date ";
		$qry .= "	FROM " . $tblprefix . "postmeta pm0 ";
		$qry .= "	INNER JOIN " . $tblprefix . "woocommerce_order_items woi0 ";
		$qry .= "	ON pm0.post_id = woi0.order_id ";
		$qry .= "	INNER JOIN " . $tblprefix . "postmeta pm1 ";
		$qry .= "	ON pm1.post_id = pm0.post_id ";
		$qry .= "	WHERE pm1.meta_key = '_schedule_end' ";
		$qry .= "	AND (woi0.order_item_name = 'Membership Renewal' ";
		$qry .= "		OR woi0.order_item_name = 'New Membership' ";
		$qry .= "		OR woi0.order_item_name = 'Senior & Out-of-State Membership Renewal' ";
		$qry .= "		OR woi0.order_item_name = 'Senior & Out-of-State New Membership') ";
		$qry .= "	AND pm0.meta_key = '_customer_user' ";
		$qry .= "	GROUP BY pm0.meta_value ";
		$qry .= "	) s0 ";
		$qry .= "ON u0.ID = s0.user_id ";
		if ($id > 0) {
			$qry .= "WHERE u0.ID = " . $id . " ";
		}
		$qry .= "ORDER BY u0.ID DESC ";
		$qry .= "LIMIT " . $qrylimit . " OFFSET " . $qryoffset . " ";
		
		// Run the query and put the results in an associative array
		$results = $this->wpdb->get_results( $qry, ARRAY_A );		

		return rest_ensure_response( $results );
    } 
  
    /***
     * expdates : Schema, @param WP_REST_Request $request Current request.
     */
    public function get_schema( $request ) {
        $schema = array(
            // This tells the spec of JSON Schema we are using which is draft 4.
            '$schema'              => 'http://json-schema.org/draft-04/schema#',
            // The title property marks the identity of the resource.
            'title'                => 'expdates',
            'type'                 => 'object',
            // In JSON Schema you can specify object properties in the properties attribute.
            'properties'           => array(
                'user_id' => array(
                    'description'  => esc_html__( 'Unique identifier for the user.', 'my-textdomain' ),
                    'type'         => 'integer',
                ),
                'user_login' => array(
                    'description'  => esc_html__( 'User Login.', 'my-textdomain' ),
                    'type'         => 'string',
                ),
                'display_name' => array(
                    'description'  => esc_html__( 'Display Name.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'user_email' => array(
                    'description'  => esc_html__( 'Email address.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'address' => array(
                    'description'  => esc_html__( 'Home Address.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'city' => array(
                    'description'  => esc_html__( 'City.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'state' => array(
                    'description'  => esc_html__( 'State.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'zip' => array(
                    'description'  => esc_html__( 'ZIP.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'user_registered' => array(
                    'description'  => esc_html__( 'When the user registered. This is not the same as when they became a member.', 'my-textdomain' ),
                    'type'         => 'datetime',
                ), 
				'expiration_date' => array(
                    'description'  => esc_html__( 'When membership did or will expire.  If null then they were never a member.', 'my-textdomain' ),
                    'type'         => 'datetime',
                ), 
				'active_members' => array(
                    'description'  => esc_html__( '1 if an active member, 0 if not.', 'my-textdomain' ),
                    'type'         => 'integer',
                ), 
				'members' => array(
                    'description'  => esc_html__( '1 if a member, 0 if never a member.  Note expired members still get a 1 here.', 'my-textdomain' ),
                    'type'         => 'integer',
                ), 
				'annual-cabin-user' => array(
                    'description'  => esc_html__( '1 if currently paid annual cabin fee, 0 if not.', 'my-textdomain' ),
                    'type'         => 'integer',
                ), 
				'cwaiver' => array(
                    'description'  => esc_html__( '1 if they are an active member with a current waiver on file, 0 if not.  When their membership expires they will be removed from the cwaiver group.', 'my-textdomain' ),
                    'type'         => 'integer',
                ),
				'nwaiver' => array(
                    'description'  => esc_html__( '1 if they have recently signed a waiver to join but have not yet paid dues.  They will be removed from this group and placed in the cwaiver group when they pay for membership.  Otherwise 0.', 'my-textdomain' ),
                    'type'         => 'integer',
                ),
				'rwaiver' => array(
                    'description'  => esc_html__( '1 if they have recently signed a waiver to renew but have not yet paid dues.  They will be removed from this group and placed in the cwaiver group when they pay for membership.  Otherwise 0.', 'my-textdomain' ),
                    'type'         => 'integer',
                ),
				'mwaiver' => array(
                    'description'  => esc_html__( '1 if they have signed a waiver at any time in the past, 0 if not.', 'my-textdomain' ),
                    'type'         => 'integer',
                ), 
				
            ),
        );

        return $schema;
    }
  
    // Sets up the proper HTTP status code for authorization.
    public function authorization_status_code() {
 
        $status = 401;
 
        if ( is_user_logged_in() ) {
            $status = 403;
        }
 
        return $status;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* WAC REST controller class for skus */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

class Wac_skus_REST_Controller {
	private $wpdb;
	
    /***
     * skus : Initialize namespace, resource, etc.
     */
    public function __construct() {
		// DB reference
		global $wpdb;
		$this->wpdb = &$wpdb;
		
		// Namespace.
        $this->namespace     = '/wac/v1';
		
		// Route.
		$this->resource_name = 'skus';
		
		// Pagination defaults.
		$this->page = 1;
		$this->per_page = 100;
		$this->offset = 0;
		
		// Parm error structure
		$parmerror = array( "code" => "rest_invalid_param",
							"message" => "Invalid parameter(s): ",
							"data" => array ( 	"status" => 400,
												"params" => array () ) );	
    }
 
    /***
     * skus : Register routes.
     */
    public function register_routes() {
		// Register a route which returns all skus.
		register_rest_route( $this->namespace, '/' . $this->resource_name, array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_results' ),
                'permission_callback' => array( $this, 'get_permissions_check' ),
            ),
            // Register our schema callback.
            'schema' => array( $this, 'get_schema' ),
        ) );
		// Register a route which returns skus for a specific member - user_id provided in path.
		register_rest_route( $this->namespace, '/' . $this->resource_name . '/(?P<id>[\d]+)', array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_results' ),
                'permission_callback' => array( $this, 'get_permissions_check' ),
            ),
            // Register our schema callback. 
            'schema' => array( $this, 'get_schema' ),
        ) );	
    }
		
    /***
     * skus : Check permissions, @param WP_REST_Request $request Current request.
     */
    public function get_permissions_check( $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the item resource.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }
  
    /***
     * skus : Prepare results, @param WP_REST_Request $request Current request.
     */
    public function get_results( $request ) {
		// Capture id from route if present.
/*		if($request['id'] === null) {
			$uflag = false;
		} else {
			$uflag = true;
			$id = (int) $request['id'];
		}
*/	
		if( isset ($request['id'] ) ) {
			$uflag = true;
			$id = (int) $request['id'];
		} else {
			$uflag = false;
		}		
		
		// Set default parms and check for overrides
		$page = $this->page;
		$per_page = $this->per_page;
		$offset = $this->offset;
		// Capture and validate override parms
		if ( isset( $request['page'] ) ) { 
			$page = (int) $request['page'];
			if ( $page == 0 || $page > 1000 ) { // Validate override parm 
				$pe = $parmerror;
				$pe ['message'] .= "page";
				$pe ['data']['params']['page'] = "page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['per_page'] ) ) { 
			$per_page = (int) $request['per_page'];
			if ( $per_page == 0 || $page > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "per_page";
				$pe ['data']['params']['per_page'] = "per_page must be an integer between 1-1000";
				return rest_ensure_response ($pe);
			}
		}
		if ( isset( $request['offset'] ) ) { 
			$offset = (int) $request['offset'];
			if ( $offset > 1000 ) { // Validate override parm
				$pe = $parmerror;
				$pe ['message'] .= "offset";
				$pe ['data']['params']['offset'] = "offset must be an integer between 0-1000";
				return rest_ensure_response ($pe);	
			}
		}
		$qrylimit = $per_page;
		$qryoffset = ( $per_page * ($page - 1) ) + $offset;
		
        $tblprefix = trim($this->wpdb->prefix);
		$qry  = "";
		$qry .= "SELECT ";
		$qry .= "	  p1.id order_id ";
		$qry .= "	, (SELECT DISTINCT pm0.meta_value from " . $tblprefix . "postmeta pm0 WHERE pm0.post_id = p1.id AND pm0.meta_key = '_customer_user') 'user_id' ";
		$qry .= "	, (SELECT DISTINCT u0.user_nicename from " . $tblprefix . "users u0 WHERE u0.id = (SELECT DISTINCT pm0.meta_value from " . $tblprefix . "postmeta pm0 WHERE pm0.post_id = p1.id AND pm0.meta_key = '_customer_user') ) 'user_nicename' ";
		$qry .= "	, (SELECT DISTINCT pm0.meta_value from " . $tblprefix . "postmeta pm0 WHERE pm0.post_id = p1.id AND pm0.meta_key = '_order_total') 'order_total' ";	
		$qry .= "	, (SELECT DISTINCT woim0.meta_value FROM " . $tblprefix . "woocommerce_order_itemmeta woim0 WHERE woim0.order_item_id = woi1.order_item_id AND woim0.meta_key = '_line_total') 'line_total' ";
		$qry .= "	, (SELECT DISTINCT 	pm0.meta_value FROM " . $tblprefix . "postmeta pm0 WHERE pm0.meta_key = '_sku' AND pm0.post_id = (SELECT DISTINCT woim0.meta_value FROM " . $tblprefix . "woocommerce_order_itemmeta woim0 WHERE woim0.order_item_id = woi1.order_item_id AND woim0.meta_key = '_product_id') ) 'product_sku' ";
		$qry .= "	, (SELECT DISTINCT 	pm0.meta_value FROM " . $tblprefix . "postmeta pm0 WHERE pm0.meta_key = '_sku' AND pm0.post_id = (SELECT DISTINCT woim0.meta_value FROM " . $tblprefix . "woocommerce_order_itemmeta woim0 WHERE woim0.order_item_id = woi1.order_item_id AND woim0.meta_key = '_variation_id') ) 'variation_sku' ";
		$qry .= "	, (SELECT DISTINCT pm0.meta_value from " . $tblprefix . "postmeta pm0 WHERE pm0.post_id = p1.id AND pm0.meta_key = '_payment_method') 'payment_method' ";
		$qry .= "	, p1.post_status ";
		$qry .= "	, p1.post_date ";
		$qry .= "	, p1.post_modified ";
		$qry .= "FROM " . $tblprefix . "posts p1 ";
		$qry .= "INNER JOIN " . $tblprefix . "woocommerce_order_items woi1 ";
		$qry .= "	ON p1.ID = woi1.order_id ";		
		if ($uflag) {
			$test = $test . " Did uflag if. ";
			$qry .= "INNER JOIN ( ";	
			$qry .= "SELECT pm1.post_id id from " . $tblprefix . "postmeta pm1 ";	
			$qry .= "WHERE pm1.meta_key = '_customer_user' ";	
			$qry .= "AND pm1.meta_value = " . $id . " ";	
			$qry .= ") cq1 ";	
			$qry .= "	ON cq1.id = p1.ID ";
		}
		$qry .= "WHERE p1.post_type='shop_order' ";
		$qry .= "ORDER BY p1.ID DESC ";
		$qry .= "LIMIT " . $qrylimit . " OFFSET " . $qryoffset . " ";
  		
		// Run the query and put the results in an associative array
		$results = $this->wpdb->get_results( $qry, ARRAY_A );		

		return rest_ensure_response( $results );
    }   
  
    /***
     * skus : Schema, @param WP_REST_Request $request Current request.
     */
    public function get_schema( $request ) {
        $schema = array(
            // This tells the spec of JSON Schema we are using which is draft 4.
            '$schema'              => 'http://json-schema.org/draft-04/schema#',
            // The title property marks the identity of the resource.
            'title'                => 'skus',
            'type'                 => 'object',
            // In JSON Schema you can specify object properties in the properties attribute.
            'properties'           => array(
                'order_id' => array(
                    'description'  => esc_html__( 'Woocommerce order number.', 'my-textdomain' ),
                    'type'         => 'integer',
                ),
                'user_id' => array(
                    'description'  => esc_html__( 'Unique identifier for the user.', 'my-textdomain' ),
                    'type'         => 'integer',
                ),
                'user_nicename' => array(
                    'description'  => esc_html__( 'User nicename.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				
				'order total' => array(
                    'description'  => esc_html__( 'Amount of total order.  Multiple items (lines) may be purchased in a single order.', 'my-textdomain' ),
                    'type'         => 'number',
                ), 
				'line_total' => array(
                    'description'  => esc_html__( 'Amount of the a signle item in an order.', 'my-textdomain' ),
                    'type'         => 'number',
                ), 
				'product_sku' => array(
                    'description'  => esc_html__( 'SKU of the product. Products may have multiple variations. For example, there may be a product sku for all memberships, and a child variation for each type of membership.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'variation_sku' => array(
                    'description'  => esc_html__( 'SKU of product variation. Products may have multiple variations. For example, there may be a product sku for all memberships, and a child variation for each type of membership.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'payment_method' => array(
                    'description'  => esc_html__( 'Method of payment used in the transaction.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'post_status' => array(
                    'description'  => esc_html__( 'Indicator if transaction was completed, refunded, etc.', 'my-textdomain' ),
                    'type'         => 'string',
                ), 
				'post_date' => array(
                    'description'  => esc_html__( 'Date the order was submitted.', 'my-textdomain' ),
                    'type'         => 'datetime',
                ), 
				'post_modified' => array(
                    'description'  => esc_html__( 'Date the order was completed, or otherwise changed.  Note that if the payment gateway fails to provide timely response that the payment was successful, woocommerce will place the order on hold so that payment can be manually verified.  This can result in a delay of some days between order initiation and completion.', 'my-textdomain' ),
                    'type'         => 'datetime',
                ), 
            ),
        );
        return $schema;
    }
   
    // Sets up the proper HTTP status code for authorization.
    public function authorization_status_code() {
 
        $status = 401;
 
        if ( is_user_logged_in() ) {
            $status = 403;
        }
 
        return $status;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* Instantiate the WAC REST controllers and register the routes */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

// Function to register our new routes from the controllers.
function prefix_register_wac_rest_routes() {
	$groups_controller = new Wac_groups_REST_Controller();
	$groups_controller->register_routes();
	
	$groupmembers_controller = new Wac_groupmembers_REST_Controller();
	$groupmembers_controller->register_routes();
		
	$groupbyuser_controller = new Wac_groupsbyuser_REST_Controller();
	$groupbyuser_controller->register_routes();
		
	$expdates_controller = new Wac_expdates_REST_Controller();
	$expdates_controller->register_routes();
	
    $skus_controller = new Wac_skus_REST_Controller();
    $skus_controller->register_routes();
}
 
add_action( 'rest_api_init', 'prefix_register_wac_rest_routes' );

?>