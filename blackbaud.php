<?php
/**
 * Plugin Name: Blackbaudhq Get New Users
 */
	
	function register_baluckbaud_menu_page(){
		add_menu_page(
			'BlackbaudHQ',
			'BlackbaudHQ',
			'manage_options',
			'blackbuadhq',
			'blackbaud_menu_page_callback',
			'dashicons-admin-generic',
			6
		);
	}
	add_action( 'admin_menu', 'register_baluckbaud_menu_page' );
	
	function load_admin_scripts($hook) {
		if( $hook != 'toplevel_page_blackbuadhq' )
			return;
		wp_enqueue_style( 'bbhq-styles', plugins_url('css/bbhq.css', __FILE__ ) );
		wp_enqueue_script( 'bbhq-js', plugins_url( 'js/bbhq.js' , __FILE__ ) );
		
	}
	add_action('admin_enqueue_scripts', 'load_admin_scripts');
	
	
	add_action('admin_init', 'check_roles');
	function check_roles(){
		if ( wp_roles()->is_role( 'members' ) ) {
			return;
		} else {
			add_role(
				'members',
				'Members',
				array('read')
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
        <p>
		<a class="button go" >Login to BlackbaudHQ</a>
		<div class="LoaderBalls1">
			<div class="LoaderBalls1__item"></div>
			<div class="LoaderBalls1__item"></div>
			<div class="LoaderBalls1__item"></div>
		</div>
		</p>
        <p class="next_step_1" style="display:none">Next Step: <br/>
            <a class="getusers button">Get New Members</a>
			<div class="LoaderBalls2">
				<div class="LoaderBalls2__item"></div>
				<div class="LoaderBalls2__item"></div>
				<div class="LoaderBalls2__item"></div>
			</div>
        </p>
        <p>
            <table class="table-responsive new_users_table">
            <th>ID</th>
            <th>Username</th>
            <th>First Name</th>
            <th>Last Name</th>
            
            </table>
        </p>
        <p>
        <table class="table-responsive existing_users_table">
            <th>ID</th>
            <th>Username</th>
            <th>First Name</th>
            <th>Last Name</th>

        </table>
        </p>
		<?php
		
	}
	add_action( 'wp_ajax_login_bbhq', 'login_bbhq' );
	function login_bbhq(){
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
		
		$databaseId = "NationalRenderersAssociationI";
		$apiKey     = "QZ9ZNlbAubcoMYhDwrbOlnPbKem3K1f0D+LwUeJdsqw=";
		
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
		$response = array( 'results' => 'success');
		wp_send_json( $response );
    }
    
	add_action( 'wp_ajax_get_bbhq_users', 'get_bbhq_users' );	
	function get_bbhq_users(){	
		require( plugin_dir_path( __FILE__ ) . 'utils/utils.php' );
		require( plugin_dir_path( __FILE__ ) . 'lib/nusoap.php' );
		
		$databaseId = "NationalRenderersAssociationI";
		$apiKey     = "QZ9ZNlbAubcoMYhDwrbOlnPbKem3K1f0D+LwUeJdsqw=";
		
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
		$queryName = "Website Access Beta";
		
		$request = array();
		$request["start"] = 0;
		$request["count"] = 500;
		$request["query"] = "$categoryName::$queryName";

		// Invoke getExistingQueryResults method
		$response = $nsc->call("getExistingQueryResults", array($request));
		
		// Did a soap fault occur?
		checkStatus($nsc);

		// Output result
		$new_users = array();
		$existing_users = array();
		foreach ( $response['data'] as $record ){
			$record_emails = explode(',', $record['email'] );
			$record_email = $record_emails[0];
			$record_firstName = $record['firstName'];
			$record_lastName = $record['lastName'];
			if ( email_exists( $record_email ) ) {
				$user = get_user_by( 'email', $record_email );
				$existing_users[] .= $user->ID;
//				echo "<td>User already exists: <a href='" . get_edit_user_link( $user->ID ) ."'>" . $user->user_email . " ID: " . $user->ID . "</a></td>";
			} else {
				$user_id = add_user_to_wp( $record_email, $record_firstName, $record_lastName );
				$new_users[] .= $user_id;
			}
		}
		$send_response = array(
		  'success' => true,
          'new_users'   => $new_users,
          'existing_users' => $existing_users
        );
		// Call logout method
		stopEtapestrySession( $nsc );
		
		wp_send_json( $send_response );		
			
	}
	function add_user_to_wp( $user_email, $user_firstName, $user_lastName ){
		$userdata = array(
			'user_login' =>  $user_email,
			'user_email' => $user_email,
			'user_pass'  =>  NULL, // When creating an user, `user_pass` is expected.
			'first_name' => $user_firstName,
			'last_name' => $user_lastName,
			'role' => 'members'
		);
		
			$user_id = wp_insert_user( $userdata );
			add_user_meta( $user_id, '_member_bbhq_enabled', 'yes' );
			// On success.
			if ( ! is_wp_error( $user_id ) ) {
			    return $user_id;
				//echo "<td>User created : <a href='" . get_edit_user_link( $user_id ) . "'>". $user_email . " ID: " . $user_id . "</a></td>";
			}

	}