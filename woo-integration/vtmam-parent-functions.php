<?php
/*
VarkTech Min and Max Purchase for WooCommerce
WOO-specific functions
Parent Plugin Integration
*/

	
	function vtmam_load_vtmam_cart_for_processing(){
      global $wpdb,  $woocommerce, $vtmam_cart, $vtmam_cart_item, $vtmam_info; 
      
      // from Woocommerce/templates/cart/mini-cart.php  and  Woocommerce/templates/checkout/review-order.php
        
      if (sizeof($woocommerce->cart->get_cart())>0) {
      
					$woocommerce->cart->calculate_totals(); //1.07.6 calculation includes generating line subtotals, used below
          
					$vtmam_cart = new VTMAM_Cart;  
          foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {
						$_product = $cart_item['data'];
						if ($_product->exists() && $cart_item['quantity']>0) {
							$vtmam_cart_item                = new VTMAM_Cart_Item;
             
              //the product id does not change in woo if variation purchased.  
              //  Load expected variation id, if there, along with constructed product title.
              $varLabels = ' ';
              if ($cart_item['variation_id'] > ' ') {      
                 
                  // get parent title
                  $parent_post = get_post($cart_item['product_id']);
                  
                  // get variation names to string onto parent title
                  foreach($cart_item['variation'] as $key => $value) {          
                    $varLabels .= $value . '&nbsp;';           
                  }
                  
                  $vtmam_cart_item->product_id    = $cart_item['variation_id'];
                  $vtmam_cart_item->product_name  = $parent_post->post_title . '&nbsp;' . $varLabels ;

              } else { 
                  $vtmam_cart_item->product_id    = $cart_item['product_id'];
                  $vtmam_cart_item->product_name  = $_product->get_title().$woocommerce->cart->get_item_data( $cart_item );
              }
  
              
              $vtmam_cart_item->quantity      = $cart_item['quantity'];
              
              //v1.07.6 commented unit price
              //$vtmam_cart_item->unit_price    = get_option( 'woocommerce_display_cart_prices_excluding_tax' ) == 'yes' || $woocommerce->customer->is_vat_exempt() ? $_product->get_price_excluding_tax() : $_product->get_price();
              
              /*
              $quantity = 1; //v1.08 vat fix
              $vtmam_cart_item->unit_price    = get_option( 'woocommerce_display_cart_prices_excluding_tax' ) == 'yes' || $woocommerce->customer->is_vat_exempt() ? $_product->get_price_excluding_tax() : $_product->get_price_including_tax( $quantity ); //$_product->get_price();   //v1.08 vat fix
              */ 
              
              //v1.07.6 commented total_price             
              //$vtmam_cart_item->total_price   = $vtmam_cart_item->quantity * $vtmam_cart_item->unit_price;
              
              
              //v1.07.6 begin
              //  pick up unit price from line subtotal only - 
              //  will include all taxation and price adjustments from other plugins
              if ( ( get_option( 'woocommerce_calc_taxes' ) == 'no' ) ||
                   ( get_option( 'woocommerce_prices_include_tax' ) == 'no' ) ) {      
                 //NO VAT included in price
                 $vtmam_cart_item->unit_price  =  $cart_item['line_subtotal'] / $cart_item['quantity'];  
                 $vtmam_cart_item->total_price =  $cart_item['line_subtotal'];                                                
              } else {
                 
                 //v1.0.7.4 begin
                 //TAX included in price in DB, and Woo $cart_item pricing **has already subtracted out the TAX **, so restore the TAX
                 //  this price reflects the tax situation of the ORIGINAL price - so if the price was originally entered with tax, this will reflect tax
                 $price           =  $cart_item['line_subtotal']  / $cart_item['quantity'];    
                 $qty = 1;           
                 $_tax  = new WC_Tax();                
                // $product = get_product( $product_id ); 
                 $product = get_product( $vtmam_cart_item->product_id  );
                 $tax_rates  = $_tax->get_rates( $product->get_tax_class() );
        			 	 $taxes      = $_tax->calc_tax( $price  * $qty, $tax_rates, false );
        				 $tax_amount = $_tax->get_tax_total( $taxes );
        				 $vtmam_cart_item->unit_price  = round( $price  * $qty + $tax_amount, absint( get_option( 'woocommerce_price_num_decimals' ) ) ); 
                 $vtmam_cart_item->total_price = ($vtmam_cart_item->unit_price * $cart_item['quantity']);
               }              
              //v1.07.6 end
              
              
              /*  *********************************
              ***  JUST the cat *ids* please...
              ************************************ */
              $vtmam_cart_item->prod_cat_list = wp_get_object_terms( $cart_item['product_id'], $vtmam_info['parent_plugin_taxonomy'], $args = array('fields' => 'ids') );
              $vtmam_cart_item->rule_cat_list = wp_get_object_terms( $cart_item['product_id'], $vtmam_info['rulecat_taxonomy'], $args = array('fields' => 'ids') );
        
              //add cart_item to cart array
              $vtmam_cart->cart_items[]       = $vtmam_cart_item;

              $vtmam_cart->purchaser_ip_address = $vtmam_info['purchaser_ip_address']; // v1.07.4
              
				    }
        } //	endforeach;
        
              /*
       ($vtmam_info['get_purchaser_info'] == 'yes') is set in parent-cart-validation.php in 
       function vtmam_wpsc_checkout_form_validation only.  This is executed only at 'pay' button,
       the only time we can be sure that the purchaser info is there.
      */ 
       //     if( defined('VTMAM_PRO_DIRNAME') && ($vtmam_info['get_purchaser_info'] == 'yes') )  {
      if(defined('VTMAM_PRO_DIRNAME')) {
       require_once ( VTMAM_PRO_DIRNAME . '/woo-integration/vtmam-get-purchaser-info.php' );    
      }
        
        
		} //end  if (sizeof($woocommerce->cart->get_cart())>0) 
           
  }      

 
   //  checked_list (o) - selection list from previous iteration of rule selection                                 
    function vtmam_fill_variations_checklist ($tax_class, $checked_list = NULL, $product_ID, $product_variation_IDs) { 
        global $post;
        // *** ------------------------------------------------------------------------------------------------------- ***
        // additional code from:  woocommerce/admin/post-types/writepanels/writepanel-product-type-variable.php
        // *** ------------------------------------------------------------------------------------------------------- ***
        //    woo doesn't keep the variation title in post title of the variation ID post, additional logic constructs the title ...
        
        $parent_post = get_post($product_ID);
        
        $attributes = (array) maybe_unserialize( get_post_meta($product_ID, '_product_attributes', true) );
    
        $parent_post_terms = wp_get_post_terms( $post->ID, $attribute['name'] );
       
        // woo parent product title only carried on parent post
        echo '<h3>' .$parent_post->post_title.    ' - Variations</h3>'; 
        
        foreach ($product_variation_IDs as $product_variation_ID) {     //($product_variation_IDs as $product_variation_ID => $info)
            // $variation_post = get_post($product_variation_ID);
         
            $output  = '<li id='.$product_variation_ID.'>' ;
            $output  .= '<label class="selectit">' ;
            $output  .= '<input id="'.$product_variation_ID.'_'.$tax_class.' " ';
            $output  .= 'type="checkbox" name="tax-input-' .  $tax_class . '[]" ';
            $output  .= 'value="'.$product_variation_ID.'" ';
            if ($checked_list) {
                if (in_array($product_variation_ID, $checked_list)) {   //if variation is in previously checked_list   
                   $output  .= 'checked="checked"';
                }                
            }
            $output  .= '>'; //end input statement
 
            $variation_label = ''; //initialize label
            
            //get the variation names
            foreach ($attributes as $attribute) :

									// Only deal with attributes that are variations
									if ( !$attribute['is_variation'] ) continue;

									// Get current value for variation (if set)
									$variation_selected_value = get_post_meta( $product_variation_ID, 'attribute_' . sanitize_title($attribute['name']), true );

									// Get terms for attribute taxonomy or value if its a custom attribute
									if ($attribute['is_taxonomy']) :
										$post_terms = wp_get_post_terms( $product_ID, $attribute['name'] );
										foreach ($post_terms as $term) :
											if ($variation_selected_value == $term->slug) {
                          $variation_label .= $term->name . '&nbsp;&nbsp;' ;
                      }
										endforeach;
									else :
										$options = explode('|', $attribute['value']);
										foreach ($options as $option) :
											if ($variation_selected_value == $option) {
                        $variation_label .= ucfirst($option) . '&nbsp;&nbsp;' ;
                      }
										endforeach;
									endif;

						endforeach;
                
            $output  .= '&nbsp;&nbsp; #' .$product_variation_ID. '&nbsp;&nbsp; - &nbsp;&nbsp;' .$variation_label;
            $output  .= '</label>';            
            $output  .= '</li>'; 
            echo $output ;             
         }   
         
         //wooCommerce 2.0 alteration...
         if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
            // Pre 2.0
         } else {
            // 2.0
         }
         
               
        return;   
    }
    

  /* ************************************************
  **   Get all variations for product
  *************************************************** */
  function vtmam_get_variations_list($product_ID) {
        
    //sql from woocommerce/classes/class-wc-product.php
   $variations = get_posts( array(
			'post_parent' 	=> $product_ID,
			'posts_per_page'=> -1,
			'post_type' 	  => 'product_variation',
			'fields' 		    => 'ids',
			'post_status'	  => 'publish',
      'order'         => 'ASC'
	  ));
   if ($variations)  {    
      $product_variations_list = array();
      foreach ( $variations as $variation) {
        $product_variations_list [] = $variation;             
    	}
    } else  {
      $product_variations_list;
    }
    
    return ($product_variations_list);
  } 
  
  
  function vtmam_test_for_variations ($prod_ID) { 
      
     $vartest_response = 'no';
     
     /* Commented => DB access method uses more IO/CPU cycles than array processing below...
     //sql from woocommerce/classes/class-wc-product.php
     $variations = get_posts( array(
    			'post_parent' 	=> $prod_ID,
    			'posts_per_page'=> -1,
    			'post_type' 	=> 'product_variation',
    			'fields' 		=> 'ids',
    			'post_status'	=> 'publish'
    		));
     if ($variations)  {
        $vartest_response = 'yes';
     }  */
     
     // code from:  woocommerce/admin/post-types/writepanels/writepanel-product-type-variable.php
     $attributes = (array) maybe_unserialize( get_post_meta($prod_ID, '_product_attributes', true) );
     foreach ($attributes as $attribute) {
       if ($attribute['is_variation'])  {
          $vartest_response = 'yes';
          break;
       }
     }
     
     return ($vartest_response);   
  }     


  //v1.07 begin
    
   function vtmam_format_money_element($price) { 
      //from woocommerce/woocommerce-core-function.php   function woocommerce_price
    	$return          = '';
    	$num_decimals    = (int) get_option( 'woocommerce_price_num_decimals' );
    	$currency_pos    = get_option( 'woocommerce_currency_pos' );
    	$currency_symbol = get_woocommerce_currency_symbol();
    	$decimal_sep     = wp_specialchars_decode( stripslashes( get_option( 'woocommerce_price_decimal_sep' ) ), ENT_QUOTES );
    	$thousands_sep   = wp_specialchars_decode( stripslashes( get_option( 'woocommerce_price_thousand_sep' ) ), ENT_QUOTES );
    
    	$price           = apply_filters( 'raw_woocommerce_price', (double) $price );
    	$price           = number_format( $price, $num_decimals, $decimal_sep, $thousands_sep );
    
    	if ( get_option( 'woocommerce_price_trim_zeros' ) == 'yes' && $num_decimals > 0 )
    		$price = woocommerce_trim_zeros( $price );
    
    	//$return = '<span class="amount">' . sprintf( get_woocommerce_price_format(), $currency_symbol, $price ) . '</span>'; 

    $current_version =  WOOCOMMERCE_VERSION;
    if( (version_compare(strval('2'), strval($current_version), '>') == 1) ) {   //'==1' = 2nd value is lower     
      $formatted = number_format( $price, $num_decimals, stripslashes( get_option( 'woocommerce_price_decimal_sep' ) ), stripslashes( get_option( 'woocommerce_price_thousand_sep' ) ) );
      $formatted = $currency_symbol . $formatted;
    } else {
      $formatted = sprintf( get_woocommerce_price_format(), $currency_symbol, $price );
    }
          
     return $formatted;
   }
   
   //****************************
   // Gets Currency Symbol from PARENT plugin   - only used in backend UI during rules update
   //****************************   
  function vtmam_get_currency_symbol() {    
    return get_woocommerce_currency_symbol();  
  } 

  function vtmam_debug_options(){ 
    global $vtmam_setup_options;
    if ( ( isset( $vtmam_setup_options['debugging_mode_on'] )) &&
         ( $vtmam_setup_options['debugging_mode_on'] == 'yes' ) ) {  
      error_reporting(E_ALL);  
    }  else {
      error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);    //only allow FATAL error types 
    } 
  }
  //v1.07 end
