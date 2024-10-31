<?php
/*
Plugin Name: Nifty Newsletters - Woocommerce Subscriptions
Plugin URL: http://solaplugins.com
Description: Allow your customers to subscribe to your newsletter during checkout.
Version: 1.0.2
Author: SolaPlugins
Author URI: http://solaplugins.com
Contributors: NickDuncan, Jarryd Long, CodeCabin_
Text Domain: nifty-newsletters-woocommerce-subscriptions
Domain Path: /languages
WC requires at least: 2.4.0
WC tested up to: 3.8.1
*/


/*
* 1.0.2 - 2019-12-04
* Tested on WordPress 5.3
* Added required and supported WooCommerce version
* 
* 1.0.1 - 2016-01-07
* Tested on WordPres 4.4
* 
* 1.0.0 - 2015-10-21
* Launch!
* 
*/


if(!defined('SOLA_NL_W_PLUGIN_DIR')) {

    define('SOLA_NL_W_PLUGIN_DIR', dirname(__FILE__));

}

global $sola_nl_w_version;

$sola_nl_w_version = "1.0.2";

register_activation_hook( __FILE__, 'sola_nl_w_activation' );

add_action( 'init', 'sola_nl_w_init' );

add_action("wp_after_admin_bar_render","sola_nl_w_check_plugins_exist");

function sola_nl_w_init(){

    if( function_exists( 'sola_init' ) && class_exists( 'WooCommerce' ) ){

        /* Sola Newsletters & Woocommerce are active */
        
        add_action( 'admin_head', 'sola_nl_w_admin_head' );
        add_action( 'sola_nl_main_settings_after', 'sola_nl_w_settings_page' );

        $enable_subscription = get_option( 'sola_nl_w_enable_subscription' );

        if( $enable_subscription ){ 
            
            add_action( 'woocommerce_after_order_notes', 'sola_nl_w_subscription_checkbox_contents' );    
        
            add_action( 'woocommerce_checkout_update_order_meta', 'sola_nl_w_process_signup_data' );

        }
    } 

}

function sola_nl_w_check_plugins_exist(){

    if (is_admin()) {

        if (!is_plugin_active('sola-newsletters/sola-newsletters.php')) {
            
            echo "<div class='error below-h1'>";
            echo "<p>".sprintf( __( '<strong><a href="%1$s" title="Install Nifty Newsletters">Nifty Newsletters</strong></a> is required for the <strong>Nifty Newsletters - WooCommerce Subscriptions</strong> add-on to work. Please install and activate it.', 'nifty-newsletters-woocommerce-subscriptions' ),
                "plugin-install.php?tab=search&s=sola+newsletters"
                )."</p>";
            echo "</div>";
            
        }

        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            
            echo "<div class='error below-h1'>";
            echo "<p>".sprintf( __( '<strong><a href="%1$s" title="Install WooCommerce">WooCommerce</strong></a> is required for the <strong>Nifty Newsletters - WooCommerce Subscriptions</strong> add-on to work. Please install and activate it.', 'nifty-newsletters-woocommerce-subscriptions' ),
                "plugin-install.php?tab=search&s=wooommerce"
                )."</p>";
            echo "</div>";
            
        }
    }

}

function sola_nl_w_activation() {

    if ( !get_option("sola_nl_w_first_run" ) ) {
        
        update_option( 'sola_nl_w_enable_subscription', 1);
        update_option( 'sola_nl_w_default_list', 1);
        update_option( 'sola_nl_w_checkout_label', __( "Subscribe me to your newsletter", "nifty-newsletters-woocommerce-subscriptions" ) );

        update_option( 'sola_nl_w_first_run', TRUE );
    }

}

function sola_nl_w_subscription_checkbox_contents( $checkout ) {
 
    $sola_nl_w_label = get_option('sola_nl_w_checkout_label');

    woocommerce_form_field( 'sola_nl_w_signup', array(
        'type'          =>  'checkbox',
        'class'         =>  array('sola-nl-w-signup'),
        'label'         =>  $sola_nl_w_label,
        ), $checkout->get_value( 'sola_nl_w_signup' ));
 
}
 
