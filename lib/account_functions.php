<?php
/**
 * Scribendi Wordpress Plugin - account_functions
 * 
 * The various account_functions used by the Scribendi plugin
 */


/**
 * Runs a check to see if the key has been registered
 *
 * @return boolean
 */
function scribendi_key_check() {
	$key = wp_filter_nohtml_kses($_POST['key']);
	if ( strlen($key) < 64 || !preg_match('/[a-z0-9]{64}/i', $key )) {
		echo 'ok';
		exit();
	}

    global $current_user;
    get_currentuserinfo();

    try {

        $oAuth = new Scribendi_Api_Auth('', '');
        $oQuery = new Scribendi_Api_Query(array());
        $oOptions = array(
            Scribendi_Api_Client::OPTION_API_SERVER => get_option(SCRIBENDI_API_SERVER, 'https://www.scribendi.com'),
            Scribendi_Api_Client::OPTION_CLIENT_CONNECTION_TIMEOUT => '10',
            Scribendi_Api_Client::OPTION_CLIENT_TIMEOUT => '10'
        );

        $oClient = new Scribendi_Api_Client( $oAuth, $oQuery, $oOptions );
        $oClient->useAdaptor('account');
        $oResponse = $oClient->useAdaptor('account')->getAPIDetails($_POST['key'])->go();
        $results_array = $oResponse->getResults();

        $public_key = $results_array[0]->getPublicKey();
        $private_key = $results_array[0]->getPrivateKey();
        $client_id = $results_array[0]->getClientID();

        if($client_id && $client_id > 0) {
            update_option(SCRIBENDI_PUBLIC_KEY, $public_key);
            update_option(SCRIBENDI_PRIVATE_KEY, $private_key);
            update_option(SCRIBENDI_CUSTOMER_ID, $client_id);
            update_user_option($current_user->ID, SCRIBENDI_CUSTOMER_ID, $client_id);

			echo json_encode( array( 'public_key' => $public_key, 'private_key' => $private_key, 'client_id' => $client_id ) );

        }

    } catch ( Exception $e ) {
        echo $e->getMessage();
    }

	exit();
}


/**
 * Scribendi_Api_Client_Adaptor_Account
 *
 * The adapter for account API request
 *
 */
class Scribendi_Api_Client_Adaptor_Account extends Scribendi_Api_Client_Adaptor_Abstract {

    protected $_RequiredParameters = array();

    function __construct(Scribendi_Api_Client $inClient, Scribendi_Api_Auth $inAuth, Scribendi_Api_Query $inQuery, array $inOptions = array()) {

        $this->setApiClient($inClient);
        $this->setApiAuth($inAuth);
        $this->setApiQuery($inQuery);
        $this->getTransportOptions()->setOptions($inOptions);
        $this->initialise();
    }

    protected function initialise() {

        $this->setName('account');
        $this->setResponseHandler('Scribendi_Api_Client_Response_Account');
        $this->setRequestUri(Scribendi_Api_Constants::API_ROOT_PATH.'account');
        $this->getTransportOptions()->setOptions( array(CURLOPT_HTTPGET => true));
    }

    protected function _isValid() {
        return true;
    }

    function getAPIDetails($inRequestKey = null) {

        $this->setResponseHandler('Scribendi_Api_Client_Response_Account');
        $this->_setRequestUri($inRequestKey);

        $this->getTransportOptions()->setOptions(
            array(
                CURLOPT_HTTPGET => true
            )
        );

        return $this->getApiClient();
    }

    private function _setRequestUri($inRequestKey) {
        $this->setRequestUri(
            Scribendi_Api_Constants::API_ROOT_PATH.
                'account/requestKeyCheck/'.$inRequestKey
        );
    }
}

/**
 * Scribendi_Api_Client_Response_Account
 *
 * The response object to hand account API requests
 */
class Scribendi_Api_Client_Response_Account extends Scribendi_Api_Client_Response {

    protected function _handleResponse() {
        foreach ( $this->getResponseAsXml()->results->result as $oResult ) {
            $oAccount = Scribendi_Api_Model_Account::factory($oResult);
            $this->_Results[] = $oAccount;
        }
    }
}

/**
 * Scribendi_Api_Model_Account
 *
 * API details factory
 */
class Scribendi_Api_Model_Account {

    protected $_Modified = false;
    protected $_PrivateKey;
    protected $_PublicKey;
    protected $_ClientID;

    function __construct() {
        $this->reset();
    }

    function reset() {
        $this->_PrivateKey = null;
        $this->_PublicKey = null;
        $this->_ClientID = null;
        $this->setModified(false);
    }

    static function factory(SimpleXMLElement $inXML) {
        $oObject = new Scribendi_Api_Model_Account();
        $oObject->setPublicKey((string) $inXML->publicKey);
        $oObject->setPrivateKey((string) $inXML->privateKey);
        $oObject->setClientID((integer) $inXML->customerId);

        return $oObject;
    }

    function isModified() {
        return $this->_Modified;
    }

    function setModified($status = true) {
        $this->_Modified = $status;
        return $this;
    }

    function setPrivateKey($inPrivateKey) {
        if ( $inPrivateKey !== $this->_PrivateKey ) {
            $this->_PrivateKey = $inPrivateKey;
            $this->setModified();
        }
        return $this;
    }

    function setPublicKey($inPublicKey) {
        if ( $inPublicKey !== $this->_PublicKey ) {
            $this->_PublicKey = $inPublicKey;
            $this->setModified();
        }
        return $this;
    }

    function setClientID($inClientID) {
        if ( $inClientID !== $this->_ClientID ) {
            $this->_ClientID = $inClientID;
            $this->setModified();
        }
        return $this;
    }

    function getPrivateKey() {
        return $this->_PrivateKey;
    }

    function getPublicKey() {
        return $this->_PublicKey;
    }

    function getClientID() {
        return $this->_ClientID;
    }
}