<?php
/*
 * Plugin Name: Woocommerce Gift Wrapper (MODIFIED)
 * Plugin URI: http://www.little-package.com/woocommerce-gift-wrapper
 * Description: This plugin shows gift wrap options on the WooCommerce cart and/or checkout page, and adds gift wrapping to the order
 * Tags: woocommerce, e-commerce, ecommerce, gift, holidays, present
 * Version: 1.3.1
 * Author: Caroline Paquette (modified by Carlos)
 * Text Domain: wc-gift-wrapper
 * Domain path: /lang
 * Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=PB2CFX8H4V49L
 * 
 * Woocommerce Gift Wrapper
 * Copyright: (c) 2015-2016 Caroline Paquette (email: cap@little-package.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Remember this plugin is free. If you have problems with it, please be
 * nice and contact me for help before leaving negative feedback!
 *
 * Woocommerce Gift Wrapper is forked from Woocommerce Gift Wrap by Gema75
 * Copyright: (c) 2014 Gema75 - http://codecanyon.net/user/Gema75
 * 
 * Original changes from Woocommerce Gift Wrapper include: OOP to avoid plugin clashes; removal of the option to
 * hide categories (to avoid unintentional, detrimental bulk database changes; use of the Woo API for the
 * settings page; complete restyling of the front-end view including a modal view to unclutter the cart view
 * and CSS tagging to allow easier customization; option for easy front end language adjustments and/or l18n;
 * addition of order notes regarding wrapping to order emails and order pages for admins; further options; support 
 * for Woo > 2.2 menu sections, security fixes, accessibility improvements.
 * 
 * I need your support & encouragement! If you have found this plugin useful,
 * and if you have benefitted commercially from it,
 * please consider donating to support the plugin's future on the web:
 * 
 * paypal.me/littlepackage
 * 
 * I understand you have a budget and might not be able to afford to buy the
 * developer (me) a beer or a pizza in thanks. Maybe you can leave a positive review?
 * 
 * https://wordpress.org/support/plugin/woocommerce-gift-wrapper/reviews
 *
 * Thank you!
 * 
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_Gift_Wrapping' ) ) :

class WC_Gift_Wrapping {

	public function __construct() {

		if ( ! defined( 'GIFT_PLUGIN_TEXT_DOMAIN' ) ) {
			define( 'GIFT_PLUGIN_TEXT_DOMAIN', 'wc-gift-wrapper' );
		}
		if ( ! defined( 'GIFT_PLUGIN_BASE_FILE' ) ) {
			define( 'GIFT_PLUGIN_BASE_FILE', plugin_basename(__FILE__) );
		}
		if ( ! defined( 'GIFT_PLUGIN_VERSION' ) ) {
			define( 'GIFT_PLUGIN_VERSION', '1.3.1' );
		}
		if ( ! defined( 'GIFT_PLUGIN_URL' ) ) {
			define( 'GIFT_PLUGIN_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
		}

		add_action( 'init',       											array( $this, 'lang' ) );
		add_action( 'wp_enqueue_scripts',       							array( $this, 'enqueue_scripts' ) );
		add_action( 'plugins_loaded',       								array( $this, 'hooks' ) );


		add_action( 'woocommerce_checkout_update_order_meta',   			array( $this, 'update_order_meta' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_meta'), 10, 1 );

		add_filter( 'woocommerce_get_sections_products',      	 			array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_products',       			array( $this, 'settings' ), 10, 2);
		//add_filter( 'woocommerce_cart_item_name',							array( $this, 'add_user_note_into_cart' ), 1, 3 );
		add_filter( 'woocommerce_email_order_meta_keys',					array( $this, 'order_meta_keys') );
		add_filter( 'plugin_action_links_' . GIFT_PLUGIN_BASE_FILE, 		array( $this, 'add_settings_link' ) );



		//$giftwrap_number = 'no';
		//$giftwrap_display = array('after_coupon');
		//$avada_theme = false;

		add_action( 'wp_ajax_add_gift', array( $this,'add_giftwrap_to_order_ajax') );
		add_action( 'wp_ajax_nopriv_add_gift', array( $this,'add_giftwrap_to_order_ajax') );

	}

	/**
	 * l10n
	 **/
	public function lang() {

		load_plugin_textdomain( GIFT_PLUGIN_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

	}

	/**
	 * Hooks
	 **/
	public function hooks() {
		add_action( 'woocommerce_checkout_before_order_review', array( &$this, 'add_gift_wrap_to_cart_page' ) );
	}

	/**
	 * Enqueue scripts
	 **/
	public function enqueue_scripts() {

		$giftwrap_modal = get_option( 'giftwrap_modal' );
		if ( $giftwrap_modal == 'yes' ) {

			// Avada already enqueues Bootstrap. Let's not do it twice.
			$current_theme = wp_get_theme();
			$current_theme_name = $current_theme->Name;
			if ( $current_theme_name != 'Avada' ){
				wp_enqueue_script( 'wcgiftwrap-js', GIFT_PLUGIN_URL .'/assets/js/wcgiftwrapper.js', 'jquery', null, true );	
			}
			//	wp_enqueue_style( 'wcgiftwrap-css', GIFT_PLUGIN_URL .'/assets/css/wcgiftwrap_modal.css' );
		} else {
			wp_enqueue_style( 'wcgiftwrap-css', GIFT_PLUGIN_URL .'/assets/css/wcgiftwrap.css' );
		}

	}

	/**
	* Add settings link to WP plugin listing
	*/
	public function add_settings_link( $links ) {

		$settings = sprintf( '<a href="%s">%s</a>' , admin_url( 'admin.php?page=wc-settings&tab=products&section=wcgiftwrapper' ) , __( 'Settings', GIFT_PLUGIN_TEXT_DOMAIN ) , __( 'Settings', GIFT_PLUGIN_TEXT_DOMAIN ) );
		array_unshift( $links, $settings );
		return $links;

	}

	/**
	 * Add settings SECTION under Woocommerce->Products
	 * @param array $sections
	 * @return array
	 **/
	public function add_section( $sections ) {

		$sections['wcgiftwrapper'] = __( 'Gift Wrapping', GIFT_PLUGIN_TEXT_DOMAIN );
		return $sections;

	}

	/**
	* Add settings to the section we created with add_section()
	* @param array Settings
	* @param string Current Section
	* @return array
	*/
	public function settings( $settings, $current_section ) {

		if ( $current_section == 'wcgiftwrapper' ) {

			$settings_slider = array();

			$settings_slider[] = array( 
				'id' 				=> 'wcgiftwrapper',
				'name' 				=> __( 'Opciones regalo', GIFT_PLUGIN_TEXT_DOMAIN ), 
				'type' 				=> 'title', 
				'desc' 				=> sprintf(__( '<strong>1.</strong> Start by <a href="%s" target="_blank">adding at least one product</a> called "Gift Wrapping" or something similar.<br /><strong>2.</strong> Create a unique product category for this/these gift wrapping product(s), and add them to this category.<br /><strong>3.</strong> Then consider the options below.', GIFT_PLUGIN_TEXT_DOMAIN ), wp_nonce_url(admin_url('post-new.php?post_type=product'),'add-product')),
			);



			$settings_slider[] = array(
				'id'       			=> 'giftwrap_header',
				'name'     			=> __( 'Cabecera regalo', GIFT_PLUGIN_TEXT_DOMAIN ),
				'desc_tip' 			=> __( 'The text you would like to use to introduce your gift wrap offering.', GIFT_PLUGIN_TEXT_DOMAIN ),
				'type'     			=> 'text',
				'default'         	=> __( 'Add gift wrapping?', GIFT_PLUGIN_TEXT_DOMAIN ),
				'css'      			=> 'min-width:300px;',
			);



			$settings_slider[] = array(
				'id'       			=> 'giftwrap_details',
				'name'     			=> __( 'Detalles regalo', GIFT_PLUGIN_TEXT_DOMAIN ),
				'desc_tip' 			=> __( 'The text to give any details or conditions of your gift wrap offering.', GIFT_PLUGIN_TEXT_DOMAIN ),
				'type'     			=> 'textarea',
				'default'         	=> '',
				'css'      			=> 'min-width:300px;',
			);
			

			$settings_slider[] = array(
				'id' => 'wcgiftwrapper',
				'type' => 'sectionend',
			);

			return $settings_slider;

		} else {
			return $settings;
		}

	}

	/**
	 * Add gift wrapping to cart
	 * Forked from Woocommerce Gift Wrap
	 * (c) Copyright 2014, Gema75 - http://codecanyon.net/user/Gema75
	 * @return void
	 **/




	public function add_giftwrap_to_order_ajax() {

		global $woocommerce;

	
		
		if ( isset( $_POST['wc_gift_wrap_notes'] ) ) {
			$woocommerce->session->set( 'gift_wrap_notes', $_POST['wc_gift_wrap_notes'] );
		}	
		if(( isset( $_POST['wc_gift_wrap_regalo'] ) )){
			$woocommerce->session->set( 'gift_wrap_regalo', $_POST['wc_gift_wrap_regalo'] );
		}else{
			$woocommerce->session->set('gift_wrap_regalo',false);
		}

		if ( isset( $_POST['wc_gift_wrap_option'] ) ) {
			$woocommerce->session->set( 'gift_wrap_option', $_POST['wc_gift_wrap_option'] );
		}



		die('1');

		//wp_die();

	}

	/**
	* Discover gift wrap products in cart
	* @return bool
	*/
	public static function is_gift_wrap_in_cart() {

		global $woocommerce;

		if ( count( $woocommerce->cart->get_cart() ) > 0 ) {

			foreach ( $woocommerce->cart->get_cart() as $key => $value ) {
				$product = $value['data'];
				$terms = get_the_terms( $product->id , 'product_cat' );

				if ( $terms ) {

					$giftwrap_category = get_option( 'giftwrap_category_id', true );	

					foreach ( $terms as $term ) {
						if ( $term->term_id == $giftwrap_category ) {
							$giftwrap_in_cart = TRUE;
						} else {
							$giftwrap_in_cart = FALSE;
						}
					}
				} 
			}
		} else {
			$giftwrap_in_cart = FALSE;				
		}
		return $giftwrap_in_cart;

	}

	/**
	 * Update the order meta with field value 
     * Forked from Woocommerce Gift Wrap
	 * (c) Copyright 2014, Gema75 - http://codecanyon.net/user/Gema75
	 * @param int Order ID
	 * @return void
	 **/
	public function update_order_meta( $order_id ) {


		global $woocommerce;

	



		if(( isset( $woocommerce->session->gift_wrap_regalo ) &&  $woocommerce->session->gift_wrap_regalo  )) {

			if ( $woocommerce->session->gift_wrap_regalo !='' ) {
				update_post_meta( $order_id, '_gift_wrap_regalo', sanitize_text_field( $woocommerce->session->gift_wrap_regalo ) );
			}	
			
			
			
			if ( isset( $woocommerce->session->gift_wrap_notes ) ) {
				if ( $woocommerce->session->gift_wrap_notes !='' ) {
					update_post_meta( $order_id, '_gift_wrap_notes', sanitize_text_field( $woocommerce->session->gift_wrap_notes ) );
				}	
			}
			

			if ( isset( $woocommerce->session->gift_wrap_option ) ) {
				if ( $woocommerce->session->gift_wrap_option !='' ) {
					update_post_meta( $order_id, '_gift_wrap_option', sanitize_text_field( $woocommerce->session->gift_wrap_option ) );
				}	
			}
			
			
		}



	}

	/**
 	* Display field value on the order edit page
 	* @param array Order
	* @return void
 	*/
	public function display_admin_order_meta( $order ) {

		if ( get_post_meta( $order->id, '_gift_wrap_notes', true ) !== '' ) {
			echo '<p><strong>'.__( 'Gift Wrap Note', GIFT_PLUGIN_TEXT_DOMAIN ).':</strong> ' . get_post_meta( $order->id, '_gift_wrap_notes', true ) . '</p>';
		}

		if(( get_post_meta( $order->id, '_gift_wrap_regalo', true ) !== '' )) {
			if( get_post_meta( $order->id, '_gift_wrap_regalo', true )) $envolver = 'Si';
			else $envolver = 'No';
			echo '<p><strong>'.__( '¿Envolver para regalo?', GIFT_PLUGIN_TEXT_DOMAIN ).':</strong> ' . $envolver . '</p>';

			//mostrar si es true, 		
			if ( get_post_meta( $order->id, '_gift_wrap_option', true ) !== '' ) {
				echo '<p><strong>'.__( 'Tipo envoltura', GIFT_PLUGIN_TEXT_DOMAIN ).':</strong> ' . get_post_meta( $order->id, '_gift_wrap_option', true ) . '</p>';
			}
		}



	}

	/**
	* Add the field to order emails
 	* @param array Keys
	* @return array
 	**/ 
	public function order_meta_keys( $keys ) {

		$keys[__( 'Gift Wrap Note', GIFT_PLUGIN_TEXT_DOMAIN )] = '_gift_wrap_notes';
		$keys[__( 'Gift Wrap Regalo?', GIFT_PLUGIN_TEXT_DOMAIN )] = '_gift_wrap_regalo';
		$keys[__( 'Gift Wrap TIPO REGALO', GIFT_PLUGIN_TEXT_DOMAIN )] = '_gift_wrap_option';
		return $keys;

	}

	/**
	 * Add gift wrap to order
	 * Forked from Woocommerce Gift Wrap
	 * (c) Copyright 2014, Gema75 - http://codecanyon.net/user/Gema75
	 * @return void
	 **/
	public function add_gift_wrap_to_cart_page() {

		global $woocommerce;


		$giftwrap_header 			= get_option( 'giftwrap_header' );
		$giftwrap_details 			= get_option( 'giftwrap_details' );

		$giftwrap_category_id 		= get_option( 'giftwrap_category_id', true );
		$giftwrap_textarea_limit	= get_option( 'giftwrap_textarea_limit' );

		$giftwrap_text_label = get_option( 'giftwrap_text_label' );

?>

<!--<div id="wc-giftwrap" class="wc-giftwrap">-->

<?php if ( is_checkout() == true ) { ?>

<!--<div class="woocommerce-info"><a href="#" class="show_giftwrap"><?php echo esc_attr($giftwrap_header); ?></a></div>-->
<h3 id="order_review_heading"><?php echo esc_attr( $giftwrap_header ); ?></h3>
<div style="clear:both"> </div>

<div  class="regalo_checkout">
	<?php if ( $giftwrap_details != '' ) { ?><p class="giftwrap_details"><?php echo esc_attr( $giftwrap_details ); ?></p><?php } ?>

	<div class="wc_giftwrap_notes_container" style="display:block">

		<input type="checkbox" name="wc_gift_wrap_regalo" id="wc_gift_wrap_regalo" value="true" onClick="if(jQuery(this).prop('checked')){ jQuery('.opciones_regalo').show(); }else{ jQuery('.opciones_regalo').hide();}"> Envolver para regalo
		<ul class="opciones_regalo" style="display:none">
			<li> <input type="radio" name="wc_gift_wrap_option" id="wc_gift_wrap_option_todo_uno" checked value="Todo en uno"><label> <?php echo __( 'Envolver todo en un regalo', GIFT_PLUGIN_TEXT_DOMAIN ) ?></label></li>
			<li> <input type="radio" name="wc_gift_wrap_option" id="wc_gift_wrap_option_separado" value="Por separado"> <label><?php echo __( 'Envolver por separado', GIFT_PLUGIN_TEXT_DOMAIN ) ?></label></li>
			<li> <input type="radio" name="wc_gift_wrap_option" id="wc_gift_wrap_option_papel" value="Enviar papel"> <label><?php echo __( 'Enviar papel para envolver aparte', GIFT_PLUGIN_TEXT_DOMAIN ) ?></label></li>
		</ul>
		<br />
		<div style="display:none">			
			<label for="wc_gift_wrap_notes"><?php echo esc_attr( $giftwrap_text_label );?></label>
			<textarea name="wc_gift_wrap_notes" id="wc_gift_wrap_notes" cols="30" rows="4" maxlength="<?php echo $giftwrap_textarea_limit; ?>" class="wc_giftwrap_notes"><?php if ( isset( $woocommerce->session->gift_wrap_notes ) ) { echo stripslashes( $woocommerce->session->gift_wrap_notes ); } ?></textarea>
		</div>
	</div>
	<!-- <button type="submit" class="button btn alt giftwrap_submit fusion-button fusion-button-default" name="giftwrap_btn" aria-label="<?php echo esc_attr( $giftwrap_button ); ?>"><?php echo esc_attr( $giftwrap_button ); ?></button> -->




</div>
<script type="text/javascript">

	//jQuery('.giftwrap_submit').click( function() {
	jQuery('#wc_gift_wrap_regalo, input[name="wc_gift_wrap_option"]').change( function() {

		var wc_gift_wrap_regalo = jQuery('#wc_gift_wrap_regalo').prop('checked');
		if(!wc_gift_wrap_regalo){
			jQuery.post( "/wp-admin/admin-ajax.php?action=add_gift", { }).done(function( data ) {
				//alert( "Data Loaded: " + data );
				if(data!=1){
					alert('Ha habido un error: no se ha podido añadir la opción de regalo');
				}
			});
			return;
		}
		
		var wc_gift_wrap_option = jQuery('input[name="wc_gift_wrap_option"]:checked').val();
		jQuery.post( "/wp-admin/admin-ajax.php?action=add_gift", { wc_gift_wrap_notes: "", wc_gift_wrap_regalo: wc_gift_wrap_regalo, wc_gift_wrap_option:wc_gift_wrap_option }).done(function( data ) {
			//alert( "Data Loaded: " + data );
			if(data!=1){
				alert('Ha habido un error: no se ha podido añadir la opción de regalo');
			}
		});

		return false;
	});

</script>

<?php }





	} // End add_gift_wrap_to_cart_page()

}  // End class WC_Gift_Wrapping

endif; // End if ( class_exists() )

new WC_Gift_Wrapping();
// That's a wrap!
?>