<?php
/*
WHMCS Multisite Provisioning Server Module
Plugin Name: WHMCS Multisite Provisioning
Plugin URI: http://premium.wpmudev.org/project/whmcs-multisite-provisioning/
Description: This plugin allows remote control of Multisite provisioning from WHMCS. Includes provisioning for Subdomain, Subdirectory or Domain Mapping Wordpress Multisite installs.
Author: WPMU DEV
Author Uri: http://premium.wpmudev.org/
Text Domain: mrp
Domain Path: languages
Version: 2.0
Network: true
WDP ID: 264
Requires PHP: 8.1
WHMCS: 8.0+
*/
/*  Copyright 2012-2025  Incsub  (http://incsub.com)

Author - Arnold Bailey

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


function whmcs_multisite_ConfigOptions() {

	if( !defined('WPMU_WHMCS_SERVER_VERSION') ) define('WPMU_WHMCS_SERVER_VERSION', '2.0');

	# Should return an array of the module options for each product - maximum of 24

	$configarray = array(
	"Default Blog Title" => array( "Type" => "text", "Size" => "25", "Description" => "<br />Title given to the blog if one is not entered in the Title custom field.", ),
	"Default Blog Domain" => array( "Type" => "text", "Size" => "25", "Description" => "<br />Domain name given to the blog if one is not entered in the Domain custom field.", ),
	"Use Title field" => array( "Type" => "yesno", "Description" => "Tick if you created a custom Title field." ),
	"Use Domain field" => array( "Type" => "yesno", "Description" => "Tick if you created a custom Domain field." ),
	"Default Role" => array( "Type" => "text", "Size" => "25", "Description" => "<br />This is the role that will be assigned to a user created by this product." ),
	"Web Space Quota" => array( "Type" => "text", "Size" => "5", "Description" => "MB <br />Allowed upload space or leave blank to use Wordpress defaults." ),
	"Product Administrator" => array( "Type" => "text", "Size" => "25", "Description" => "<br />The WHMCS Administrator authorizing this product.<br />REQUIRED for the API to function" ),
	"ProSites" => array( "Type" => "text", "Size" => "25", "Description" => "<br />ProSite Plan Name" ),
	//"Subdomains" => array( "Type" => "dropdown", "Options" => "1,2,5,10,25,50,Unlimited"),
	);

	return $configarray;
}

/**
* Get url content and response headers (given a url, follows all redirections on it
* and returnes content and response headers of final url)
*
*	@return array[0]    content
* 				array[1]    array of response headers
* 				array[2]    curl Error message
*/
function get_url( $url, $post_fields, $javascript_loop = 0, $timeout = 30 )
{
	//$url = str_replace( "&amp;", "&", urldecode(trim($url)) );

	if($javascript_loop == 0) $curl_error = '';

	$cookie = tempnam ("/tmp", "CURLCOOKIE");
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false ); //Follow redirect explicitly to avoid open_basedir and safemode problem
	curl_setopt( $ch, CURLOPT_ENCODING, "" );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_fields);
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
	// WARNING: SSL verification disabled for compatibility. Enable for production use:
	// curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
	// curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
	curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
	curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
	$content = curl_exec( $ch );
	$response = curl_getinfo( $ch );

	//Save the error
	if (curl_error($ch))
	$curl_error = "Connection Error: " . curl_errno($ch) . ' - ' . curl_error($ch);

	curl_close ( $ch );

	// If any rediects get the new location
	if (in_array($response['http_code'], array(300, 301, 302, 303, 307) ) ){
		ini_set("user_agent", "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");

		if ( $headers = get_headers($response['url']) ){
			foreach( $headers as $value ){
				if ( preg_match('#Location: (.+)#i',$value,$match ) )
				return get_url( trim($match[1]), $post_fields );
			}
		}
	}

	//Check for javascript redirects
	if ( ( preg_match("/>[[:space:]]+window\.location\.replace\('(.*)'\)/i", $content, $value)
	|| preg_match("/>[[:space:]]+window\.location\=\"(.*)\"/i", $content, $value) )
	&& $javascript_loop < 5 ){
		return get_url( $value[1], $post_fields, $javascript_loop+1 );
	}	else {
		return array( $content, $response, $curl_error );
	}
}

