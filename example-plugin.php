<?php
/*
Plugin Name: BlackbaudHQ Process Users
Plugin URI: 
Description: 
Author: rayflores
Version: 0.1
Author URI: 
Text Domain: example-plugin
Domain Path: /languages/
*/

class Example_Background_Processing {
	
	/**
	 * @var WP_Example_Request
	 */
	protected $process_single;
	
	/**
	 * @var WP_Example_Process
	 */
	protected $process_all;
	
	/**
	 * Example_Background_Processing constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
		add_action( 'init', array( $this, 'process_handler' ) );
		add_action( 'admin_init', array( $this, 'check_roles' ) );
	}
	
	/**
	 * Init
	 */
	public function init() {
		require_once plugin_dir_path( __FILE__ ) . 'classes/wp-async-request.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/wp-background-process.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-logger.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-logger.php';
		require_once plugin_dir_path( __FILE__ ) . 'async-requests/class-example-request.php';
		require_once plugin_dir_path( __FILE__ ) . 'background-processes/class-example-process.php';
		
		$this->process_single = new WP_Example_Request();
		$this->process_all    = new WP_Example_Process();
	}
	public function check_roles() {
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
	 * Admin bar
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function admin_bar( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$wp_admin_bar->add_menu( array(
			'id'    => 'example-plugin',
			'title' => __( 'BlackbaudHQ', 'example-plugin' ),
			'href'  => '#',
		) );
		
		
		$wp_admin_bar->add_menu( array(
			'parent' => 'example-plugin',
			'id'     => 'example-plugin-all',
			'title'  => __( 'Get All Users', 'example-plugin' ),
			'href'   => wp_nonce_url( admin_url( '?process=all'), 'process' ),
		) );
	}
	
	/**
	 * Process handler
	 */
	public function process_handler() {
		if ( ! isset( $_GET['process'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}
		
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'process') ) {
			return;
		}
		
		if ( 'all' === $_GET['process'] ) {
			$this->handle_all();
		}
	}

	
	/**
	 * Handle all
	 */
	protected function handle_all() {
		$records = $this->get_records();
		
		foreach ( $records as $_key => $record ) {
			foreach ( $record['data'] as $data ) {
				if ( isset( $data['email'] ) ) { // is there an email 
					if ( ! email_exists( $data['email'] ) ) { // user not already in the system
						$firstName  = $data['firstName'];
						$lastName   = $data['lastName'];
						$email      = $data['email'];
						$user_array = array( $firstName, $lastName, $email );
						$this->process_all->push_to_queue( $user_array );
					}
				}
			}
		}
		
		$this->process_all->save()->dispatch();
	}
	
	/**
	 * Get names
	 *
	 * @return array
	 */
	protected function get_records() {
		global $wpdb;
		$total_response = array();
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
//		$queryName    = "Website Access";
		$queryName    = "Active Individuals";
		
		$request          = array();
		$request["start"] = 0;
		$request["count"] = 100;
		$request["query"] = "$categoryName::$queryName";
		
		// Invoke getExistingQueryResults method
		$first_response = array();
		$response1 = $nsc->call( "getExistingQueryResults", array( $request ) );
		$first_response['1'] = $response1;
		// Did a soap fault occur?
		checkStatus( $nsc );
		
		// Attempt to retrieve next page results
		if ( $response1['pages'] > 1 ) {
			$hasMore = true;
			$resp    = 2;
			$following_response = array();
			do {
				// Invoke getNextQueryResults method
				$response2 = $nsc->call( "getNextQueryResults", array() );
				$following_response[$resp] = $response2;
				// Did a soap fault occur?
				checkStatus( $nsc );
				
				// Invoke hasMoreQueryResults method
				$hasMore = $nsc->call( "hasMoreQueryResults", array() );
				
				// Did a soap fault occur?
				checkStatus( $nsc );
				
				$resp ++;
				
			} while ( $hasMore );
		}
		$total_response = array_merge( $first_response, $following_response );
		return $total_response;
		// Call logout method
		stopEtapestrySession($nsc);
	}
	
}

new Example_Background_Processing();