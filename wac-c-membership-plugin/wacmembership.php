<?php 
/*
Plugin Name: WAC Membership Plugin
Description: WAC customizations related to managing memberships and site functionality.
Version: 1.9.1
Author: Dave Wilson
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/* Start Adding Functions Below this Line */

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* Custom logging functions - for troubleshooting. */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*00 start**************************************/
function log_me( $message ) {
    if ( WP_DEBUG === true ) {
        if ( is_array( $message ) || is_object( $message ) ) {
            error_log( print_r( $message, true ) );
        } else {
            error_log( $message );
        }
    }
}

function log_me_wac( $message ) {
    if ( WAC_DEBUG === true ) {
        if ( is_array( $message ) || is_object( $message ) ) {
            error_log( print_r( $message, true ) );
        } else {
            error_log( $message );
        }
    }
}

function log_me_stripe( $message ) {
    if ( WCSTRIPE_DEBUG === true ) {
        if ( is_array( $message ) || is_object( $message ) ) {
            error_log( print_r( $message, true ) );
        } else {
            error_log( $message );
        }
    }
}
/*00 end****************************************/

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* Misc. stuff to customize behaviours. */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*01 start**************************************
 *
 * Add filters to allow use of shortcodes in widgets
 */
add_filter( 'widget_text', 'shortcode_unautop' );
add_filter( 'widget_text', 'do_shortcode' );
/*01 end****************************************/

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* Woocommerce checkout and shop customizations. */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*01 start**************************************
 *
 * Add an asterisk to required user-meta fields
 */
add_filter( 'user_meta_field_config', 'wac_user_meta_field_config_add_asterisk', 10, 3 );

function wac_user_meta_field_config_add_asterisk( $field, $fieldID, $formName )
    {
    if ( !empty( $field['required'] ) || in_array( $field['field_type'], array( 
        'user_login',
        'user_email'
 ) ) )
        {
        if ( !empty( $field['field_title'] ) ) $field['field_title'].= '<span class="um_required">*</span>';
        }

    return $field;
    }
/*01 end****************************************/

/*02 begin**************************************
 *
 * Customize checkout fields:
 * - Make the phone number so it is not a required field at woocommerce checkout.
 * - Remove Billing Company, Order Notes fields
 *
 */
add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );
function custom_override_checkout_fields( $fields ) {
	$fields['billing']['billing_phone']['required'] = false;	/* make phone not required */
	unset($fields['billing']['billing_company']);				/* remove billing_company field */
	unset($fields['order']['order_comments']);					/* remove order_comments field */
	return $fields;
}
/*02 end****************************************/

/*03 begin**************************************
 *
 * Remove the related products that show up on the woocommerce shop and product pages.
 * wc_remove_related_products
 * 
 * Clear the query arguments for related products so none show.
 * Add this code to your theme functions.php file.  
 */
add_filter( 'woocommerce_related_products_args','wac_wc_remove_related_products', 10 ); 
function wac_wc_remove_related_products( $args ) {
	return array( );
}
/*03 end****************************************/

/*04 begin**************************************
 * Redirect the Continue Shopping URL from the default ( most recent product ) to a custom URL.
 */
add_filter( 'woocommerce_continue_shopping_redirect', 'custom_continue_shopping_redirect_url' );
function custom_continue_shopping_redirect_url ( $url ) {
	$url = site_url( '/shop-the-wac/' ); 
	return $url;
}
/*04 end****************************************/

/*05 begin**************************************
 * Changes the redirect URL for the Return To Shop button in the cart.
 */
add_filter( 'woocommerce_return_to_shop_redirect', 'wc_empty_cart_redirect_url' );
function wc_empty_cart_redirect_url( ) {
	return site_url( '/shop-the-wac/' ); 
}
/*05 end****************************************/

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* Custom top menu. */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*06 start**************************************
 *
 * Custom top-menu
 * - Differs depending on whether user is logged in
 */
add_filter( 'wp_nav_menu_items','wac_custom_top_menu', 10, 2 );