function whmcs_multisite_CreateAccount($params) {

	# ** The variables listed below are passed into all module functions **

	$serviceid = $params["serviceid"]; # Unique ID of the product/service in the WHMCS Database
	$pid = $params["pid"]; # Product/Service ID
	$producttype = $params["producttype"]; # Product Type: hostingaccount, reselleraccount, server or other
	$domain = $params["domain"];
	$username = $params["username"];
	$password = $params["password"];
	$clientsdetails = $params["clientsdetails"]; # Array of clients details - firstname, lastname, email, country, etc...
	$customfields = $params["customfields"]; # Array of custom field values for the product
	$configoptions = $params["configoptions"]; # Array of configurable option values for the product

	# Product module option settings from ConfigOptions array above
	$configoption1 = $params["configoption1"];
	$configoption2 = $params["configoption2"];
	$configoption3 = $params["configoption3"];
	$configoption4 = $params["configoption4"];
	$configoption5 = $params["configoption5"];
	$configoption6 = $params["configoption6"];

	# Additional variables if the product/service is linked to a server
	$server = $params["server"]; # True if linked to a server
	$serverid = $params["serverid"];
	$serverhostname = $params["serverhostname"];
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$serveraccesshash = $params["serveraccesshash"];
	$serversecure = $params["serversecure"]; # If set, SSL Mode is enabled in the server config

	# Code to perform action goes here...

	$credentials = array(
	'user_login' => $params['serverusername'],
	'user_password' => $params['serverpassword'],
	'remember' => 0,
	'whmcs_client_id' => $clientsdetails['userid'],
	'whmcs_service_id' => $params['serviceid'],
	'whmcs_product_id' => $params['pid'],
	'level' => $params['configoption8'],
	);

	// Default Wordpress user name everything before the @ in their whmcs email
	$wp_user_name = explode('@',$clientsdetails['email']);
	$wp_user_name = $wp_user_name[0];

	$api_admin = $params["configoption7"];

	if (empty($api_admin)) return 'Product does not have an authorizing administrator defined. Please update the product module setting.';

	$request = array();

	$request['action'] = 'create';

	// figure out the subdomain/subdirectory or domain mapping
	if (empty($params['domain'])){
		//Has to be either subdomain or subdirectory
		$request['domain'] = ($params['configoption4'] == 'on') ? $customfields['Domain'] : $params['configoption2'];
	} else {
		//Could be domain mapping
		$request['mapped_domain'] = $params['domain'];
		//Or it could be sub
		$sub = explode('.',$params['domain']);
		if (count($sub) > 2) $request['domain'] = $sub[0];
		else $request['domain'] = $params['configoption2'];
	}


	if( $request['domain'] . $request['mapped_domain'] == '') return 'Domain field is Empty!';

	$request['title'] = ($params['configoption3'] == 'on' and !empty($customfields['Title'])) ? $customfields['Title'] : $params['configoption1'];

	$request['user_name'] = (empty($username)) ? $wp_user_name : $username;

	$request['password'] = $password;

	$request['email'] = $clientsdetails['email'];
	$request['last_name'] = $clientsdetails['lastname'];
	$request['first_name'] = $clientsdetails['firstname'];
	$request['default_role'] = $params['configoption5'];
	$request['upload_space'] = $params['configoption6'];
	$request['level'] = $params['configoption8'];
	$request['credentials'] = $credentials;

	$whmcs = array('whmcs' => $request);

	$post_fields =  http_build_query($whmcs);

	$url = (empty($params['serversecure'])) ? 'http://' : 'https://';
	$url .= $params['serverhostname'];

	$response = get_url($url, $post_fields);

	$ret = json_decode($response[0], true);

	logModuleCall('WHMCS_Multisite Server '.WPMU_WHMCS_SERVER_VERSION , 'CreateAccount', $post_fields, $response, $ret, array() );

	//return print_r($response,true);

	if (empty($ret)) {
		if (! empty($response[2])) return $response[2];
		return "Invalid data or no response: The receiving plugin may not be activated at: $url";
	}

	if (is_array($ret) && isset($ret['error'])) {
		$result = $ret['error'] . ":" . $ret['message'];
	} else {
		//Good data so update whmcs


		//Save to database for updates
		insert_query('mod_whmcs_multisite',
		array(
		'blog_id' => intval($ret['blog_id']),
		'service_id' => intval($params["serviceid"]),
		'domain' => $ret['domain'],
		'path' => $ret['path'],
		'level' => empty( $ret['level'] ) ? 0 : intval( $ret['level'] ),
		));

		//Save to service record
		$update = array(
		'serviceid' => $params["serviceid"],
		'serviceusername' => $ret['user_name'],
		);

		if( empty($ret['mapped_domain'])){ //Didn't domain map
			$update['domain'] = $ret['domain'].$ret['path'];
		}else{
			$update['domain'] = $ret['mapped_domain'];
		}

		if( !empty( $ret['password'] ) )			$update['servicepassword'] = $ret['password'];


		$result = localAPI('updateclientproduct', $update, $api_admin);

		$result = ($result['result']=='success') ? $result['result'] : $result['message'];
	}
	return $result;
}

