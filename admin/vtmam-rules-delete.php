<?php
class VTMAM_Rule_delete {
	
	public function __construct(){
     
    }
    
  public  function vtmam_delete_rule () {
    global $post, $vtmam_info, $vtmam_rules_set, $vtmam_rule;
    $post_id = $post->ID;    
    $vtmam_rules_set = get_option( 'vtmam_rules_set' ) ;
    for($i=0; $i < sizeof($vtmam_rules_set); $i++) { 
       if ($vtmam_rules_set[$i]->post_id == $post_id) {
          unset ($vtmam_rules_set[$i]);   //this is the 'delete'
          $i =  sizeof($vtmam_rules_set); 
       }
    }
   
    if (count($vtmam_rules_set) == 0) {
      delete_option( 'vtmam_rules_set' );
    } else {
      update_option( 'vtmam_rules_set', $vtmam_rules_set );
    }
 }  
 
  /* Change rule status to 'pending'
        if status is 'pending', the rule will not be executed during cart processing 
  */ 
  public  function vtmam_trash_rule () {
    global $post, $vtmam_info, $vtmam_rules_set, $vtmam_rule;
    $post_id = $post->ID;    
    $vtmam_rules_set = get_option( 'vtmam_rules_set' ) ;
    for($i=0; $i < sizeof($vtmam_rules_set); $i++) { 
       if ($vtmam_rules_set[$i]->post_id == $post_id) {
          if ( $vtmam_rules_set[$i]->rule_status =  'publish' ) {    //only update if necessary, may already be pending
            $vtmam_rules_set[$i]->rule_status =  'pending';
            update_option( 'vtmam_rules_set', $vtmam_rules_set ); 
          }
          $i =  sizeof($vtmam_rules_set); //set to done
       }
    }
 }  

  /*  Change rule status to 'publish' 
        if status is 'pending', the rule will not be executed during cart processing  
  */
  public  function vtmam_untrash_rule () {
    global $post, $vtmam_info, $vtmam_rules_set, $vtmam_rule;
    $post_id = $post->ID;     
    $vtmam_rules_set = get_option( 'vtmam_rules_set' ) ;
    for($i=0; $i < sizeof($vtmam_rules_set); $i++) { 
       if ($vtmam_rules_set[$i]->post_id == $post_id) {
          if  ( sizeof($vtmam_rules_set[$i]->rule_error_message) > 0 ) {   //if there are error message, the status remains at pending
            //$vtmam_rules_set[$i]->rule_status =  'pending';   status already pending
            global $wpdb;
            $wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $post_id ) );    //match the post status to pending, as errors exist.
          }  else {
            $vtmam_rules_set[$i]->rule_status =  'publish';
            update_option( 'vtmam_rules_set', $vtmam_rules_set );  
          }
          $i =  sizeof($vtmam_rules_set);   //set to done
       }
    }
 }  
 
     
  public  function vtmam_nuke_all_rules() {
    global $post, $vtmam_info;
    
   //DELETE all posts from CPT
   $myPosts = get_posts( array( 'post_type' => 'vtmam-rule', 'number' => 500, 'post_status' => array ('draft', 'publish', 'pending', 'future', 'private', 'trash' ) ) );
   //$mycustomposts = get_pages( array( 'post_type' => 'vtmam-rule', 'number' => 500) );
   foreach( $myPosts as $mypost ) {
     // Delete's each post.
     wp_delete_post( $mypost->ID, true);
    // Set to False if you want to send them to Trash.
   }
    
   //DELETE matching option array
   delete_option( 'vtmam_rules_set' );
 }  
     
  public  function vtmam_nuke_all_rule_cats() {
    global $vtmam_info;
    
   //DELETE all rule category entries
   $terms = get_terms($vtmam_info['rulecat_taxonomy'], 'hide_empty=0&parent=0' );
   $count = count($terms);
   if ( $count > 0 ){  
       foreach ( $terms as $term ) {
          wp_delete_term( $term->term_id, $vtmam_info['rulecat_taxonomy'] );
       }
   } 
 }  
      
  public  function vtmam_repair_all_rules() {
    global $wpdb, $post, $vtmam_info, $vtmam_rules_set, $vtmam_rule;    
    $vtmam_rules_set = get_option( 'vtmam_rules_set' ) ;
    for($i=0; $i < sizeof($vtmam_rules_set); $i++) { 
       $test_post = get_post($vtmam_rules_set[$i]->post_id );
       if ( !$test_post ) {
           unset ($vtmam_rules_set[$i]);   //this is the 'delete'
       }
    } 
    
    if (count($vtmam_rules_set) == 0) {
      delete_option( 'vtmam_rules_set' );
    } else {
      update_option( 'vtmam_rules_set', $vtmam_rules_set );
    }
 }
       
  public  function vtmam_nuke_max_purchase_history() {
    global $wpdb;    
    $purchaser_table = $wpdb->prefix.'vtmam_rule_purchaser';
    $product_table = $wpdb->prefix.'vtmam_rule_product';
    $wpdb->query("DROP TABLE IF EXISTS $purchaser_table");
    $wpdb->query("DROP TABLE IF EXISTS $product_table");

  }

} //end class
