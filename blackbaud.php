<?php

/**
 * Plugin Name: BlackbaudHQ Get New Users
 * Plugin URI: https://rayflores.com/plugins/wcpas/
 * Description: Add users from eTapestry API ( BlackbaudHQ ), step through
 * Version: 0.1.1
 * Author: Ray Flores
 * Author URI: http://rayflores.com
 */
class WP_Blackbaudhq_User_Sync {
	/** @var WP_Blackbaud single instance of this plugin */
	protected static $instance;
	/**
	 * @var WP_BBHQ_Get_Users_Request
	 */
	protected $process_single;
	
	/**
	 * @var WP_Example_Process
	 */
	protected $process_all;
	
	/**
	 * WP_Blackbaudhq_User_Sync constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_baluckbaud_menu_page' ) );
		add_action( 'wp_ajax_get_more_members', array( $this, 'get_more_members' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
		add_action( 'wp_ajax_get_bbhq_users', array( $this, 'get_bbhq_users' ) );
		add_action( 'wp_ajax_login_bbhq', array( $this, 'login_bbhq' ) );
		add_action( 'admin_init', array( $this, 'check_roles' ) );
		
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		//add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
		//add_action( 'init', array( $this, 'process_handler' ) );
	}
	/**
	 * Init
	 */
	public function init() {
	    require_once plugin_dir_path( __FILE__ ) . 'classes/wp-async-request.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/wp-background-process.php';
		
		require_once plugin_dir_path( __FILE__ ) . 'class-logger.php';
		require_once plugin_dir_path( __FILE__ ) . 'async-requests/class-example-request.php';
		require_once plugin_dir_path( __FILE__ ) . 'background-processes/class-example-process.php';
		
		$this->process_single = new WP_Example_Request();
		
		$this->process_all    = new WP_Example_Process();  // this one first... 
		$this->process_all    = new WP_Users_Process();  // this one first... 
		
	}

	
	function register_baluckbaud_menu_page() {
		add_menu_page(
			'BlackbaudHQ',
			'BlackbaudHQ',
			'manage_options',
			'blackbuadhq',
			array( $this, 'blackbaud_menu_page_callback' ),
			'dashicons-admin-generic',
			6
		);
		add_submenu_page( 'blackbuadhq', 'tester', 'tester', 'manage_options', 'bbhq-tester', array( $this, 'render_tester_page' ) );
	}


	function render_tester_page() {
		echo 'hi';
		
	}