function get_blog_data($service_id){
	// Updated for PHP 8.1+ and WHMCS 8.x - use Capsule instead of mysql_*
	try {
		$data = \WHMCS\Database\Capsule::table('mod_whmcs_multisite')
			->where('service_id', $service_id)
			->first();

		// Convert object to array for backwards compatibility
		return $data ? (array)$data : null;
	} catch (\Exception $e) {
		logModuleCall('whmcs_multisite', 'get_blog_data', $service_id, $e->getMessage(), '', []);
		return null;
	}
}

function whmcs_multisite_TerminateAccount($params) {

	# Code to perform action goes here...
	$customfields = $params["customfields"]; # Array of custom field values for the product
	$clientsdetails = $params["clientsdetails"]; # Array of clients details - firstname, lastname, email, country, etc...

	$credentials = array(
	'user_login' => $params['serverusername'],
	'user_password' => $params['serverpassword'],
	'remember' => 0,
	'whmcs_client_id' => $clientsdetails['userid'],
	'whmcs_service_id' => $params['serviceid'],
	'whmcs_product_id' => $params['pid'],
	);

	$data = get_blog_data($params['serviceid']);

	$request = array();

	$request['action'] = 'terminate';
	$request['blog_id'] = $data['blog_id'];
	$request['domain'] = $data['domain'];
	$request['credentials'] = $credentials;

	$whmcs = array('whmcs' => $request);

	$post_fields =  http_build_query($whmcs);

	$url = (empty($params['serversecure'])) ? 'http://' : 'https://';
	$url .= $params['serverhostname'];

	$response = get_url($url, $post_fields);

	$ret = json_decode($response[0], true);

	logModuleCall('WHMCS_Multisite Server '.WPMU_WHMCS_SERVER_VERSION, 'TerminateAccount', $post_fields, $response, $ret, array() );

	if (empty($ret)) {
		if(! empty($response[2])) return $response[2];
		return "Invalid data: The receiving plugin may not be activated at: $url";
	}

	if (is_array($ret) && isset($ret['error'])) {
		$result = $ret['error'] . ":" . $ret['message'];
	} else {
		$result = 'success';
	}
	return $result;
}

