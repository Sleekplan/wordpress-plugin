<?php 

/** *****************
 * Actions
 ***************** */

add_action( 'admin_post_sp_auth_form_response', 'sp_auth_form');
add_action( 'admin_post_sp_settings_form_response', 'sp_settings_form');
add_action( 'admin_post_sp_website_form_response', 'sp_website_form');
add_action( 'admin_post_sp_logout_form_response', 'sp_logout_form');
add_action( 'wp_head', 'sp_load_script', 99999 );
add_action( 'wp_footer', 'sp_load_sso', 99999 );
add_action( 'admin_enqueue_scripts', 'custom_admin_scripts' );

/** *****************
 * Form submit functions
 ***************** */

// submit authentication form
function sp_auth_form() {
		
	if( isset( $_POST['sp_auth_nonce'] ) && wp_verify_nonce( $_POST['sp_auth_nonce'], 'sp_auth_nonce') ) {

		// register
		if( $_POST['type'] == 'register' ) {

			// call api
			$auth_data = call_api( 'POST', 'user/create', [
				'user_product' 	=> $_POST['user_product'],
				'user_mail' 	=> $_POST['user_mail'],
				'user_name' 	=> $_POST['user_name'],
				'user_pass' 	=> $_POST['user_pass'],
			] );

			// check for error
			if( $auth_data['status'] == 'error' ) {

				// add the notice
				wp_redirect( SP_PLUGIN_FILE . '&notice=error&message=' . urlencode( $auth_data['data']['message'] ) );

				// stop here
				exit;

			}

			// setup account
			sp_update_data( ['user_id' => $auth_data['data']['user_data']['ID'], 'token' => $auth_data['data']['token'], 'refresh_token' => $auth_data['data']['refresh_token'], 'product' => $auth_data['data']['product_id']] );

		}

		// sign in
		if( $_POST['type'] == 'signin' ) {

			// call api
			$auth_data = call_api( 'POST', 'user/login', [
				'user_mail' 	=> $_POST['user_mail'],
				'user_pass' 	=> $_POST['user_pass'],
			] );

			// check for error
			if( $auth_data['status'] == 'error' ) {

				// add the notice
				wp_redirect( SP_PLUGIN_FILE . '&notice=error&message=' . urlencode( $auth_data['data']['message'] ) );

				// stop here
				exit;

			}
			
			// set SSO key
			sp_set_sso_key( $auth_data['data']['product_id'] );

			// setup account
			sp_update_data( ['user_id' => $auth_data['data']['user_data']['ID'], 'token' => $auth_data['data']['token'], 'refresh_token' => $auth_data['data']['refresh_token'], 'product' => $auth_data['data']['product_id']] );

		}

		// add the notice
        wp_redirect( SP_PLUGIN_FILE . '&notice=success&message=' . urlencode('Successfully authenticated') );
        
        // stop here
        exit;
        
	} else {

        // on error
		wp_die( __( 'Invalid nonce specified' ), __( 'Error' ), array(
					'response' 	=> 403,
					'back_link' => SP_PLUGIN_FILE,
        ) );
            
	}
}

// submit settings form
function sp_settings_form() {

	if( isset( $_POST['sp_settings_nonce'] ) && wp_verify_nonce( $_POST['sp_settings_nonce'], 'sp_settings_nonce') ) {
		
		// product data
		$product_data = load_settings()['data'];

		// merge settings here
		$product_data['product_settings'] = array_merge( $product_data['product_settings'], $_POST['setting'] );
		
		// get data
		$data = sp_get_data();

		// send settings via api
		$auth_data = call_api( 'PUT', 'product/' . $data['product'], $product_data );

		// set SSO
		sp_update_data( ['sso' => $_POST['sso'] ] );

		// check for error
		if( $auth_data['status'] == 'error' ) {

			// add the notice
			wp_redirect( SP_PLUGIN_SETTINGS . '&notice=error&message=' . urlencode( $auth_data['data']['message'] ) );

			// stop here
			exit;

		}
		
		// add the notice
		wp_redirect( SP_PLUGIN_SETTINGS . '&notice=success&message=' . urlencode('Settings updated') );

	}

}

// submit website selector
function sp_website_form() {

	if( isset( $_POST['sp_website_nonce'] ) && wp_verify_nonce( $_POST['sp_website_nonce'], 'sp_website_nonce') ) {
		
		// set SSO key
		sp_set_sso_key( $_POST['selected_website'] );

		// update active website
		sp_update_data( ['product' => $_POST['selected_website']] );

		// add the notice
        wp_redirect( SP_PLUGIN_SETTINGS . '&notice=success&message=' . urlencode('Active website successfully updated') );
        
        // stop here
        exit;

	}

}

// submit log out
function sp_logout_form() {

	if( isset( $_POST['sp_logout_nonce'] ) && wp_verify_nonce( $_POST['sp_logout_nonce'], 'sp_logout_nonce') ) {
		
		// delete local data
		delete_option('sleekplan_data');

		// add the notice
        wp_redirect( SP_PLUGIN_FILE . '&notice=success&message=' . urlencode('Successfully logged out') );
        
        // stop here
        exit;

	}

}

// submit log out
function sp_set_sso_key( $product_id ) {

	// load user websites
	$product_data = call_api( 'GET', 'product/' . $product_id, [
		'admin' => 'true'
	]);
	
	// set SSO
	sp_update_data( ['sso' => $product_data['data']['product_private']['sso_key'] ] );

}


