<?php
/*
Plugin Name: WAC .csv Download Plugin
Description: Used for reporting via .csv files.
Version: 1.8.0
Author: Dave Wilson
*/
/*
 *Enqueue the script needed by the wac csv ajax function.  Note that they are dependent on jquery.
 *Hook it to the wp_enqueue_scrips hook.
 */
add_action( 'wp_enqueue_scripts', 'wac_csv_ajax_scripts' );
function wac_csv_ajax_scripts() {
wp_enqueue_script( 'wac-csv-ajax-handle', plugin_dir_url( __FILE__ ) . 'waccsv.js', array( 'jquery' ), '1.0.0', false );
wp_localize_script( 'wac-csv-ajax-handle', 'wac_csv_ajax', 
	//URL to wp-admin/admin-ajax.php to process the request   
	array( 'ajaxurl' => admin_url( 'admin-ajax.php' ),
	// generate a nonce with a unique ID "waccsvajax"
	// so that you can check it later when an AJAX request is sent
	'security' => wp_create_nonce( 'waccsvajax' )
	)); 
}

/*
 *Add the ajax action(s) and function.  An action value of wac_csv_ajax_hook coming in from the browser calls the wac_csv_action_function.
 */
add_action( 'wp_ajax_wac_csv_ajax_hook', 'wac_csv_action_function' );
//add_action( 'wp_ajax_nopriv_wac_csv_ajax_hook', 'wac_csv_action_function' ); // need this to serve non logged in users
function wac_csv_action_function(){
	check_ajax_referer( 'waccsvajax', 'security' ); //Check the nonce to see if it's valid.  If not simply die.
	
	//Get the wordpress database object and needed table names
	global $wpdb;
	//$users_table = trim($wpdb->prefix . 'users');
	//$usermeta_table = trim($wpdb->prefix . 'usermeta');
	$tblprefix = trim($wpdb->prefix);
	$qry = "";
	switch($_REQUEST['wac_csv_qry']) {
		case 'users':
			// Here's the user table query
			$qry = 'SELECT * FROM ' . $tblprefix . 'users'; 
			$filename = 'users';
			log_me ('WACCSV users query: ' . $qry);
			break;
		case 'usermeta':
			$qry = 'SELECT * FROM ' . $tblprefix . 'usermeta';
			$filename = 'usermeta';
			log_me ('WACCSV usermeta query: ' . $qry);
			break;
		case 'userdata':
			$qry .= 	"SELECT ";
			$qry .= 	"  u0.id ";
			$qry .= 	", u0.user_login ";
		//	$qry .= 	", u0.user_nicename ";
			$qry .= 	", u0.user_email ";
			$qry .= 	", u0.user_registered ";
			$qry .= 	", u0.user_status ";
			$qry .= 	", u0.display_name ";
			$qry .= 	", if((SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'mail_bulletin') = 1, TRUE, FALSE) 'mail_bulletin' ";
			$qry .= 	", (SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'address') 'address' ";
			$qry .= 	", (SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'city') 'city' ";
			$qry .= 	", (SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'state')  'state' ";
			$qry .= 	", (SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'zip')  'zip' ";
			$qry .= 	"FROM " . $tblprefix . "users u0 ";
			$filename = 'userdata';
			log_me ('WACCSV userdata query: ' . $qry);
			break;		
		case 'skus':
			$qry .= 	"SELECT p1.id ";
			$qry .= 	"	, woi1.order_id ";
			$qry .= 	"	, woi1.order_item_id ";
			$qry .= 	"	, p1.post_status ";
			$qry .= 	"	, (SELECT DISTINCT pm0.meta_value from " . $tblprefix . "postmeta pm0 WHERE pm0.post_id = p1.id AND pm0.meta_key = '_customer_user') 'customer_user' ";
			$qry .= 	"	, (SELECT DISTINCT u0.user_nicename from " . $tblprefix . "users u0 WHERE u0.id = (SELECT DISTINCT pm0.meta_value from " . $tblprefix . "postmeta pm0 WHERE pm0.post_id = p1.id AND pm0.meta_key = '_customer_user') ) 'nicename' ";
			$qry .= 	", (SELECT DISTINCT pm0.meta_value from " . $tblprefix . "postmeta pm0 WHERE pm0.post_id = p1.id AND pm0.meta_key = '_order_total') 'order_total' ";			
			$qry .= 	"	, (SELECT DISTINCT woim0.meta_value FROM " . $tblprefix . "woocommerce_order_itemmeta woim0 WHERE woim0.order_item_id = woi1.order_item_id AND woim0.meta_key = '_line_total') 'line_total' ";
			$qry .= 	"	, (SELECT DISTINCT woim0.meta_value FROM " . $tblprefix . "woocommerce_order_itemmeta woim0 WHERE woim0.order_item_id = woi1.order_item_id AND woim0.meta_key = '_product_id') 'product_id' ";
			$qry .= 	"	, (SELECT DISTINCT 	pm0.meta_value FROM " . $tblprefix . "postmeta pm0 WHERE pm0.meta_key = '_sku' AND pm0.post_id = (SELECT DISTINCT woim0.meta_value FROM " . $tblprefix . "woocommerce_order_itemmeta woim0 WHERE woim0.order_item_id = woi1.order_item_id AND woim0.meta_key = '_product_id') ) 'product_sku' ";
			$qry .= 	"	, (SELECT DISTINCT woim0.meta_value FROM " . $tblprefix . "woocommerce_order_itemmeta woim0 WHERE woim0.order_item_id = woi1.order_item_id AND woim0.meta_key = '_variation_id') 'variation_id' ";
			$qry .= 	"	, (SELECT DISTINCT 	pm0.meta_value FROM " . $tblprefix . "postmeta pm0 WHERE pm0.meta_key = '_sku' AND pm0.post_id = (SELECT DISTINCT woim0.meta_value FROM " . $tblprefix . "woocommerce_order_itemmeta woim0 WHERE woim0.order_item_id = woi1.order_item_id AND woim0.meta_key = '_variation_id') ) 'variation_sku' ";
			$qry .= 	"	, (SELECT DISTINCT pm0.meta_value from " . $tblprefix . "postmeta pm0 WHERE pm0.post_id = p1.id AND pm0.meta_key = '_payment_method') 'payment_method' ";
			$qry .= 	"	, (SELECT DISTINCT pm0.meta_value from " . $tblprefix . "postmeta pm0 WHERE pm0.post_id = p1.id AND pm0.meta_key = '_transaction_id') 'transaction_id' ";			
			$qry .= 	"	, p1.post_type ";
			/* $qry .= 	"	, p1.post_author "; */
			$qry .= 	"	, p1.post_date ";
			$qry .= 	"	, CONCAT(EXTRACT(YEAR FROM p1.post_date),'-',LPAD(EXTRACT(MONTH FROM p1.post_date),2,'0')) 'post_date_ym' ";
			$qry .= 	"	, p1.post_modified ";
			$qry .= 	"	, CONCAT(EXTRACT(YEAR FROM p1.post_modified),'-',LPAD(EXTRACT(MONTH FROM p1.post_modified),2,'0')) 'post_modified_ym' ";
			/* $qry .= 	"	, p1.post_title "; */
			$qry .= 	"	, p1.post_name ";
			$qry .= 	"FROM " . $tblprefix . "posts p1 ";
			$qry .= 	"INNER JOIN " . $tblprefix . "woocommerce_order_items woi1 ";
			$qry .= 	"	ON p1.ID = woi1.order_id ";
			$qry .= 	"WHERE p1.post_type='shop_order' ";
			$qry .= 	"ORDER BY p1.ID DESC ";
			$filename = 'skus';
			log_me ('WACCSV skus query: ' . $qry);
			break;				
		case 'userlist':
			$qry .= 	"SELECT ";
			$qry .= 	"	 u0.ID user_id ";
			$qry .= 	"	,u0.user_login user_login ";
			$qry .= 	"	,u0.display_name display_name ";
			$qry .= 	"	,u0.user_email user_email ";
			$qry .= 	"	,(SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'address') address ";
			$qry .= 	"	,(SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'city') city ";
			$qry .= 	"	,(SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'state') state ";
			$qry .= 	"	,(SELECT DISTINCT um0.meta_value FROM " . $tblprefix . "usermeta um0 WHERE um0.user_id = u0.ID AND um0.meta_key = 'zip') zip		 ";
			$qry .= 	"	,date(u0.user_registered) user_registered ";
		//	$qry .= 	"	,s0.subscription_type subscription_type ";
			$qry .= 	"	,date ( ";
			$qry .= 	"		GREATEST ( ";
			$qry .= 	"			 COALESCE ( DATE_SUB(s0.end_date,INTERVAL '11:30' HOUR_MINUTE),0) ";
			$qry .= 	"			,COALESCE ( STR_TO_DATE ((SELECT meta_value FROM " . $tblprefix . "usermeta WHERE meta_key = 'membership_expiration' AND user_id = u0.id),'%m-%d-%Y'),0) ";
			$qry .= 	"		) ";
			$qry .= 	"	) expiration_date ";
			$qry .= 	"	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND 	gg0.name = 'Active Members') 'Active Members' ";
			$qry .= 	"	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'Members') 'Members' ";
			$qry .= 	"	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'Annual Cabin User') 'Annual Cabin User' ";
			$qry .= 	"	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'CWaiver') 'CWaiver' ";
			$qry .= 	"	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'NWaiver') 'NWaiver' ";
			$qry .= 	"	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'RWaiver') 'RWaiver' ";	
			$qry .= 	"	, (SELECT count(gg0.name) from " . $tblprefix . "groups_group gg0 INNER JOIN " . $tblprefix . "groups_user_group gug0 ON gug0.group_id = gg0.group_id WHERE gug0.user_id = u0.id AND gg0.name = 'MWaiver') 'MWaiver' ";
			$qry .= 	"FROM " . $tblprefix . "users u0 ";
			$qry .= 	"LEFT OUTER JOIN	 ";
			$qry .= 	"	( ";
			$qry .= 	"	SELECT pm0.meta_value user_id ";
			$qry .= 	"	,woi0.order_item_name subscription_type ";
			$qry .= 	"	,max(pm1.meta_value) end_date ";
			$qry .= 	"	FROM " . $tblprefix . "postmeta pm0 ";
			$qry .= 	"	INNER JOIN " . $tblprefix . "woocommerce_order_items woi0 ";
			$qry .= 	"	ON pm0.post_id = woi0.order_id ";
			$qry .= 	"	INNER JOIN " . $tblprefix . "postmeta pm1 ";
			$qry .= 	"	ON pm1.post_id = pm0.post_id ";
			$qry .= 	"	WHERE pm1.meta_key = '_schedule_end' ";
			$qry .= 	"	AND (woi0.order_item_name = 'Membership Renewal' ";
			$qry .= 	"		OR woi0.order_item_name = 'New Membership' ";
			$qry .= 	"		OR woi0.order_item_name = 'Senior & Out-of-State Membership Renewal' ";
			$qry .= 	"		OR woi0.order_item_name = 'Senior & Out-of-State New Membership') ";
			$qry .= 	"	AND pm0.meta_key = '_customer_user' ";
			$qry .= 	"	GROUP BY pm0.meta_value ";
			$qry .= 	"	) s0 ";
			$qry .= 	"ON u0.ID = s0.user_id ";
			$qry .= 	"ORDER BY u0.ID DESC ";
			$filename = 'userlist';
			log_me ('WACCSV userlist query: ' . $qry);
			break;				
		default:
			//$qry = ''; //should add some logic here to handle this condition...
			$qry = 'SELECT * FROM ' . $users_table . ' LIMIT 0 , 5';
	}
	
	//Get the wordpress database object
	global $wpdb;
	// Run the query and put the results in an associative array
	$results = $wpdb->get_results($qry, ARRAY_A);
	$numrows = $wpdb->num_rows;
	log_me ('WACCSV query num_rows: ' . $numrows);
	log_me ('WACCSV query results count: ' . count($results));
/*
	foreach($results as $r => $r_value) {
		log_me ('WACCSV query results row: ' . "Key=" . $r . ", Value=" . $r_value . "<br>");
		foreach($r_value as $x => $x_value) {
			log_me ('WACCSV query results array: ' . "Key=" . $x . ", Value=" . $x_value . "<br>");
		}			
	}
*/	
	log_me ('WACCSV query results: ' . $results);
	
			
	//A name with a time stamp, to avoid duplicate filenames
	$date = new DateTime();
	$ts = $date->format( 'Y-m-d H:i:s' );	
	$filename .= "-$ts.csv";	
	
	//Download the file
	download_csv_results($results, $filename);

	die(); // this is required to return a proper result
}
/*
 * Add a shortcode and function that renders a form to let users download the specified query as a .csv file.
 * Usage: [wac_csv_export csvquery=Users]
 * Valid options for <query name>:
 *	- Users (dumps the user table)
 * @param array $atts attributes
 * @param string $content not used
 */
