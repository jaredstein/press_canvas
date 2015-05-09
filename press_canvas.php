<?php
/*
Plugin Name: Press+Canvas
Plugin URI: http://jaredstein.org/tools/press+canvas/
Description: Students can submit their WordPress Posts directly to the Canvas LMS as an assignment submission.
Version: 0.2
Author: Jared Stein
Author URI: http://jaredstein.org
*/

/*  Copyright 2012 Jared Stein  (email : jared@jstein.us)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/

/* @todo
VERIFY On publish, submit
VERIFY Confirmation message
 */

/* FUTURE DEV
Cache list of courses so we don't request it every time a Post is loaded, or at least wait for checkbox
Assignments ordered by date
Add comment with Submission URL
Add Page support
Handle multiple JSON response pages from Canvas (right now we handle just the first at 100 per page)
Allow for multiple Canvas schools: This requires not using WP Options but rather 
setting up a db table and inserting school URLs and tokens from Press+Canvas settings page
Support file submission by converting post to PDF
*/
 
/* Preliminary plugin setup */
define('PRCNVS_VERSION', '0.2');

//language
load_plugin_textdomain('press_canvas', false, dirname(plugin_basename(__FILE__)) . '/language');

if (!defined('PLUGINDIR')) {
	define('PLUGINDIR','wp-content/plugins');
}

if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'press_canvas.php')) {
	define('PRCNVS_FILE', trailingslashit(ABSPATH.PLUGINDIR).'press_canvas.php');
}

else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'press_canvas/press_canvas.php')) {
	define('PRCNVS_FILE', trailingslashit(ABSPATH.PLUGINDIR).'press_canvas/press_canvas.php');
}
 

/* Install Press+Canvas */

global $prcnvs_db_version;

$prcnvs_db_version = "0.01";

/**
 * Install Press+Canvas
 *
 * 
 */
function prcnvs_install_data() {
   global $wpdb;
   $welcome_text = 'Congratulations, installation of this plugin is complete!';
  }

register_activation_hook(__FILE__,'prcnvs_install');
register_activation_hook(__FILE__,'prcnvs_install_data');


/**
 * Load scripts, including jquery, our ajax.js; localize them
 *
 * @param int $hook WP stuff for the current page
 */
 