function wac_custom_top_menu( $items, $args ) {
	global $woocommerce; 
	$loginurl=$woocommerce->cart->get_cart_url( );
	$cartcount=0;
	$cartcount=$woocommerce->cart->cart_contents_count;
	// $carttotal=$woocommerce->cart->get_cart_total( ); 
	
	//global $user_info, $user_ID;
	$current_user=wp_get_current_user( );
	$name=$current_user->user_firstname; // or user_login , display_name, user_firstname, user_lastname
	$login=$current_user->user_login;
	$uid=$current_user->ID;
		
    if( $args->theme_location == 'top-menu' ) {
		$items = "";
	//	$items .='<li>';

		if ( !is_user_logged_in( ) ) {
			$items .= '<a href="' . get_page_link( 171 ) . '">Login</a>';
			if( $cartcount > 0 ) $items = $items . ' |<a href="' . get_page_link( 5 ).'"> Cart ( '.$cartcount.' )</a>';
		} else {
			// get the membership expiration date, and prep a couple values for use below.
			$expiration_date = wac_getexpirationdate( $uid );
			$exp_year = date( 'Y', strtotime( $expiration_date ) );
			$exp_month = date ( 'M', strtotime( $expiration_date ) );
			$exp_day = date ( 'd', strtotime( $expiration_date ) );
			// this conditional is a hack to address expirations occuring early the following year because of GMT/local time differences.
			// ugly, but...
			if ( $exp_month == 'Jan' && $exp_day == 1 ) { 
				$exp_year = $exp_year - 1; 
				$exp_month = "Dec";
			}
			$current_date = date( 'Y-m-d H:i:s' );
			
			// find out if the current user is in the Member Waiver group.  If not, we'll show the update waiver link.
			$current_waiver = false;
			// get array of groups to which member belongs and set flags accordingly
			$groups = wac_user_groups_list ( $uid );
			foreach( $groups as $group ) {
				switch( $group["group_name"] ) {
					case 'MWaiver': $current_waiver = true; break;				// They have signed a waiver at some point in the past.
				}
			}
			// set up the menu links...
			// get_page_link( 171 ) is the login/logout page
			// get_page_link( 355 ) is the membership status page
			// get_page_link( 5 ) is the cart 
			// get_page_link( 1034 ) is the user profile
		
			$items = $items . '<div style="display:table">';
			$items = $items . ' <div style="display: table-row">';
			$items = $items . '  <div class="wach1img" style="display: table-cell; vertical-align: middle; padding: 0px 5px 0px 0px;" title="Update your photo!">';
			$default = '';
			$items = $items . '   <a href="' . get_home_url( ) . '/profile">' . get_avatar( $uid, 40,$default,$name ) . ' </a>';
			$items = $items . '  </div> '; 
			$items = $items . '  <div class="wach1links" style="display: table-cell; vertical-align: middle; text-align: left">';
			$items = $items . '   <a href="' . get_home_url( ) . '/profile">Welcome '.$name.' </a> |';
			$items = $items . '<a href="' . get_home_url( ) . '/my-membership-status/login&action=logout&redirect_to=' . get_home_url( ) . '/my-membership-status/login"> Logout</a>';
			if( $cartcount > 0 ) $items = $items . ' |<a href="' . get_home_url( ) . '/cart"> Cart ( '.$cartcount.' )</a>';
			$items = $items . '<br>';
			$items = $items . '<a href="' . get_home_url( ) . '/my-membership-status">Member Status: ';
			if ( $current_date > $expiration_date ) { 
				if ( date( 'Y', strtotime( $expiration_date ) ) < "2000" ) {
					$items = $items . '<span style="color:red">*Non-Member</span>';
				} else {
					$items = $items . '<span class="wach1exp" style="color:red">*Expired ' . $exp_month . '. ' . $exp_year . '</span>';
				}
			} else {
				$items = $items . 'Paid Thru Dec. '.$exp_year;
			} 
			$items = $items . '</a>';	
			if ( !$current_waiver ) {
				$items = $items . ' |';
				$items = $items . '<a href="' . get_home_url( ) . '/membership-waiver"><span style="color:red">*Update Waiver</span></a>';
			}
	//		$items = $items . '</li>';			
			
			$items = $items . '  </div>';
			$items = $items . ' </div>';
			$items = $items . '</div>';
		}
		
	}
	log_me( 'Top1 menu string:' );
	log_me( $items );
	return $items;
}
/*06 end****************************************/

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* WAC custom subscription ( membership ) expiration dates. */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*07 start**************************************
 *
 * WAC memberships expire on 12/31.  Signups before 7/1 expire 12/31 of same year.  Signups on or after 7/1 expire 12/31 of the following year.  In other
 * words, if you sign up mid-year you get the remainder of the current year free.
 *
 * Adds a filter on ‘woocommerce_subscriptions_product_expiration_date’ to override expiration date.  
 *
 * To change the cutoff date, modify $subscription_cutoff_month and $subscription_cutoff_day.
 */
add_filter( 'woocommerce_subscriptions_product_expiration_date', 'wac_subscriptions_product_expiration_date', 10, 3 );

function wac_subscriptions_product_expiration_date( $expiration_date, $product_id, $from_date ) {	
	
	//set the subscription cutoff month and day
	$options = get_option('wac_options');
	$mmdd = $options['membership_mmdd'];
	$subscription_cutoff_month = intval(substr($mmdd,0,2)); //first month of new subscription year
	$subscription_cutoff_day = intval(substr($mmdd,2,2)); //first day of new subscription year	
	
	//decompose the incoming expiration date
	$exp_dttm=strtotime( $expiration_date ); 
	$exp_year = date( 'Y', $exp_dttm );
	$exp_month = date( 'n', $exp_dttm );
	$exp_day = date( 'j', $exp_dttm );

	//compare subscription cutoff month and day to determine if expiration date needs to be adjusted.
	//We're working with annual subscriptions here.  The subscription_cutoff_month and day indicate
	//the first day of the new subscription cutoff, and our subscriptions always expire on 12/31.  
	//If we haven't reached the new subscription cutoff then we take a year off.  E.g. if I sign-up
	//for a 1-year membership on January 30, 2014, and the cutoff is 7/1, then the membership will
	//expire on 12/31/14, not 12/31/15, hence the need to deduct a year.
	if ( $exp_month < $subscription_cutoff_month ) {
	  --$exp_year;
	} elseif ( $exp_month = $subscription_cutoff_month && $exp_day < $subscription_cutoff_day ) {
	  --$exp_year;
	}  

	//create the new expiration date: adjusted to year end, appropriate year.
	$expiration_date = date( 'Y-m-d H:i:s',strtotime( '31-12-'.( $exp_year ).' 23:59:00' ) ); 

	return $expiration_date;
}
/*07 end****************************************/

/*08 start**************************************
 *
 * Since WAC memberships don't ( presently ) automatically renew, it is not helpful for a next payment date to be set.  This is because
 * a next payment date triggers renewal invoices, etc.  Therefore we're setting the next payment date to 0 for all subscriptions.
 * If, at some point in the future, we wished to automatically charge renewals, one would need to turn of manual renewals in Settings and
 * do away with this filter.
 *
 * If one wanted to do this only for specific products then this function would need to interrogate the product ID and act appropriately.
 *
 * The filter referenced is applied in calculate_date in the wc_subscription class.
 *
 */ 
add_filter( 'woocommerce_subscription_calculated_next_payment_date', 'wac_subscription_calculated_next_payment_date', 10, 2 );
 
function wac_subscription_calculated_next_payment_date( $next_payment_date, $subscription ) {	
	
	$next_payment_date = 0;

	return $next_payment_date;
}
/*08 end****************************************/

