<?php
/*
VarkTech Minimum Purchase for WooCommerce
Woo-specific functions
Parent Plugin Integration
*/


class VTMAM_Parent_Definitions {
	
	public function __construct(){
    
    define('VTMAM_PARENT_PLUGIN_NAME',                      'WooCommerce');
    define('VTMAM_EARLIEST_ALLOWED_PARENT_VERSION',         '1.0');
    define('VTMAM_TESTED_UP_TO_PARENT_VERSION',             '1.6.6');
    define('VTMAM_DOCUMENTATION_PATH_PRO_BY_PARENT',        'http://www.varktech.com/woocommerce/minimum-purchase-pro-for-woocommerce/?active_tab=tutorial');                                                                                                     //***
    define('VTMAM_DOCUMENTATION_PATH_FREE_BY_PARENT',       'http://www.varktech.com/woocommerce/minimum-purchase-for-woocommerce/?active_tab=tutorial');      
    define('VTMAM_INSTALLATION_INSTRUCTIONS_BY_PARENT',     'http://www.varktech.com/woocommerce/minimum-purchase-for-woocommerce/?active_tab=instructions');
    define('VTMAM_PRO_INSTALLATION_INSTRUCTIONS_BY_PARENT', 'http://www.varktech.com/woocommerce/minimum-purchase-pro-for-woocommerce/?active_tab=instructions');
    define('VTMAM_PURCHASE_PRO_VERSION_BY_PARENT',          'http://www.varktech.com/woocommerce/minimum-purchase-pro-for-woocommerce/');
    define('VTMAM_DOWNLOAD_FREE_VERSION_BY_PARENT',         'http://wordpress.org/extend/plugins/minimum-purchase-for-woocommerce/');
    
    //html default selector locations in checkout where error message will display before.
    define('VTMAM_CHECKOUT_PRODUCTS_SELECTOR_BY_PARENT',    '.shop_table');        // PRODUCTS TABLE on BOTH cart page and checkout page
    define('VTMAM_CHECKOUT_ADDRESS_SELECTOR_BY_PARENT',     '#customer_details');      //  address area on checkout page    default = on


    global $vtmam_info;      
    $vtmam_info = array(                                                                    
      	'parent_plugin' => 'woo',
      	'parent_plugin_taxonomy' => 'product_cat',
        'parent_plugin_taxonomy_name' => 'Product Categories',
        'parent_plugin_cpt' => 'product',
        'applies_to_post_types' => 'product', //rule cat only needs to be registered to product, not rule as well...
        'rulecat_taxonomy' => 'vtmam_rule_category',
        'rulecat_taxonomy_name' => 'Min and Max Purchase Rules',
        
        //elements used in vtmam-apply-rules.php at the ruleset level
        'error_message_needed' => 'no',
        'cart_grp_info' => '',
          /*  cart_grp_info will contain the following:
            array(
              'qty'    => '',
              'price'    => ''
            )
          */
        'cart_color_cnt' => '',
        'rule_id_list' => '',
        'line_cnt' => 0,
        'action_cnt'  => 0,
        'bold_the_error_amt_on_detail_line'  => 'no',
        'currPageURL'  => '',
        'woo_cart_url'  => '',
        'woo_checkout_url'  => '',
        'woo_pay_url'  => '',
        
        //elements used at the ruleset/product level 
        'purch_hist_product_row_id'  => '',              
        'purch_hist_product_price_total'  => '',      
        'purch_hist_product_qty_total'  => '',          
        'get_purchaser_info' => '',          
        'purch_hist_done' => ''  
      );

	}

} //end class
$vtmam_parent_definitions = new VTMAM_Parent_Definitions;