/** *****************
 * Load data functions
 ***************** */

// load websites
function load_websites() {

	// get data
	$data = sp_get_data();

	// load user websites
	$user_websites = call_api( 'GET', 'user/' . $data['user_id'] . '/product' );
	
	// return websites
	return $user_websites['data'];

}

// load settings
function load_settings() {

	// get data
	$data = sp_get_data();

	// load user websites
	$product_data = call_api( 'GET', 'product/' . $data['product'], ['settings' => 'true'] );
	
	// return websites
	return [
		'data' 		=> $product_data['data'],
		'settings' 	=> $product_data['data']['product_settings'],
		'sso'		=> sp_get_data()['sso']
	];

}

// load stats
function load_stats() {

	// get data
	$data 	= sp_get_data();
	$stats 	= [];

	// load general stats
	$stats['product'] 		= call_api( 'GET', 'product/' . $data['product'] )['data'];
	$stats['general'] 		= call_api( 'GET', 'product/' . $data['product'] . '/stats/general' )['data'];
	$stats['satisfaction'] 	= call_api( 'GET', 'product/' . $data['product'] . '/satisfaction' )['data'];
	$stats['feedback'] 		= call_api( 'GET', 'feedback/' . $data['product'] . '/items', [
		'type' 		=> 'all',
		'sort' 		=> 'trend',
		'filter' 	=> 'all',
		'page' 		=> 0
	] )['data'];
	
	// prepare types
	$stats['types'] = [];
    foreach( $stats['general']['type']['color'] as $key => $value ) {
        $stats['types'][$stats['general']['type']['label'][$key]] = $stats['general']['type']['color'][$key];
    }

	// return websites
	return $stats;

}

// load plan
function load_subscription() {

	// get data
	$data 	= sp_get_data();

	// load plan
	$plan	= call_api( 'GET', 'subscription/product/' . $data['product'] )['data'];
	
	// returned
	return [
		'subscribed' => ( ($plan['plan'] === 'free') ? false : true ),
		'quota'		 => $plan['quota'],
		'usage'		 => $plan['usage']
	];

}


/** *****************
 * Helper functions
 ***************** */

// update data
function sp_update_data( $data ) {

	// get current data
	$current = get_option('sleekplan_data');

	// merge & save into database
	update_option('sleekplan_data', array_merge( (($current) ? $current : []), $data ));

}

// get data
function sp_get_data() {

	// get current data
	return get_option('sleekplan_data');

}


/** *****************
 * Script functions
 ***************** */

// load sleekplan script
function sp_load_script() {

	// get product id
	$data = sp_get_data();
	
	// return false in case we have no product id
	if( ! isset( $data['product'] ) ) return false;

	// add JavaScript SDK
	echo '<script type="text/javascript">
		window.$sleek=[];
		window.SLEEK_PRODUCT_ID=' . $data['product'] . ';
		(function(){d=document;s=d.createElement("script");s.src="https://client.sleekplan.com/sdk/e.js";s.async=1;d.getElementsByTagName("head")[0].appendChild(s);})();
		</script>';

}

// load sleekplan SSO
function sp_load_sso() {

	// load jwt classes
	require_once dirname(__FILE__) . '/jwt/BeforeValidException.php';
	require_once dirname(__FILE__) . '/jwt/ExpiredException.php';
	require_once dirname(__FILE__) . '/jwt/SignatureInvalidException.php';
	require_once dirname(__FILE__) . '/jwt/JWT.php';

	// get product id
	$data = sp_get_data();
	
	// return false in case we have no product id
	if( 
		! isset( $data['product'] ) || 
		! isset( $data['sso'] ) || 
		! is_user_logged_in() ) return false;

	// get current user
	$current_user = wp_get_current_user();

	$userData = [
		'mail' 	=> $current_user->user_email,
		'id' 	=> $current_user->ID,
		'name' 	=> $current_user->user_login,
		'img' 	=> get_avatar_url( $current_user->ID ),
	];

	// get JSON Web Token
	$jwt = \Firebase\JWT\JWT::encode( $userData, $data['sso_token'], 'HS256' );

	// print javascript
	?>
		<script>
		$sleek.sso = function( callback ) {
			// return the generated token to the widget
			callback( {token: '<?php echo $jwt; ?>'} );
		};
		</script>
	<?php

}

// load custom scripts
function custom_admin_scripts(){

	// load style
	wp_enqueue_style( 'sp-style', plugins_url( 'assets/css/style.css', SP_BASE ) );

	// load script for settings
    if( $_GET['page'] == 'sleekplan-settings' ) { 
		// custom js
		wp_enqueue_script( 'sp-settings-script', plugins_url( 'assets/js/settings.js', SP_BASE ) );
	}

	// load script for dashbaord
	if( $_GET['page'] == 'sleekplan' ) { 
		// custom js
		wp_enqueue_script( 'sp-dashboard-script', plugins_url( 'assets/js/dashboard.js', SP_BASE ) );
		// plugin: chart.js
		wp_enqueue_style( 'sp-plugin-chart-css', plugins_url( 'assets/css/chart.min.css', SP_BASE ) );
		wp_enqueue_script( 'sp-plugin-chart-script', plugins_url( 'assets/js/chart.min.js', SP_BASE ) );
	}

}