/*09 start**************************************
 *
 * This is one more filter ( action ) that could be used to clear a next payment date or change other aspects of a subscription.  
 * The associated do_action is in function update_statuses in the wc_subscription class.
 *
 */
/* 
add_action( 'dw_woocommerce_subscription_status_active', 'wac_subscription_status_active', 10, 1 );
 
function wac_subscription_status_active( $subscription ) {	
	
	$subscription->delete_date( 'next_payment' );

	return;
}
*/
/*09 end****************************************/

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* WAC functions regarding membership status, expiration, waivers, etc. */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*10 begin**************************************
 *
 * This is a shortcode to list info about a user's subscriptions
 */
add_shortcode( 'wac_usersubscriptions','wac_usersubscriptions_sc' ); 
 
function wac_usersubscriptions_sc ( $attributes, $content=null ) {    
	// parse attributes.  if no ID provided, use current user ID.
	extract( shortcode_atts( array( "id" => null ), $attributes ) );
	if( '{{empty}}'==$content ) { $content=""; }
	if( isset ( $id ) ) {
		$user_info = get_userdata( $id );
	} else {
		$id = get_current_user_id( );
		if ( $id == 0 ) {
			return ""; //not logged in
		} else {
			$user_info = get_userdata ( $id );
		}
	}
	if ( !$user_info ) { return ""; } //somethings wrong so return
	$uid = $user_info->ID;

	$user_subscriptions = wcs_get_users_subscriptions( $uid );
	$htmls = "";
	$htmls=$htmls."<ul>";  
	foreach ( $user_subscriptions as $subscription ) {
		if ( $subscription->has_status( array( 'active', 'expired' ) ) ) {
			$htmls=$htmls."Subscription ID: ".$subscription->order->id;
			$htmls=$htmls."<ul>";
				$order = new WC_Order( $subscription->order->id );
				foreach ( $order->get_items( ) as $key => $lineItem ) {
					//uncomment the following to see the full data
					//        echo '<pre>';
					//        print_r( $lineItem );
					//        echo '</pre>';
					$htmls=$htmls. '<li>Product : ' . $lineItem['name'];
					//if ( $lineItem['variation_id'] ) {
					//	$htmls=$htmls. ', Product Type : Variable Product';
					//} else {
					//	$htmls=$htmls. ', Product Type : Simple Product';
					//}
					$htmls=$htmls. ', Product ID : ' . $lineItem['product_id'] . '</li>';
				}
				$htmls=$htmls."<li>Status: ".$subscription->get_status( )."</li>";
				$htmls=$htmls."<li>Start Date: ".date( 'm-d-Y', strtotime( $subscription->get_date( 'start' ) ) )."</li>";
				$htmls=$htmls."<li>End Date:   ".date( 'm-d-Y', strtotime( $subscription->get_date( 'end' ) ) )."</li>";				
			$htmls=$htmls."</ul>";
		}
	}
	$htmls=$htmls."</ul>";

	return $htmls;
}
/*10 end****************************************/

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* Check all aspects of a users membership status */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*10a begin**************************************
 *
 * Evaluate a user's membership status and add/remove from groups which drive membership workflow.  The three basically
 * requirements for membership are:
 * 1. Must be registered.  
 *		- 	If they're signed on then they're registered, if they're not logged in then return null.
 * 2. Must have current waiver.
 *		- 	Waiver must have guid of valid waiver. If we update our waiver language we can give it a different guid which will 
 *			result in the member needing to sign the updated waiver.
 *		-	Waiver must have been signed with a specified number of days or the member will need to sign a new one.  This is currently
 *			set to 180 days.  
 *		-	If these conditions are not met, the user will not be able to pay for membership.
 *		-	Using Smartwaiver API_VERSION = v3, which can be found in wp-config.php
 *	3. Must have an active membership subscription.
 *		-	This can simply be determined by checking the the individual is in the "Active Members" group.  Woocommerce takes care
 *			of adding and removing members from this group, as appropriate.
 *
 * Workflow: State is captured by adding and removing a user from groups.  This allows control of next possible states because
 * group membership can control which pages and products are accessible by a user.
 *
 * 
 *
 */
add_shortcode( 'wac_membershipstatus','wac_membershipstatus_sc' ); 
 
