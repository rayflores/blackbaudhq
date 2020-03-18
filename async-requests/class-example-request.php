<?php

class WP_Example_Request extends WP_Async_Request {

	use WP_Example_Logger;

	/**
	 * @var string
	 */
	protected $action = 'example_request';

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	protected function handle() {
	  $message = 'User ID: ' . $_POST['ID'] . ' is Enabled';
    $enabled = $this->get_enabled( $_POST['ID'] );
		$this->really_long_running_task();
		if ( !$enabled ) {
      wp_delete_user( $_POST['ID'] );
      $message = 'User ID: ' . $_POST['ID'] . ' has been deleted.';
    }

		$this->log( $message );
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