function prcnvs_load_scripts($hook) {
	//only load this on edit or post of pages
	if( $hook != 'edit.php' && $hook != 'post.php' && $hook != 'post-new.php' ) 
		return;
		
	// load jQuery, just in case
	wp_enqueue_script( 'jquery' );

	// embed the javascript file that makes the AJAX request
	wp_enqueue_script( 'cnvs_ajax_request', plugin_dir_url( __FILE__ ) . 'js/prcnvs_ajax.js', array( 'jquery' ) );

	// declare the URL to the WP file that handles the Ajax reqs (wp-admin/admin-ajax.php)
	// localize_script lets us pass values into JavaScript object properties, since PHP 
	// cannot directly echo values into our JavaScript file
	wp_localize_script( 'cnvs_ajax_request', 'PressCanvasAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}

// WP add this function in
add_action( 'admin_enqueue_scripts', 'prcnvs_load_scripts' );


/**
 * Create a Press+Canvas options box inside the WP Post menu
 *
 * @param int $hook WP stuff for the current page
 */
 
function prcnvs_meta_box($hook) {
		
	// Ensure our variables reference global variables
	global $prcnvs, $post, $prcnvs_link;
	
	//var_dump($post);
	
	// Ensure WP HTTP API is included
	if( !class_exists( 'WP_Http' ) ){
    	include_once( ABSPATH . WPINC. '/class-http.php' );
	}
	
	// Designate the url of this Post, either permalink or GUID
	if(!isset($permalink)){
		//If the post hasn't been saved yet it doesn't have a permalink
		$prcnvs_link = $post->guid;
	} else {
		$prcnvs_link = $permalink;
	}
	
	//Create the HTML for the Press+Canvas form elements

	?>
	<input type="checkbox" name="prcnvs_submit_url" id="prcnvs_submit_url" value="prcnvs_submit" 
	disabled />
	<?php
	echo '<label for="prcnvs_submit_url">'.__('Submit this post to Canvas?', 'press_canvas').'</label>';
	?>
	
	<div id="prcnvs_wait" style="margin: .5em 0; font-size: 80%; font-style: italic; color: gray; display:none">
	<img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting" alt="waiting" />
	 loading Canvas assignments -- this may take a while if you have a lot!
	</div>

	<div id="prcnvs_assign_list">
	</div>
	<?php
}


// Tell WP to use the WP Ajax for the action defined in the Javascript
// Using the PHP function in this page
add_action( 'wp_ajax_prcnvs_get_assignments', 'prcnvs_course_assignments' );

//Set up the Press+Canvas function to be called by Ajax
function prcnvs_course_assignments($post){
	global $prcnvs, $prcnvs_link, $cnvs_url, $cnvs_token, $cnvs_api_base;

	// Add an nonce field so we can check for it later.
	//wp_nonce_field( 'prcnvs_meta_box', 'prcnvs_meta_box_nonce' );

	//basic Canvas API parameters
	$cnvs_api_base = "https://".$cnvs_url."/api/v1";
	
	//Prepare to get the courses for registered token
	$cnvs_api_params = array(
		'headers' => array(
			'Authorization' => 'Bearer '.$cnvs_token
			),
		'body' => array(
		'enrollment_type'=> 'student',
		'per_page' => '100'
		)
	);

	/* TODO
	// Add similar self-check to setup to verify access token & domain
	// Move courses check to initiation of widget
	*/
	
	// set up the Canvas API Profile request using WP's HTTP API
	$cnvs_courses_request = wp_remote_request( $cnvs_api_base . '/courses', $cnvs_api_params);

	// echo $cnvs_base."/courses?access_token=".$cnvs_token."<br/>";

	// make sure we actually received a winning response from Canvas
	if( ! wp_remote_retrieve_response_code( $cnvs_courses_request ) == 200 ){
		echo '<p>Canvas connection error! Please check <a href="wp-admin/options-general.php?page=prscnvs_setup">your Press+Canvas Settings</a> or try again.</p>';
		$prscnvs_connection = 0; 
		return;
	} else { $prscnvs_connection = 1; }
		
	// json decode the request results
	$cnvs_courselist = json_decode( wp_remote_retrieve_body( $cnvs_courses_request ), true );
	
	// Prepare to get the assignments for each course
	// Change api parameters for assignments
	$cnvs_api_params = array( 
		'headers' => array(
			'Authorization' => 'Bearer '.$cnvs_token
			),
		'body' => array( 
		'per_page' => '100',
		'include[]' => 'submission'
		)
	);
	
	echo '<ul class="cnvs_courses">';
	
	// turn each assignment from the Canvas API response into an OPTION
	
	foreach ( $cnvs_courselist as $cnvs_crs ) {
		//assignment counter
		$i=0;
		
		$cnvs_assigns_list = NULL;
		$cnvs_assignments_list = NULL;
		echo '<li>';

		//get the list of assignments and decode
		$cnvs_assignments_request = wp_remote_request( $cnvs_api_base. '/courses/' . $cnvs_crs['id'] . '/assignments', $cnvs_api_params);
		$cnvs_assignments_list = json_decode( wp_remote_retrieve_body( $cnvs_assignments_request ),true);
		
		//check for assignments
		if( $cnvs_assignments_list !== NULL ){
			$cnvs_assigns_list .= '<ul class="cnvs_assignments">';
			
			//loop through each assignment and get stuff
			foreach ( $cnvs_assignments_list as $cnvs_asn ){
				/* TODO
				// Use HTML5's localstorage to save the courses and assignments as a single string
				*/
				
				// make sure the assignment's not locked for this user
				if ( $cnvs_asn['locked_for_user'] == false 
					&& in_array( "online_url", $cnvs_asn['submission_types'] )
					) {
					
					$i++;
					$cnvs_asn_name = $cnvs_asn['name'];
					//make a shorter name
					if ( strlen( $cnvs_asn['name'] ) > 20 ) {
						$cnvs_asn_name = substr( $cnvs_asn['name'], 0, 20 ) . '...';
					}
	
					// Output the element
					$cnvs_assigns_list .= '<li>';
					//var_dump($cnvs_asn);
					
					$cnvs_assigns_list .= '<input class="prcnvs_assign_radio" type="radio" name="prcnvs_assign" id="prcnvs_assign-'.$i.'" value="' . $cnvs_crs['id'] . '-' . $cnvs_asn['id'] . '" disabled>';
		
					$cnvs_assigns_list .= ' <label for="prcnvs_assign-' . $i . '"';
					
					/* TODO
					// Use CSS class instead of inline style 
					*/
					// Mark assignments that have already been submitted
					if ( $cnvs_asn['has_submitted_submissions'] == 1 ) {
						$cnvs_assigns_list .= ' style="text-decoration: line-through"';
					}
					
					$cnvs_assigns_list .= '><a href="' . $cnvs_asn['html_url'] . '" target="_blank">' . $cnvs_asn_name . '</a>';
					
					//create human-readable date for the assignment
					$cnvs_assigns_list .= '<span style="font-size: 75%">';
					$due_date = substr( $cnvs_asn['due_at'], 0, 10 );
					 if ( isset( $cnvs_asn['due_at'] )) { 
					 	$cnvs_assigns_list .= ' due ' . $due_date; 
					 	} else { $cnvs_assigns_list .= ' no due date'; }
					 $cnvs_assigns_list .= '</span>';
					//} // end if assignment not locked for this user
					
					$cnvs_assigns_list .= '</label></li>';
					
				} //end if assignment	 
			} //end assignment loop
		
		$cnvs_assigns_list .= '</ul></li>';
		}
		
		// Output a convenient hyperlink to the course
		echo '<a href="https://' . $cnvs_url . '/courses/' . $cnvs_crs['id'] .'" target="_blank" style="color:inherit; text-decoration: none;';
		
		/* TODO
		// Use CSS class instead of inline style 
		*/
		//indicate if a course has no relevant assignments
		if ( $i == 0 ){ 
			echo ' opacity: 0.66;';
		}
		echo '">';
		
		//Shorten course names if necessary
		if ( strlen( $cnvs_crs['name']) > 28 ){
			$cnvs_crs_name = substr( $cnvs_crs['name'], 0, 25 ) . '...';
		} else { $cnvs_crs_name = $cnvs_crs['name']; }
		echo $cnvs_crs_name . '</a>';
		
		/* TODO
		// ???
		*/
		echo $cnvs_assigns_list;
		
		/*
		if ( $i ==0 ) {
				echo '<em>No URL assignments</em>';
			}
		*/
	echo '</li>';
	} //end course loop
	echo '</ul>';
	die();
}

/*
// add the Press+Canvas box to the admin page for Posts
 */
 
function prcnvs_add_meta_box() {
	global $prcnvs, $cnvs_url, $cnvs_token;
	//get the Canvas URL set in Press+Canvas Settings
	$cnvs_url = get_option('cnvs_url');
	$cnvs_token = get_option('cnvs_token');
	add_meta_box('prcnvs_post_form', __('Press+Canvas '.$cnvs_url, 'press_canvas'), 
	'prcnvs_meta_box', 'post', 'side');
}
add_action('admin_init', 'prcnvs_add_meta_box');


/**
 * When the post is saved, submit to Canvas Assignment
 *
 * @param int $post_id The ID of the post being saved.
 */
function prcnvs_meta_box_data($post_id) {
	global $post, $prcnvs_link, $cnvs_api_base, $cnvs_url, $cnvs_token;
	/*
	 * We need to verify this came from our screen and with proper authorization,
	 * because the save_post action can be triggered at other times.
	 */

	/* TODO
	// Determine if nonce usage is advisable here
	*/
	/*
	// Check if our nonce is set.
	if ( ! isset( $_POST['prcnvs_meta_box_nonce'] ) ) {
		return;
	}


	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['prcnvs_meta_box_nonce'], 'prcnvs_meta_box' ) ) {
		return;
	}
	*/

	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	
	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

	} else {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}
	
	// OK, it's safe for us to save the data
	// Make sure that our Canvas assignment id is set
	if ( ! isset( $_POST['prcnvs_assign'] ) ) {
		return;
	}
	
	//var_dump($_POST);
	//echo $_POST['prcnvs_assign_list'];
	
	// Identify the url of this post
	if ( ! isset( $permalink ) ){
		//If the post hasn't been saved yet it doesn't have a permalink
		$prcnvs_link = $post->guid;
	} else {
		$prcnvs_link = $permalink;
	}
	
	//basic Canvas API parameters
	$cnvs_api_base = 'https://' . $cnvs_url . '/api/v1';
	
	//split course id from assign id
	$cnvs_ids = explode( "-", $_POST['prcnvs_assign'] );
	$cnvs_crs_id = $cnvs_ids[0];
	$cnvs_assn_id = $cnvs_ids[1];
	
	// set up for the Canvas Submission API
	$cnvs_api_submit = $cnvs_api_base . '/courses/' . $cnvs_crs_id . '/assignments/' 
	.$cnvs_assn_id . '/submissions';
	//echo $cnvs_api_submit; 
	//echo $prcnvs_link;
	
	//set API parameters
	$cnvs_submit_params = array(
		'headers' => array(
			'Authorization' => 'Bearer '.$cnvs_token
			),
		'body' => array(
			'submission[submission_type]' => 'online_url',
			'submission[url]' => $prcnvs_link
		)
	);
			
		// set up the Canvas API Courses request using WP's HTTP API
		$cnvs_submit_request = wp_remote_post( $cnvs_api_submit, $cnvs_submit_params);
		$cnvs_submit_response = json_decode(wp_remote_retrieve_body($cnvs_submit_request),true);
		
		/* TODO
		// Updated msg on Post
		*/
		
		add_filter( 'post_updated_messages', 'rw_post_updated_messages' );

		//var_dump($cnvs_submit_response);
		/* TODO
		// check status and post a message
		*/
		/*
		if(wp_remote_retrieve_response_code($cnvs_request)==200){
			_e('Post URL submitted to Canvas', 'prcanvas_meta_box' ); //actually not sure of the WP convention here for 2nd val 
		} else {
			_e('Post URL NOT submitted to Canvas! Check your Press+Canvas settings.', 'prcanvas_meta_box' ); //actually not sure of the WP convention here for 2nd val 
		}
		*/
}

add_action( 'save_post', 'prcnvs_meta_box_data' );


/*
//Press+Canvas Setup Menus & Options
 */
 
/* TODO
//Validate Canvas domain and token
*/

add_action( 'admin_menu', 'prcnvs_menu' );

function prcnvs_menu() {
	add_options_page( 'Press+Canvas Setup', 'Press+Canvas', 'manage_options', 'prscnvs_setup', 'prcnvs_options' );
}

function prcnvs_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	
	global $wpdb, $prcnvs, $wp_version;
	
	//get the WP-stored options needed to make Canvas API requests
	$cnvs_url = get_option('cnvs_url');
	$cnvs_token = get_option('cnvs_token');
	
	//if submitted val
	if ( isset( $_POST['cnvs_submit_hidden'] ) ) {
		// var_dump($_POST);
		// get just the host of the url
		if( strpos( $_POST['cnvs_url'], "http" ) !== false ){
			$cnvs_this_url = parse_url( $_POST['cnvs_url'] );
			$cnvs_this_url = $cnvs_this_url['host'];
		} else { $cnvs_this_url = $_POST['cnvs_url']; }
		
		// update both values in WP db
		update_option( 'cnvs_url', $cnvs_this_url );
		update_option( 'cnvs_token', ($_POST['cnvs_token']) );
		?>
	
		<div class="updated"><p><strong>
	
		<?php
		_e( 'Token saved for ', 'prscanvas_setup' ); 
		echo $cnvs_this_url;
		?>
		
		<p>That's it! You will now see a new Press+Canvas widget when editing a post that will let you choose a Canvas assignment.</p>

		</strong></p></div>
	
	<?php

	}
    // Now display the settings editing screen
    echo '<h2>' . __( 'Press+Canvas Setup', 'prcnvs_setup' ) . '</h2>';
    ?>
    
	<p>Press+Canvas is made for students. It lets you automatically submit the URL of any post directly to your school's <a href='http://canvaslms.com'>Canvas</a> LMS when you publish or update it.</p>
	
	<p><strong style='color:red'>Warning:</strong> Press+Canvas is very experimental. <strong>Do not</strong> depend on Press+Canvas to turn your assignments in on time untill you have tested it thoroughly.</p>
	
	<div style='float:right; font-size: 80%; max-width: 30%; margin: 0 1em 1em 1em'>
		<a href='<?php echo plugins_url( 'images/canvas_generate_token.png', __FILE__ );?>'><img src='<?php echo plugins_url( 'images/canvas_generate_token.png', __FILE__ );?>' style='max-width: 100%; display:block;' /></a>
		Within Canvas, <a target='_blank' href='https://guides.instructure.com/m/4214/l/40399-how-do-i-obtain-an-api-access-token'>generate an access token</a> via your profile Settings.</div>
	
	<p>You need just 2 things to set up Press+Canvas:</p>
	
	<ol>
		<li>The URL of your school's Canvas account</li>
		<li>An Access Token (<a target='_blank' class='prcnvs_exturl' href='https://guides.instructure.com/m/4214/l/40399-how-do-i-obtain-an-api-access-token'>how to generate one</a>).</p>
	</ol>
	
	<form name="prcnvs_setup_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI'] ); ?>">
	
	<input type="hidden" name="cnvs_submit_hidden" value="Y">

	<fieldset class='options' style='margin: 1.5em;'>
	
		<div>Canvas URL: <input type='text' name='cnvs_url' style='width:22em' value='https://<?php echo (get_option ('cnvs_url')); ?>'/></div>
	
		<div>Access Token: <input type='password' name='cnvs_token' style='width:26em' value='<?php echo (get_option ('cnvs_token')); ?>'/></div>
	
		</fieldset>
	
		<input type="submit" name="Submit" value="Update Options" />
	
	</form>
	
	<?php
} // End Press+Canvas Settings Menus & Options
?>