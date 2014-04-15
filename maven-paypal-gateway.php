<?php

/*
  Plugin Name: Maven Paypal Gateway
  Plugin URI:
  Description:
  Author: Site Mavens
  Version: 0.1
  Author URI:
 */

namespace MavenPaypalGateway;

// Exit if accessed directly 
if ( ! defined( 'ABSPATH' ) ) exit;
 				
use Maven\Settings\OptionType,
	Maven\Settings\Option;

/**
 * Description 
 *
 * @author Emiliano Jankowski
 */
class PaypalGateway extends \Maven\Gateways\Gateway {
    

    public function __construct() {

        parent::__construct();

        $this->setLiveUrl( "https://api-3t.paypal.com/nvp" );
        $this->setTestUrl( "https://api-3t.sandbox.paypal.com/nvp" );
        $this->setParameterPrefix( "" );
        $this->setItemDelimiter( "|" );
        $this->setName( "Paypal" );

        $defaultOptions = array(
            new Option(
                "authorizationType", "Authorization Type", '', '', OptionType::DropDown
            ),
            new Option(
                "authorizationTypeTest", "Authorization Type Test", 'Authorization', '', OptionType::DropDown // Sale is the default one
            ),
           
            new Option(
                "username", "Username", '', '', OptionType::Input
            ),
            new Option(
                "password", "Password", '', '', OptionType::Input
            ),
            new Option(
                "signature", "Signature", '', '', OptionType::Input
            ),
            new Option(
                "usernameTest", "Username", '', '', OptionType::Input
            ),
            new Option(
                "passwordTest", "Password", '', '', OptionType::Input
            ),
            new Option(
                "signatureTest", "Signature", '', '', OptionType::Input
            )
            );

            $this->addSettings( $defaultOptions );
    }

    public function execute() {
		
		//Load the library 
		\Maven\Core\Loader::load(__DIR__, '/lib/phpPayPal.php');
		
		// Let people adjust the settings
		$settings = \Maven\Core\HookManager::instance()->applyFilters('maven/gateway/settings', $this->getSettings() );
		$this->addSettings($settings);
		
		$config = array(
			'use_proxy' => false,
			'proxy_host' => "",
			'proxy_port' => "",
			'return_url' => "",
			'cancel_url' => ""
		);
		
		$authorizationType = "";
		 
		if ( $this->isTestMode() ){
			$config['api_username'] = $this->getSetting( 'usernameTest' );
			$config['api_password'] = $this->getSetting( 'passwordTest' );
			$config['api_signature'] = $this->getSetting( 'signatureTest' );
			$authorizationType = $this->getSetting( 'authorizationTypeTest' );
			
		}else{
			$config['api_username'] = $this->getSetting( 'username' );
			$config['api_password'] = $this->getSetting( 'password' );
			$config['api_signature'] = $this->getSetting( 'signature' );
			$authorizationType = $this->getSetting( 'authorizationType' );
		}
		
		// Create instance of the phpPayPal class
		$paypal = new \phpPayPal($config, $this->isTestMode());

		$paypal->payment_type = $authorizationType;
		
		// (required)
		$paypal->ip_address = $this->getRemoteIp();

		// Order Totals (amount_total is required)
		$paypal->amount_total = $this->getAmount();
		$paypal->amount_shipping = $this->getShippingAmount();

		// Credit Card Information (required)
		$paypal->credit_card_number = $this->getCCNumber();
		$paypal->credit_card_type =  $this->getCcType();
		$paypal->cvv2_code = $this->getCCVerificationCode();
		$paypal->expire_date = $this->getCCMonth().$this->getCCYear();

		// Billing Details (required)
		$paypal->first_name = $this->getFirstName();
		$paypal->last_name = $this->getLastName();
		$paypal->address1 =$this->getAddress();
		$paypal->city = $this->getCity();
		$paypal->state = $this->getState();
		$paypal->postal_code = $this->getZip();
		$paypal->phone_number = $this->getPhone();
		$paypal->country_code = $this->getCountry();
		$paypal->email = $this->getEmail();
		
		if($this->hasOrderItems())
        {
			$items = $this->getOrderItems();
			
            foreach($items as $item )
            {
				$paypal->add_item($item->getName(), $item->getItemId(), $item->getQuantity(), $item->getTaxable(), $item->getUnitPrice());
            }
        }

		// Perform the payment
		$result = $paypal->do_direct_payment();

		if ($result === false) {
			
			$this->setError(true);
			$this->setErrorDescription( $paypal->Error['LONGMESSAGE'] );
			
		} else {
			
			$this->setApproved( true );
			if ( $paypal->Response && isset( $paypal->Response['TRANSACTIONID'] ) && !empty( $paypal->Response['TRANSACTIONID'] ) ) {
					$this->setTransactionId( $paypal->Response['TRANSACTIONID'] );
			}
			else{
			   $this->setTransactionId( -1 );
			}

		}
    }

	public function getAvsCode () {
		
	}
	
	public function register( $gateways ){
		
		$gateways[$this->getKey()] = $this;
		
		return $gateways;
	}

}
 


$paypalGateway = new PaypalGateway();
\Maven\Core\HookManager::instance()->addFilter('maven/gateways/register', array($paypalGateway,'register'));