add_shortcode('wac_csv_export','wac_csv_export_sc');
function wac_csv_export_sc( $atts, $content = null ) {

	//Get the shortcode options and create a variable for each
	$options = shortcode_atts(
		array(
			'csvquery'			=> '',
			'submit_text'       => __( 'Download the %s file', WAC_CSV_DOMAIN )
		),
		$atts
	);
	extract( $options );
	
	//Create the hidden form values that will be needed...
	//	How this works. There are two hidden form fields that will be employed:
	//		- wac_csv_action = the value "csv" indicates the post came from the appropriate form
	//		- wac_csv_qry = contains the value the csvquery so we know which csv file to create
	$csvquery = trim( $options['csvquery'] ); //This is the trimmed csv query as specified
	$wac_csv_qry = str_replace(" ","",strtolower($csvquery)); //This is the form , created from the trimmed csv query
	
	//Check for a valid query action
	switch($wac_csv_qry) {
		case 'users':
		case 'usermeta':
		case 'userdata':
		case 'skus':
		case 'userlist':
		$formname = 'waccsv_' . $wac_csv_qry;
		break;
	default:
		$formname = '';
	}

	//If there is a valid csvquery, proceed
	if ( !empty($formname) ) { 
		//Build the user form
		$submit_text = sprintf( $options['submit_text'], wp_filter_nohtml_kses( $csvquery ) );
		$output .= '<div class="wac-csv">';
		$output .= '<form id="' . $formname . '">';
		$output .= '<input name="action" type="hidden" value="wac_csv_ajax_hook" />&nbsp;';
		$output .= '<input type="hidden" name="wac_csv_qry" value="' . esc_attr( $wac_csv_qry ) . '" />';
		$output .= '<input id="submit_button" value = "Export ' . $wac_csv_qry . '" type="button" onClick="waccsvexport(this);" />';
		$output .= '</form>';
		$output .= '</div>';
	} else {
		$output .= '<div class="wac_csv">';
		$output .= sprintf( __( 'Invalid csv extract query specified: %s ', WAC_CSV_DOMAIN ), wp_filter_nohtml_kses( $csvquery ) );
		$output .= '</div>';
	}
	return $output;
}