function whmcs_multisite_SuspendAccount($params) {

	# Code to perform action goes here...
	$customfields = $params["customfields"]; # Array of custom field values for the product
	$clientsdetails = $params["clientsdetails"]; # Array of clients details - firstname, lastname, email, country, etc...

	$credentials = array(
	'user_login' => $params['serverusername'],
	'user_password' => $params['serverpassword'],
	'remember' => 0,
	'whmcs_client_id' => $clientsdetails['userid'],
	'whmcs_service_id' => $params['serviceid'],
	'whmcs_product_id' => $params['pid'],
	);

	$data = get_blog_data($params['serviceid']);

	$request = array();

	$request['action'] = 'suspend';
	$request['blog_id'] = $data['blog_id'];
	$request['domain'] = $data['domain'];
	$request['credentials'] = $credentials;

	$whmcs = array('whmcs' => $request);

	$post_fields =  http_build_query($whmcs);

	$url = (empty($params['serversecure'])) ? 'http://' : 'https://';
	$url .= $params['serverhostname'];

	$response = get_url($url, $post_fields);

	$ret = json_decode($response[0], true);

	logModuleCall('WHMCS_Multisite Server '.WPMU_WHMCS_SERVER_VERSION, 'SuspendAccount', $post_fields, $response, $ret, array() );

	if (empty($ret)) {
		if(! empty($response[2])) return $response[2];
		return "Invalid data: The receiving plugin may not be activated at: $url";
	}

	if (is_array($ret) && isset($ret['error'])) {
		$result = $ret['error'] . ":" . $ret['message'];
	} else {
		$result = 'success';
	}
	return $result;
}

function whmcs_multisite_ChangePackage($params) {

	$credentials = array(
	'user_login' => $params['serverusername'],
	'user_password' => $params['serverpassword'],
	'remember' => 0,
	'whmcs_client_id' => $clientsdetails['userid'],
	'whmcs_service_id' => $params['serviceid'],
	'whmcs_product_id' => $params['pid'],
	);

	$service_id = $params['serviceid'];
	$data = get_blog_data($params['serviceid']);
	$request = array();
	$request['action'] = 'changepackage';
	$request['blog_id'] = $data['blog_id'];
	$request['domain'] = $data['domain'];
	$request['credentials'] = $credentials;
	$request['level'] = $params['configoption8'];
	$whmcs = array('whmcs' => $request);
	$post_fields =  http_build_query($whmcs);
	$url = (empty($params['serversecure'])) ? 'http://' : 'https://';
	$url .= $params['serverhostname'];
	$response = get_url($url, $post_fields);
	$ret = json_decode($response[0], true);

	logModuleCall('WHMCS_Multisite Server '.WPMU_WHMCS_SERVER_VERSION, 'ChangePackage', $post_fields, $response, $ret, array() );

	if (is_array($ret) && isset($ret['error'])) {
		$result = $ret['error'] . ":" . $ret['message'];
	} else {
		$level = empty( $ret['level'] ) ? 0 : intval( $ret['level'] );
		update_query('mod_whmcs_multisite', array('level' => $level ), array( 'service_id' => $service_id ) );
		$result = 'success';
	}
	return $result;
}

function whmcs_multisite_UnsuspendAccount($params) {

	# Code to perform action goes here...
	$customfields = $params["customfields"]; # Array of custom field values for the product
	$clientsdetails = $params["clientsdetails"]; # Array of clients details - firstname, lastname, email, country, etc...

	$credentials = array(
	'user_login' => $params['serverusername'],
	'user_password' => $params['serverpassword'],
	'remember' => 0,
	'whmcs_client_id' => $clientsdetails['userid'],
	'whmcs_service_id' => $params['serviceid'],
	'whmcs_product_id' => $params['pid'],
	);

	$data = get_blog_data($params['serviceid']);

	$request = array();

	$request['action'] = 'unsuspend';
	$request['blog_id'] = $data['blog_id'];
	$request['domain'] = $data['domain'];
	$request['credentials'] = $credentials;

	$whmcs = array('whmcs' => $request);

	$post_fields =  http_build_query($whmcs);

	$url = (empty($params['serversecure'])) ? 'http://' : 'https://';
	$url .= $params['serverhostname'];

	$response = get_url($url, $post_fields);

	$ret = json_decode($response[0], true);

	logModuleCall('WHMCS_Multisite Server '.WPMU_WHMCS_SERVER_VERSION, 'UnsuspendAccount', $post_fields, $response, $ret, array() );

	if (empty($ret)) {
		if(! empty($response[2])) return $response[2];
		return "Invalid data: The receiving plugin may not be activated at: $url";
	}

	if (is_array($ret) && isset($ret['error'])) {
		$result = $ret['error'] . ":" . $ret['message'];
	} else {
		$result = 'success';
	}
	return $result;
}

