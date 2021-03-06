<?php
/*
Plugin Name: BlackbaudHQ Process/Check Enabled Members
Plugin URI: 
Description: 
Author: rayflores
Version: 0.2
Author URI: https://rayflores.com
Text Domain: example-plugin
Domain Path: /languages/
*/

class Example_Background_Processing {
	
	/**
	 * @var WP_Example_Process_Single
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
    // Hooks near the bottom of profile page (if current user) 
    add_action('show_user_profile', array( $this, 'custom_user_profile_fields' ) );

    // Hooks near the bottom of the profile page (if not current user) 
    add_action('edit_user_profile', array( $this, 'custom_user_profile_fields' ) );
	}
	public function custom_user_profile_fields( $user ){
	  ?>
        <table class="form-table">
            <tr>
                <th>
                    <label for="ref">REF</label>
                </th>
                <td>
                    <input type="text" name="ref" id="ref" value="<?php echo esc_attr( get_user_meta( $user->ID, 'ref', true ) ); ?>" class="regular-text"/>
                </td>
            </tr>
        </table>
    <?php
  }
	/**
	 * Init
	 */
	public function init() {
		require_once plugin_dir_path( __FILE__ ) . 'classes/wp-async-request.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/wp-background-process.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-logger.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-logger.php';
		require_once plugin_dir_path( __FILE__ ) . 'background-processes/class-example-process.php';
		require_once plugin_dir_path( __FILE__ ) . 'background-processes/class-example-process-single.php';
		
		$this->process_single = new WP_Example_Process_Single();
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
        $wp_admin_bar->add_menu( array(
          'parent' => 'example-plugin',
          'id'     => 'example-plugin-sync',
          'title'  => __( 'Sync Users', 'example-plugin' ),
          'href'   => wp_nonce_url( admin_url( '?process=sync'), 'process' ),
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
      if ( 'sync' === $_GET['process'] ) {
        $this->handle_sync();
      }
		if ( 'all' === $_GET['process'] ) {
			$this->handle_all();
		}
	}

  /**
   * Handle single
   */
  protected function handle_sync() {
     
    $members = $this->get_members();
    foreach ( $members as $member ) {
      $enabled = $this->get_enabled( $member->ID );
      $member_array = array( 
                $member->ID, 
                $enabled 
      );          
      $this->process_single->push_to_queue( $member_array );
      
    }
    
  }
	/**
	 * Handle all
	 */
	protected function handle_all() {
		$records = $this->get_records();
		
		foreach ( $records as $_key => $record ) {		  
			foreach ( $record['data'] as $data ) {
				$user_array = array( 
				    $data['firstName'], 
                    $data['lastName'], 
                    $data['email'], 
                    $data['ref'] 
                );
				$this->process_all->push_to_queue( $user_array );
			}
		}
		
		$this->process_all->save()->dispatch();
	}
  /**
   * Get users - members role
   */
	protected function get_members(){
	    $users = '';
	    $args = array(
	      'role__in' => 'members'      
        );
	    $users = get_users( $args );
	    
	    return $users;
    }
	/**
	 * Get records
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
	protected function get_enabled( $id ) {
      global $wpdb;
      $total_response = array();
      require( dirname( __FILE__ ) .'/../utils/utils.php' );
      require( dirname( __FILE__ ) .'/../lib/nusoap.php' );

      $databaseId = 'NationalRenderersAssociationI';
      $apiKey     = 'QZ9ZNlbAubcoMYhDwrbOlnPbKem3K1f0D+LwUeJdsqw=';

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
//      $ref = "INPUT_DATABASE_REF"; // example: 1234.0.567812
      $ref = get_user_meta( $id, 'ref', true ); // example: 1234.0.567812

// Invoke getAccount method

      $response = $nsc->call("getAccount", array($ref));


// Did a soap fault occur?
      checkStatus($nsc);

// Output result

      $enabled = true;
      foreach ( $response['accountDefinedValues'] as $_key => $value ){
        if ( $value['fieldName'] === 'Website Access' ) {
          if ( $value['value'] !== 'Enabled' )  {
            $enabled = false;
          }

        }
      }
      return $enabled;
    }

}

new Example_Background_Processing();