function download_csv_results($results=array(), $name = NULL, $delimiter=',', $enclosure='"' ) {
/*
A word on headers and output buffering to explain this code.  In PHP the header function does not respect code-initiatied output buffering, 
such as ob_start, etc.  This means headers can be coded out-of-sequence relative to the data they are intended to precede.  That's a good 
thing in this case because it allows the output buffer to be created and filled, the size of the buffer to be interrogated and sent 
in a header to the browser, then contents of the buffer to be sent to the browser using ob_end_flush.  
*/

	//If no file name gets passed into the function create one that will be unique...
	if( ! $name) {
		$name = md5(uniqid() . microtime(TRUE) . mt_rand()). '.csv';
  	}
	
	//Send the HTTP header to prep the browser for a .csv download.
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0");
	header('Content-Description: File Transfer');
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename='. $name);
	//  the following cache control header parameters are from http 1.0	
	//	header("Expires: 0");
	//	header("Pragma: no-cache");
	
	//start an output buffer, after first ending and cleaning any preexiting output buffer.  
	//The ob_end_clean may be unnecessary depending on the configuration of the specific PHP implementation, but at least it guarentees that the 
	//output buffer is empty when we start.
	ob_end_clean(); 
	ob_start();
	
	//Open the output stream
	$outstream = fopen("php://output", "w");
	
	//Create the column header row using array keys of first record.  Since Excel thinks files with "ID" as the first two 
	//characters are SYLK formatted, check for that and change ID to _ID if present.  This prevents a warning from being presented by Excel.
	$hrow = array_keys($results[0]);
	if (substr($hrow[0],0,2) == "ID") {
		$hrow[0] = "_" . $hrow[0];
	}
	log_me ('WACCSV column header row: ' . implode(",",$hrow));	

	//Write the column header row
	fputcsv($outstream, $hrow, $delimiter, $enclosure);
	
	//Write the rest of the data
	foreach($results as $result) {
		fputcsv($outstream, $result, $delimiter, $enclosure);
	}
	
	// Send the size of the output buffer to the browser
	$contLength = ob_get_length();
	header( 'Content-Length: '.$contLength);

	//log 
	log_me ('WACCSV ob_get_lenght: ' . $contLength);
	log_me ('WACCSV ob_get_level: ' . ob_get_level());
	log_me ('WACCSV ob_get_contents: ' . ob_get_contents());
	
	//close the output stream
	fclose($outstream);
	
	//flush the output buffer to the browser and stop
	ob_end_flush();
}	

?>