function whmcs_multisite_ChangePassword($params) {

	# Code to perform action goes here...
	$customfields = $params["customfields"]; # Array of custom field values for the product
	$clientsdetails = $params["clientsdetails"]; # Array of clients details - firstname, lastname, email, country, etc...
	$configoptions = $params["configoptions"]; # Array of configurable option values for the product
	$domain = $params["domain"];
	$username = $params["username"];
	$password = $params["password"];

	$credentials = array(
	'user_login' => $params['serverusername'],
	'user_password' => $params['serverpassword'],
	'remember' => 0,
	'whmcs_client_id' => $clientsdetails['userid'],
	'whmcs_service_id' => $params['serviceid'],
	'whmcs_product_id' => $params['pid'],
	);

	$data = get_blog_data($params['serviceid']);

	$request = array();

	$request['action'] = 'password';
	$request['blog_id'] = $data['blog_id'];
	$request['domain'] = $data['domain'];
	$request['user_name'] = $username;
	$request['password'] = $password;
	$request['email'] = $clientsdetails['email'];
	$request['credentials'] = $credentials;

	$whmcs = array('whmcs' => $request);

	$post_fields =  http_build_query($whmcs);

	$url = (empty($params['serversecure'])) ? 'http://' : 'https://';
	$url .= $params['serverhostname'];

	$response = get_url($url, $post_fields);

	$ret = json_decode($response[0], true);

	logModuleCall('WHMCS_Multisite Server '.WPMU_WHMCS_SERVER_VERSION, 'ChangePassword', $post_fields, $response, $ret, array() );

	if (empty($ret)) {
		if(! empty($response[2])) return $response[2];
		return "Invalid data: The receiving plugin may not be activated at: $url";
	}

	if (is_array($ret) && isset($ret['error'])) {
		$result = $ret['error'] . ":" . $ret['message'];
	} else {
		$result = 'success';
	}
	return $result;

}

function whmcs_multisite_AdminLink($params) {
	// Updated to support both HTTP and HTTPS
	$protocol = (!empty($params['serversecure'])) ? 'https://' : 'http://';
	$hostname = htmlspecialchars($params["serverhostname"], ENT_QUOTES, 'UTF-8');
	$username = htmlspecialchars($params["serverusername"], ENT_QUOTES, 'UTF-8');
	$password = htmlspecialchars($params["serverpassword"], ENT_QUOTES, 'UTF-8');

	$code = '<form action="' . $protocol . $hostname . '/wp-login.php" method="post" target="wpadmin">
	<input type="hidden" name="log" value="' . $username . '" />
	<input type="hidden" name="pwd" value="' . $password . '" />
	<input type="hidden" name="redirect_to" value="' . $protocol . $hostname . '/wp-admin/" />
	<input type="submit" value="Login to WordPress" class="btn btn-default" />
	</form>';
	return $code;

}


