<?php
/*
Plugin Name: VarkTech Min and Max Purchase for WooCommerce
Plugin URI: http://varktech.com
Description: An e-commerce add-on for WooCommerce, supplying minimum and maximum purchase functionality.
Version: 1.06
Author: Vark
Author URI: http://varktech.com
*/

/*
== Changelog ==

= 1.06 - 2013-02-23 =
* Bug Fix - "unexpected T_CLASS" - File admin/vtmin-rules-ui.php was corrupted, but the corruption only showed up on some hosts (?!).  Huge thanks to Don for allowing full access to his installation to debug.   

= 1.05 - 2013-02-13 =
* Bug Fix - Rule Add screen was being overwritten by some other plugins' global metaboxes - thanks to Dagofee for debug help
* Bug Fix - PHP version check not being executed correctly on activation hook (minimum PHP version 5 required)
* Bug Fix - Nuke and Repair buttons on Options screen were also affecting main Options settings, now fixed
 
= 1.0  - 2013-01-15 =
* Initial Public Release

*/

/*
** define Globals 
*/
   $vtmam_info;  //initialized in VTMAM_Parent_Definitions
   $vtmam_rules_set;
   $vtmam_rule;
   $vtmam_cart;
   $vtmam_cart_item;
   $vtmam_setup_options;


     
class VTMAM_Controller{
	
