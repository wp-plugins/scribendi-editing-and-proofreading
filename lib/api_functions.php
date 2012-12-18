<?php
/**
 * Scribendi Wordpress Plugin - api_functions
 * 
 * Assorted wrapper functions around the main Scribendi API
 */


/**
 * Prepares and returns an instance of the API client
 * 
 * @return Scribendi_Api_Client
 */
function scribendi_prepare_client() {
	static $client;
	if ( $client instanceof Scribendi_Api_Client ) {
		return $client;
	}
	
	$custID = get_user_option(SCRIBENDI_CUSTOMER_ID);
	if ( !$custID || !is_numeric($custID) ) {
		$custID = scribendi_get_default_customer_id();
	}
	
	if ( !$custID || !is_numeric($custID) ) {	
		throw new LogicException('Whoops! It looks like you forgot to type in your customer ID. You can get that from your Scribendi account API settings page <a href="https://www.scribendi.com/customer?view=cp_profile_api" target="_blank">here</a>.');
	}
	
	$client = new Scribendi_Api_Client(
		new Scribendi_Api_Auth(get_option(SCRIBENDI_PUBLIC_KEY), get_option(SCRIBENDI_PRIVATE_KEY)),
		new Scribendi_Api_Query(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_CUSTOMER => $custID,
				Scribendi_Api_Constants::FIELD_CURRENCY_ID => get_option(SCRIBENDI_DEFAULT_CURRENCY, 840),
				Scribendi_Api_Constants::FIELD_ORDER_ENGLISH_VERSION => get_option(SCRIBENDI_DEFAULT_ENGLISH, 'US'),
			)
		),
		array(
			Scribendi_Api_Client::OPTION_API_SERVER => get_option(SCRIBENDI_API_SERVER, 'https://www.scribendi.com'),
			Scribendi_Api_Client::OPTION_CLIENT_CONNECTION_TIMEOUT => get_option(SCRIBENDI_API_CONN_TIMEOUT, 10),
			Scribendi_Api_Client::OPTION_CLIENT_TIMEOUT => get_option(SCRIBENDI_API_TIMEOUT, 10),
		)
	);
	
	return $client;
}

/**
 * Tests the various settings and reports any errors
 * 
 * If an error occurs during testing, the Exception is returned,
 * otherwise this function will return boolean true if all is OK.
 *
 * @return boolean|Exception
 */
function scribendi_test_api_settings() {
	try {
		$oClient = scribendi_prepare_client();
		$oClient->getCurrencies();
	} catch ( Exception $e ) {
		return $e;
	}
	
	return true;
}

/**
 * Returns the variations of English supported by Scribendi
 *
 * @return array
 */
function scribendi_get_english_types() {
	return array('Australian', 'British', 'Canadian', 'US');
}

/**
 * Gets the available currencies, querying the API if not loaded
 *
 * @return array(Scribendi_Api_Model_Currency)
 */
function scribendi_get_currencies() {
	$currencies = false;
	if ( get_option(SCRIBENDI_PUBLIC_KEY) ) {
		$refresh = false;
		if ( get_option(SCRIBENDI_API_CURRENCIES) ) {
			if ( (time()-get_option(SCRIBENDI_API_CURRENCIES_DATE) > SCRIBENDI_API_CURRENCIES_LIFETIME) ) {
				$refresh = true;
			} else {
				$currencies = get_option(SCRIBENDI_API_CURRENCIES);
				if ( !is_array($currencies) || count($currencies) < 1 ) {
					$refresh = true;
				}
			}
		} else {
			$refresh = true;
		}
		
		if ( $refresh ) {
			try {
				$oClient = scribendi_prepare_client();
				$oResponse = $oClient->useCurrencyAdaptor()->getCurrencies()->go();
				$currencies = $oResponse->getResults();
				if ( is_array($currencies) && count($currencies) > 0 ) {
					update_option(SCRIBENDI_API_CURRENCIES, $currencies);
					update_option(SCRIBENDI_API_CURRENCIES_DATE, time());
				}
			} catch ( Exception $e ) {
			}
		}
	}
	if ( !$currencies ) {
		/*
		 * Ensure that there is at least one value
		 */
		$oCurrency = new Scribendi_Api_Model_Currency();
		$oCurrency->setCurrencyId(840);
		$oCurrency->setDescription('US Dollars');
		$oCurrency->setExponent(2);
		$oCurrency->setIsoName('USD');
		$oCurrency->setSymbol('$');
		
		$currencies = array($oCurrency);
	}
	return $currencies;
}