	function get_more_members() {
		require( plugin_dir_path( __FILE__ ) . 'utils/utils.php' );
		require( plugin_dir_path( __FILE__ ) . 'lib/nusoap.php' );
		
		$databaseId = get_transient( 'bbhq_dbid' ) ? get_transient( 'bbhq_dbid' ) : 'NationalRenderersAssociationI';
		$apiKey     = get_transient( 'bbhq_apikey' ) ? get_transient( 'bbhq_apikey' ) : 'QZ9ZNlbAubcoMYhDwrbOlnPbKem3K1f0D+LwUeJdsqw=';
		
		// Set initial endpoint
		$endpoint = "https://sna.etapestry.com/v3messaging/service?WSDL";
		
		// Instantiate nusoap_client
		$nsc = new nusoap_client( $endpoint, true );
		
		// Did an error occur?
		checkStatus( $nsc );
		
		// Invoke apiKeyLogin method
		$newEndpoint = $nsc->call( "apiKeyLogin", array( $databaseId, $apiKey ) );
		
		// Did a soap fault occur?
		checkStatus( $nsc );
		
		// Determine if the apiKeyLogin method returned a value...this will occur
		// when the database you are trying to access is located at a different
		// environment that can only be accessed using the provided endpoint
		if ( $newEndpoint != "" ) {
			
			// Instantiate nusoap_client with different endpoint
			$nsc = new nusoap_client( $newEndpoint, true );
			
			// Did an error occur?
			checkStatus( $nsc );
			
			// Invoke apiKeyLogin method
			$nsc->call( "apiKeyLogin", array( $databaseId, $apiKey ) );
			
			// Did a soap fault occur?
			checkStatus( $nsc );
		}
		
		// Initialize parameters
		$categoryName = "Custom Testing Queries";
		$queryName    = "Website Access";
		
		$request          = array();
		$request["start"] = $_REQUEST['start'] ? $_REQUEST['start'] : 0;
		$request["count"] = $_REQUEST['count'] ? $_REQUEST['count'] : 100;
		$request["query"] = "$categoryName::$queryName";

        // Invoke getExistingQueryResults method
		$response = $nsc->call( "getExistingQueryResults", array( $request ) );

        // Did a soap fault occur?
		checkStatus( $nsc );

        // set result in transients for later use
        $pages = $response['pages'];
        set_transient( 'response_page_pages_', $pages, MONTH_IN_SECONDS );
		$resp           = 1;
		$init_transient = 'response_page_' . $resp;
		set_transient( $init_transient, $response['data'], MONTH_IN_SECONDS );
		
// Attempt to retrieve next page results
		if ( $response['pages'] > 1 ) {
			$hasMore = true;
			$resp = 2;
			do {
				WP_Example_Logger::really_long_running_task();
				// Invoke getNextQueryResults method
				$response = $nsc->call( "getNextQueryResults", array() );

				
				// Did a soap fault occur?
				checkStatus( $nsc );
				
				$set_transient = 'response_page_' . $resp;
				set_transient( $set_transient, $response['data'], MONTH_IN_SECONDS );
				
    			// Invoke hasMoreQueryResults method
				$hasMore = $nsc->call( "hasMoreQueryResults", array() );
				
				// Did a soap fault occur?
				checkStatus( $nsc );
				
				$resp++;
				
			} while ( $hasMore );
			
		}
		
		$success = array(
		  'success' => true,
			'pages' => $response['pages'],
        );
		wp_send_json( $success );
		
		
		stopEtapestrySession( $nsc );
		
		
	}
	
	function load_admin_scripts( $hook ) {
		if ( $hook != 'toplevel_page_blackbuadhq' ) {
			return;
		}
		wp_enqueue_style( 'bbhq-styles', plugins_url( 'css/bbhq.css', __FILE__ ) );
		wp_enqueue_script( 'bbhq-js', plugins_url( 'js/bbhq.js', __FILE__ ) );
		
	}

	function check_roles() {
		if ( wp_roles()->is_role( 'members' ) ) {
			return;
		} else {
			add_role(
				'members',
				'Members',
				array( 'read' )
			);
		}
	}
	
