<?php
/*
VarkTech Maximum Purchase for WooCommerce
Woo-specific functions
Parent Plugin Integration
*/


class VTMAM_Parent_Cart_Validation {
	
	public function __construct(){
     global $vtmam_info, $woocommerce; //$woocommerce_checkout = $woocommerce->checkout();
     /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++   
     *        Apply Maximum Amount Rules to ecommerce activity
     *                                                          
     *          WOO-Specific Checkout Logic and triggers 
     *                                               
     *  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++   */
                                
    //  add actions for early entry into Woo's 3 shopping cart-related pages, and the "place order" button -    
    
    $vtmam_info['woo_cart_url']      =  $this->vtmam_woo_get_url('cart'); 
    $vtmam_info['woo_checkout_url']  =  $this->vtmam_woo_get_url('checkout');
    $vtmam_info['woo_pay_url']       =  $this->vtmam_woo_get_url('pay');   
    $vtmam_info['currPageURL']       =  $this->vtmam_currPageURL();
      
    if ( in_array($vtmam_info['currPageURL'], array($vtmam_info['woo_cart_url'],$vtmam_info['woo_checkout_url'], $vtmam_info['woo_pay_url'] ) ) )  {      
       add_action( 'init', array(&$this, 'vtmam_woo_apply_checkout_cntl'),99 );                                                            
    }  
     /*   Priority of 99 in the action above, to delay add_action execution. The
          priority delays us in the exec sequence until after any quantity change has
          occurred, so we pick up the correct altered state. */

    //if "place order" button hit, this action catches and errors as appropriate
    add_action( 'woocommerce_before_checkout_process', array(&$this, 'vtmam_woo_place_order_cntl') );   
    
    //save info to Lifetime tables following purchase       
    add_action('woocommerce_checkout_order_processed', array( &$this, 'vtmam_post_purchase_save_info' ));
    /*  =============+ */      
                                                                                
	}

 
  // from woocommerce/classes/class-wc-cart.php 
  public function vtmam_woo_get_url ($pageName) {            
     global $woocommerce;
      $checkout_page_id = $this->vtmam_woo_get_page_id($pageName);
  		if ( $checkout_page_id ) {
  			if ( is_ssl() )
  				return str_replace( 'http:', 'https:', get_permalink($checkout_page_id) );
  			else
  				return apply_filters( 'woocommerce_get_checkout_url', get_permalink($checkout_page_id) );
  		}
  }
      
  // from woocommerce/woocommerce-core-functions.php 
  public function vtmam_woo_get_page_id ($pageName) { 
    $page = apply_filters('woocommerce_get_' . $pageName . '_page_id', get_option('woocommerce_' . $pageName . '_page_id'));
		return ( $page ) ? $page : -1;
  }    
 /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++    */
    
    
           