function sola_nl_w_process_signup_data( $order_id ) {
    
    if( isset( $_POST['sola_nl_w_signup'] ) ){

        global $wpdb;

        global $sola_nl_subs_tbl;

        global $sola_nl_subs_list_tbl;

        if( isset ($_POST['billing_first_name'] ) ){
        
            $sub_name = sanitize_text_field( $_POST['billing_first_name'] );
        
        } else if ( isset( $_POST['shipping_first_name'] ) ){
        
            $sub_name = sanitize_text_field( $_POST['shipping_first_name'] );
        
        } else {
        
            $sub_name = "";
        
        }

        if( isset ($_POST['billing_last_name'] ) ){
        
            $sub_last_name = sanitize_text_field( $_POST['billing_last_name'] );
        
        } else if ( isset( $_POST['shipping_last_name'] ) ){
        
            $sub_last_name = sanitize_text_field( $_POST['shipping_last_name'] );
        
        } else {
        
            $sub_last_name = "";
        
        }

        $sub_email = sanitize_email( $_POST['billing_email'] );

        $sub_key = wp_hash_password( $sub_email );

        $default_list = get_option( 'sola_nl_w_default_list' );

        /* Add subscriber to subscriber's table */

        $wpdb->insert( $sola_nl_subs_tbl, 
            array(  
                'sub_name'      => $sub_name, 
                'sub_last_name' => $sub_last_name,
                'sub_email'     => $sub_email, 
                'sub_key'       => $sub_key, 
                'status'        => 1 
            ), 
            array( 
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
            )
        );

        /* Add subscriber to the default chosen list */

        $subscriber_id = $wpdb->insert_id;

        $default_list = get_option( 'sola_nl_w_default_list' );

        if( isset( $default_list ) ){

            $wpdb->insert( $sola_nl_subs_list_tbl, array( 'list_id' => $default_list, 'sub_id' => $subscriber_id ) );

       }
        
        update_post_meta( $order_id, 'sola_nl_w_signup', esc_attr( $_POST['sola_nl_w_signup'] ) );

    }

}

function sola_nl_w_settings_page(){

    $ret = "";
    $ret .= "<hr/>";
    $ret .= "<h1>" . __('WooCommerce Integration', 'nifty-newsletters-woocommerce-subscriptions') . "</h1>";

    $ret .= "<table width='100%'>";
    $ret .= "   <tbody>";
    $ret .= "   <tr>";
    $ret .= "       <td width='250px'>";
    $ret .= "           <label>" . __('Subscription Label', 'nifty-newsletters-woocommerce-subscriptions') . "</label>";
    $ret .= "           <p class='description'>" . __('This label will appear on the checkout page next to the checkbox.', 'nifty-newsletters-woocommerce-subscriptions') . "</p>";
    $ret .= "       </td>";
    $ret .= "       <td>";

    $subscription_enabled = get_option( 'sola_nl_w_checkout_label' );

    $ret .= "           <input class='sola-input' type='text' name='sola_nl_w_checkout_label' value='" . $subscription_enabled . "'/>";
    $ret .= "       </td>";
    $ret .= "   </tr>";

    $ret .= "   <tr>";
    $ret .= "       <td width='250px'>";
    $ret .= "           <label>" . __('Allow users to subscribe to your newsletter when checking out', 'nifty-newsletters-woocommerce-subscriptions') . "</label>";
    $ret .= "           <p class='description'>" . __('This will allow your customers to subscribe to your newsletter when checking out', 'nifty-newsletters-woocommerce-subscriptions') . "</p>";
    $ret .= "       </td>";
    $ret .= "       <td>";

    $subscription_enabled = get_option( 'sola_nl_w_enable_subscription' );

    if( $subscription_enabled == 1 ){ $checked = 'checked'; } else { $checked = ''; }

    $ret .= "           <input class='sola-input' type='checkbox' name='sola_nl_w_enable_subscription' value='1' " . $checked . "/>";
    $ret .= "       </td>";
    $ret .= "   </tr>";

    $ret .= "   <tr>";
    $ret .= "       <td width='250px'>";
    $ret .= "           <label>" . __('Default list your customers will be added to', 'nifty-newsletters-woocommerce-subscriptions') . "</label>";
    $ret .= "           <p class='description'>" . __('This is the list your users will be subscribed to when checking the check box on the checkout page', 'nifty-newsletters-woocommerce-subscriptions') . "</p>";
    $ret .= "       </td>";
    $ret .= "       <td>";

    if( function_exists( 'sola_nl_get_lists' ) ){

        $lists = sola_nl_get_lists();

        $current_chosen_list = get_option( 'sola_nl_w_default_list' );

        $ret .= "<select name='sola_nl_w_default_list'>";

        if( $lists ){

            foreach( $lists as $list ){

                if( $current_chosen_list == $list->list_id ) { $selected = 'selected'; } else { $selected = ''; }

                $ret .= "<option value='" . $list->list_id . "' " . $selected . ">" . $list->list_name . "</option>";                

            }

        }

        $ret .= "</select>";

    }

    $ret .= "       </td>";
    $ret .= "   </tr>";

    $ret .= "   </tbody>";
    $ret .= "</table>";

    echo $ret;

}

function sola_nl_w_admin_head(){

    if( isset ( $_POST['sola_nl_save_settings'] ) ){

        if( isset( $_POST['sola_nl_w_enable_subscription'] ) ){

            update_option( 'sola_nl_w_enable_subscription', 1);

        } else {

            update_option( 'sola_nl_w_enable_subscription', 0);

        }

        update_option( 'sola_nl_w_default_list', sanitize_text_field( $_POST['sola_nl_w_default_list'] ) );

        update_option ( 'sola_nl_w_checkout_label', sanitize_text_field( $_POST['sola_nl_w_checkout_label'] ) );

    }
    
}