	/**
	 * Display a custom menu page
	 */
	function blackbaud_menu_page_callback() {
		?>
        <h1>BlackbuadHQ User Sync</h1>
        <div class="notice notice-success loggedin" style="display:none;">
            <p><strong>Logged into BlackbaudHQ successfully!</strong></p>
        </div>
        <div class="enter_creds">
            <!--  //	API keys / Database
			  //  API Key:
			  //  QZ9ZNlbAubcoMYhDwrbOlnPbKem3K1f0D+LwUeJdsqw=
			  //	Database Id:
			  //  NationalRenderersAssociationI
			  -->
            <p>Enter Your Database ID and your API Key below</p>
            <p>
                <input type="text" name="bbhq_dbid" class="regular-text bbhq_dbid" placeholder="Database ID here" value="<?php echo get_transient( 'bbhq_dbid' ) ? get_transient( 'bbhq_dbid' ) : ''; ?>"/>
            </p>
            <p>
                <input type="password" name="bbhq_apikey" class="regular-text bbhq_apikey" placeholder="API Key here" value="<?php echo get_transient( 'bbhq_apikey' ) ? get_transient( 'bbhq_apikey' ) : ''; ?>"/>
            </p>
            <a class="button go">Login to BlackbaudHQ</a>
        </div>
        <div class="LoaderBalls1">
            <div class="LoaderBalls1__item"></div>
            <div class="LoaderBalls1__item"></div>
            <div class="LoaderBalls1__item"></div>
        </div>

        <p class="next_step_1" style="display:none">Next Step: <br/>
            <a class="getusers button">Get New Members</a>
        <div class="LoaderBalls2">
            <div class="LoaderBalls2__item"></div>
            <div class="LoaderBalls2__item"></div>
            <div class="LoaderBalls2__item"></div>
        </div>
        </p>
        <div class="newUsersTable" style="display: none;">
            <h2>New Users Inserted:</h2>
            <p>
            <table class="table-responsive new_users_table">
                <th>ID</th>
                <th>Username</th>
                <th>First Name</th>
                <th>Last Name</th>

            </table>
            </p>
        </div>
        <div class="existingUsersTable" style="display: none;">
            <h2>Existing Users Found:</h2>
            <p>
            <table class="table-responsive existing_users_table">
                <th>ID</th>
                <th>Username</th>
                <th>First Name</th>
                <th>Last Name</th>

            </table>
            </p>
        </div>
		<?php
		
	}

	function login_bbhq() {
		$dbid   = $_REQUEST['dbid'];
		$apikey = $_REQUEST['apikey'];
		// store for a day
		set_transient( 'bbhq_dbid', $dbid, MONTH_IN_SECONDS );
		set_transient( 'bbhq_apikey', $apikey, MONTH_IN_SECONDS );
		
		require( plugin_dir_path( __FILE__ ) . 'utils/utils.php' );
		require( plugin_dir_path( __FILE__ ) . 'lib/nusoap.php' );
		
		// Set login details. This info is visible to admin users within eTapestry.
		// Navigate to Management -> My Organization -> Subscriptions and look under
		// the API Subscription section.
		//	API keys / Database
		//  API Key:	
		//  QZ9ZNlbAubcoMYhDwrbOlnPbKem3K1f0D+LwUeJdsqw=
		//	Database Id:	
		//  NationalRenderersAssociationI
		
		$databaseId = $dbid;
		$apiKey     = $apikey;
		
		// Set initial endpoint
		$endpoint = "https://sna.etapestry.com/v3messaging/service?WSDL";
		
		// Instantiate nusoap_client
		$nsc = new nusoap_client( $endpoint, true );
		
		// Did an error occur?
		checkStatus( $nsc );
		
		// Invoke apiKeyLogin method
		$newEndpoint = $nsc->call( "apiKeyLogin", array( $databaseId, $apiKey ) );
		
		// Did a soap fault occur?
		checkStatus( $nsc );
		// Output results
		$response = array( 'results' => 'success' );
		wp_send_json( $response );
	}