function wac_membershipstatus_sc ( $attributes, $content=null ) {
	// parse attributes.  if no ID provided, use current user ID.
	extract( shortcode_atts( array( "id" => null, "login" => null, "group" => null, "silent" => null ), $attributes ) );
	if( '{{empty}}'==$content ) { $content=""; }
	if( isset ( $id ) ) {
		$user_info = get_userdata( $id );
	} else {
		$id = get_current_user_id( );
		if ( $id == 0 ) {
			return ""; //not logged in
		} else {
			$user_info = get_userdata ( $id );
		}
	}
	if ( !$user_info ) { return ""; } //somethings wrong so return
	$uid = $user_info->ID;
	
	// initialize group flags
	$gRegistered = false;
	$gActiveMembers = false;
	$gMembers = false;
	$gRWaiver = false;
	$gNWaiver = false;
	$gCWaiver = false;
	
	// get array of groups to which member belongs and set flags accordingly
	$groups = wac_user_groups_list ( $uid );
    foreach( $groups as $group ) {
		switch( $group["group_name"] ) {
			case 'Registered': $gRegistered = true; break;			// They have registered for the site
			case 'Active-Members': $gActiveMembers = true; break;	// They are an active member (i.e. dues are current)
			case 'Members': $gMembers = true; break;				// They are either an active member now or were in the past.
			case 'MWaiver': $gMWaiver = true; break;				// They have signed a waiver at some point in the past.
			case 'RWaiver': $gRWaiver = true; break;				// They have a current waiver to renew but have not paid yet.
			case 'NWaiver': $gNWaiver = true; break;				// They have a current waiver to join but have not paid yet.
			case 'CWaiver': $gCWaiver = true; break;				// They have a current waiver and have paid.
		}
	}
	
	// 1. Registered
	// create the registered status html.
	if ( $gRegistered ) {
		$rHtmlStatus = '<div class="wacbox wacgreenbox"><strong>1. You are Registered!</strong></div><br>';
	} else {
		$rHtmlStatus = '<div class="wacbox wacredbox"><strong>1. You are NOT Registered!</strong></div><br>';
	}
	
	// 2. Waiver on file	
	// 2.a get any waivers on file for user
	$wApiResult = simplexml_load_file( "https://www.smartwaiver.com/api/" . API_VERSION . "/?rest_request=" . API_KEY . "&rest_request_tag=$uid" );

	// 2.b check for a current waiver.  Create the waiver detail html as you go. Start by getting the options.
	$options = get_option('wac_options');
	$wValidGuid = $options['waiver_id']; //waiver must have this guid to be current. e.g. $wValidGuid = "54090f9a22acc"
	$wAgeLimit = intval($options['waiver_age']); // waiver must be younger than this to be current. e.g. $wAgeLimit = 180;

	$wCurrent = false; 
	$wCount = 0;
	$wCurrTime = time( );
	$HighValues = 99999999;
	$wAgeMin = $HighValues; // age of the youngest waiver in days, or $HighValues if not set.
	$wHtmlDetail = '<span>';
	if( isset( $wApiResult->participants ) ) {
		$wHtmlDetail .= '<ul>';
		foreach( $wApiResult->participants->participant as $participant ) {
			$wCount++; // a signed waiver exists. may or may not be current				
			$wHtmlDetail .= '<li>';
			$wTime = date( "Y-m-d H:i", strtotime( $participant->date_created_utc ) );
			$wAgeDays = floor( ( $wCurrTime-strtotime( $participant->date_created_utc ) )/( 24*60*60 ) );
			if ( $wAgeDays < $wAgeMin ) { $wAgeMin = $wAgeDays; }
			// status for debugging
			$tStatus = "";
			$tStatus = '(age=' . $wAgeDays . ' days, valid=';
			if( $participant->waiver_type_guid == $wValidGuid ) { 
				$tStatus .= "TRUE) "; 
			} else { 
				$tStatus .= "FALSE) ";
			}
			// 
			if ( $participant->waiver_type_guid == $wValidGuid && $wAgeDays <= $wAgeLimit ) { 
				$wCurrent = true; // the waiver is current.
				$wHtmlDetail .= '<b>Current: </b>';
			} else {

				$wHtmlDetail .= '<b>Expired: </b>';
			}
			$wHtmlDetail .= $participant->waiver_title . ' dated ' . $wTime . ' for ' . $participant->firstname . ' ' . $participant->lastname . ' ';
			$wHtmlDetail .= '<br><i>' . $tStatus . '</i>';
			$wHtmlDetail .= '<a href="https://www.smartwaiver.com/api/' . API_VERSION . '/?rest_request=' . API_KEY . '& restapi_viewpdf=' . $participant->pdf_url . '" target="_blank"> ( view pdf ) </a>'; 
			$wHtmlDetail .= '</li>';			
		}
	}
	$wHtmlDetail .= '</ul>';
	
	// 2.c waiver status html
	$wHtmlStatus = "";
	if ( $wCurrent ) {
		$wHtmlStatus .= '<div class="wacbox wacgreenbox"><strong>2. Your Waiver is On File!  </strong></div><br>';
	} else {
		if ( $wCount == 0 ) {
			$wHtmlStatus .= '<div class="wacbox wacredbox"><strong>2. Waiver not on-file.</strong></div>';
		} else {
			$wHtmlStatus .= '<div class="wacbox wacyellowbox"><strong>2. Waiver not current.</strong></div>';
		}
		$wHtmlStatus .= '<ul>';
		$wHtmlStatus .= '	<li><a title="Membership Waiver" href="' . get_home_url( ) . '/membership-waiver">Click here to complete a new Membership Waiver</a></li><li>Please note that you will need to validate your email address to complete this online waiver.  Fill out the waiver, then check your inbox for an email requesting the validation of your email address.  If you don\'t find the email in your inbox, check your spam.</li><li><strong><i>Once your waiver is completed, a link to pay membership dues will appear after the Membership Status box, below.</i></strong></li>';
		$wHtmlStatus .= '</ul>';
	}
	
	// 3. Active Membership Subscription
	// 3.a membership status html
	$expirationdate = wac_expirationdate_sc( array( "id"=>$uid ) );
	$mHtmlStatus = "";
	if ( $gActiveMembers ) {
		$mHtmlStatus .= '<div class="wacbox wacgreenbox"><strong>3. Your Membership Dues paid thru: ' . $expirationdate . ' </strong></div>';
	} else {
		$mHtmlStatus .= '<div class="wacbox wacredbox"><strong>3. Your Membership Dues are NOT Current!</strong></div>';
	}
	$mHtmlStatus .= '<ul>';
	$mHtmlStatus .= '<li>Expiration date on file: ' . $expirationdate . '</li>';
	if ( $wCurrent ) {
		$mHtmlStatus .= '<li><strong><a title="Pay Membership Dues" href="' . get_home_url( ) . '/pay-membership-dues">Click here to pay for Membership Dues</a></strong></li>';
	} else {
		$mHtmlStatus .= '<li><strong><i>You must have a current waiver on file to pay for membership.  Complete a waiver then return here to pay membership dues.</i></strong></li>';
	}
	$mHtmlStatus .= '</ul>';	

	// 3.b membership detail html
	$mHtmlDetail = wac_usersubscriptions_sc( array( "id"=>$uid ) );
	
	// update group memberships
	//  - About this: 
	//    	a. 	When a person pays for a membership subscription, woocommerce adds them 
	//			to <Active Members> and <CWaivers> for the duration of the subscription.
	//		b.	If <Active Members> then the user should also be in <Members>, which is a group containing 
	//			active and prior members.  There's a cron function that does that, but we also do it here for this specific
	//			member in case the cron hasn't fired yet.
	//		c.	If the waiver is valid ( i.e. the right waiver and not too old ) we add them 
	//			to NWaiver ( for new members ) or RWaiver ( for renewal members ).  These groups enable access to the
	//			appropriate membership products.  There is a cron function that removes them from these groups once
	//			they've paid for membership.
	//
	// q: 
	$gHtmlStatus ="";
	if ( $gActiveMembers ) {
		// add to <Members>
		wac_user_groups_add ( $uid, "Members" );
		//$gHtmlStatus .= "Added to Members <br>";
	}
	if ( $wCurrent ) {
		wac_user_groups_add ( $uid, "MWaiver" );
		if ( $gRegistered && $gMembers && !$gRWaiver ) {
			// add to <RWaiver>
			wac_user_groups_add ( $uid, "RWaiver" );
			//$gHtmlStatus .= "Added to RWaiver <br>";
		} 
		if ( $gRegistered && !$gMembers && !$gNWaiver ) {
			// add to <NWaiver>
			wac_user_groups_add ( $uid, "NWaiver" );
			//$gHtmlStatus .= "Added to NWaiver <br>";
		}
	} else {
		// remove from NWaiver and RWaiver if no current waiver exists
		wac_user_groups_remove ( $uid, "NWaiver" );
		wac_user_groups_remove ( $uid, "RWaiver" );
	}
	
	// Put together all the html and return it
	$rHtmls = '';
	$rHtmls .= '<h3>Membership Status for ' . $user_info->first_name . ' ' . $user_info->last_name . ':</h3>';
	$rHtmls .= $rHtmlStatus . $wHtmlStatus . $mHtmlStatus;
	$rHtmls .= '<h4>Waivers On-File:</h4>';
	$rHtmls .= $wHtmlDetail;
	$rHtmls .= '<h4>Membership Subscription History:</h4>';
	$rHtmls .= $mHtmlDetail;
	$rHtmls .= '<br>' . $gHtmlStatus . '<br>'; // For testing - remove for prod
	
	return ( $rHtmls );
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* Functions to work with groups */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*11a start**************************************
 * Get the list of groups a user belongs to
 */
function wac_user_groups_list ( $uid=null ) {

	if ( !isset( $uid ) ) {return;}
	
	global $wpdb;
	$tblprefix = trim( $wpdb->prefix );
	$qry  = "";
	$qry .= "SELECT gg0.group_id ";
	$qry .= "	,REPLACE ( gg0.name,' ','-' ) group_name ";
	$qry .= "	,gug0.user_id user_id ";
	$qry .= "FROM " . $tblprefix . "groups_group gg0 ";
	$qry .= "INNER JOIN " . $tblprefix . "groups_user_group gug0 ";
	$qry .= "ON gg0.group_id = gug0.group_id ";
	$qry .= "WHERE gug0.user_id = " . $uid . " ";

	// Run the query and put the results in an associative array
	$results = $wpdb->get_results( $qry, ARRAY_A );		

	return ( $results );
}
/*11a end****************************************/

/*11b start**************************************
 * Add a user to a group ( silently )
 */
add_shortcode( 'wac_groups_user_add_silent','wac_user_groups_add_sc' );
function wac_user_groups_add_sc( $attributes, $content = null ) {
	extract( shortcode_atts( array( "id" => null, "group" => null ), $attributes, 'wac_groups_join_silent' ) );
	return wac_user_groups_add ( $id, $group );
}
function wac_user_groups_add( $id, $group ) {
	if( !isset ( $group ) ) { return; }
	$group = trim( $group );
	if( isset ( $id ) ) {
		$user_info = get_userdata( $id );
	} else {
		$id = get_current_user_id( );
		if ( $id == 0 ) {
			return false; //not logged in
		} else {
			$user_info = get_userdata ( $id );
		}
	}
	if ( !$user_info ) { return false; } //somethings wrong so return
	$uid = $user_info->ID;

	//verify that it's a valid group and if so, add user to the group
	$current_group = Groups_Group::read( $group );
	if ( !$current_group ) {
		$current_group = Groups_Group::read_by_name( $group );
	}
	if ( $current_group ) {
		Groups_User_Group::create( 
			array( 
				'group_id' => $current_group->group_id,
				'user_id' => $uid
			 )
		 );
	} else {
		return false;
	}
	return;
}
/*11b end****************************************/

/*11c start**************************************
 * Remove a user from a group ( silently )
 */
add_shortcode( 'wac_groups_user_remove_silent','wac_user_groups_remove_sc' );
function wac_user_groups_remove_sc( $attributes, $content = null ) {
	extract( shortcode_atts( array( "id" => null, "group" => null ), $attributes, 'wac_groups_join_silent' ) );
	return wac_user_groups_remove ( $id, $group );
} 
function wac_user_groups_remove ( $id, $group ) {
	if( !isset ( $group ) ) { return; }
	$group = trim( $group );
	if( isset ( $id ) ) {
		$user_info = get_userdata( $id );
	} else {
		$id = get_current_user_id( );
		if ( $id == 0 ) {
			return false; //not logged in
		} else {
			$user_info = get_userdata ( $id );
		}
	}
	if ( !$user_info ) { return false; } //somethings wrong so return
	$uid = $user_info->ID;
	
	//verify that it's a valid group and if so, add user to the group
	$current_group = Groups_Group::read( $group );
	if ( !$current_group ) {
		$current_group = Groups_Group::read_by_name( $group );
	}
	if ( $current_group ) {
		Groups_User_Group::delete( $uid, $group );
	} else {
		return false;
	}
	return;
}
/*11c end****************************************/

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* Functions to work with waivers */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*09 begin**************************************
 * Shortcode to create a tagged link to pass user data to a new waiver.
 * Shortcode Usage e.g.: [wac_newwaiver] click here [/wac_newwaiver]
 */
add_shortcode( 'wac_newwaiver','wac_newwaiver_sc' );   
 
function wac_newwaiver_sc ( $attributes, $content=null ) {   
	
	//use {{empty}} instead of nothing inside the shortcode
	if( '{{empty}}'==$content ) $content="";
	
	return '<a href="' . wac_newwaiverurl( ) . '" target="_blank">' . do_shortcode( $content ) . '</a>';
}
/*09 end****************************************/

/*10 begin**************************************
 * Create tagged URL for entering a new waiver.
 */
function wac_newwaiverurl ( ) {   
	
    //global $user_info, $user_ID;
	global $current_user;
	
    get_currentuserinfo( );

    //$user_info = get_userdata( $user_ID );
	
	if ( !is_user_logged_in( ) ) {
		//User must be logged in to create waiver, so if not return them to the login page.
		//$htmls = get_page_link( 171 );
		$htmls = get_home_url( ) . '/my-membership-status/login';
	} else {
		$uid = $current_user->ID;
		//$uid = "wac" . $uid; 
		$ufirstname = $current_user->first_name;
		$ulastname = $current_user->last_name;
		$ubirthday = date( 'Ymd', strtotime($current_user->birthday));
		$uphone = $current_user->home_phone;
		$uaddresslineone = $current_user->address;
		$ucity = $current_user->city;
		$ustate = $current_user->state;
		$uzip = $current_user->zip;
		$uemail = $current_user->user_email;
	/*
		$htmls = $htmls . 'https://www.smartwaiver.com/auto/?auto_waiverid=54090f9a22acc&auto_tag=' . $uid .
			'&auto_fill_firstname=' . $ufirstname . 
			'&auto_fill_lastname=' . $ulastname . 
			'&auto_fill_phone=' . $uphone . 
			'&auto_fill_addresslineone=' . $uaddresslineone . 
			'&auto_fill_city=' . $ucity . 
			'&auto_fill_state=' . $ustate . 
			'&auto_fill_zip=' . $uzip . 
			'&auto_fill_email=' . $uemail;
	*/
		$htmls =  'https://www.smartwaiver.com/w/54090f9a22acc/web/' . 
			'?wautofill_firstname=' . $ufirstname . 
			'&wautofill_lastname=' . $ulastname .
			'&wautofill_dobyyyymmdd=' . $ubirthday .
			'&wautofill_tag=' . $uid;
	}
		
	return $htmls;
}
/*10 end****************************************/

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* Functions to work with membership subscriptions */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*12 begin**************************************
 * Shortcode to return membership expiration date.
 *
 * Shortcode Usage e.g.: [wac_expirationdate] or [wac_expirationdate id=138]
 * Attributes:
 * - "id" : ( optional ) user_id
 */
add_shortcode( 'wac_expirationdate','wac_expirationdate_sc' ); 
function wac_expirationdate_sc ( $attributes, $content=null ) {   
	extract( shortcode_atts( array( "id" => null ), $attributes ) );
	if( '{{empty}}'==$content ) { $content=""; }
	if( isset ( $id ) ) {
		$user_info = get_userdata( $id );
	} else {
		$id = get_current_user_id( );
		if ( $id == 0 ) {
			return ""; //not logged in
		} else {
			$user_info = get_userdata ( $id );
		}
	}
	if ( !$user_info ) { return ""; } //somethings wrong so return
	$uid = $user_info->ID;
	
	//a word about what follows.  If a timestamp is null, or otherwise a non-date, php will return a date
	//along the lines of 01-01-1970.  Rather than just check for this value, the logic below assumes that for practical 
	//purposes anything prior to the year 2000 means a valid membership expiration date doesn't exist.  
	//Similar logic is in wac_custom_top_menu.
	//There are two conditions when this condition will be met:
	// 1. Admin accounts that aren't associated with subscriptions ( such as wacadmin )
	// 2. Potential members who have registered, but who haven't purchased a subscription.
		// parse attributes.  if no ID provided, use current user ID.	
	if ( date( 'Y', strtotime( wac_getexpirationdate( $uid ) ) ) < "2000" ) {
		return "*Non-Member";
	} else {
		return date( 'm-d-Y', strtotime( wac_getexpirationdate( $uid ) ) );
	}
}
/*12 end****************************************/

/*11 begin**************************************
 * Get the WAC membership expiration date for the current user.
 * - This will be the greater of either the conversion expiration date or the expiration date of a subscription.
 */
function wac_getexpirationdate ( $id ) {  

	//global $user_info, $user_ID;
	if( isset ( $id ) ) {
		$user_info = get_userdata( $id );
	} else {
		$id = get_current_user_id( );
		if ( $id == 0 ) {
			return ""; //not logged in
		} else {
			$user_info = get_userdata ( $id );
		}
	}
	if ( !$user_info ) { return ""; } //somethings wrong so return
	
	//here we find the expiration data for the current user.  This will be the greater of either the 
	//conversion expiration date or the expiration data of a subscripton.
	//set $expiration_date to sometime in the past
	$expiration_date = date( 'Y-m-d H:i:s', 0 );
	log_me ( 'MbrExpirationDate ( $expiration_date ): ' . $expiration_date );
	
	//check conversion expiration date - these are the expiration dates ported from the old website.  
	//get_currentuserinfo( );
	//$user_info = get_userdata( $user_ID );
	$field = "membership_expiration";
	$ymd = date( 'Y-m-d H:i:s', strtotime( str_replace( '-', '/', $user_info->$field ) ) ); 
	if ( strtotime( $ymd ) > strtotime( $expiration_date ) ) {
		$expiration_date = date( 'Y-m-d H:i:s', strtotime( $ymd ) );
	}
	log_me ( 'MbrExpirationDate ( $user_info->$field ): ' . $user_info->$field );
	
	//check subscription expiration date - in the new website subscription expiry dates signify when memberships expire.
	$user_subscriptions = wcs_get_users_subscriptions( );
	foreach ( $user_subscriptions as $subscription ) {
		if ( $subscription->has_status( array( 'active', 'expired' ) ) ) {
			if ( strtotime( $subscription->get_date( 'end_date' ) ) > strtotime( $expiration_date ) ) { 
				$expiration_date = date( 'Y-m-d H:i:s', strtotime( $subscription->get_date( 'end_date' ) ) );	
			}
		}
	}
	return $expiration_date;
}
/*11 end****************************************/



///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* CRON Functions */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*15 begin**************************************
 Background: Woocommerce can automatically add and remove people from a group at membership creation and expiration.  This is how people come and go from the "Active Members" group.
 
 However, to make it so previous members don't need to pay the new member fee we needed a group that they get added to when they become a member, but from which they don't get removed when their membership expires.  This is called the "Members" group and should include current and prior members.  Woocommerce doesn't handle this add-but-not-remove case, so this code was devised.
 
 The shortcode could be invoked on some common page ( admin reports ) in the website to run this query whenever that page is accessed.  Shortcode Usage: [wac_memberfix]
 
 A cron action is also created, with functions to schedule and unschedule the daily execution on plugin activation, deactivation.
*/
add_shortcode( 'wac_memberfix','wac_memberfix_sc' ); 
function wac_memberfix_sc ( $attributes, $content=null ) {   
	if( '{{empty}}'==$content ) $content=""; //use {{empty}} instead of nothing inside the shortcode
			
	//Get the wordpress database object and table prefix
    global $wpdb;
	$tblprefix = trim( $wpdb->prefix );

	//If someone is a member of the "Active Members" group, but not
	//a member of the "Members" group, add them to the "Members" group
	//by inserting a row into the groups_user_group table with their 
	//user_id and the group_id for the "Members" group.
	$qry  = 	"";
	$qry .= 	"INSERT INTO " . $tblprefix . "groups_user_group ( user_id, group_id ) ";
				//user_id's in "Active Members" group who are not in "Members" group...
	$qry .= 	"SELECT gug0.user_id, ( SELECT gg1.group_id FROM " . $tblprefix . "groups_group gg1 WHERE gg1.name = 'Members' ) 'targetgroup_id' ";
	$qry .= 	"FROM " . $tblprefix . "groups_user_group gug0 ";
	$qry .= 	"INNER JOIN " . $tblprefix . "groups_group gg0 ON gug0.group_id = gg0.group_id ";
	$qry .= 	"WHERE gg0.name = 'Active Members' ";
	$qry .= 	"AND gug0.user_id NOT IN ";
					//user_id's in "Members" group...
	$qry .= 	"	( ";
	$qry .= 	"	SELECT gug1.user_id ";
	$qry .= 	"	FROM " . $tblprefix . "groups_user_group gug1 ";
	$qry .= 	"	INNER JOIN " . $tblprefix . "groups_group gg1 ON gug1.group_id = gg1.group_id ";
	$qry .= 	"	WHERE gg1.name = 'Members' ";
	$qry .= 	"	 ) ";
	
	// Run the query, results will ether be number of rows returned or error
	$results = $wpdb->query( $qry );
	log_me ( 'WAC query memberfix: ' . $results );
	
	return;
}
/*15 end****************************************/

/*16 begin**************************************
 Once you have paid dues, you don’t need to do so again for a while so remove from
 the groups enabling dues payment.  Note that WACCheckWaiver, which is called when a 
 user accesses the Membership Status page, may re-add to one of these groups until the
 waiver is > 180 days old.  
 
 Logic:
	If ( <NWaiver> && <Active Members> ) { remove from <NWaiver> }
	If ( <RWaiver> && <Active Members> ) { remove rom <RWaiver> }

 A cron action is created below, with functions to schedule and unschedule the daily execution on plugin activation, deactivation.
*/
add_shortcode( 'wac_mwaiverfix','wac_waiverfix_sc' ); 
function wac_waiverfix_sc ( $attributes, $content=null ) {   
	if( '{{empty}}'==$content ) $content=""; //use {{empty}} instead of nothing inside the shortcode
			
	//Get the wordpress database object and table prefix
    global $wpdb;
	$tblprefix = trim( $wpdb->prefix );

	$qry  = 	"";
	$qry .= 	"DELETE gug0 FROM " . $tblprefix . "groups_user_group gug0 ";
	$qry .= 	"WHERE gug0.group_id IN ( SELECT gg1.group_id FROM " . $tblprefix . "groups_group gg1 WHERE gg1.name = 'NWaiver' or gg1.name = 'RWaiver' ) ";
	$qry .= 	"AND gug0.user_id IN ";
	$qry .= 	"	( ";
	$qry .= 	"	SELECT tmp0.user_id FROM ";
	$qry .= 	"		( ";
	$qry .= 	"		SELECT gug1.user_id ";
	$qry .= 	"		FROM " . $tblprefix . "groups_user_group gug1 ";
	$qry .= 	"		WHERE gug1.group_id IN ";
	$qry .= 	"			( ";
	$qry .= 	"			SELECT gug2.group_id ";
	$qry .= 	"			FROM " . $tblprefix . "groups_group gug2  ";
	$qry .= 	"			WHERE gug2.name = 'Active Members' ";
	$qry .= 	"			 ) ";
	$qry .= 	"		 ) tmp0 ";
	$qry .= 	"	 ) ";
	
	// Run the query, results will ether be number of rows returned or error
	$results = $wpdb->query( $qry );
	log_me ( 'WAC query waiverfix: ' . $results );
	
	return;
}
/*16 end****************************************/

/*17 begin**************************************
 CRON scheduling and unscheduling occurs here.
*/
// wac_<>_action will be called when the Cron is executed, invoking wac_<>_sc.
add_action( 'wac_memberfix_action', 'wac_memberfix_sc' );
add_action( 'wac_waiverfix_action', 'wac_waiverfix_sc' );

// schedule cron event on plugin activation ( if it doesn't already exist )
function waccron_activate( ) {
	if( !wp_next_scheduled( 'wac_memberfix_action' ) ) {  
	   wp_schedule_event( mktime( 0, 0, 0 ), 'daily', 'wac_memberfix_action' );  //start midnight gmt
	}
	if( !wp_next_scheduled( 'wac_waiverfix_action' ) ) {  
	   wp_schedule_event( mktime( 0, 30, 0 ), 'daily', 'wac_waiverfix_action' ); //start midnight + 30 min. gmt
	}	
}
register_activation_hook( __FILE__, 'waccron_activate' );

// unschedule cron event upon plugin deactivation
function waccron_deactivate( ) {	
	// find out when the last event was scheduled
	$timestamp = wp_next_scheduled ( 'wac_memberfix_action' );
	// unschedule previous event if any
	wp_unschedule_event ( $timestamp, 'wac_memberfix_action' );
	
	// find out when the last event was scheduled
	$timestamp = wp_next_scheduled ( 'wac_waiverfix_action' );
	// unschedule previous event if any
	wp_unschedule_event ( $timestamp, 'wac_waiverfix_action' );
} 
register_deactivation_hook ( __FILE__, 'waccron_deactivate' );

/*17 end****************************************/
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* WAC Admin Options  */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*18 begin**************************************
 This code creates a wac admin item on the wordpress admin settings menu, and a page to set some of the wac variables:
  . Waiver GUID	- guid of the current valid waiver.  If a signed waiver doesn't have this guid they'll need to sign one that does.
  . Waiver age	- maximum age of a waiver in days after which user must sign a new waiver to pay for membership.
  . Membership cutoff - mmdd after which member gets rest of current year for free.
*/
// add the admin options page
add_action('admin_menu', 'wac_admin_add_page');
function wac_admin_add_page() {
add_options_page('WAC Membership Settings Page', 'WAC Membership Settings', 'manage_options', 'wacadmin', 'wac_options_page');
}
// display the admin options page
function wac_options_page() {
?>
<div>
<h2>WAC Membership Settings</h2>
Options related to the WAC Membership plugin.
<form action="options.php" method="post">
<?php settings_fields('wac_options'); ?>
<?php do_settings_sections('wacadmin'); ?>
<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
</form></div>
<?php
}
// add the admin settings and such
add_action('admin_init', 'wac_admin_init');
function wac_admin_init(){
register_setting( 'wac_options', 'wac_options', 'wac_options_validate' );
add_settings_section('wacadmin_main', 'Main Settings', 'wac_section_text', 'wacadmin');
add_settings_field('wac_waiver_id', 'Valid SmartWaiver Waiver GUID', 'wac_setting_waiver_id', 'wacadmin', 'wacadmin_main');
add_settings_field('wac_waiver_age', 'Valid Max Waiver Age in Days (1-9999)', 'wac_setting_waiver_age', 'wacadmin', 'wacadmin_main');
add_settings_field('wac_membership_mmdd', 'Month and day from which membership purchase includes the rest of the current year for free (mmdd)', 'wac_setting_membership_mmdd', 'wacadmin', 'wacadmin_main');
}
// function callback for add_settings_section
function wac_section_text() {
echo '<p>These settings impact how the WAC Membership plugin behaves.</p>';
} 
// function callback for add_settings_field
function wac_setting_waiver_id() {
$options = get_option('wac_options');
echo "<input id='wac_waiver_id' name='wac_options[waiver_id]' size='40' type='text' value='{$options['waiver_id']}' />";
} 
// function callback for add_settings_field
function wac_setting_waiver_age() {
$options = get_option('wac_options');
echo "<input id='wac_waiver_age' name='wac_options[waiver_age]' size='40' type='text' value='{$options['waiver_age']}' />";
} 
// function callback for add_settings_field
function wac_setting_membership_mmdd() {
$options = get_option('wac_options');
echo "<input id='wac_membership_mmdd' name='wac_options[membership_mmdd]' size='40' type='text' value='{$options['membership_mmdd']}' />";
} 
// validate our options
function wac_options_validate($input) {
$message = null;
$type = null;
$options = get_option('wac_options');
// validate waiver_id
$options['waiver_id'] = trim($input['waiver_id']);
if(!preg_match('/^[a-z0-9]{10,15}$/i', $options['waiver_id'])) {
	//echo "Waiver ID must be from 10-15 alpha-numeric chars long.";
	$options['waiver_id'] = '';
	if ($type == 'error') { $message .= '<br>';}
	$message .= __('Waiver ID must be from 10-15 alpha-numeric chars long.');
	$type = 'error';
}
// validate waiver_age
$options['waiver_age'] = trim($input['waiver_age']);
if(!preg_match('/^[1-9][0-9]{0,3}$/i', $options['waiver_age'])) {
	$options['waiver_age'] = '';
	if ($type == 'error') { $message .= '<br>';}
	$message .= __('Waiver Age must be a number from 1 to 9999, inclusive.');
	$type = 'error';
}
// validate membership_mmdd
$options['membership_mmdd'] = trim($input['membership_mmdd']);
if(!preg_match('/^(01|03|05|07|08|10|12)(0[1-9]|1\d|2\d|3[01])|(04|06|09|11)(0[1-9]|1\d|2\d|30)|(02)(0[1-9]|1\d|2[0-8])$/i', $options['membership_mmdd'])) {
	$options['membership_mmdd'] = '';
	if ($type == 'error') { $message .= '<br>';}
	$message .= __('Month and day for Membership must be valid and in the form "mmdd".');
	$type = 'error';
}
if ($type != 'error') {
	$message = __('Options Saved!');
	$type = 'updated';
}
// add_settings_error( $setting, $code, $message, $type )
add_settings_error('wac_options_notice', 'wac_options_notice', $message, $type);
return $options;
}
?>
