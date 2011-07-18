<?php
/**
 * Scribendi API Client for PHP (v1.1) - Single File
 *
 * Copyright (C) 2010-2011 Scribendi Inc.
 *
 * Use of this software program, and use of Scribendi's API and services
 * are governed by the SCRIBENDI INC. API LICENSE AGREEMENT.
 * 
 * You may also, at your option, use this software program under terms of 
 * the GNU General Public License as published by the Free Software 
 * Foundation; either version 2 of the License, or (at your option) any 
 * later version. Please see the file "gpl.txt" supplied with this software 
 * for a full copy of this license. However, the SCRIBENDI INC. API 
 * LICENSE AGREEMENT always applies to your use of Scribendi's API and 
 * services. 
 */

class Scribendi_Api_Exception extends Exception {
}
class Scribendi_Api_Client_Exception extends Scribendi_Api_Exception {
}
class Scribendi_Api_Client_Adaptor_Exception extends Scribendi_Api_Client_Exception {
}
class Scribendi_Api_Client_Adaptor_Transport_Exception extends Scribendi_Api_Client_Exception {
}
class Scribendi_Api_Client_Response_Exception extends Scribendi_Api_Client_Exception {
}
class Scribendi_Api_Client_Response_NotValidXML extends Scribendi_Api_Client_Exception {
	protected $_Response;
	function __construct($inResponse = null) {
		parent::__construct('The response was not a valid XML document');
			$this->setResponse($inResponse);
	}
		function getResponse() {
		return $this->_Response;
	}
	function setResponse($inResponse) {
		if ( $inResponse !== $this->_Response ) {
			$this->_Response = $inResponse;
		}
		return $this;
	}
}
class Scribendi_Api_Autoloader {
	static function autoload($classname) {
		if ( strpos($classname, 'Scribendi_Api') !== false ) {
			$root = dirname(dirname(dirname(__FILE__)));
			$path = str_replace('_', DIRECTORY_SEPARATOR, $classname).'.php';
			$fullpath = $root.DIRECTORY_SEPARATOR.$path;
					if ( file_exists($fullpath) && is_readable($fullpath) ) {
				require_once $fullpath;
				return true;
			}
		}
		return false;
	}
}
class Scribendi_Api_Options implements IteratorAggregate, ArrayAccess, Countable  {
	protected $_Modified = false;
	protected $_Options = array();
	function __construct(array $inOptions = array()) {
		$this->reset();
		if ( count($inOptions) > 0 ) {
			$this->setOptions($inOptions);
		}
	}
	function reset() {
		$this->_Options = array();
		$this->setModified(false);
	}
	function isModified() {
		return $this->_Modified;
	}
	function setModified($status = true) {
		$this->_Modified = $status;
		return $this;
	}
	function getOptions($inOption = null, $inDefault = null) {
		if ( $inOption === null ) {
			return $this->_Options;
		}
			if ( $this->offsetExists($inOption) ) {
			return $this->offsetGet($inOption);
		}
			if ( $inDefault !== null ) {
			return $inDefault;
		} else {
			return null;
		}
	}
	function setOptions(array $inOptions = array()) {
		if ( count($inOptions) > 0 ) {
			foreach ( $inOptions as $key => $option ) {
				$this->offsetSet($key, $option);
			}
		}
		return $this;
	}
	function removeOptions(array $inOptions = array()) {
		if ( count($inOptions) > 0 ) {
			foreach ( $inOptions as $option ) {
				$this->offsetUnset($option);
			}
		}
		return $this;
	}
	function toArray() {
		return $this->_Options;
	}
	public function offsetExists($offset) {
		return isset($this->_Options[$offset]);
	}
	public function offsetGet($offset) {
		 return isset($this->_Options[$offset]) ? $this->_Options[$offset] : null;
	}
	public function offsetSet($offset, $value) {
		$this->_Options[$offset] = $value;
		$this->setModified();
	}
	public function offsetUnset($offset) {
		unset($this->_Options[$offset]);
		$this->setModified();
	}
	public function count() {
		return count($this->_Options);
	}
	public function getIterator() {
		return new ArrayIterator($this->_Options);
	}
}
class Scribendi_Api_Query extends Scribendi_Api_Options {
	function getQuery() {
		$query = array();
		foreach ( $this as $option => $value ) {
			if ( is_object($value) && method_exists($value, '__toString') ) {
				$query[$option] = $value->__toString();
			} else {
				$query[$option] = $value;
			}
		}
		return http_build_query($query, null, '&');
	}
	function getQueryDate() {
		if (
			!$this->offsetExists(Scribendi_Api_Constants::FIELD_REQUEST_DATE) ||
			!$this[Scribendi_Api_Constants::FIELD_REQUEST_DATE] instanceof Scribendi_Api_DateTime
		) {
			$oDate = Scribendi_Api_DateTime::getInstanceUtc();
			$oDate->setToStringFormat('YmdHis');
					$this[Scribendi_Api_Constants::FIELD_REQUEST_DATE] = $oDate;
		}
		return $this[Scribendi_Api_Constants::FIELD_REQUEST_DATE];
	}
	function getQueryParam($inParam, $inDefault = null) {
		return $this->getOptions($inParam, $inDefault);
	}
	function clearQueryParam($inParam) {
		return $this->removeOptions((array)$inParam);
	}
}
class Scribendi_Api_Auth {
	private static $_Instance = null;
	protected $_Modified = false;
	protected $_PublicKey;
	protected $_PrivateKey;
	public static function getInstance($inPublicKey = null, $inPrivateKey = null) {
		if ( !self::$_Instance instanceof Scribendi_Api_Auth ) {
			self::$_Instance = new self($inPublicKey, $inPrivateKey);
		}
		return self::$_Instance;
	}
	function __construct($inPublicKey = null, $inPrivateKey = null) {
		$this->reset();
		if ( $inPublicKey !== null ) {
			$this->setPublicKey($inPublicKey);
		}
		if ( $inPrivateKey !== null ) {
			$this->setPrivateKey($inPrivateKey);
		}
	}
	function createSignature($inRequestType, Scribendi_Api_DateTime $inRequestTime, $inRequestUri) {
		if ( !in_array($inRequestType, array('GET', 'POST')) ) {
			throw new Scribendi_Api_Auth_InvalidRequestType($inRequestType);
		}
		if ( strpos($inRequestUri, '/') !== 0 ) {
			$inRequestUri = '/'.$inRequestUri;
		}
		$request = array(
			$inRequestType,
			$inRequestTime->format('YmdHis'),
			$inRequestUri,
			$this->getPrivateKey()
		);
		return sha1(implode("\n", $request));
	}
	function reset() {
		$this->_PublicKey = null;
		$this->_PrivateKey = null;
		$this->setModified(false);
	}
	function isModified() {
		return $this->_Modified;
	}
	function setModified($status = true) {
		$this->_Modified = $status;
		return $this;
	}
	function getPublicKey() {
		return $this->_PublicKey;
	}
	function setPublicKey($inPublicKey) {
		if ( $inPublicKey !== $this->_PublicKey ) {
			$this->_PublicKey = $inPublicKey;
			$this->setModified();
		}
		return $this;
	}
	function getPrivateKey() {
		return $this->_PrivateKey;
	}
	function setPrivateKey($inPrivateKey) {
		if ( $inPrivateKey !== $this->_PrivateKey ) {
			$this->_PrivateKey = $inPrivateKey;
			$this->setModified();
		}
		return $this;
	}
}
class Scribendi_Api_Auth_InvalidRequestType extends Scribendi_Api_Exception {
	function __construct($inType) {
		parent::__construct("Request must be either GET or POST; $inType is not valid");
	}
}
class Scribendi_Api_Client {
	public $query = null;
	protected $_ApiAuth = null;
	protected $_Options;
	const OPTION_CLIENT_CONNECTION_TIMEOUT = 'client.connection.timeout';
	const OPTION_CLIENT_TIMEOUT = 'client.timeout';
	const OPTION_API_SERVER = 'api.server';
	protected $_Adaptors = array();
	protected $_ActiveAdaptor = null;
	function __construct(Scribendi_Api_Auth $inAuth, Scribendi_Api_Query $inQuery, array $inOptions = array()) {
		$this->setApiAuth($inAuth);
		$this->getOptionsSet()->setOptions($inOptions);
		$this->query = $inQuery;
		$this->query[Scribendi_Api_Constants::FIELD_PUBLIC_KEY] = $inAuth->getPublicKey();
	}
	function useAdaptor($inAdaptor) {
		$inAdaptor = strtolower($inAdaptor);
		$this->_setupAdaptor($inAdaptor);
			return $this->getAdaptor($inAdaptor);
	}
	private function _setupAdaptor($name, $arguments = array()) {
		if ( $this->getAdaptor($name) === null ) {
			$adaptor = 'Scribendi_Api_Client_Adaptor_'.ucfirst($name);
			if ( class_exists($adaptor) ) {
				$this->setAdaptor(new $adaptor($this, $this->getApiAuth(), $this->getQuery()));
			} else {
				throw new Scribendi_Api_Client_Exception("Unable to locate client adaptor ($name)");
			}
		}
		$this->getQuery()->setOptions($arguments);
		$this->setActiveAdaptor($name);
	}
	function useCurrencyAdaptor() {
		return $this->useAdaptor(Scribendi_Api_Constants::ADAPTOR_CURRENCY);
	}
	function useHelpAdaptor() {
		return $this->useAdaptor(Scribendi_Api_Constants::ADAPTOR_HELP);
	}
	function useOrderAdaptor() {
		return $this->useAdaptor(Scribendi_Api_Constants::ADAPTOR_ORDER);
	}
	function useQuoteAdaptor() {
		return $this->useAdaptor(Scribendi_Api_Constants::ADAPTOR_QUOTE);
	}
	function useSearchAdaptor() {
		return $this->useAdaptor(Scribendi_Api_Constants::ADAPTOR_SEARCH);
	}
	function go() {
		$oAdaptor = $this->getAdaptor($this->getActiveAdaptor());
		if ( !$oAdaptor instanceof Scribendi_Api_Client_Adaptor_Abstract ) {
			throw new Scribendi_Api_Client_Exception('Failed to call '.$this->getActiveAdaptor().'; adaptor is not active');
		}
		$oAdaptor->setRequestServer($this->getOption(self::OPTION_API_SERVER));
		$oAdaptor->getTransportOptions()->setOptions(
			array(
				CURLOPT_CONNECTTIMEOUT => $this->getOption(self::OPTION_CLIENT_CONNECTION_TIMEOUT, 10),
				CURLOPT_TIMEOUT => $this->getOption(self::OPTION_CLIENT_TIMEOUT, 10),
			)
		);
		return $oAdaptor->go();
	}
	function getCurrencies() {
		$this->useCurrencyAdaptor();
		return $this->go();
	}
	function doSearch(array $inOptions = array()) {
		if ( count($inOptions) > 0 ) {
			$this->getQuery()->setOptions($inOptions);
		}
		$this->useSearchAdaptor();
		return $this->go();
	}
	function getQuotes(array $inOptions = array()) {
		if ( count($inOptions) > 0 ) {
			$this->getQuery()->setOptions($inOptions);
		}
		$this->useQuoteAdaptor();
		return $this->go();
	}
	function getApiAuth() {
		return $this->_ApiAuth;
	}
	function setApiAuth($inApiAuth) {
		if ( $inApiAuth !== $this->_ApiAuth ) {
			$this->_ApiAuth = $inApiAuth;
		}
		return $this;
	}
	function getQuery() {
		return $this->query;
	}
	function setQuery(Scribendi_Api_Query $inQuery) {
		if ( $inQuery !== $this->query ) {
			$this->query = $inQuery;
		}
		return $this;
	}
	function getOptionsSet() {
		if ( !$this->_Options instanceof Scribendi_Api_Options ) {
			$this->_Options = new Scribendi_Api_Options();
		}
		return $this->_Options;
	}
	function setOptionsSet(Scribendi_Api_Options $inOptions) {
		if ( $inOptions !== $this->_Options ) {
			$this->_Options = $inOptions;
		}
		return $this;
	}
	function getOption($inName, $inDefault = null) {
		return $this->getOptionsSet()->getOptions($inName, $inDefault);
	}
	function setOption($inName, $inValue) {
		$this->getOptionsSet()->setOptions(array($inName => $inValue));
		return $this;
	}
	function getAdaptor($inName = null) {
		if ( $inName !== null ) {
			if ( array_key_exists($inName, $this->_Adaptors) ) {
				return $this->_Adaptors[$inName];
			} else {
				return null;
			}
		}
		return $this->_Adaptors;
	}
	function setAdaptor(Scribendi_Api_Client_Adaptor_Abstract $inAdaptor) {
		if ( !in_array($inAdaptor->getName(), $this->_Adaptors) ) {
			$this->_Adaptors[$inAdaptor->getName()] = $inAdaptor;
		}
		return $this;
	}
	function getActiveAdaptor() {
		return $this->_ActiveAdaptor;
	}
	function setActiveAdaptor($inActiveAdaptor) {
		if ( $inActiveAdaptor !== $this->_ActiveAdaptor ) {
			$this->_ActiveAdaptor = $inActiveAdaptor;
		}
		return $this;
	}
}
class Scribendi_Api_Constants {
	private function __construct() {}
	const API_CLIENT_VERSION = '1.1.0';
	const API_REQUEST_POST = 'POST';
	const API_REQUEST_GET = 'GET';
	const API_ROOT_PATH = '/rest/';
	const ADAPTOR_CURRENCY = 'currency';
	const ADAPTOR_HELP = 'help';
	const ADAPTOR_ORDER = 'order';
	const ADAPTOR_QUOTE = 'quote';
	const ADAPTOR_SEARCH = 'search';
	const FIELD_PUBLIC_KEY = 'publickey';
	const FIELD_REQUEST_SIGNATURE = 'signature';
	const FIELD_REQUEST_DATE = 'date';
	const FIELD_REQUEST_CUSTOMER = 'cid';
	const FIELD_KEYWORDS = 'keywords';
	const FIELD_OFFSET = 'offset';
	const FIELD_LIMIT = 'limit';
	const FIELD_CATEGORY_ID = 'categoryid';
	const FIELD_CURRENCY_ID = 'currencyid';
	const FIELD_SERVICE_ID = 'serviceid';
	const FIELD_WORD_COUNT = 'wordcount';
	const FIELD_PAGE_COUNT = 'pagecount';
	const FIELD_ORDER_ID = 'oid';
	const FIELD_ORDER_STATUS = 'ostatus';
	const FIELD_ORDER_DESCRIPTION = 'odesc';
	const FIELD_ORDER_NOTES = 'onotes';
	const FIELD_ORDER_REQUESTED_EDITOR = 'oreqed';
	const FIELD_ORDER_ENGLISH_VERSION = 'oengv';
	const FIELD_ORDER_STYLE_GUIDE = 'ostyle';
	const FIELD_ORDER_JOURNAL_STYLE_GUIDE = 'ojstyle';
	const FIELD_ORDER_DOC_TYPE = 'odoctype';
	const FIELD_ORDER_FILE_UPLOAD = 'ofile';
	const FIELD_ORDER_FILE_ID = 'ofileid';
}
class Scribendi_Api_DateTime extends DateTime implements Serializable {
	private $_ToStringFormat;
	public function __construct($inTime = 'now', $inTimezone = null) {
		if ( !$inTimezone instanceof DateTimeZone && strlen($inTimezone) > 1 ) {
			$zones = DateTimeZone::listIdentifiers();
			foreach ( $zones as $timezone ) {
				if ( $inTimezone == $timezone ) {
					$inTimezone = new DateTimeZone($inTimezone);
					break;
				}
			}
		}
		if ( !$inTimezone instanceof DateTimeZone ) {
			$inTimezone = new DateTimeZone('UTC');
		}
		parent::__construct($inTime, $inTimezone);
		$this->setToStringFormat('Y-m-d H:i:s');
	}
	function __toString() {
		return $this->format($this->getToStringFormat());
	}
	function serialize() {
		return serialize(
			array(
				'date' => $this->format('Y-m-d H:i:s'),
				'tz' => $this->getTimezone()->getName()
			)
		);
	}
	function unserialize($serialized) {
		$data = unserialize($serialized);
		if ( is_array($data) && count($data) == 2 ) {
			$this->__construct($data['date'], $data['tz']);
		}
	}
	static function getInstance($inTime = 'now', $inTimezone = null) {
		$oObject = new Scribendi_Api_DateTime($inTime, $inTimezone);
		return $oObject;
	}
	static function getInstanceUtc($inTime = 'now') {
		$oTimezone = new DateTimeZone('UTC');
		$oObject = new Scribendi_Api_DateTime($inTime, $oTimezone);
		return $oObject;
	}
	static function getInstanceFromUnixEpoch($inTimestamp, $inTimezone = null) {
		$oObject = new Scribendi_Api_DateTime("@$inTimestamp", $inTimezone);
		return $oObject;
	}
	function setTimeZone($inTimezone) {
		if ( !$inTimezone instanceof DateTimeZone && strlen($inTimezone) > 1 ) {
			$zones = DateTimeZone::listIdentifiers();
			foreach ( $zones as $timezone ) {
				if ( $inTimezone == $timezone ) {
					$inTimezone = new DateTimeZone($inTimezone);
					break;
				}
			}
		}
		if ( !$inTimezone instanceof DateTimeZone ) {
			$inTimezone = new DateTimeZone('UTC');
		}
		parent::setTimezone($inTimezone);
		return $this;
	}
	function setDate($inYear = null, $inMonth = null, $inDay = null) {
		parent::setDate($inYear, $inMonth, $inDay);
		return $this;
	}
	function setTime($inHours = null, $inMinutes = null, $inSeconds = null) {
		parent::setTime($inHours, $inMinutes, $inSeconds);
		return $this;
	}
	function getToStringFormat() {
		return $this->_ToStringFormat;
	}
	function setToStringFormat($inToStringFormat) {
		if ( $inToStringFormat !== $this->_ToStringFormat ) {
			$this->_ToStringFormat = $inToStringFormat;
		}
		return $this;
	}
	function format($inFormat) {
		return parent::format($inFormat);
	}
	function cloneDateTime() {
		return clone $this;
	}
	function getDate() {
		return $this->format('Y-m-d');
	}
	function getTime() {
		return $this->format('H:i:s');
	}
	function getDay() {
		return $this->format('d');
	}
	function getDayAsString() {
		return $this->format('D');
	}
	function getMonth() {
		return $this->format('m');
	}
	function getMonthAsString() {
		return $this->format('M');
	}
	function getYear() {
		return $this->format('Y');
	}
	function getHour() {
		return $this->format('H');
	}
	function getMinute() {
		return $this->format('i');
	}
	function getSecond() {
		return $this->format('s');
	}
	function toUnix() {
		return $this->format('U');
	}
}
class Scribendi_Api_Model_Currency {
	protected $_Modified = false;
	protected $_CurrencyId;
	protected $_Description;
	protected $_IsoName;
	protected $_Symbol;
	protected $_Exponent;
	function __construct() {
		$this->reset();
	}
	function reset() {
		$this->_CurrencyId = null;
		$this->_Description = null;
		$this->_IsoName = null;
		$this->_Symbol = null;
		$this->_Exponent = 0;
		$this->setModified(false);
	}
	static function factory(SimpleXMLElement $inXML) {
		$oObject = new Scribendi_Api_Model_Currency();
		$oObject->setCurrencyId((integer) $inXML->currencyId);
		$oObject->setDescription((string) $inXML->description);
		$oObject->setExponent((integer) $inXML->exponent);
		$oObject->setIsoName((string) $inXML->isoName);
		$oObject->setSymbol((string) $inXML->symbol);
		return $oObject;
	}
	function isModified() {
		return $this->_Modified;
	}
	function setModified($status = true) {
		$this->_Modified = $status;
		return $this;
	}
	function getCurrencyId() {
		return $this->_CurrencyId;
	}
	function setCurrencyId($inCurrencyId) {
		if ( $inCurrencyId !== $this->_CurrencyId ) {
			$this->_CurrencyId = $inCurrencyId;
			$this->setModified();
		}
		return $this;
	}
	function getDescription() {
		return $this->_Description;
	}
	function setDescription($inDescription) {
		if ( $inDescription !== $this->_Description ) {
			$this->_Description = $inDescription;
			$this->setModified();
		}
		return $this;
	}
	function getIsoName() {
		return $this->_IsoName;
	}
	function setIsoName($inIsoName) {
		if ( $inIsoName !== $this->_IsoName ) {
			$this->_IsoName = $inIsoName;
			$this->setModified();
		}
		return $this;
	}
	function getSymbol() {
		return $this->_Symbol;
	}
	function setSymbol($inSymbol) {
		if ( $inSymbol !== $this->_Symbol ) {
			$this->_Symbol = $inSymbol;
			$this->setModified();
		}
		return $this;
	}
	function getExponent() {
		return $this->_Exponent;
	}
	function setExponent($inExponent) {
		if ( $inExponent !== $this->_Exponent ) {
			$this->_Exponent = $inExponent;
			$this->setModified();
		}
		return $this;
	}
	function formatPrice($inPrice) {
		return $this->getSymbol().number_format($inPrice, $this->getExponent());
	}
}
class Scribendi_Api_Model_File {
	protected $_Modified = false;
	protected $_FileId;
	protected $_FileName;
	protected $_UploadDate;
	protected $_FileType;
	const FILE_TYPE_PROCESSED = 'Processed';
	const FILE_TYPE_USER = 'User';
	protected $_FileSize;
	protected $_DownloadKey;
	protected $_DownloadLocation;
	protected $_TemporaryUploadLocation;
	function __construct() {
		$this->reset();
	}
	function reset() {
		$this->_FileId = null;
		$this->_FileName = null;
		$this->_FileSize = 0;
		$this->_UploadDate = null;
		$this->_FileType = null;
		$this->_DownloadKey = null;
		$this->_DownloadLocation = null;
		$this->_TemporaryUploadLocation = null;
		$this->setModified(false);
	}
	static function factory(SimpleXMLElement $inXML) {
		$oObject = new Scribendi_Api_Model_File();
		$oObject->setFileId((integer) $inXML->fileId);
		$oObject->setFileName((string) $inXML->fileName);
		$oObject->setFileSize((integer) $inXML->fileSize);
		$oObject->setFileType((string) $inXML->type);
		if ( is_numeric($inXML->uploadDate) && strpos($inXML->uploadDate, '-') === false ) {
			$oObject->setUploadDate(Scribendi_Api_DateTime::getInstanceFromUnixEpoch((string) $inXML->uploadDate, 'UTC'));
		} else {
			$oObject->setUploadDate(Scribendi_Api_DateTime::getInstanceUtc((string) $inXML->uploadDate));
		}
		$oObject->setDownloadKey((string) $inXML->downloadKey);
		$oObject->setDownloadLocation((string) $inXML->downloadLocation);
		return $oObject;
	}
	function isModified() {
		return $this->_Modified;
	}
	function setModified($status = true) {
		$this->_Modified = $status;
		return $this;
	}
	function getFileId() {
		return $this->_FileId;
	}
	function setFileId($inFileId) {
		if ( $inFileId !== $this->_FileId ) {
			$this->_FileId = $inFileId;
			$this->setModified();
		}
		return $this;
	}
	function getFileName() {
		return $this->_FileName;
	}
	function setFileName($inFileName) {
		if ( $inFileName !== $this->_FileName ) {
			$this->_FileName = $inFileName;
			$this->setModified();
		}
		return $this;
	}
	function getUploadDate() {
		return $this->_UploadDate;
	}
	function setUploadDate(Scribendi_Api_DateTime $inUploadDate) {
		if ( $inUploadDate !== $this->_UploadDate ) {
			$this->_UploadDate = $inUploadDate;
			$this->setModified();
		}
		return $this;
	}
	function getFileType() {
		return $this->_FileType;
	}
	function setFileType($inFileType) {
		if ( $inFileType !== $this->_FileType ) {
			$this->_FileType = $inFileType;
			$this->setModified();
		}
		return $this;
	}
	function getFileSize() {
		return $this->_FileSize;
	}
	function setFileSize($inFileSize) {
		if ( $inFileSize !== $this->_FileSize ) {
			$this->_FileSize = $inFileSize;
			$this->setModified();
		}
		return $this;
	}
	function getDownloadKey() {
		return $this->_DownloadKey;
	}
	function setDownloadKey($inDownloadKey) {
		if ( $inDownloadKey !== $this->_DownloadKey ) {
			$this->_DownloadKey = $inDownloadKey;
			$this->setModified();
		}
		return $this;
	}
	function getDownloadLocation() {
		return $this->_DownloadLocation;
	}
	function setDownloadLocation($inDownloadLocation) {
		if ( $inDownloadLocation !== $this->_DownloadLocation ) {
			$this->_DownloadLocation = $inDownloadLocation;
			$this->setModified();
		}
		return $this;
	}
	function getTemporaryUploadLocation() {
		return $this->_TemporaryUploadLocation;
	}
	function setTemporaryUploadLocation($inTemporaryUploadLocation) {
		if ( $inTemporaryUploadLocation !== $this->_TemporaryUploadLocation ) {
			$this->_TemporaryUploadLocation = $inTemporaryUploadLocation;
			$this->setModified();
		}
		return $this;
	}
}
class Scribendi_Api_Model_FileSet implements IteratorAggregate, Countable {
	protected $_Modified = false;
	protected $_Set;
	function __construct() {
		$this->reset();
	}
	function reset() {
		$this->_Set = array();
		$this->setModified(false);
	}
	function isModified() {
		return $this->_Modified;
	}
	function setModified($status = true) {
		$this->_Modified = $status;
		return $this;
	}
	function addFile(Scribendi_Api_Model_File $inFile) {
		if ( !in_array($inFile, $this->_Set) ) {
			$this->_Set[] = $inFile;
			$this->setModified();
		}
		return $this;
	}
	function removeFile(Scribendi_Api_Model_File $inFile) {
		$key = array_search($inFile, $this->_Set);
		if ( $key !== false ) {
			$this->_Set[$key] = null;
			unset($this->_Set[$key]);
			$this->setModified();
		}
		return $this;
	}
	function getFileByName($inFileName) {
		if ( $this->count() > 0 ) {
			foreach ( $this as $oFile ) {
				if ( $oFile->getFileName() == $inFileName ) {
					return $oFile;
				}
			}
		}
		return null;
	}
	function getFileByType($inType) {
		$return = array();
		if ( $this->count() > 0 ) {
			foreach ( $this as $oFile ) {
				if ( $oFile->getFileType() == $inType ) {
					$return[] = $oFile;
				}
			}
		}
		return $return;
	}
	function getFileById($inFileId) {
		if ( $this->count() > 0 ) {
			foreach ( $this as $oFile ) {
				if ( $oFile->getFileId() == $inFileId ) {
					return $oFile;
				}
			}
		}
		return null;
	}
	function getFileByKey($inKey) {
		if ( $this->count() > 0 ) {
			if ( array_key_exists($inKey, $this->_Set) ) {
				return $this->_Set[$inKey];
			}
		}
		return null;
	}
	function getSet() {
		return $this->_Set;
	}
	function setSet($inSet) {
		if ( $inSet !== $this->_Set ) {
			$this->_Set = $inSet;
			$this->setModified();
		}
		return $this;
	}
	function count() {
		return count($this->_Set);
	}
	function getIterator() {
		return new ArrayIterator($this->_Set);
	}
}
class Scribendi_Api_Model_Quote {
	protected $_Modified = false;
	protected $_QuoteTime;
	protected $_ServiceTitle;
	protected $_ServiceTime;
	protected $_ReadyBy;
	protected $_ClientId;
	protected $_ServiceId;
	protected $_WordCount;
	protected $_PageCount;
	protected $_CurrencyId;
	protected $_LocalPriceExTax;
	protected $_LocalPriceIncTax;
	function __construct() {
		$this->reset();
	}
	function reset() {
		$this->_QuoteTime = null;
		$this->_ServiceTitle = '';
		$this->_ServiceTime = '';
		$this->_ReadyBy = null;
		$this->_ClientId = null;
		$this->_ServiceId = null;
		$this->_WordCount = 0;
		$this->_PageCount = 0;
		$this->_CurrencyId = null;
		$this->_LocalPriceExTax = 0.00;
		$this->_LocalPriceIncTax = 0.00;
		$this->setModified(false);
	}
	static function factory(SimpleXMLElement $inXML) {
		$oObject = new Scribendi_Api_Model_Quote();
		$oObject->setClientId((integer) $inXML->clientId);
		$oObject->setCurrencyId((integer) $inXML->currencyId);
		$oObject->setLocalPriceExTax((string) $inXML->localPriceExTax);
		$oObject->setLocalPriceIncTax((string) $inXML->localPriceIncTax);
		$oObject->setPageCount((integer) $inXML->pageCount);
		$oObject->setQuoteTime(Scribendi_Api_DateTime::getInstanceUtc((string) $inXML->quoteTime));
		$oObject->setReadyBy(Scribendi_Api_DateTime::getInstanceUtc((string) $inXML->readyBy));
		$oObject->setServiceId((integer) $inXML->serviceId);
		$oObject->setServiceTime((string) $inXML->serviceTime);
		$oObject->setServiceTitle((string) $inXML->serviceTitle);
		$oObject->setWordCount((integer) $inXML->wordCount);
		return $oObject;
	}
	function isModified() {
		return $this->_Modified;
	}
	function setModified($status = true) {
		$this->_Modified = $status;
		return $this;
	}
	function getQuoteTime() {
		return $this->_QuoteTime;
	}
	function setQuoteTime(Scribendi_Api_DateTime $inQuoteTime) {
		if ( $inQuoteTime !== $this->_QuoteTime ) {
			$this->_QuoteTime = $inQuoteTime;
			$this->setModified();
		}
		return $this;
	}
	function getServiceTitle() {
		return $this->_ServiceTitle;
	}
	function setServiceTitle($inServiceTitle) {
		if ( $inServiceTitle !== $this->_ServiceTitle ) {
			$this->_ServiceTitle = $inServiceTitle;
			$this->setModified();
		}
		return $this;
	}
	function getServiceTime() {
		return $this->_ServiceTime;
	}
	function setServiceTime($inServiceTime) {
		if ( $inServiceTime !== $this->_ServiceTime ) {
			$this->_ServiceTime = $inServiceTime;
			$this->setModified();
		}
		return $this;
	}
	function getReadyBy() {
		return $this->_ReadyBy;
	}
	function setReadyBy($inReadyBy) {
		if ( $inReadyBy !== $this->_ReadyBy ) {
			$this->_ReadyBy = $inReadyBy;
			$this->setModified();
		}
		return $this;
	}
	function getClientId() {
		return $this->_ClientId;
	}
	function setClientId($inClientId) {
		if ( $inClientId !== $this->_ClientId ) {
			$this->_ClientId = $inClientId;
			$this->setModified();
		}
		return $this;
	}
	function getServiceId() {
		return $this->_ServiceId;
	}
	function setServiceId($inServiceId) {
		if ( $inServiceId !== $this->_ServiceId ) {
			$this->_ServiceId = $inServiceId;
			$this->setModified();
		}
		return $this;
	}
	function getWordCount() {
		return $this->_WordCount;
	}
	function setWordCount($inWordCount) {
		if ( $inWordCount !== $this->_WordCount ) {
			$this->_WordCount = $inWordCount;
			$this->setModified();
		}
		return $this;
	}
	function getPageCount() {
		return $this->_PageCount;
	}
	function setPageCount($inPageCount) {
		if ( $inPageCount !== $this->_PageCount ) {
			$this->_PageCount = $inPageCount;
			$this->setModified();
		}
		return $this;
	}
	function getCurrencyId() {
		return $this->_CurrencyId;
	}
	function setCurrencyId($inCurrencyId) {
		if ( $inCurrencyId !== $this->_CurrencyId ) {
			$this->_CurrencyId = $inCurrencyId;
			$this->setModified();
		}
		return $this;
	}
	function getLocalPriceExTax() {
		return $this->_LocalPriceExTax;
	}
	function setLocalPriceExTax($inLocalPriceExTax) {
		if ( $inLocalPriceExTax !== $this->_LocalPriceExTax ) {
			$this->_LocalPriceExTax = $inLocalPriceExTax;
			$this->setModified();
		}
		return $this;
	}
	function getLocalPriceIncTax() {
		return $this->_LocalPriceIncTax;
	}
	function setLocalPriceIncTax($inLocalPriceIncTax) {
		if ( $inLocalPriceIncTax !== $this->_LocalPriceIncTax ) {
			$this->_LocalPriceIncTax = $inLocalPriceIncTax;
			$this->setModified();
		}
		return $this;
	}
}
class Scribendi_Api_Model_Order extends Scribendi_Api_Model_Quote {
	const STATUS_CANCELLED = -2;
	const STATUS_SUSPENDED = -1;
	const STATUS_QUOTE = 0;
	const STATUS_IN_PROGRESS = 2;
	const STATUS_DONE = 4;
	const STATUS_RETURNED = 5;
	protected $_OrderId;
	protected $_OrderDate;
	protected $_Description;
	protected $_Status;
	protected $_StatusText;
	protected $_Notes;
	protected $_RequestedEditor;
	protected $_ActualEditor;
	protected $_PropertySet;
	protected $_FileSet;
	static function factory(SimpleXMLElement $inXML) {
		$oObject = new Scribendi_Api_Model_Order();
		$oObject->setActualEditor((string) $inXML->actualEditor);
		$oObject->setClientId((integer) $inXML->clientId);
		$oObject->setCurrencyId((integer) $inXML->currencyId);
		$oObject->setDescription((string) $inXML->description);
		$oObject->setLocalPriceExTax((string) $inXML->localPriceExTax);
		$oObject->setLocalPriceIncTax((string) $inXML->localPriceIncTax);
		$oObject->setNotes(htmlspecialchars_decode((string) $inXML->notes, ENT_QUOTES));
		$oObject->setOrderDate(Scribendi_Api_DateTime::getInstanceUtc((string) $inXML->orderDate));
		$oObject->setOrderId((integer) $inXML->orderId);
		$oObject->setPageCount((integer) $inXML->pageCount);
		$oObject->setReadyBy(Scribendi_Api_DateTime::getInstanceUtc((string) $inXML->readyBy));
		$oObject->setRequestedEditor((string) $inXML->requestedEditor);
		$oObject->setServiceId((integer) $inXML->serviceId);
		$oObject->setServiceTime((string) $inXML->serviceTime);
		$oObject->setServiceTitle((string) $inXML->serviceTitle);
		$oObject->setStatus((integer) $inXML->statusNum);
		$oObject->setStatusText((string) $inXML->status);
		$oObject->setWordCount((integer) $inXML->wordCount);
		return $oObject;
	}
	function reset() {
		$this->_OrderId = null;
		$this->_OrderDate = null;
		$this->_Description = null;
		$this->_Status = null;
		$this->_StatusText = null;
		$this->_Notes = null;
		$this->_RequestedEditor = null;
		$this->_ActualEditor = null;
		$this->_PropertySet = null;
		$this->_FileSet = null;
		parent::reset();
	}
	function getOrderId() {
		return $this->_OrderId;
	}
	function setOrderId($inOrderId) {
		if ( $inOrderId !== $this->_OrderId ) {
			$this->_OrderId = $inOrderId;
			$this->setModified();
		}
		return $this;
	}
	function getOrderDate() {
 		return $this->_OrderDate;
 	}
   	function setOrderDate($inOrderDate) {
 		if ( $inOrderDate !== $this->_OrderDate ) {
 			$this->_OrderDate = $inOrderDate;
 			$this->setModified();
 		}
 		return $this;
 	}
 	function getDescription() {
		return $this->_Description;
	}
	function setDescription($inDescription) {
		if ( $inDescription !== $this->_Description ) {
			$this->_Description = $inDescription;
			$this->setModified();
		}
		return $this;
	}
	function getStatus() {
		return $this->_Status;
	}
	function setStatus($inStatus) {
		if ( $inStatus !== $this->_Status ) {
			$this->_Status = $inStatus;
			$this->setModified();
		}
		return $this;
	}
	function getStatusText() {
		return $this->_StatusText;
	}
	function setStatusText($inStatusText) {
		if ( $inStatusText !== $this->_StatusText ) {
			$this->_StatusText = $inStatusText;
			$this->setModified();
		}
		return $this;
	}
	function getNotes() {
		return $this->_Notes;
	}
	function setNotes($inNotes) {
		if ( $inNotes !== $this->_Notes ) {
			$this->_Notes = $inNotes;
			$this->setModified();
		}
		return $this;
	}
	function getRequestedEditor() {
		return $this->_RequestedEditor;
	}
	function setRequestedEditor($inRequestedEditor) {
		if ( $inRequestedEditor !== $this->_RequestedEditor ) {
			$this->_RequestedEditor = $inRequestedEditor;
			$this->setModified();
		}
		return $this;
	}
	function getActualEditor() {
		return $this->_ActualEditor;
	}
	function setActualEditor($inActualEditor) {
		if ( $inActualEditor !== $this->_ActualEditor ) {
			$this->_ActualEditor = $inActualEditor;
			$this->setModified();
		}
		return $this;
	}
	function getPropertySet() {
		if ( !$this->_PropertySet instanceof Scribendi_Api_Model_OrderPropertySet ) {
			$this->_PropertySet = new Scribendi_Api_Model_OrderPropertySet();
		}
		return $this->_PropertySet;
	}
	function setPropertySet(Scribendi_Api_Model_OrderPropertySet $inPropertySet) {
		if ( $inPropertySet !== $this->_PropertySet ) {
			$this->_PropertySet = $inPropertySet;
			$this->setModified();
		}
		return $this;
	}
	function getFileSet() {
		if ( !$this->_FileSet instanceof Scribendi_Api_Model_FileSet ) {
			$this->_FileSet = new Scribendi_Api_Model_FileSet();
		}
		return $this->_FileSet;
	}
	function setFileSet(Scribendi_Api_Model_FileSet $inFileSet) {
		if ( $inFileSet !== $this->_FileSet ) {
			$this->_FileSet = $inFileSet;
			$this->setModified();
		}
		return $this;
	}
}
class Scribendi_Api_Model_OrderPropertySet extends Scribendi_Api_Options {
	static function translatePropName($inPropName) {
		$mappings = array(
			'document_english_version' => Scribendi_Api_Constants::FIELD_ORDER_ENGLISH_VERSION,
			'document_journal_style_guide' => Scribendi_Api_Constants::FIELD_ORDER_JOURNAL_STYLE_GUIDE,
			'document_science_arts' => Scribendi_Api_Constants::FIELD_ORDER_DOC_TYPE,
			'document_style_guide' => Scribendi_Api_Constants::FIELD_ORDER_STYLE_GUIDE,
		);
		if ( array_key_exists($inPropName, $mappings) ) {
			return $mappings[$inPropName];
		} else {
			return $inPropName;
		}
	}
	function getProperty($inPropName) {
		return $this->getOptions($inPropName);
	}
	function setProperty($inPropName, $inValue) {
		return $this->setOptions(array($inPropName => $inValue));
	}
	function getEnglishVersion() {
		return $this->getProperty(Scribendi_Api_Constants::FIELD_ORDER_ENGLISH_VERSION);
	}
	function getDocumentType() {
		return $this->getProperty(Scribendi_Api_Constants::FIELD_ORDER_DOC_TYPE);
	}
	function getJournalStyleGuide() {
		return $this->getProperty(Scribendi_Api_Constants::FIELD_ORDER_JOURNAL_STYLE_GUIDE);
	}
	function getStyleGuide() {
		return $this->getProperty(Scribendi_Api_Constants::FIELD_ORDER_STYLE_GUIDE);
	}
}
class Scribendi_Api_Model_SearchResult {
	protected $_Modified = false;
	protected $_CategoryId;
	protected $_Description;
	protected $_Summary;
	protected $_Tag;
	protected $_Private;
	protected $_FlatRate;
	protected $_PageBased;
	protected $_ScribendiUri;
	function __construct() {
		$this->reset();
	}
	function reset() {
		$this->_CategoryId = null;
		$this->_Description = null;
		$this->_Summary = null;
		$this->_Tag = null;
		$this->_Private = false;
		$this->_FlatRate = false;
		$this->_PageBased = false;
		$this->_ScribendiUri = null;
		$this->setModified(false);
	}
	static function factory(SimpleXMLElement $inXML) {
		$oObject = new self();
		$oObject->setCategoryId((int)$inXML->categoryId);
		$oObject->setDescription((string)$inXML->description);
		$oObject->setFlatRate(((int)$inXML->isFlatRate == 1 ? true : false));
		$oObject->setPageBased(((int)$inXML->isPageBased == 1 ? true : false));
		$oObject->setPrivate(((int)$inXML->isPrivate == 1 ? true : false));
		$oObject->setScribendiUri((string)$inXML->scribendiUri);
		$oObject->setSummary((string)$inXML->summary);
		$oObject->setTag((string)$inXML->tag);
		return $oObject;
	}
	function isModified() {
		return $this->_Modified;
	}
	function setModified($status = true) {
		$this->_Modified = $status;
		return $this;
	}
	function getCategoryId() {
		return $this->_CategoryId;
	}
	function setCategoryId($inCategoryId) {
		if ( $inCategoryId !== $this->_CategoryId ) {
			$this->_CategoryId = $inCategoryId;
			$this->setModified();
		}
		return $this;
	}
	function getDescription() {
		return $this->_Description;
	}
	function setDescription($inDescription) {
		if ( $inDescription !== $this->_Description ) {
			$this->_Description = $inDescription;
			$this->setModified();
		}
		return $this;
	}
	function getSummary() {
		return $this->_Summary;
	}
	function setSummary($inSummary) {
		if ( $inSummary !== $this->_Summary ) {
			$this->_Summary = $inSummary;
			$this->setModified();
		}
		return $this;
	}
	function getTag() {
		return $this->_Tag;
	}
	function setTag($inTag) {
		if ( $inTag !== $this->_Tag ) {
			$this->_Tag = $inTag;
			$this->setModified();
		}
		return $this;
	}
	function isPrivate() {
		return $this->_Private;
	}
	function setPrivate($inPrivate) {
		if ( $inPrivate !== $this->_Private ) {
			$this->_Private = $inPrivate;
			$this->setModified();
		}
		return $this;
	}
	function isFlatRate() {
		return $this->_FlatRate;
	}
	function setFlatRate($inFlatRate) {
		if ( $inFlatRate !== $this->_FlatRate ) {
			$this->_FlatRate = $inFlatRate;
			$this->setModified();
		}
		return $this;
	}
	function isPageBased() {
		return $this->_PageBased;
	}
	function setPageBased($inPageBased) {
		if ( $inPageBased !== $this->_PageBased ) {
			$this->_PageBased = $inPageBased;
			$this->setModified();
		}
		return $this;
	}
	function getScribendiUri() {
		return $this->_ScribendiUri;
	}
	function setScribendiUri($inScribendiUri) {
		if ( $inScribendiUri !== $this->_ScribendiUri ) {
			$this->_ScribendiUri = $inScribendiUri;
			$this->setModified();
		}
		return $this;
	}
}
class Scribendi_Api_Helper_OrderToUpdateOrderQuery {
	static function orderToUpdateOrderQuery(Scribendi_Api_Model_Order $inOrder, Scribendi_Api_Query $inQuery) {
		$inQuery->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_ORDER_ID => $inOrder->getOrderId(),
				Scribendi_Api_Constants::FIELD_CURRENCY_ID => $inOrder->getCurrencyId(),
				Scribendi_Api_Constants::FIELD_ORDER_DESCRIPTION => $inOrder->getDescription(),
				Scribendi_Api_Constants::FIELD_ORDER_NOTES => $inOrder->getNotes(),
				Scribendi_Api_Constants::FIELD_ORDER_REQUESTED_EDITOR => $inOrder->getRequestedEditor(),
				Scribendi_Api_Constants::FIELD_PAGE_COUNT => $inOrder->getPageCount(),
				Scribendi_Api_Constants::FIELD_REQUEST_CUSTOMER => $inOrder->getClientId(),
				Scribendi_Api_Constants::FIELD_SERVICE_ID => $inOrder->getServiceId(),
				Scribendi_Api_Constants::FIELD_WORD_COUNT => $inOrder->getWordCount(),
				Scribendi_Api_Constants::FIELD_ORDER_DOC_TYPE => $inOrder->getPropertySet()->getDocumentType(),
				Scribendi_Api_Constants::FIELD_ORDER_ENGLISH_VERSION => $inOrder->getPropertySet()->getEnglishVersion(),
				Scribendi_Api_Constants::FIELD_ORDER_JOURNAL_STYLE_GUIDE => $inOrder->getPropertySet()->getJournalStyleGuide(),
				Scribendi_Api_Constants::FIELD_ORDER_STYLE_GUIDE => $inOrder->getPropertySet()->getStyleGuide(),
			)
		);
	}
}
class Scribendi_Api_Helper_QuoteToCreateOrderQuery {
	static function quoteToCreateOrderQuery(Scribendi_Api_Model_Quote $inQuote, Scribendi_Api_Query $inQuery) {
		$description = 'Order for '.$inQuote->getServiceTitle().' in '.$inQuote->getServiceTime();
			$inQuery->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_CURRENCY_ID => $inQuote->getCurrencyId(),
				Scribendi_Api_Constants::FIELD_ORDER_DESCRIPTION => $description,
				Scribendi_Api_Constants::FIELD_PAGE_COUNT => $inQuote->getPageCount(),
				Scribendi_Api_Constants::FIELD_REQUEST_CUSTOMER => $inQuote->getClientId(),
				Scribendi_Api_Constants::FIELD_SERVICE_ID => $inQuote->getServiceId(),
				Scribendi_Api_Constants::FIELD_WORD_COUNT => $inQuote->getWordCount(),
			)
		);
	}
}
interface Scribendi_Api_Client_Adaptor_ITransport {
	function call();
	function getApiQuery();
	function setApiQuery(Scribendi_Api_Query $inQuery);
	function getRequestServer();
	function setRequestServer($inRequestServer);
	function getRequestUri();
	function setRequestUri($inRequestUri);
	function getTransportOptions();
	function setTransportOptions(Scribendi_Api_Options $inOptions);
}
abstract class Scribendi_Api_Client_Adaptor_Abstract {
	protected $_Modified = false;
	protected $_AdaptorName = 'Abstract';
	protected $_ResponseHandler = 'Scribendi_Api_Client_Response';
	protected $_ApiClient;
	protected $_ApiAuth = null;
	protected $_ApiQuery = null;
	protected $_RequiredParameters = array(
		Scribendi_Api_Constants::FIELD_PUBLIC_KEY,
		Scribendi_Api_Constants::FIELD_REQUEST_DATE,
		Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE,
	);
	protected $_RequestServer;
	protected $_RequestUri;
	protected $_Options;
	protected $_Transport = null;
	function __construct(Scribendi_Api_Client $inClient, Scribendi_Api_Auth $inAuth, Scribendi_Api_Query $inQuery, array $inOptions = array()) {
		$this->setApiClient($inClient);
		$this->setApiAuth($inAuth);
		$this->setApiQuery($inQuery);
		$this->getTransportOptions()->setOptions($inOptions);
		$this->initialise();
	}
	abstract protected function initialise();
	abstract protected function _isValid();
	function go() {
		if ( $this->isValid() ) {
			return $this->_call();
		}
	}
	function isValid() {
		$valid = true;
		$target = count($this->getRequiredParameters());
		$matched = array();
		foreach ( $this->getRequiredParameters() as $parameter ) {
			if ( $this->getApiQuery()->getOptions($parameter) ) {
				$matched[] = $parameter;
			}
		}
			if ( $target !== count($matched) ) {
			throw new Scribendi_Api_Client_Exception(
				'Missing required query parameter(s): '.implode(', ', array_diff($this->getRequiredParameters(), $matched))
			);
		}
		return $this->_isValid();
	}
	private function _call() {
		$oTransport = $this->getTransport();
				$oTransport->setApiQuery($this->getApiQuery());
		$oTransport->setRequestServer($this->getRequestServer());
		$oTransport->setRequestUri($this->getRequestUri());
		$oTransport->setTransportOptions($this->getTransportOptions());
			$response = $oTransport->call();
			$oResponse = new $this->_ResponseHandler($response);
		$oResponse->handleResponse();
		return $oResponse;
	}
	function getRequestSignature($inType = Scribendi_Api_Constants::API_REQUEST_GET) {
		return $this->getApiAuth()->createSignature(
			$inType, $this->getApiQuery()->getQueryDate(), $this->getRequestUri() 
		);
	}
	function isModified() {
		return $this->_Modified;
	}
	function setModified($status = true) {
		$this->_Modified = $status;
		return $this;
	}
	function getName() {
		return $this->_AdaptorName;
	}
	function setName($inName) {
		$this->_AdaptorName = $inName;
		return $this;
	}
	function getResponseHandler() {
		return $this->_ResponseHandler;
	}
	function setResponseHandler($inResponseHandler) {
		if ( $inResponseHandler !== $this->_ResponseHandler ) {
			$this->_ResponseHandler = $inResponseHandler;
			$this->setModified();
		}
		return $this;
	}
	function getApiClient() {
		return $this->_ApiClient;
	}
	function setApiClient($inApiClient) {
		if ( $inApiClient !== $this->_ApiClient ) {
			$this->_ApiClient = $inApiClient;
			$this->setModified();
		}
		return $this;
	}
	function getApiAuth() {
		return $this->_ApiAuth;
	}
	function setApiAuth($inApiAuth) {
		if ( $inApiAuth !== $this->_ApiAuth ) {
			$this->_ApiAuth = $inApiAuth;
			$this->setModified();
		}
		return $this;
	}
	function getApiQuery() {
		return $this->_ApiQuery;
	}
	function setApiQuery($inApiQuery) {
		if ( $inApiQuery !== $this->_ApiQuery ) {
			$this->_ApiQuery = $inApiQuery;
			$this->setModified();
		}
		return $this;
	}
	function getRequiredParameters() {
		return $this->_RequiredParameters;
	}
	function addRequiredParameters($inParameter) {
		if ( !in_array($inParameter, $this->_RequiredParameters) ) {
			$this->_RequiredParameters[] = $inParameter;
			$this->setModified();
		}
		return $this;
	}
	function setRequiredParameters($inRequiredParameters) {
		if ( $inRequiredParameters !== $this->_RequiredParameters ) {
			$this->_RequiredParameters = $inRequiredParameters;
			$this->setModified();
		}
		return $this;
	}
	function getRequestServer() {
		return $this->_RequestServer;
	}
	function setRequestServer($inRequestServer) {
		if ( $inRequestServer !== $this->_RequestServer ) {
			$this->_RequestServer = $inRequestServer;
			$this->setModified();
		}
		return $this;
	}
	function getRequestUri() {
		return $this->_RequestUri;
	}
	function setRequestUri($inRequestUri) {
		if ( $inRequestUri !== $this->_RequestUri ) {
			$this->_RequestUri = $inRequestUri;
			$this->setModified();
		}
		return $this;
	}
	function getTransportOptions() {
		if ( !$this->_Options instanceof Scribendi_Api_Options ) {
			$this->_Options = new Scribendi_Api_Options();
		}
		return $this->_Options;
	}
	function setTransportOptions(Scribendi_Api_Options $inOptions) {
		if ( $inOptions !== $this->_Options ) {
			$this->_Options = $inOptions;
		}
		return $this;
	}
	function getTransport() {
		if ( !$this->_Transport instanceof Scribendi_Api_Client_Adaptor_ITransport ) {
			$this->_Transport = new Scribendi_Api_Client_Adaptor_Transport_Curl(
				$this->getApiQuery(), $this->getRequestServer(), $this->getRequestUri(), $this->getTransportOptions()->toArray()
			);
		}
		return $this->_Transport;
	}
	function setTransport(Scribendi_Api_Client_Adaptor_ITransport $inTransport) {
		if ( $inTransport !== $this->_Transport ) {
			$this->_Transport = $inTransport;
					$this->setTransportOptions($inTransport->getTransportOptions());
			$this->setModified();
		}
		return $this;
	}
}
class Scribendi_Api_Client_Adaptor_Currency extends Scribendi_Api_Client_Adaptor_Abstract {	
	protected function initialise() {
		$this->setName('currency');
		$this->setResponseHandler('Scribendi_Api_Client_Response_Currency');
		$this->setRequestUri(Scribendi_Api_Constants::API_ROOT_PATH.'currencies');
			$this->getTransportOptions()->setOptions(
			array(
				CURLOPT_HTTPGET => true,
			)
		);
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('GET')
			)
		);
	}
	protected function _isValid() {
		return true;
	}
	function getCurrencies() {
		return $this->getApiClient();
	}
}
class Scribendi_Api_Client_Adaptor_Help extends Scribendi_Api_Client_Adaptor_Abstract {
	protected function initialise() {
		$this->setName('help');
		$this->setResponseHandler('Scribendi_Api_Client_Response');
		$this->setRequestUri(Scribendi_Api_Constants::API_ROOT_PATH.'help');
			$this->getTransportOptions()->setOptions(
			array(
				CURLOPT_HTTPGET => true,
			)
		);
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('GET')
			)
		);
	}
	protected function _isValid() {
		return true;
	}
	function getHelp() {
		return $this->getApiClient();
	}
}
class Scribendi_Api_Client_Adaptor_Order extends Scribendi_Api_Client_Adaptor_Abstract {
	protected $_ValidationType = null;
	const VALIDATE_LIST = 'list';
	const VALIDATE_CANCEL = 'cancel';
	const VALIDATE_COMMIT = 'commit';
	const VALIDATE_UPDATE = 'update';
	const VALIDATE_CREATE = 'create';
	const VALIDATE_ADDFILE = 'addfile';
	const VALIDATE_REMOVEFILE = 'removefile';
	const VALIDATE_STATUS = 'status';
	protected function initialise() {
		$this->setName('order');
		$this->setResponseHandler('Scribendi_Api_Client_Response_Order');
		$this->setRequestUri(Scribendi_Api_Constants::API_ROOT_PATH.'order');
	}
	protected function _isValid() {
		switch ( $this->getValidationType() ) {
			case self::VALIDATE_ADDFILE:
			case self::VALIDATE_CANCEL:
			case self::VALIDATE_COMMIT:
			case self::VALIDATE_REMOVEFILE:
			case self::VALIDATE_STATUS:
			case self::VALIDATE_UPDATE:
				$regEx = preg_quote(Scribendi_Api_Constants::API_ROOT_PATH, '/').'order\/([0-9]{1,})\/'.$this->getValidationType();
				if ( !preg_match('/^'.$regEx.'$/', $this->getRequestUri()) ) {
					throw new Scribendi_Api_Client_Exception(
						'Order '.$this->getValidationType().' requires an order id be set in the API query'
					);
				}
			break;
			case self::VALIDATE_CREATE:
				if (
					!$this->getApiQuery()->getOptions(Scribendi_Api_Constants::FIELD_PAGE_COUNT, false) &&
					!$this->getApiQuery()->getOptions(Scribendi_Api_Constants::FIELD_WORD_COUNT, false)
				) {
					throw new Scribendi_Api_Client_Exception(
						'Orders require either '.Scribendi_Api_Constants::FIELD_PAGE_COUNT.
						' or '.Scribendi_Api_Constants::FIELD_WORD_COUNT
					);
				}
			break;
		}
		return true;
	}
	private function setPostParameters() {
		$this->getTransportOptions()->setOptions(
			array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $this->getApiQuery()->getQuery(),
			)
		);
	}
	function listOrders($inClientID = null) {
		if ( $inClientID === null ) {
			$inClientID = $this->getApiQuery()->getOptions(Scribendi_Api_Constants::FIELD_REQUEST_CUSTOMER);
		}
		$this->setValidationType(self::VALIDATE_LIST);
		$this->addRequiredParameters(Scribendi_Api_Constants::FIELD_REQUEST_CUSTOMER);
		$this->setRequestUri(Scribendi_Api_Constants::API_ROOT_PATH.'order/list');
		$this->getTransportOptions()->setOptions(
			array(
				CURLOPT_HTTPGET => true,
			)
		);
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('GET'),
				Scribendi_Api_Constants::FIELD_REQUEST_CUSTOMER => $inClientID,
			)
		);
		return $this->getApiClient();
	}
	function getOrderStatus($inOrderID = null) {
		if ($inOrderID !== null) {
			$this->getApiQuery()->setOptions(array(Scribendi_Api_Constants::FIELD_ORDER_ID => $inOrderID));
		}
			$this->setValidationType(self::VALIDATE_STATUS);
		$this->setResponseHandler('Scribendi_Api_Client_Response_Order');
		$this->_setRequestUri('status');
			$this->getApiQuery()->clearQueryParam(Scribendi_Api_Constants::FIELD_ORDER_ID);
		$this->getTransportOptions()->setOptions(
			array(
				CURLOPT_HTTPGET => true,
			)
		);
			$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('GET'),
			)
		);
		return $this->getApiClient();
	}
	function createOrder($inOptions = null) {
		$this->setValidationType(self::VALIDATE_CREATE);
		$this->setRequestUri(Scribendi_Api_Constants::API_ROOT_PATH.'order');
		$this
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_REQUEST_CUSTOMER)
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_CURRENCY_ID)
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_ORDER_DESCRIPTION)
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_SERVICE_ID);
		if ( $inOptions instanceof Scribendi_Api_Model_Quote ) {
			Scribendi_Api_Helper_QuoteToCreateOrderQuery::quoteToCreateOrderQuery($inOptions, $this->getApiQuery());
		} elseif ( is_array($inOptions) && count($inOptions) > 0 ) {
			$this->getApiQuery()->setOptions($inOptions);
		}
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('POST')
			)
		);
		$this->setPostParameters();
		return $this->getApiClient();
	}
	function updateOrder($inOptions = null) {
		if ( $inOptions instanceof Scribendi_Api_Model_Order ) {
			Scribendi_Api_Helper_OrderToUpdateOrderQuery::orderToUpdateOrderQuery($inOptions, $this->getApiQuery());
		} elseif ( is_array($inOptions) && count($inOptions) > 0 ) {
			$this->getApiQuery()->setOptions($inOptions);
		}
		$this->setValidationType(self::VALIDATE_UPDATE);
		$this->_setRequestUri('update');
		$this
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_REQUEST_CUSTOMER)
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_ORDER_ID)
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_CURRENCY_ID)
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_ORDER_DESCRIPTION)
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_SERVICE_ID);
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('POST')
			)
		);
		$this->setPostParameters();
		return $this->getApiClient();
	}
	function addFileToOrder($inOrderID = null, $inFile = null) {
		if ( $inOrderID !== null && $inFile !== null ) {
			if ( !is_resource($inFile) && strpos($inFile, '@') !== 0 ) {
				$inFile = '@'.$inFile;
			}
			$this->getApiQuery()->setOptions(
				array(
					Scribendi_Api_Constants::FIELD_ORDER_ID => $inOrderID,
					Scribendi_Api_Constants::FIELD_ORDER_FILE_UPLOAD => $inFile,
				)
			);
		}
		$this->setValidationType(self::VALIDATE_ADDFILE);
		$this->_setRequestUri('addfile');
		$this
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_ORDER_ID)
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_ORDER_FILE_UPLOAD);
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('POST')
			)
		);
		if ( !is_resource($this->getApiQuery()->getQueryParam(Scribendi_Api_Constants::FIELD_ORDER_FILE_UPLOAD)) ) {
			$filename = substr($this->getApiQuery()->getQueryParam(Scribendi_Api_Constants::FIELD_ORDER_FILE_UPLOAD), 1);
			if ( !file_exists($filename) || !is_readable($filename) ) {
				throw new Scribendi_Api_Client_Exception('File '.$filename.' cannot be read or does not exist');
			}
		}
		$this->getTransportOptions()->setOptions(
			array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $this->getApiQuery()->toArray(),
			)
		);
		return $this->getApiClient();
	}
	function removeFileFromOrder($inOrderID = null, $inFileID = null) {
		if ( $inOrderID !== null && $inFileID !== null ) {
			$this->getApiQuery()->setOptions(
				array(
					Scribendi_Api_Constants::FIELD_ORDER_ID => $inOrderID,
					Scribendi_Api_Constants::FIELD_ORDER_FILE_ID => $inFileID,
				)
			);
		}
		$this->setValidationType(self::VALIDATE_REMOVEFILE);
		$this->_setRequestUri('removefile');
		$this
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_ORDER_ID)
			->addRequiredParameters(Scribendi_Api_Constants::FIELD_ORDER_FILE_ID);
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('POST')
			)
		);
		$this->setPostParameters();
		return $this->getApiClient();
	}
	function cancelOrder($inOrderID = null) {
		if ( $inOrderID !== null ) {
			$this->getApiQuery()->setOptions(
				array(
					Scribendi_Api_Constants::FIELD_ORDER_ID => $inOrderID,
				)
			);
		}
		$this->setValidationType(self::VALIDATE_CANCEL);
		$this->_setRequestUri('cancel');
			$this->addRequiredParameters(Scribendi_Api_Constants::FIELD_ORDER_ID);
			$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('POST')
			)
		);
		$this->setPostParameters();
		return $this->getApiClient();
	}
	function commitOrder($inOrderID = null) {
		if ( $inOrderID !== null ) {
			$this->getApiQuery()->setOptions(
				array(
					Scribendi_Api_Constants::FIELD_ORDER_ID => $inOrderID,
				)
			);
		}
		$this->setValidationType(self::VALIDATE_COMMIT);
		$this->_setRequestUri('commit');
		$this->addRequiredParameters(Scribendi_Api_Constants::FIELD_ORDER_ID);
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('POST')
			)
		);
		$this->setPostParameters();
		return $this->getApiClient();
	}
	private function _setRequestUri($inSubAction) {
		$this->setRequestUri(
			Scribendi_Api_Constants::API_ROOT_PATH.
			'order/'.
			$this->getApiQuery()->getQueryParam(Scribendi_Api_Constants::FIELD_ORDER_ID).
			'/'.$inSubAction
		);
	}
	private function getValidationType() {
		return $this->_ValidationType;
	}
	private function setValidationType($inValidationType) {
		if ( $inValidationType !== $this->_ValidationType ) {
			$this->_ValidationType = $inValidationType;
			$this->setModified();
		}
		return $this;
	}
}
class Scribendi_Api_Client_Adaptor_Quote extends Scribendi_Api_Client_Adaptor_Abstract {
	protected function initialise() {
		$this->setName('quote');
		$this->setResponseHandler('Scribendi_Api_Client_Response_Quote');
		$this->setRequestUri(Scribendi_Api_Constants::API_ROOT_PATH.'quote');
		$this->addRequiredParameters(Scribendi_Api_Constants::FIELD_CURRENCY_ID);
		$this->getTransportOptions()->setOptions(
			array(
				CURLOPT_HTTPGET => true,
			)
		);
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('GET')
			)
		);
	}	
	protected function _isValid() {
		if (
			!$this->getApiQuery()->getOptions(Scribendi_Api_Constants::FIELD_CATEGORY_ID, false) &&
			!$this->getApiQuery()->getOptions(Scribendi_Api_Constants::FIELD_SERVICE_ID, false)
		) {
			throw new Scribendi_Api_Client_Exception(
				'Quotes require either '.Scribendi_Api_Constants::FIELD_CATEGORY_ID.' or '.Scribendi_Api_Constants::FIELD_SERVICE_ID
			);
		}
		if (
			!$this->getApiQuery()->getOptions(Scribendi_Api_Constants::FIELD_PAGE_COUNT, false) &&
			!$this->getApiQuery()->getOptions(Scribendi_Api_Constants::FIELD_WORD_COUNT, false)
		) {
			throw new Scribendi_Api_Client_Exception(
				'Quotes require either '.Scribendi_Api_Constants::FIELD_PAGE_COUNT.' or '.Scribendi_Api_Constants::FIELD_WORD_COUNT
			);
		}
		return true;
	}
	function getQuotes($inOptions = null) {
		if ( is_array($inOptions) && count($inOptions) > 0 ) {
			$this->getApiQuery()->setOptions($inOptions);
		}
		return $this->getApiClient();
	}
}
class Scribendi_Api_Client_Adaptor_Search extends Scribendi_Api_Client_Adaptor_Abstract {
	protected function initialise() {
		$this->setName('search');
		$this->setResponseHandler('Scribendi_Api_Client_Response_Search');
		$this->setRequestUri(Scribendi_Api_Constants::API_ROOT_PATH.'search');
		$this->addRequiredParameters(Scribendi_Api_Constants::FIELD_KEYWORDS);
		$this->getTransportOptions()->setOptions(
			array(
				CURLOPT_HTTPGET => true,
			)
		);
		$this->getApiQuery()->setOptions(
			array(
				Scribendi_Api_Constants::FIELD_REQUEST_SIGNATURE => $this->getRequestSignature('GET')
			)
		);
	}
	protected function _isValid() {
		return true;
	}
	function doSearch($inOptions = null) {
		if ( is_array($inOptions) && count($inOptions) > 0 ) {
			$this->getApiQuery()->setOptions($inOptions);
		}
		return $this->getApiClient();
	}
}
abstract class Scribendi_Api_Client_Adaptor_Transport_Abstract implements Scribendi_Api_Client_Adaptor_ITransport {
	protected $_ApiQuery = null;
	protected $_RequestServer;
	protected $_RequestUri;
	protected $_Options;
	function __construct(Scribendi_Api_Query $inQuery, $inRequestServer = null, $inRequestUri = null, array $inOptions = array()) {
		$this->setApiQuery($inQuery);
		$this->setRequestServer($inRequestServer);
		$this->setRequestUri($inRequestUri);
		$this->getTransportOptions()->setOptions($inOptions);
	}
	function getApiQuery() {
		return $this->_ApiQuery;
	}
	function setApiQuery(Scribendi_Api_Query $inApiQuery) {
		if ( $inApiQuery !== $this->_ApiQuery ) {
			$this->_ApiQuery = $inApiQuery;
		}
		return $this;
	}
	function getRequestServer() {
		return $this->_RequestServer;
	}
	function setRequestServer($inRequestServer) {
		if ( $inRequestServer !== $this->_RequestServer ) {
			$this->_RequestServer = $inRequestServer;
		}
		return $this;
	}
	function getRequestUri() {
		return $this->_RequestUri;
	}
	function setRequestUri($inRequestUri) {
		if ( $inRequestUri !== $this->_RequestUri ) {
			$this->_RequestUri = $inRequestUri;
		}
		return $this;
	}
	function getTransportOptions() {
		if ( !$this->_Options instanceof Scribendi_Api_Options ) {
			$this->_Options = new Scribendi_Api_Options();
		}
		return $this->_Options;
	}
	function setTransportOptions(Scribendi_Api_Options $inOptions) {
		if ( $inOptions !== $this->_Options ) {
			$this->_Options = $inOptions;
		}
		return $this;
	}
}
class Scribendi_Api_Client_Adaptor_Transport_Curl extends Scribendi_Api_Client_Adaptor_Transport_Abstract {
	function call() {
		$uri = $this->getRequestServer().preg_replace('/[\/]{2,}/', '/', $this->getRequestUri());
			if ( $this->getTransportOptions()->getOptions(CURLOPT_HTTPGET) ) {
			$uri .= '?'.$this->getApiQuery()->getQuery();
		}
		$ch = curl_init($uri);
		if ( !$ch ) {
			$response  = "<error><code>0</code><message>";
			$response .= "cURL failed to create handle for: {$this->getRequestServer()}";
			$response .= "{$this->getRequestUri()}</message></error>";
		} else {
			curl_setopt_array($ch, $this->getTransportOptions()->toArray());
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FAILONERROR, false);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Scribendi API Client for PHP (v.'.Scribendi_Api_Constants::API_CLIENT_VERSION.')');
			$response = curl_exec($ch);
			$errNum = curl_errno($ch);
			$errMsg = htmlentities(curl_error($ch).' (URI: '.$uri.')', ENT_QUOTES, 'UTF-8');
			curl_close($ch);
			if ( $errNum != CURLE_OK ) {
				$response = "<error><code>$errNum</code><message>$errMsg</message></error>";
			}
		}
		return $response;
	}
}
class Scribendi_Api_Client_Response {
	protected $_Modified = false;
	protected $_Response = null;
	protected $_SimpleXML = null;
	protected $_Results = array();
	function __construct($inResponse) {
		$this->reset();
		$this->setResponse($inResponse);
	}
	function reset() {
		$this->_Response = null;
		$this->_SimpleXML = null;
		$this->_Results = array();
		$this->setModified(false);
	}
	function handleResponse() {
		if ( $this->isError() ) {
			$this->throwException($this->getResponseAsXml());
		}
		$this->_handleResponse();
	}
	protected function _handleResponse() {}
	function isError() {
		return $this->getResponseAsXml()->getName() == 'error';
	}
	function throwException(SimpleXMLElement $inXML) {
		if ( isset($inXML->code) && isset($inXML->message) ) {
			throw new Scribendi_Api_Client_Response_Exception((string)$inXML->message, (string)$inXML->code);
		} else {
			throw new Scribendi_Api_Client_Response_Exception(
				'There was an unknown error with the API, no further information is available.'
			);
		}
	}
	function getResponseAsXml() {
		if ( !$this->_SimpleXML instanceof SimpleXMLElement ) {
			$oXML = simplexml_load_string($this->getResponse());
			if ( !$oXML || !$oXML instanceof SimpleXMLElement ) {
				throw new Scribendi_Api_Client_Response_NotValidXML($this->getResponse());
			}
			$this->_setSimpleXML($oXML);
		}
		return $this->_SimpleXML;
	}
	function isModified() {
		return $this->_Modified;
	}
	function setModified($status = true) {
		$this->_Modified = $status;
		return $this;
	}
	function getResponse() {
		return $this->_Response;
	}
	function setResponse($inResponse) {
		if ( $inResponse !== $this->_Response ) {
			$this->_Response = $inResponse;
			$this->setModified();
		}
		return $this;
	}
	private function _setSimpleXML(SimpleXMLElement $inSimpleXML) {
		if ( $inSimpleXML !== $this->_SimpleXML ) {
			$this->_SimpleXML = $inSimpleXML;
			$this->setModified();
		}
		return $this;
	}
	function getResults() {
		return $this->_Results;
	}
	function setResults($inResults) {
		if ( $inResults !== $this->_Results ) {
			$this->_Results = $inResults;
			$this->setModified();
		}
		return $this;
	}
}
class Scribendi_Api_Client_Response_Currency extends Scribendi_Api_Client_Response {
	protected function _handleResponse() {
		foreach ( $this->getResponseAsXml()->results->result as $oResult ) {
			$this->_Results[] = Scribendi_Api_Model_Currency::factory($oResult);
		}
	}
	function getCurrencies() {
		return $this->_Results;
	}
}
class Scribendi_Api_Client_Response_Order extends Scribendi_Api_Client_Response {
	protected function _handleResponse() {
		foreach ( $this->getResponseAsXml()->results->result as $oResult ) {
			$oOrder = Scribendi_Api_Model_Order::factory($oResult);
			if ( isset($oResult->files) && count($oResult->files->file) > 0 ) {
				foreach ( $oResult->files->file as $file ) {
					$oOrder->getFileSet()->addFile(Scribendi_Api_Model_File::factory($file));
				}
			}
			if ( isset($oResult->properties) && count($oResult->properties->property) > 0 ) {
				foreach ( $oResult->properties->property as $property ) {
					$oOrder->getPropertySet()->setProperty(
						Scribendi_Api_Model_OrderPropertySet::translatePropName((string) $property['name']),
						(string) $property['value']
					);
				}
			}
			$this->_Results[] = $oOrder;
		}
	}
	function getOrders() {
		return $this->_Results;
	}
	function getOrder() {
		return $this->_Results[0];
	}
}
class Scribendi_Api_Client_Response_Quote extends Scribendi_Api_Client_Response {
	protected function _handleResponse() {
		foreach ( $this->getResponseAsXml()->results->result as $oResult ) {
			if ( (string) $oResult['valid'] == 'yes' ) {
				$this->_Results[] = Scribendi_Api_Model_Quote::factory($oResult);
			}
		}
	}
	function getQuotes() {
		return $this->_Results;
	}
}
class Scribendi_Api_Client_Response_Search extends Scribendi_Api_Client_Response {
	protected function _handleResponse() {
		foreach ( $this->getResponseAsXml()->results->result as $oResult ) {
			$this->_Results[] = Scribendi_Api_Model_SearchResult::factory($oResult);
		}
	}
}