function whmcs_multisite_LoginLink($params) {
	// Updated to support both HTTP and HTTPS, and improved security
	$protocol = (!empty($params['serversecure'])) ? 'https://' : 'http://';
	$hostname = htmlspecialchars($params["serverhostname"], ENT_QUOTES, 'UTF-8');
	$username = htmlspecialchars($params["serverusername"], ENT_QUOTES, 'UTF-8');

	// Lock the username and custom fields for WordPress Sites since it shouldn't be changed
	?>
	<script type="text/javascript">
		jQuery(document).ready(function(){
			jQuery(
			'input[name="username"],\
			input[name="domain"],\
			input[name^="customfield"],\
			select[name^="packageid"],\
			').attr('readonly','readonly');
		});
	</script>
	<form action="<?php echo $protocol . $hostname; ?>/wp-login.php" method="post" target="wpadmin" style="display:inline;">
		<input type="hidden" name="log" value="<?php echo $username; ?>" />
		<input type="hidden" name="pwd" value="<?php echo htmlspecialchars($params['serverpassword'], ENT_QUOTES, 'UTF-8'); ?>" />
		<input type="submit" value="Login to WordPress" class="btn btn-link" style="color:#cc0000;padding:0;border:0;background:none;" />
	</form>

	<?php
}

/*
function whmcs_multisite_reboot($params) {

# Code to perform reboot action goes here...

if ($successful) {
$result = "success";
} else {
$result = "Error Message Goes Here...";
}
return $result;

}

function whmcs_multisite_shutdown($params) {

# Code to perform shutdown action goes here...

if ($successful) {
$result = "success";
} else {
$result = "Error Message Goes Here...";
}
return $result;

}


function whmcs_multisite_ClientAreaCustomButtonArray() {
$buttonarray = array(
"Reboot Server" => "reboot",
);
return $buttonarray;
}

function whmcs_multisite_AdminCustomButtonArray() {
$buttonarray = array(
"Reboot Server" => "reboot",
"Shutdown Server" => "shutdown",
);
return $buttonarray;
}

function whmcs_multisite_extrapage($params) {
$pagearray = array(
'templatefile' => 'example',
'breadcrumb' => ' > <a href="#">Example Page</a>',
'vars' => array(
'var1' => 'demo1',
'var2' => 'demo2',
),
);
return $pagearray;
}

function whmcs_multisite_UsageUpdate($params) {

$serverid = $params['serverid'];
$serverhostname = $params['serverhostname'];
$serverip = $params['serverip'];
$serverusername = $params['serverusername'];
$serverpassword = $params['serverpassword'];
$serveraccesshash = $params['serveraccesshash'];
$serversecure = $params['serversecure'];

# Run connection to retrieve usage for all domains/accounts on $serverid

# Now loop through results and update DB

foreach ($results AS $domain=>$values) {
update_query("tblhosting",array(
"diskused"=>$values['diskusage'],
"dislimit"=>$values['disklimit'],
"bwused"=>$values['bwusage'],
"bwlimit"=>$values['bwlimit'],
"lastupdate"=>"now()",
),array("server"=>$serverid,"domain"=>$values['domain']));
}

}
*/

function whmcs_multisite_AdminServicesTabFields($params) {
	// Updated for PHP 8.1+ and WHMCS 8.x - use Capsule instead of mysql_*
	try {
		$data = \WHMCS\Database\Capsule::table('mod_whmcs_multisite')
			->where('service_id', $params['serviceid'])
			->first();

		if ($data) {
			$fieldsarray = array(
				'Subdomain/Subdirectory' => $data->domain ?? 'N/A',
				'Path' => $data->path ?? 'N/A',
				'Pro-Sites Level' => $data->level ?? 0,
			);
		} else {
			$fieldsarray = array(
				'Status' => 'No data found for this service',
			);
		}

		return $fieldsarray;
	} catch (\Exception $e) {
		logModuleCall('whmcs_multisite', 'AdminServicesTabFields', $params['serviceid'], $e->getMessage(), '', []);
		return array('Error' => 'Unable to retrieve service data');
	}

}

/*
function whmcs_multisite_AdminServicesTabFieldsSave($params) {
update_query("mod_customtable",array(
"var1"=>$_POST['modulefields'][0],
"var2"=>$_POST['modulefields'][1],
"var3"=>$_POST['modulefields'][2],
),array("serviceid"=>$params['serviceid']));
}

*/