	function get_bbhq_users() {
		require( plugin_dir_path( __FILE__ ) . 'utils/utils.php' );
		require( plugin_dir_path( __FILE__ ) . 'lib/nusoap.php' );
		
		$databaseId = get_transient( 'bbhq_dbid' );
		$apiKey     = get_transient( 'bbhq_apikey' );
		
		// Set initial endpoint
		$endpoint = "https://sna.etapestry.com/v3messaging/service?WSDL";
		
		// Instantiate nusoap_client
		$nsc = new nusoap_client( $endpoint, true );
		
		// Did an error occur?
		checkStatus( $nsc );
		
		// Invoke apiKeyLogin method
		$newEndpoint = $nsc->call( "apiKeyLogin", array( $databaseId, $apiKey ) );
		
		// Did a soap fault occur?
		checkStatus( $nsc );
		
		// Determine if the apiKeyLogin method returned a value...this will occur
		// when the database you are trying to access is located at a different
		// environment that can only be accessed using the provided endpoint
		if ( $newEndpoint != "" ) {
			
			// Instantiate nusoap_client with different endpoint
			$nsc = new nusoap_client( $newEndpoint, true );
			
			// Did an error occur?
			checkStatus( $nsc );
			
			// Invoke apiKeyLogin method
			$nsc->call( "apiKeyLogin", array( $databaseId, $apiKey ) );
			
			// Did a soap fault occur?
			checkStatus( $nsc );
		}
		
		// Initialize parameters
		$categoryName = "Custom Testing Queries";
		$queryName    = "Website Access";
		
		$request          = array();
		$request["start"] = 0;
		$request["count"] = 100;
		$request["query"] = "$categoryName::$queryName";
		
		// Invoke getExistingQueryResults method
		$response = $nsc->call( "getExistingQueryResults", array( $request ) );
		
		// Did a soap fault occur?
		checkStatus( $nsc );
		
		// Output result
		$new_users      = array();
		$existing_users = array();
		$stuff          = json_encode( $response['data'] );
		foreach ( $response['data'] as $record ) {
			
			$record_emails    = explode( ',', $record['email'] );
			$record_email     = $record_emails[0];
			$record_firstName = $record['firstName'];
			$record_lastName  = $record['lastName'];
			if ( email_exists( $record_email ) ) {
				$user             = get_user_by( 'email', $record_email );
				$existing_users[] .= $user->ID;
//				echo "<td>User already exists: <a href='" . get_edit_user_link( $user->ID ) ."'>" . $user->user_email . " ID: " . $user->ID . "</a></td>";
			} else {
				$user_id     = $this->add_user_to_wp( $record_email, $record_firstName, $record_lastName );
				$new_users[] .= $user_id;
			}
		}
		$new_user_html = '';
		foreach ( $new_users as $new_user ) {
			$user = get_user_by( 'ID', $new_user );
			
			$new_user_html .= '<tr><td><a href="' . get_edit_user_link( $user->ID ) . '">' . $user->ID . '</a></td><td>' . $user->user_login . '</td><td>' . $user->first_name . '</td><td>' . $user->last_name . '</td></tr>';
		}
		$existing_user_html = '';
		foreach ( $existing_users as $existing_user ) {
			$user               = get_user_by( 'ID', $existing_user );
			$existing_user_html .= '<tr><td><a href="' . get_edit_user_link( $user->ID ) . '">' . $user->ID . '</a></td><td>' . $user->user_login . '</td><td>' . $user->first_name . '</td><td>' . $user->last_name . '</td></tr>';
		}

		$send_response = array(
		  'success' => true,
          'new_users'   => $new_user_html,
          'existing_users' => $existing_user_html
        );
		
		wp_send_json( $send_response );
		// Call logout method
		stopEtapestrySession( $nsc );
		
	}
	
	function add_user_to_wp( $user_email, $user_firstName, $user_lastName ) {
		$userdata = array(
			'user_login' => $user_email,
			'user_email' => $user_email,
			'user_pass'  => null, // When creating an user, `user_pass` is expected.
			'first_name' => $user_firstName,
			'last_name'  => $user_lastName,
			'role'       => 'members'
		);
		
		$user_id = wp_insert_user( $userdata );
		add_user_meta( $user_id, '_member_bbhq_enabled', 'yes' );
		// On success.
		if ( ! is_wp_error( $user_id ) ) {
			return $user_id;
			//echo "<td>User created : <a href='" . get_edit_user_link( $user_id ) . "'>". $user_email . " ID: " . $user_id . "</a></td>";
		}
		
	}
	/**
	 * Main Class Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see new_plugin_class_lowercase()
	 * @return \New_Plugin_Class
	 */
	public static function instance(){
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
//			self::$instance->hooks();
		}
	}
}
// fire it up!
	add_action('plugins_loaded', 'wp_blackbaudhq_user_sync');
	function wp_blackbaudhq_user_sync(){
		return WP_Blackbaudhq_User_Sync::instance();
	}