  /* ************************************************
  **   Application - Apply Rules at E-Commerce Checkout
  *************************************************** */
	public function vtmam_woo_apply_checkout_cntl(){
    global $vtmam_cart, $vtmam_cart_item, $vtmam_rules_set, $vtmam_rule, $vtmam_info, $woocommerce;
        
    //input and output to the apply_rules routine in the global variables.
    //    results are put into $vtmam_cart
    
    if ( $vtmam_cart->error_messages_processed == 'yes' ) {  
      $woocommerce->add_error(  __('Purchase error found.', 'vtmam') );  //supplies an error msg and prevents payment from completing 
      return;
    }
    
     $vtmam_apply_rules = new VTMAM_Apply_Rules;   
    
    //ERROR Message Path
    if ( sizeof($vtmam_cart->error_messages) > 0 ) {      
      //insert error messages into checkout page
      add_action( "wp_enqueue_scripts", array($this, 'vtmam_enqueue_error_msg_css') );
      add_action('wp_head', array(&$this, 'vtmam_display_rule_error_msg_at_checkout') );  //JS to insert error msgs   
      $vtmam_cart->error_messages_processed = 'yes';
      $woocommerce->add_error(  __('Purchase error found.', 'vtmam') );  //supplies an error msg and prevents payment from completing  
    }     
  }
      
           
  /* ************************************************
  **   Application - Apply Rules at Woo E-Commerce  ==> AT Place Order Time <==
  *************************************************** */
	public function vtmam_woo_place_order_cntl(){
    global $vtmam_cart, $vtmam_cart_item, $vtmam_rules_set, $vtmam_rule, $vtmam_info, $woocommerce;
        
    //input and output to the apply_rules routine in the global variables.
    //    results are put into $vtmam_cart
    
    if ( $vtmam_cart->error_messages_processed == 'yes' ) {  
      $woocommerce->add_error(  __('Purchase error found.', 'vtmam') );  //supplies an error msg and prevents payment from completing 
      return;
    }
    
     $vtmam_apply_rules = new VTMAM_Apply_Rules;   
    
  /*  *********************************************************************************************************
      These two add_actiions cannot be used to display error msgs in this situation, as they are not executed when
      errors are found at "place order" time in woo land.  They depend on a screen refresh, and woo doesn't do one...
         // add_action( "wp_enqueue_scripts", array($this, 'vtmam_enqueue_error_msg_css') );
         // add_action('wp_head', array(&$this, 'vtmam_display_rule_error_msg_at_checkout') );  //JS to insert error msgs 
      *********************************************************************************************************  
    */
    
    //ERROR Message Path
    if ( sizeof($vtmam_cart->error_messages) > 0 ) {  
        
      //insert error messages into checkout page
      //this echo may result in multiple versions of the css file being called for, can't be helped.
      echo '<link rel="stylesheet" type="text/css" media="all" href="'.VTMAM_URL.'/core/css/vtmam-error-style.css" />' ;     //mwnt
            
      /* WOO crazy error display, in this situation only:
          {"result":"failure","messages":"
            \n\t\t\t
            Purchase error found.<\/li>\n\t<\/ul>","refresh":"false"}     
      */
      //  These are the incorrectly displayed contens of the 'add_error' function below, and are only a problem in this particular situation
      echo '<div class="woo-apply-checkout-cntl">';  // This 'echo' allows the incorrectly displayed error msg to fall within the 'woo-apply-checkout-cntl' div, and be deleted by following JS
      $woo_apply_checkout_cntl = 'yes';
      
      //display VTMAM error msgs   
      $this->vtmam_display_rule_error_msg_at_checkout();      
      
      $vtmam_cart->error_messages_processed = 'yes';
      
      //tell WOO that an error has occurred, and not to proceed further
      $woocommerce->add_error(  __('Purchase error found.', 'vtmam') );  //supplies an error msg and prevents payment from completing      

    }  
   // else { $woocommerce->add_error(  __('FAKE Purchase error found.', 'vtmam') ); } //Test lifetime history max rule logic
    
  }  

  
  /* ************************************************
  **   Application - On Error Display Message on E-Commerce Checkout Screen  
  *************************************************** */ 
  public function vtmam_display_rule_error_msg_at_checkout($woo_apply_checkout_cntl = null){
    global $vtmam_info, $vtmam_cart, $vtmam_setup_options;
    //error messages are inserted just above the checkout products, and above the checkout form
      //In this situation, this 'id or class Selector' may not be blank, supply woo checkout default - must include '.' or '#'
    if ( $vtmam_setup_options['show_error_before_checkout_products_selector']  <= ' ' ) {
       $vtmam_setup_options['show_error_before_checkout_products_selector'] = VTMAM_CHECKOUT_PRODUCTS_SELECTOR_BY_PARENT;             
    }
      //In this situation, this 'id or class Selector' may not be blank, supply woo checkout default - must include '.' or '#'
    if ( $vtmam_setup_options['show_error_before_checkout_address_selector']  <= ' ' ) {
       $vtmam_setup_options['show_error_before_checkout_address_selector'] = VTMAM_CHECKOUT_ADDRESS_SELECTOR_BY_PARENT;             
    }
    
      /*   **  WOO changes **
        remove previous onscreen error msgs: 
                <php if ($woo_apply_checkout_cntl == 'yes')  { >
                $('</div>').insertBefore('<php echo $vtmam_setup_options['show_error_before_checkout_address_selector'] >');  //ends the 'woo-apply-checkout-cntl' div, allows for incorrect error msg display to fall within it and be deleted by the next statement
                <php } >
                $('.woo-apply-checkout-cntl').remove();  //removes the stray error msg displays at checkout place order time
                $('.vtmam-error').remove();  //removes old error msgs at checkout place order time
           previous error msgs normally removed at screen refresh, but at "place order" time,
           messages are returned via Ajax, and error messages will stack up.
      */    
     ?>     
        <script type="text/javascript">
        
        jQuery(document).ready(function($) {
          <?php if ($woo_apply_checkout_cntl == 'yes')  { ?>
          $('</div>').insertBefore('<?php echo $vtmam_setup_options['show_error_before_checkout_address_selector'] ?>');  //ends the 'woo-apply-checkout-cntl' div, allows for incorrect error msg display to fall within it and be deleted by the next statement
          <?php } ?>
          $('.woo-apply-checkout-cntl').remove();  //removes the stray error msg displays at checkout place order time (if included in the 'if', doesn't work for some reason)
          $('.vtmam-error').remove();  //removes old error msgs at checkout place order time
    <?php 
    //loop through all of the error messages 
    //          $vtmam_info['line_cnt'] is used when table formattted msgs come through.  Otherwise produces an inactive css id. 
    for($i=0; $i < sizeof($vtmam_cart->error_messages); $i++) { 
     ?>
        <?php 
          //default selector for products area (".shop_table") is used on BOTH cart page and checkout page. Only use on cart page
          if ( ( $vtmam_setup_options['show_error_before_checkout_products'] == 'yes' ) &&  ($vtmam_info['currPageURL'] == $vtmam_info['woo_cart_url']) ){ 
        ?>
           $('<div class="vtmam-error" id="line-cnt<?php echo $vtmam_info['line_cnt'] ?>"><h3 class="error-title">Purchase Error</h3><p> <?php echo $vtmam_cart->error_messages[$i]['msg_text'] ?> </p></div>').insertBefore('<?php echo $vtmam_setup_options['show_error_before_checkout_products_selector'] ?>');
        <?php 
          } 
          //Only message which shows up on actual checkout page.
          if ( $vtmam_setup_options['show_error_before_checkout_address'] == 'yes' ){ 
        ?>
           $('<div class="vtmam-error" id="line-cnt<?php echo $vtmam_info['line_cnt'] ?>"><h3 class="error-title">Purchase Error</h3><p> <?php echo $vtmam_cart->error_messages[$i]['msg_text'] ?> </p></div>').insertBefore('<?php echo $vtmam_setup_options['show_error_before_checkout_address_selector'] ?>');
    <?php 
          }
    }  //end 'for' loop      
     ?>   
            });   
          </script>
     <?php    


     /* ***********************************
        CUSTOM ERROR MSG CSS AT CHECKOUT
        *********************************** */
     if ($vtmam_setup_options[custom_error_msg_css_at_checkout] > ' ' )  {
        echo '<style type="text/css">';
        echo $vtmam_setup_options[custom_error_msg_css_at_checkout];
        echo '</style>';
     }
     
     /*
      Turn off the messages processed switch.  As this function is only executed out
      of wp_head, the switch is only cleared when the next screenful is sent.
     */
     $vtmam_cart->error_messages_processed = 'no';   
 } 
 
   
  /* ************************************************
  **   Application - get current page url
  *************************************************** */ 
 public  function vtmam_currPageURL() {
     $pageURL = 'http';
     if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
        $pageURL .= "://";
     if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
     } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
     }
     return $pageURL;
  } 
 
    

  /* ************************************************
  **   Application - On Error enqueue error style
  *************************************************** */
  public function vtmam_enqueue_error_msg_css() {
    wp_register_style( 'vtmam-error-style', VTMAM_URL.'/core/css/vtmam-error-style.css' );  
    wp_enqueue_style('vtmam-error-style');
  } 
 
 
  
  /* ************************************************
  **   After purchase, store max purchase info for lifetime rules on db
  *************************************************** */ 
  function vtmam_post_purchase_save_info () {
   
    if(defined('VTMAM_PRO_DIRNAME')) {
      require ( VTMAM_PRO_DIRNAME . '/woo-integration/vtmam-save-purchase-info.php');
    } 
   
  } // end  function vtmam_store_max_purchaser_info() 
 
} //end class
$vtmam_parent_cart_validation = new VTMAM_Parent_Cart_Validation;