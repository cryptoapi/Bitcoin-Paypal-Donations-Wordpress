<?php
/*
Plugin Name: 		GoUrl Bitcoin Paypal Donations - Give Addon
Plugin URI: 		https://gourl.io/bitcoin-donations-wordpress-plugin.html
Description: 		Bitcoin/Altcoin & Paypal Donations in Wordpress. Provides a Bitcoin/Altcoin Payment Gateway for <a href='https://github.com/cryptoapi/Give-Wordpress-Donations-Bitcoin'>Give 2.0.6.x</a> - easy to use wordpress donation plugin for accepting bitcoins, altcoins, paypal, authorize.net, stripe, paymill donations directly onto your website.
Version: 			1.1.5
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/Bitcoin-Paypal-Donations-Wordpress
*/

if (!defined( 'ABSPATH' )) exit;  // Exit if accessed directly

if (!function_exists('gourl_give_gateway_load'))
{
	// gateway load
	add_action( 'plugins_loaded', 'gourl_give_gateway_load', 20);
	// localisation load
	add_action( 'plugins_loaded', 'gourl_give_load_textdomain' );
	
	DEFINE('GOURLGV', "gourl-give");



	function gourl_give_load_textdomain()
	{
		load_plugin_textdomain( GOURLGV, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
	
	
	
	function gourl_give_gateway_load()
	{
	
	class gourl_give
	{
		private $payments 			= array();
		private $languages 			= array();
		private $coin_names			= array('BTC' => 'bitcoin', 'BCH' => 'bitcoincash', 'LTC' => 'litecoin', 'DASH' => 'dash', 'DOGE' => 'dogecoin', 'SPD' => 'speedcoin', 'RDD' => 'reddcoin', 'POT' => 'potcoin', 'FTC' => 'feathercoin', 'VTC' => 'vertcoin', 'PPC' => 'peercoin', 'MUE' => 'monetaryunit');
		private $mainplugin_url		= '';
		private $url				= '';
		private $url2				= '';
		private $url3				= '';
		private $cointxt			= '';
		
		private $active				= '';
		private $title				= '';
		private $description		= '';
		private $logo				= '';
		private $emultiplier		= '';
		private $deflang			= '';
		private $defcoin			= '';
		private $iconwidth			= '';
			
		
		
		/*
		 *  1
		*/
		public function __construct()
		{
			
			// Register Gateway
			add_filter( 'give_payment_gateways', array(&$this, 'register_gateway'), 1 );
			add_filter( 'give_settings_gateways', array(&$this, 'settings'), 1 );
			
			add_action( 'give_gourl_cc_form', '__return_false' );
			add_filter( 'give_require_billing_address', '__return_false' );
			add_action( 'give_gateway_gourl', array(&$this, 'process_payment') );
			add_filter( 'give_enabled_payment_gateways', array(&$this, 'give_enabled_payment_gateways') );
			add_filter( 'give_default_gateway', array(&$this, 'give_default_gateway') );
			add_filter( 'give_currencies', array(&$this, 'give_currencies') );
			add_action( 'template_redirect', array(&$this, 'template_redirect'), 1 );

			
			if (is_admin() && isset($_GET["page"]) && $_GET["page"] == "give-settings" && isset($_GET["tab"]) && $_GET["tab"] == "gateways" && strpos($_SERVER["SCRIPT_NAME"], "edit.php"))
			{
				add_action( 'admin_footer_text', array(&$this, 'admin_footer_text'), 25);
			}
				
			// Except view receipt
			if (!(isset($_GET["give_action"]) && $_GET["give_action"] == "view_receipt" && isset($_GET["payment_key"]) && $_GET["payment_key"]))
				add_action( 'give_payment_receipt_before_table', array(&$this, 'cryptocoin_payment'), 1 );
			
			// Plugin Links
			add_filter('plugin_action_links', array(&$this, 'plugin_action_links'), 10, 2 );

			// Settings
			$this->get_settings();

			return true;
		}

			
			
		/*
		 * 2
		*/
		public function plugin_action_links($links, $file)
		{
			static $this_plugin;
		
			if (!class_exists('Give')) return $links;
		
			if (false === isset($this_plugin) || true === empty($this_plugin)) {
				$this_plugin = plugin_basename(__FILE__);
			}
		
			if ($file == $this_plugin) {
				$settings_link = '<a href="'.admin_url('edit.php?post_type=give_forms&page=give-settings&tab=gateways').'">'.__( 'Settings', GOURLGV ).'</a>';
				array_unshift($links, $settings_link);
		
				if (defined('GOURL'))
				{
					$unrecognised_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=unrecognised').'">'.__( 'Unrecognised', GOURLGV ).'</a>';
					array_unshift($links, $unrecognised_link);
					$payments_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=gourlgive').'">'.__( 'Donations', GOURLGV ).'</a>';
					array_unshift($links, $payments_link);
				}
			}
		
			return $links;
		}
		
		
		
		/*
		 * 3
		*/
		public function register_gateway( $gateways = array() ) 
		{
			global $give_options;
			
			$gateways['gourl'] = array
			(
					'admin_label'    => __( 'Gourl Bitcoin/Altcoin', GOURLGV ),
					'checkout_label' => $this->title
			);
					
			return $gateways;
		}
		
		
		
		/*
		 * 4
		*/
		public function give_enabled_payment_gateways ($gateway_list = array())
		{
			if (!$gateway_list || $this->active) $gateway_list = array_merge($gateway_list, $this->register_gateway());
			
			return $gateway_list;
		}
		
		
		
		/*
		 * 5
		*/
		public function give_default_gateway ($default)
		{
			global $give_options;
			
			if (!isset($give_options['gateways']) || count($give_options['gateways']) == 1 || $give_options["default_gateway"] == "gourl") $default = "gourl";
			
			return $default;
		}
		
		
		
		/*
		 * 6
		*/
		public function give_currencies ($currencies)
		{
			global $gourl; 
			
			if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
			{
				$arr = $gourl->coin_names(); 
			
				foreach ($arr as $k => $v)
					$currencies[$k] = __( "Cryptocurrency", GOURLGV ) . " - " . __( ucfirst($v), GOURLGV ) . " (" . $k . ")";
			}
			
			__( 'Bitcoin', GOURLGV );  // use in translation
			
			return $currencies;
		}

		
		
		/*
		 * 7
		*/
		private function get_settings()
		{
			global $gourl, $give_options;
		
			$this->mainplugin_url 		= admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads");
			$this->method_title       	= __( 'GoUrl Bitcoin/Altcoins', GOURLGV );
			$this->method_description  	= "<a target='_blank' href='https://gourl.io/'><img border='0' style='float:left; margin-right:15px' src='".plugin_dir_url( __FILE__ )."images/gourlpayments.png'></a>";
			$this->method_description  .= "<a target='_blank' href='https://gourl.io/bitcoin-donations-wordpress-plugin.html'>".__( 'Plugin Homepage', GOURLGV )."</a> &#160;&amp;&#160; <a target='_blank' href='https://gourl.io/bitcoin-donations-wordpress-plugin.html#screenshot'>".__( 'screenshots', GOURLGV )." &#187;</a><br>";
			$this->method_description  .= "<a target='_blank' href='https://github.com/cryptoapi/Bitcoin-Paypal-Donations-Wordpress'>".__( 'Plugin on Github - 100% Free Open Source', GOURLGV )." &#187;</a><br><br>";
				
			
			$give_ver = "";
			if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
			{
				if (true === version_compare(GOURL_VERSION, '1.4.14', '<'))
				{
					$this->method_description .= '<div class="error"><p><b>' .sprintf(__( "Your GoUrl Bitcoin Gateway <a href='%s'>Main Plugin</a> version is too old. Requires 1.4.14 or higher version. Please <a href='%s'>update</a> to latest version.", GOURLGV ), GOURL_ADMIN.GOURL, $this->mainplugin_url)."</b> &#160; &#160; &#160; &#160; " .
							__( 'Information', GOURLGV ) . ": &#160; <a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', GOURLGV )."</a> &#160; &#160; &#160; " .
							"<a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>".__( 'WordPress.org Plugin Page', GOURLGV )."</a></p></div>";
				}
				elseif (true === version_compare(GIVE_VERSION, '2.0.7', '>') || true === version_compare(GIVE_VERSION, '2.0.6', '<') || !(defined('GIVECORE_GOURL')))
				{
				    $give_ver = '<p><b>' .sprintf(__( "This Give version not support Bitcoin Gateway! You can DELETE current Give plugin and download free Give plugin with Bitcoin Support ver2.0.6.x from <a href='%s'>Github here</a> &#187;", GOURLGV ), "https://github.com/cryptoapi/Give-Wordpress-Donations-Bitcoin").'</b></p>';
				    $this->method_description .= '<div class="error">'.$give_ver.'</div>';
				}
				else
				{
					$this->payments 			= $gourl->payments(); 		// Activated Payments
					$this->coin_names			= $gourl->coin_names(); 	// All Coins
					$this->languages			= $gourl->languages(); 		// All Languages
				}
			
				$this->url		= GOURL_ADMIN.GOURL."settings";
				$this->url2		= GOURL_ADMIN.GOURL."payments&s=gourlgive";
				$this->url3		= GOURL_ADMIN.GOURL;
				$this->cointxt 	= (implode(", ", $this->payments)) ? implode(", ", $this->payments) : __( '- Please setup -', GOURLGV );
			}
			else
			{
				$this->method_description .= '<div class="error"><p><b>' .
						sprintf(__( "You need to install GoUrl Bitcoin Gateway Main Plugin also. Go to - <a href='%s'>Automatic installation</a> or <a href='%s'>Manual</a>.", GOURLGV ), $this->mainplugin_url, "https://gourl.io/bitcoin-wordpress-plugin.html") . "</b> &#160; &#160; &#160; &#160; " .
						__( 'Information', GOURLGV ) . ": &#160; &#160;<a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', GOURLGV )."</a> &#160; &#160; &#160; <a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>" .
						__( 'WordPress.org Plugin Page', GOURLGV ) . "</a></p></div>";
				
				$this->url		= $this->mainplugin_url;
				$this->url2		= $this->url;
				$this->url3		= $this->url;
				$this->cointxt 	= '<b>'.__( 'Please install GoUrl Bitcoin Gateway WP Plugin', GOURLGV ).' &#187;</b>';
				
			}
			
			$this->method_description  .= "<b>" . __( "Secure donations with virtual currency. <a target='_blank' href='https://bitcoin.org/'>What is Bitcoin?</a>", GOURLGV ) . '</b><br>';
			$this->method_description  .= sprintf(__( 'Accept %s donations online in Give.', GOURLGV ), __( ucwords(implode(", ", $this->coin_names)), GOURLGV )).'<br>';
			$this->method_description .= sprintf(__( "If you use multiple websites online, please create separate <a target='_blank' href='%s'>GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites.", GOURLGV ), "https://gourl.io/editrecord/coin_boxes/0");
			if ($give_ver) $this->method_description .= "<div style='color:red'>".$give_ver."</div>";
				

			// Re-check
			$this->active        	= $this->exst($give_options["gourl_active"]);
			$this->title        	= $this->exst($give_options["gourl_title"]);
			$this->description  	= $this->exst($give_options["gourl_description"]);
			$this->logo      		= $this->exst($give_options["gourl_logo"]);
			$this->emultiplier  	= str_replace("%", "", $this->exst($give_options["gourl_emultiplier"]));
			$this->deflang  		= $this->exst($give_options["gourl_deflang"]);
			$this->defcoin  		= $this->exst($give_options["gourl_defcoin"]);
			$this->iconwidth  		= str_replace("px", "", $this->exst($give_options["gourl_iconwidth"]));
			 
			if (!$this->title)								$this->title 		= __('Bitcoin/Altcoins', GOURLGV);
			if (!$this->description)						$this->description 	= __('Secure, anonymous donation with virtual currency', GOURLGV);
			if (!isset($this->languages[$this->deflang])) 	$this->deflang 		= 'en';
			 
			if (!in_array($this->logo, $this->coin_names) && $this->logo != 'global') 					$this->logo = 'bitcoin';
			if (!$this->emultiplier || !is_numeric($this->emultiplier) || $this->emultiplier < 0.01) 	$this->emultiplier = 1;
			if (!is_numeric($this->iconwidth) || $this->iconwidth < 30 || $this->iconwidth > 250) 		$this->iconwidth = 60;
			
			if ($this->defcoin && $this->payments && !isset($this->payments[$this->defcoin])) $this->defcoin = key($this->payments);
			elseif (!$this->payments)						$this->defcoin		= '';
			elseif (!$this->defcoin)						$this->defcoin		= key($this->payments);
			
			return true;
		}
			
		
		
		/*
		 * 8 
		*/
		public function settings ($settings )
		{
			$logos = array('global' => __( "GoUrl default logo - Global Payments", GOURLGV ));
			foreach ($this->coin_names as $v) $logos[$v] = sprintf(__( 'GoUrl logo with text - %s Payments', GOURLGV ), ucfirst($v));

			$gourl_settings = array(
					array(
							'name' 		=> __( 'GoUrl Bitcoin/Altcoin Gateway', GOURLGV ),
							'desc' 		=> '<hr>',
							'id'   		=> 'give_title',
							'type' 		=> 'give_title'
					),
					array(
							'name' 		=> __( 'GoUrl Bitcoin Gateway', GOURLGV ),
							'desc' 		=> '<div style="font-style:normal;color:#444">' . $this->method_description . '</div>',
							'id'   		=> 'gourl_active',
							'type' 		=> 'checkbox'
					),
					array(
							'name'      => __( 'Title', GOURLGV ),
							'id'		=> 'gourl_title',
							'type'      => 'text',
							'default'   => __( 'Bitcoin/Altcoin', GOURLGV ),
							'desc' 		=> __( 'Donation method title that the customer will see on your checkout', GOURLGV )
					),
					array(
							'name' 		=> __('Exchange Rate Multiplier', GOURLGV ),
							'id'		=> 'gourl_emultiplier',
							'type' 		=> 'text',
							'default' 	=> '1.00',
							'desc' 		=> __('The system uses the multiplier rate with today LIVE cryptocurrency exchange rates (which are updated every 30 minutes) when the transaction is calculating from a fiat currency (e.g. USD, EUR, etc) to cryptocurrency. <br> Example: <b>1.05</b> - will add an extra 5% to the total price in bitcoin/altcoins, <b>0.85</b> - will be a 15% discount for the price in bitcoin/altcoins. Default: 1.00 ', GOURLGV ) 
					),
					array(
							'name' 		=> __('Donation Box Language', GOURLGV ),
							'id'		=> 'gourl_deflang',
							'type' 		=> 'select',
							'options' 	=> $this->languages,
							'default' 	=> 'en',
							'desc' 		=> __("Default Crypto Donation Box Localisation", GOURLGV)
					),
					array(
							'name' 		=> __('Donation Box Coin', GOURLGV ),
							'id'		=> 'gourl_defcoin',
							'type' 		=> 'select',
							'options' 	=> $this->payments,
							'default' 	=> key($this->payments),
							'desc' 		=> sprintf(__( "Default Coin in Crypto Donation Box. &#160; Activated Payments : <a href='%s'>%s</a>", GOURLGV ), $this->url, $this->cointxt)
					),
					array(
							'name'      => __( 'Icons Size', GOURLGV ),
							'id'		=> 'gourl_iconwidth',
							'type'      => 'text',
							'label'     => 'px',
							'default'   => "60px",
							'desc' 		=> __( "Cryptocoin icons size in 'Select Payment Method' that the customer will see on your checkout. Default 60px. Allowed: 30..250px", GOURLGV ) . 
										   '<div style="margin-top:50px;">' . sprintf(__( "Payment Box <a href='%s'>sizes</a> and border <a href='%s'>shadow</a> you can change <a href='%s'>here &#187;</a>", GOURLGV ), plugin_dir_url( __FILE__ )."images/sizes.png", plugin_dir_url( __FILE__ )."images/styles.png", $this->url) . '</div>' .
										   '<div style="margin-top:20px;">' . sprintf(__( "If you want to use GoUrl Give Bitcoin Gateway plugin in a language other than English, see the page <a href='%s'>Languages and Translations</a>", GOURLGV ), "https://gourl.io/languages.html") . '</div>'
					)
			);
		
		
			return array_merge( $settings, $gourl_settings );
		}
			
	

		/*
		 * 9 Forward to payment page 
		*/
		public function process_payment( $purchase_data ) 
		{
		
			global $give_options;
		
			if ( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'give-gateway' ) ) {
				wp_die( __( 'Nonce verification has failed', GOURLGV ), __( 'Error', GOURLGV ), array( 'response' => 403 ) );
			}
		
			/*
			 * Purchase data comes in like this
			*/
			$payment_data = array(
					'price'           => $purchase_data['price'],
					'give_form_title' => $purchase_data['post_data']['give-form-title'],
					'give_form_id'    => intval( $purchase_data['post_data']['give-form-id'] ),
					'date'            => $purchase_data['date'],
					'user_email'      => $purchase_data['user_email'],
					'purchase_key'    => $purchase_data['purchase_key'],
					'currency'        => give_get_currency(),
					'user_info'       => $purchase_data['user_info'],
					'status'          => 'pending',
					'gateway'         => 'gourl'
			);
			// Record the pending payment
			$payment = give_insert_payment( $payment_data );
		
			
			if ( $payment ) 
			{
				// Add new note
				$userID = $purchase_data["user_info"]["id"];
				$user = (!$userID) ? __('Guest', GOURLGV) : "<a href='".admin_url("user-edit.php?user_id=".$userID)."'>user".$userID."</a>";
				give_insert_payment_note($payment, sprintf(__('Donation created by %s. <br>Awaiting cryptocurrency payment ...<br>', GOURLGV), $user));

				// Empty the shopping cart
				give_send_to_success_page();
			} else 
			{
				give_record_gateway_error( __( 'Payment Error', GOURLGV ), sprintf( __( 'Payment creation failed while processing a bitcoin/altcoin donation. Donation data: %s', GOURLGV ), json_encode( $payment_data ) ), $payment );
				// If errors are present, send the user back to the purchase page so they can be corrected
				give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
			}
			
			return true;
		}
			

		
		/*
		 *  10
		*/
		public function cryptocoin_payment ($payment)
		{
			global $gourl;
			
			if (give_get_payment_gateway( $payment->ID ) != "gourl") return true;

			$orderID		= $payment->ID;
			$meta 			= give_get_payment_meta( $orderID );
			$amount 		= give_get_payment_amount( $orderID );
			$status			= get_post_status( $orderID );
			$currency 		= $meta["currency"];
			$userID			= $meta["user_info"]["id"];
			$period			= "NOEXPIRY";
			$language		= $this->deflang;
			$coin 			= $this->coin_names[$this->defcoin];
				
			if (!$amount || !$orderID) 
			{
				echo '<h4>' . __( 'Information', GOURLGV ) . '</h4>' . PHP_EOL;
				echo "<div class='give_error'>". sprintf(__( 'The GoUrl Bitcoin Plugin was called to process a donation but could not retrieve the donation details %s. Cannot continue!', GOURLGV ), ($orderID?"#".$orderID:""))."</div>";
			}
			elseif (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl))
			{
				echo '<h4>' . __( 'Information', GOURLGV ) . '</h4>' . PHP_EOL;
				echo "<div class='give_error'>".sprintf(__( "Please try a different payment method. Admin need to install and activate wordpress plugin <a href='%s'>GoUrl Bitcoin Gateway for Wordpress</a> to accept Bitcoin/Altcoin Donations online.", GOURLGV ), "https://gourl.io/bitcoin-wordpress-plugin.html")."</div>";
			}
			elseif (!$this->payments || !$this->defcoin || true === version_compare(GOURL_VERSION, '1.4.14', '<') ||  
					(array_key_exists($currency, $this->coin_names) && !array_key_exists($currency, $this->payments)))
			{
				echo '<h4>' . __( 'Information', GOURLGV ) . '</h4>' . PHP_EOL;
				
				if (true === version_compare(GIVE_VERSION, '2.0.7', '>') || true === version_compare(GIVE_VERSION, '2.0.6', '<') || !(defined('GIVECORE_GOURL')))
				{
				    $give_ver = sprintf(__( "This Give version not support Bitcoin Gateway!<br>You can DELETE current Give plugin and <b>download free Give plugin with Bitcoin Support ver2.0.6.x from <a href='%s'>Github here</a> &#187;</b>", GOURLGV ), "https://github.com/cryptoapi/Give-Wordpress-Donations-Bitcoin");
				    echo  "<div class='give_error'>".$give_ver."</div>";
				}
				else 
				{
				    echo  "<div class='give_error'>".sprintf(__( 'Sorry, but there was an error processing your donation. Please try a different payment method or contact us if you need assistance (GoUrl Bitcoin Plugin not configured / %s not activated).', GOURLGV ),(!$this->payments || !$this->defcoin || !isset($this->coin_names[$currency])?$this->title:$this->coin_names[$currency]))."</div>";
				}
												 
			}
			else 
			{ 	
				$plugin			= "gourlgive";
				$orderID		= "donation" . $orderID;
				$affiliate_key 	= "gourl";
				$crypto			= array_key_exists($currency, $this->coin_names);
				
				if (!$userID) $userID = "guest"; // allow guests to donate
				
		
				
				if (!$userID) 
				{
					echo '<h4>' . __( 'Information', GOURLGV ) . '</h4>' . PHP_EOL;
					echo "<div align='center'><a href='".wp_login_url(get_permalink())."'>
							<img style='border:none;box-shadow:none;' title='".__('You need first to login or register on the website to make Bitcoin/Altcoin Donation', GOURLGV )."' vspace='10'
							src='".$gourl->box_image()."' border='0'></a></div>";
				}
				elseif ($amount <= 0)
				{
					echo '<h4>' . __( 'Information', GOURLGV ) . '</h4>' . PHP_EOL;
					echo "<div class='give_error'>". sprintf(__( "This donation amount is '%s' - it cannot be paid for. Please contact us if you need assistance.", GOURLGV ), $amount ." " . $currency)."</div>";
				}
				else
				{
		
					// Exchange (optional)
					// --------------------
					if ($currency != "USD" && !$crypto)
					{
						$amount = gourl_convert_currency($currency, "USD", $amount);
							
						if ($amount <= 0)
						{
							echo '<h4>' . __( 'Information', GOURLGV ) . '</h4>' . PHP_EOL;
							echo "<div class='give_error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. Cannot receive exchange rates for %s/USD from Google Finance', GOURLGV ), $currency)."</div>";
						}
						else $currency = "USD";
					}
						
					if (!$crypto) $amount = $amount * $this->emultiplier;
						
					
						
					// Payment Box
					// ------------------
					if ($amount > 0)
					{
						// crypto payment gateway
						$result = $gourl->cryptopayments ($plugin, $amount, $currency, $orderID, $period, $language, $coin, $affiliate_key, $userID, $this->iconwidth);
						
						if (!$result["is_paid"]) 
						{
						    echo  "<script>
    					           jQuery(document).ready(function() {
	   				                   jQuery( '.entry-title' ).text('" . __( 'Donate Now -', GOURLGV ) . "');
					                   jQuery( '.woocommerce-thankyou-order-received, .give_notices, .".CRYPTOBOX_PREFIX_HTMLID."texts_pay_now, .".CRYPTOBOX_PREFIX_HTMLID."header' ).remove();
					               });
					           </script>";
						}
						else echo "<br>";
						
						if ($result["error"]) echo "<div class='give_error'>".__( "Sorry, but there was an error processing your donation. Please try a different payment method.", GOURLGV )."<br/>".$result["error"]."</div>";
						else
						{
							// display payment box or successful payment result
							echo $result["html_payment_box"];
							
							// payment received
							if ($result["is_paid"]) 
							{	
								if (false) echo "<div align='center'>" . sprintf( __('%s Donation ID: #%s', GOURLGV), ucfirst($result["coinname"]), $result["paymentID"]) . "</div>";
								
								if ($status == "pending" && !isset($_GET["rl"])) header('Location: '.$_SERVER['REQUEST_URI']."&rl=1"); 
							}
							
						}
					}	
				}
			}

			echo "<br>";
					
			return true;
		}

		
		
		/*
		 * 11
		*/
		public function template_redirect()
		{
			global $wp;
			
			if (strpos($wp->request, 'donation-confirmation/') === 0 && substr($wp->request, -6) == "/&rl=1") wp_redirect(home_url(add_query_arg(array(), substr($wp->request, 0, -6))));
			
			return true;
		}
		
		
		
		/*
		 * 12
		*/
		public function admin_footer_text()
		{
    		return sprintf( __( "If you like <b>GoUrl Give Bitcoin Gateway</b> please leave us a %s rating on %s. A huge thank you from GoUrl in advance!", GOURLGV ), "<a href='https://wordpress.org/support/view/plugin-reviews/gourl-bitcoin-paypal-donations-give-addon?filter=5#postform' target='_blank'>&#9733;&#9733;&#9733;&#9733;&#9733;</a>", "<a href='https://wordpress.org/support/view/plugin-reviews/gourl-bitcoin-paypal-donations-give-addon?filter=5#postform' target='_blank'>WordPress.org</a>");
		}
		
		
		
		/*
		 * 12
		*/
		private function exst( & $var, $default = "")
		{
			$t = "";
			if ( !isset($var)  || !$var )
			{
				if (isset($default) && $default !== "") $t = $default;
			}
			else
			{
				$t = $var;
			}
		
			if (is_string($t)) $t = trim($t);
		
			return $t;
		}
		
				
	}
	// end class

	
	// new class init
	if (class_exists('Give')) new gourl_give;
	
	
	
	
	
	
	
	
	/*
	 *  12. Instant Payment Notification Function - pluginname."_gourlcallback"
	*
	*  This function will appear every time by GoUrl Bitcoin Gateway when a new payment from any user is received successfully.
	*  Function gets user_ID - user who made payment, current order_ID (the same value as you provided to bitcoin payment gateway),
	*  payment details as array and box status.
	*
	*  The function will automatically appear for each new payment usually two times :
	*  a) when a new payment is received, with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 0
	*  b) and a second time when existing payment is confirmed (6+ confirmations) with values: $box_status = cryptobox_updated, $payment_details[is_confirmed] = 1.
	*
	*  But sometimes if the payment notification is delayed for 20-30min, the payment/transaction will already be confirmed and the function will
	*  appear once with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 1
	*
	*  Payment_details example - https://gourl.io/images/plugin2.png
	*  Read more - https://gourl.io/affiliates.html#wordpress
	*/
	function gourlgive_gourlcallback ($user_id, $order_id, $payment_details, $box_status)
	{
		// Security
		if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
	
		if (strpos($order_id, "donation") === 0) $order_id = substr($order_id, 8); else return false;
	
		if (!$user_id || $payment_details["status"] != "payment_received") return false;
	
		if (give_get_payment_gateway( $order_id ) != "gourl") return false;
	
		
		// Init
		$coinName 	= ucfirst($payment_details["coinname"]);
		$amount		= $payment_details["amount"] . " " . $payment_details["coinlabel"] . "&#160; ( $" . $payment_details["amountusd"] . " )";
		$payID		= $payment_details["paymentID"];
		$tx         = $payment_details["tx"];
		$confirmed	= ($payment_details["is_confirmed"]) ? __('Yes', GOURLGV) : __('No', GOURLGV);
	
	
		// New Payment Received
		if ($box_status == "cryptobox_newrecord")
		{
			give_insert_payment_note($order_id, sprintf(__("%s Payment Received <br>%s. <br><a href='%s'>Payment id %s</a>. <br>Awaiting network confirmation...", GOURLGV), $coinName, $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID) . '<br>');
			give_insert_payment_note($order_id, sprintf(__("%s Transaction ID: %s", GOURLGV), $coinName, $tx));
			
			// Update status to Payment completed 
			give_update_payment_status( $order_id, 'publish' );
		}
		
		// Existing Payment confirmed (6+ confirmations)
		if ($payment_details["is_confirmed"])
		{
			give_insert_payment_note($order_id, sprintf(__("%s Payment id <a href='%s'>%s</a> Confirmed", GOURLGV), $coinName, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID) . '<br>');
		}
	
		return true;
	}	

	
	
	}
	// end gourl_give_gateway_load()     
}