/**
 * Returns the default customer id, or false if not set
 *
 * @return integer|boolean
 */
function scribendi_get_default_customer_id() {
	return get_option(SCRIBENDI_CUSTOMER_ID, false);
}

/**
 * Returns the default currency object
 *
 * @return Scribendi_Api_Model_Currency
 * @throws LogicException
 */
function scribendi_get_default_currency() {
	$currencyId = get_option(SCRIBENDI_DEFAULT_CURRENCY, 840);
	$currencies = scribendi_get_currencies();
	if ( count($currencies) > 1 ) {
		foreach ( $currencies as $oCurrency ) {
			if ( $oCurrency->getCurrencyId() == $currencyId ) {
				return $oCurrency;
			}
		}
	} elseif ( count($currencies) == 1 ) {
		return $currencies[0];
	} else {
		throw new LogicException(__('No currency data is available', 'scribendi'));
	}
}

/**
 * Returns the scribendi order object from the post ID
 *
 * First checks for a scribendi order reference before loading
 * the order details. If (for whatever reason) the order object
 * has become corrupted, it will attempt to be reloaded from
 * the Scribendi API.
 *
 * This function always returns an order object - check status
 * and description for errors.
 *
 * @param integer $inPostId
 * @throws LogicException
 * @return Scribendi_Api_Model_Order
 */
function scribendi_get_order_from_post($inPostId) {
	$scribendiOrderId = get_post_meta($inPostId, SCRIBENDI_OPTION_ORDER_ID, true);
	if ( $scribendiOrderId ) {
		$serialisedOrder = get_post_meta($inPostId, SCRIBENDI_OPTION_ORDER_DETAILS, true);
		$oOrder = unserialize(base64_decode($serialisedOrder));
		if ( !$oOrder instanceof Scribendi_Api_Model_Order ) {
			/*
			 * If we didn't get the object, try to restore from the API
			 * otherwise, hack it
			 */
			try {
				/**
				 * @var Scribendi_Api_Client_Response_Order $oResult
				 */
				
				$oClient = scribendi_prepare_client();
				$oResult = $oClient->useOrderAdaptor()->getOrderStatus($scribendiOrderId)->go();
				$oOrder = $oResult->getOrder();
				if ( $oOrder instanceof Scribendi_Api_Model_Order && $oOrder->getOrderId() == $scribendiOrderId ) {
					/*
					 * Replace or add the order object so it's cached
					 */
					scribendi_store_order_object($inPostId, $oOrder);
				} else {
					throw new LogicException('Failed to retrieve order from Scribendi.com');
				}
			} catch ( Exception $e ) {
				/*
				 * Create place holder object instead
				 */
				$oOrder = new Scribendi_Api_Model_Order();
				$oOrder->setStatus(-5);
				$oOrder->setDescription($e->getMessage());
			}
		}
	} else {
		/*
		 * Return empty order
		 */
		$oOrder = new Scribendi_Api_Model_Order();
	}
	
	return $oOrder;
}

/**
 * Stores an order object, protecting it from Wordpresses idiotic internal API
 *
 * @param integer $inPostId
 * @param Scribendi_Api_Model_Order $inOrder
 * @return boolean
 */
function scribendi_store_order_object($inPostId, Scribendi_Api_Model_Order $inOrder) {
	return update_post_meta($inPostId, SCRIBENDI_OPTION_ORDER_DETAILS, base64_encode(serialize($inOrder)));
}

/**
 * Does a basic word-count find on the post content, returns false on error
 *
 * @param string $inContent
 * @return integer
 */
function scribendi_get_word_count($inContent) {
	$matches = array();
	$inContent = strip_tags($inContent);
	
	return preg_match_all("/\S+/", $inContent, $matches);
}

/**
 * Generates a request key for registering with scribendi.com
 *
 * @return string
 */
function scribendi_generate_reg_key() {
	$l = 64;
	$c = "npqrstvwxyzbcdfghjkmBCDFGHJKLMNPQRSTVWXYZ56789234";
	$s = $c{mt_rand(0,40)};

	while (strlen($s) < $l) {
		$s .= $c{mt_rand(0,48)};
	} // while

	return $s;
}