	public function __construct(){    
   
		define('VTMAM_VERSION',                               '1.06');
    define('VTMAM_LAST_UPDATE_DATE',                      '2013-02-23');
    define('VTMAM_DIRNAME',                               ( dirname( __FILE__ ) ));
    define('VTMAM_URL',                                   plugins_url( '', __FILE__ ) );
    define('VTMAM_EARLIEST_ALLOWED_WP_VERSION',           '3.3');   //To pick up wp_get_object_terms fix, which is required for vtmam-parent-functions.php
    define('VTMAM_EARLIEST_ALLOWED_PHP_VERSION',          '5');
    define('VTMAM_PLUGIN_SLUG',                           plugin_basename(__FILE__));
    define('VTMAM_PLUGIN_PATH',                            WP_PLUGIN_DIR . '/min-and-max-purchase-for-woocommerce/vt-minandmax-purchase.php/');
    
    require ( VTMAM_DIRNAME . '/woo-integration/vtmam-parent-definitions.php');
   
    /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    //  these control the rules ui, add/save/trash/modify/delete
    /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    add_action('init',          array( &$this, 'vtmam_controller_init' )); 
    add_action('admin_init',    array( &$this, 'vtmam_admin_init' ));
    add_action('save_post',     array( &$this, 'vtmam_admin_update_rule' ));
    add_action('delete_post',   array( &$this, 'vtmam_admin_delete_rule' ));    
    add_action('trash_post',    array( &$this, 'vtmam_admin_trash_rule' ));
    add_action('untrash_post',  array( &$this, 'vtmam_admin_untrash_rule' ));
    /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    
    //get rid of bulk actions on the edit list screen, which aren't compatible with this plugin's actions...
    add_action('bulk_actions-edit-vtmam-rule', array($this, 'vtmam_custom_bulk_actions') ); 

	}   //end constructor

  	                                                             
 /* ************************************************
 **   Overhead and Init
 *************************************************** */
	public function vtmam_controller_init(){
    global $vtmam_setup_options;
   
    load_plugin_textdomain( 'vtmam', null, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
                                                                               
    require ( VTMAM_DIRNAME . '/core/vtmam-backbone.php' );    
    require ( VTMAM_DIRNAME . '/core/vtmam-rules-classes.php');
    require ( VTMAM_DIRNAME . '/woo-integration/vtmam-parent-functions.php');
    require ( VTMAM_DIRNAME . '/woo-integration/vtmam-parent-cart-validation.php');
    
    if (is_admin()){
        require ( VTMAM_DIRNAME . '/admin/vtmam-setup-options.php');
        //fix 02-03-2013 - register_activation_hook now at bottom of file, after class instantiates
        
        if(defined('VTMAM_PRO_DIRNAME')) {
          require ( VTMAM_PRO_DIRNAME . '/admin/vtmam-rules-ui.php' );
          require ( VTMAM_PRO_DIRNAME . '/admin/vtmam-rules-update.php');
        } else {
          require ( VTMAM_DIRNAME .     '/admin/vtmam-rules-ui.php' );
          require ( VTMAM_DIRNAME .     '/admin/vtmam-rules-update.php');
        }
        
        require ( VTMAM_DIRNAME . '/admin/vtmam-checkbox-classes.php');
        require ( VTMAM_DIRNAME . '/admin/vtmam-rules-delete.php');
     
    } 
    
    //unconditional branch for these resources needed for WOOCommerce, at "place order" button time
    require ( VTMAM_DIRNAME . '/core/vtmam-cart-classes.php');
    
    if(defined('VTMAM_PRO_DIRNAME')) {
      require ( VTMAM_PRO_DIRNAME . '/core/vtmam-apply-rules.php' );
    } else {
      require ( VTMAM_DIRNAME .     '/core/vtmam-apply-rules.php' );
    }
    
    wp_enqueue_script('jquery'); 
    if (get_option( 'vtmam_setup_options' ) ) {
      $vtmam_setup_options = get_option( 'vtmam_setup_options' );  //put the setup_options into the global namespace
    }

  }
  
         
  /* ************************************************
  **   Admin - Remove bulk actions on edit list screen, actions don't work the same way as onesies...
  ***************************************************/ 
  function vtmam_custom_bulk_actions($actions){
    
    ?> 
    <style type="text/css"> #delete_all {display:none;} /*kill the 'empty trash' buttons, for the same reason*/ </style>
    <?php
    
    unset( $actions['edit'] );
    unset( $actions['trash'] );
    unset( $actions['untrash'] );
    unset( $actions['delete'] );
    return $actions;
  }
    
  /* ************************************************
  **   Admin - Show Rule UI Screen
  *************************************************** 
  *  This function is executed whenever the add/modify screen is presented
  *  WP also executes it ++right after the update function, prior to the screen being sent back to the user.   
  */  
	public function vtmam_admin_init(){
     if ( !current_user_can( 'edit_posts', 'vtmam-rule' ) )
          return;

     $vtmam_rules_ui = new VTMAM_Rules_UI; 
  }
  
 
  /* ************************************************
  **   Admin - Update Rule 
  *************************************************** */
	public function vtmam_admin_update_rule(){
    /* *****************************************************************
         The delete/trash/untrash actions *will sometimes fire save_post*
         and there is a case structure in the save_post function to handle this.
    
          the delete/trash actions are sometimes fired twice, 
               so this can be handled by checking 'did_action'
     ***************************************************************** */
      
      global $post, $vtmam_rules_set;
      if ( !( 'vtmam-rule' == $post->post_type )) {
        return;
      }  
      if (( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
            return; 
      }
     if (isset($_REQUEST['vtmam_nonce']) ) {     //nonce created in vtmam-rules-ui.php  
          $nonce = $_REQUEST['vtmam_nonce'];
          if(!wp_verify_nonce($nonce, 'vtmam-rule-nonce')) { 
            return;
          }
      } 
      if ( !current_user_can( 'edit_posts', 'vtmam-rule' ) ) {
          return;
      }

      
      /* ******************************************
       The 'SAVE_POST' action is fired at odd times during updating.
       When it's fired early, there's no post data available.
       So checking for a blank post id is an effective solution.
      *************************************************** */      
      if ( !( $post->ID > ' ' ) ) { //a blank post id means no data to proces....
        return;
      } 
      //AND if we're here via an action other than a true save, do the action and exit stage left
      $action_type = $_REQUEST['action'];
      if ( in_array($action_type, array('trash', 'untrash', 'delete') ) ) {
        switch( $action_type ) {
            case 'trash':
                $this->vtmam_admin_trash_rule();  
              break;
            case 'untrash':
                $this->vtmam_admin_untrash_rule();
              break;
            case 'delete':
                $this->vtmam_admin_delete_rule();  
              break;
        }
        return;
      }
                 
      $vtmam_rule_update = new VTMAM_Rule_update;
  }
   
  
 /* ************************************************
 **   Admin - Delete Rule
 *************************************************** */
	public function vtmam_admin_delete_rule(){
     global $post, $vtmam_rules_set; 
     if ( !( 'vtmam-rule' == $post->post_type ) ) {
      return;
     }        

     if ( !current_user_can( 'delete_posts', 'vtmam-rule' ) )  {
          return;
     }
    
    $vtmam_rule_delete = new VTMAM_Rule_delete;            
    $vtmam_rule_delete->vtmam_delete_rule();
        
    if(defined('VTMAM_PRO_DIRNAME')) {
      require ( VTMAM_PRO_DIRNAME . '/core/vtmam-delete-purchaser-info.php' );   
    }
  }
  
  
  /* ************************************************
  **   Admin - Trash Rule
  *************************************************** */   
	public function vtmam_admin_trash_rule(){
     global $post, $vtmam_rules_set; 
     if ( !( 'vtmam-rule' == $post->post_type ) ) {
      return;
     }        
  
     if ( !current_user_can( 'delete_posts', 'vtmam-rule' ) )  {
          return;
     }  
     
     if(did_action('trash_post')) {    
         return;
    }
    
    $vtmam_rule_delete = new VTMAM_Rule_delete;            
    $vtmam_rule_delete->vtmam_trash_rule();

  }
  
  
 /* ************************************************
 **   Admin - Untrash Rule
 *************************************************** */   
	public function vtmam_admin_untrash_rule(){
     global $post, $vtmam_rules_set; 
     if ( !( 'vtmam-rule' == $post->post_type ) ) {
      return;
     }        

     if ( !current_user_can( 'delete_posts', 'vtmam-rule' ) )  {
          return;
     }       
    $vtmam_rule_delete = new VTMAM_Rule_delete;            
    $vtmam_rule_delete->vtmam_untrash_rule();
  }


  /* ************************************************
  **   Admin - Activation Hook
  *************************************************** */  
   function vtmam_activation_hook() {
     //the options are added at admin_init time by the setup_options.php as soon as plugin is activated!!!
    //verify the requirements for Vtmin.
    global $wp_version;
		if((float)$wp_version < 3.3){
			// delete_option('vtmam_setup_options');
			 wp_die( __('<strong>Looks like you\'re running an older version of WordPress, you need to be running at least WordPress 3.3 to use the Varktech Minimum Purchase plugin.</strong>', 'vtmam'), __('VT Minimum Purchase not compatible - WP', 'vtmam'), array('back_link' => true));
			return;
		}
           
    //fix 02-13-2013 - changed php version_compare, altered error msg   
   if (version_compare(PHP_VERSION, VTMAM_EARLIEST_ALLOWED_PHP_VERSION) < 0) {    //'<0' = 1st value is lower 
			wp_die( __('<strong><em>PLUGIN CANNOT ACTIVATE &nbsp;&nbsp;-&nbsp;&nbsp;     Varktech Min and Max Purchase </em>
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;   Your installation is running on an older version of PHP 
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;   - your PHP version = ', 'vtmam') .PHP_VERSION. __(' . 
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;   You need to be running **at least PHP version 5** to use this plugin.  
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;   Please contact your host and request an upgrade to PHP 5+ . 
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;   Then activate this plugin following the upgrade.</strong>', 'vtmam'), __('VT Min and Max Purchase not compatible - PHP', 'vtmam'), array('back_link' => true));
			return; 
		}
    
        
    if(defined('WPSC_VERSION') && (VTMAM_PARENT_PLUGIN_NAME == 'WP E-Commerce') ) { 
      $new_version =      VTMAM_EARLIEST_ALLOWED_PARENT_VERSION;
      $current_version =  WPSC_VERSION;
      if( (version_compare(strval($new_version), strval($current_version), '>') == 1) ) {   //'==1' = 2nd value is lower 
  			// delete_option('vtmam_setup_options');
  			 wp_die( __('<strong>Looks like you\'re running an older version of WP E-Commerce. <br>You need to be running at least ** WP E-Commerce 3.8 **, to use the Varktech Min and Max Purchase plugin.</strong>', 'vtmam'), __('VT Min and Max Purchase not compatible - WPEC', 'vtmam'), array('back_link' => true));
  			return;
  		}
    }  else 
    if (VTMAM_PARENT_PLUGIN_NAME == 'WP E-Commerce') {
        wp_die( __('<strong>Varktech Min and Max Purchase for WP E-Commerce requires that WP E-Commerce be installed and activated.</strong>', 'vtmam'), __('WP E-Commerce not installed or activated', 'vtmam'), array('back_link' => true));
  			return;
    }

    if(defined('WOOCOMMERCE_VERSION') && (VTMAM_PARENT_PLUGIN_NAME == 'WooCommerce')) { 
      $new_version =      VTMAM_EARLIEST_ALLOWED_PARENT_VERSION;
      $current_version =  WOOCOMMERCE_VERSION;
      if( (version_compare(strval($new_version), strval($current_version), '>') == 1) ) {   //'==1' = 2nd value is lower 
  			// delete_option('vtmam_setup_options');
  			 wp_die( __('<strong>Looks like you\'re running an older version of WooCommerce. <br>You need to be running at least ** WooCommerce 1.0 **, to use the Varktech Minimum Purchase plugin.</strong>', 'vtmam'), __('VT Minimum Purchase not compatible - WooCommerce', 'vtmam'), array('back_link' => true));
  			return;
  		}
    }   else 
    if (VTMAM_PARENT_PLUGIN_NAME == 'WooCommerce') {
        wp_die( __('<strong>Varktech Minimum Purchase for WooCommerce requires that WooCommerce be installed and activated.</strong>', 'vtmam'), __('WooCommerce not installed or activated', 'vtmam'), array('back_link' => true));
  			return;
    }
    

    if(defined('JIGOSHOP_VERSION') && (VTMAM_PARENT_PLUGIN_NAME == 'JigoShop')) { 
      $new_version =      VTMAM_EARLIEST_ALLOWED_PARENT_VERSION;
      $current_version =  JIGOSHOP_VERSION;
      if( (version_compare(strval($new_version), strval($current_version), '>') == 1) ) {   //'==1' = 2nd value is lower
  			// delete_option('vtmam_setup_options');
  			 wp_die( __('<strong>Looks like you\'re running an older version of JigoShop. <br>You need to be running at least ** JigoShop 1.0 **, to use the Varktech Minimum Purchase plugin.</strong>', 'vtmam'), __('VT Minimum Purchase not compatible - JigoShop', 'vtmam'), array('back_link' => true));
  			return;
  		}
    }  else 
    if (VTMAM_PARENT_PLUGIN_NAME == 'JigoShop') {
        wp_die( __('<strong>Varktech Minimum Purchase for JigoShop requires that JigoShop be installed and activated.</strong>', 'vtmam'), __('JigoShop not installed or activated', 'vtmam'), array('back_link' => true));
  			return;
    }
     
  }
  
  /* ************************************************
  **   Admin - **Uninstall** Hook and cleanup
  *************************************************** */ 
  function vtmam_uninstall_hook() {
      
      if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
      	return;
        //exit ();
      }
  
      delete_option('vtmam_setup_options');
      $vtmam_nuke = new VTMAM_Rule_delete;            
      $vtmam_nuke->vtmam_nuke_all_rules();
      $vtmam_nuke->vtmam_nuke_all_rule_cats();
      
  }


} //end class
$vtmam_controller = new VTMAM_Controller; 

  //***************************************************************************************
  //fix 02-13-2013  -  problems with activation hook and class, solved herewith....
  //   FROM http://website-in-a-weekend.net/tag/register_activation_hook/
  //***************************************************************************************
  if (is_admin()){ 
        register_activation_hook(__FILE__, array($vtmam_controller, 'vtmam_activation_hook'));
        register_activation_hook(__FILE__, array($vtmam_controller, 'vtmam_uninstall_hook'));                                   
  }
