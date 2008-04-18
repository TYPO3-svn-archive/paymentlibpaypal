<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2008 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * payment_PAYPAL.php
 *
 * This script handles payment via the PAYPAL gateway.
 *
 * This script is used as a "handleScript" with the default productsLib.inc shopping system.
 *
 * PAYPAL:	http://www.paypal.com
 * 
 * $Id$
 *
 * @author	Franz Holzinger <contact@fholzinger.com>
 * @package TYPO3
 * @subpackage tt_products
 *
 *
 */

define(TRANSACTION_OK, 0);
define(TRANSACTION_NOT_PROCESSED, '999');
define(TRANSACTION_FAILURE, '998');
define(WRONG_TRANSACTION, '997');
define(TRANSACTION_OK_MSG, 'Transaction processed');
define(TRANSACTION_NOT_PROCESSED_MSG, 'Transaction not processed');
define(TRANSACTION_FAILURE_MSG, 'Transaction failure');
define(WRONG_TRANSACTION_MSG, 'Received wrong transaction');
define(TRANSACTION_SUCCESS, 'ok');
define(TRANSACTION_FAILED, 'failure');
define(TRANSACTION_NOPROCESS, 'not processed');

require_once (t3lib_extMgM::extPath('paymentlib').'lib/class.tx_paymentlib_provider.php');
// require_once (t3lib_extMGM::extPath('paymentlib_paypal').'lib/class.tx_paymentlibpaypal_basket.php');



class tx_paymentlibpaypal_provider extends tx_paymentlib_provider {
	var $providerKey = "paymentlib_paypal";
	var $action = 0;
	var $paymentMethod = '';
	var $callingExtension ='';
	var $gatewayMode = 0;
	var $processed = false;

	// Settings for paypal 
	var $sendBasket = false;	// Submit detailled basket informations like single produrcts
	var $setTax = true;		// Add the total tax to the submitted informations
	var $setShipping = true;	// Add the total shipping to the submitted informations
	var $setHandlingFee = true;	// Set a basket_wide handling fee
	var $createPaypalUser = true;	// Create new paypal users
	var $formActionURI = '';	// The action uri for the submit form
	
//	var $articleMap = array();	// Maps article fields from the basket to paypal fields
//	var $addressMap = array();	// Maps invoiceFields to from the basekt to paypal

	var $conf = array ();	// paypal pre-configuration array

	var $total = array();
	var $address = array();
	var $basket = array();

	var $articleFields;
	var $prePopulatingFields;
	var $transactionId;
	var $authToken = false;
	var $syncURI;
	var $syncScheme;
	var $orderId='';
	var $db;
        var $priceFormat = '';

	var $libObj;			// Pay Suite Library object

		// Setup array for modifying the inputs

	public $modifyRules = array(
			'/Ä/' => 'AE',
			'/ä/' => 'ae',
			'/Ü/' => 'UE',
			'/ü/' => 'ue',
			'/Ö/' => 'OE',
			'/ö/' => 'oe',
			'/ß/' => 'ss',
		);

	public function __construct()	{
		global $TSFE;

		$testChar1 = 'ü';
		if (extension_loaded('iconv'))	{
			$testChar = iconv('iso-8859-1', 'utf-8', 'ü');
		};

		$testLen = strlen($testChar);

		if ($testLen == 2 && ord($testChar{0}) == 195 && ord($testChar{1}) == 188)	{	// utf-8 has been detected. But this file is stored in iso-8859-1
			$utf8ModifyRules = array();
			foreach ($this->modifyRules as $k => $v)	{
				$k1 = iconv('iso-8859-1', 'utf-8', $k);
				$utf8ModifyRules[$k1] = $v;
			}
			$this->modifyRules = $utf8ModifyRules;
		}

		$extManagerConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['paymentlib_paypal']);
		
		// Paypal settings directly given to the setup at installation

		$tmpKeyArray = array(
			// Set paypalsite looking
			'image_url' => 'logo', 	// Set the logo which will be displayed
			'cbt' => 'continueButton',	// Value of the continue button
			'business' => 'business',	// The buisness for which the payment should apply
			// Set some additionial information 
			'no_shipping' => 'enableShippingaddress',	// Should the customer enter a shipping-address?
			'address_override' => 'overwriteAddress',	// Should the paypal-address be overwritten by the submitted address fields
			'rm' => 'submitMode',	// Submit mode for vars, could be 'get' or 'post'
						//$this->conf['custom'] = $extManagerConf['customVar'];
						// Passthrough var, not handled by paypal. Possible values ar ###ORDER_ID###, ###DATE###, any other customer value or combination of these
			'invoice' => 'invoiceVar',	// Passthrough var, not handled by paypal. Behavio just like 'custom'
			'no_note' => 'enablePaymentNote'	// Should the customer leave a note on payment
		);


		foreach ($tmpKeyArray as $payPalKey => $extManagerConfKey)	{
			$this->conf[$payPalKey] = $extManagerConf[$extManagerConfKey];
		}
		if ($TSFE->metaCharset) {
			$charset = $TSFE->csConvObj->parse_charset($TSFE->metaCharset);
		} else {
			$charset = 'iso-8859-1'; // charset to be used in emails and form conversions
		}
		// Set used charset
		$this->conf['charset'] = $charset;

		if ($this->conf['no_note'] == '0')	{
			$this->conf['cn'] = $extManagerConf['noteLabel'];
		}
		// Layout behaviors

		if ($extManagerConf['setLayout'])	{
			if (($this->conf['page_style'] = $extManagerConf['layoutPageStyle']) == '')	{
				unset($this->conf['page_style']);	
				$this->conf['cpp_header_image'] = $extManagerConf['cppHeaderImage'];
				$this->conf['cpp_headerback_color'] = $extManagerConf['cppHeaderBackColor'];
				$this->conf['cpp_headerborder_color'] = $extManagerConf['cppHeaderBorderColor'];
				$this->conf['cpp_payflow_color'] = $extManagerConf['cppPayFlowColor'];
				$this->conf['cs'] = $extManagerConf['pageBackgroundColor'];	
			}
		}

		$this->setTax = $extManagerConf['sendTaxAmount'];	// Decides whether the baskets own (car_wide) tax is send or if the tax is calculated by paypal
		$this->setShipping = $extManagerConf['sendShipping'];		// Should shipping cost be send (cart_wide)
		$this->setHandlingFee = $extManagerConf['sendHandlingFee'];		// Should (cart_wide) handling fee be send
		$this->formActionURI = $extManagerConf['formActionURI'];	// The used action uri
		
		$this->sendInvoiceAddress = $extManagerConf['sendPersonInfo'];		// Decides whether person info for the customers should be send
		$this->createPaypalUser = $extManagerConf['createNewUser'];		// Decides if new users are redirected to paypal creation

		if ($this->sendInvoiceAddress || $this->createPaypalUser)
			$this->prePopulatingFields = $extManagerConf['prepopulatingFields'];		// The invoice fields which should be send to paypal
			
		$this->sendBasket = $extManagerConf['sendBasket'];			// Decides whether the basket is submitted or not
		$this->priceFormat = $extManagerConf['basketCurrency'];

		if ($this->sendBasket)
			$this->articleFields = $extManagerConf['articleFields'];	// The article fields which should be send to paypal
		if ($this->createPaypalUser && !((bool)$this->sendBasket))	{
			$this->conf['cmd'] = '_ext-enter';
			$this->conf['redirect_cmd'] = '_xclick';
		} else {
			$this->conf['cmd'] = '_cart';
		}

		// Settings for getting transaction results
		$this->authToken = $extManagerConf['authToken'];	// Paypal generated auth-token to get informations about transactions
		$this->syncURI = $extManagerConf['syncUri'];	// Synchronisation uri for PDT-synchronisations
		$this->syncScheme = $extManagerConf['syncScheme'];	// Decides which port to use for synchronisation (80/443)

		// $this->payment_basket = t3lib_div::makeInstance('tx_paymentlibpaypal_basket');
		$this->db = &$GLOBALS['TYPO3_DB'];

		if (t3lib_extMgm::isLoaded(PAYSUITE_EXTkey)) {
			include_once (PATH_BE_paysuite.'lib/class.tx_paysuite_lib.php');
			$this->libObj = &t3lib_div::makeInstance('tx_paysuite_lib');
			$this->libObj->init($this->providerKey);
		}

		return true;
	}

	public function usesBasket()	{
		return (intval($this->sendBasket) > 0);
	}

	public function getProviderKey()	{
			return $this->providerKey;
	}

	public function &getLibObj()	{
		return $this->libObj;
	}

	/**
	 * Returns TRUE if the payment implementation supports the given gateway mode.
	 * All implementations should at least support the mode 
	 * TX_PAYMENTLIB_GATEWAYMODE_FORM.
	 *
	 * TX_PAYMENTLIB_GATEWAYMODE_WEBSERVICE usually requires your webserver and
	 * the whole application to be certified if used with certain credit cards.
	 *
	 * @param	integer		$gatewayMode: The gateway mode to check for. One of the constants TX_PAYMENTLIB_GATEWAYMODE_*
	 * @return	boolean		TRUE if the given gateway mode is supported
	 * @access	public
	 */

	 public function getAvailablePaymentMethods () {
	 	return t3lib_div::xml2array (t3lib_div::getUrl(t3lib_extMgm::extPath ('paymentlib_paypal').'paymentmethods.xml'));
	 }

	/**
	 * Initializes a transaction.
	 *
	 * @param	integer		$action: Type of the transaction, one of the constants TX_PAYMENTLIB_TRANSACTION_ACTION_*
	 * @param	string		$paymentMethod: Payment method, one of the values of getSupportedMethods()
	 * @param	integer		$gatewayMode: Gateway mode for this transaction, one of the constants TX_PAYMENTLIB_GATEWAYMODE_*
	 * @param	string		$callingExtKey: Extension key of the calling script.
	 * @return	void
	 * @access	public
	 */

	public function supportsGatewayMode($gatewayMode)	{

		switch ($gatewayMode) {
			case TX_PAYMENTLIB_GATEWAYMODE_FORM :
				return TRUE;

			default:
				return FALSE;
		}
	}

	/**
	 * Initializes a transaction.
	 *
	 * @param	integer		$action: Type of the transaction, one of the constants TX_PAYMENTLIB_TRANSACTION_ACTION_*
	 * @param	string		$paymentMethod: Payment method, one of the values of getSupportedMethods()
	 * @param	integer		$gatewayMode: Gateway mode for this transaction, one of the constants TX_PAYMENTLIB_GATEWAYMODE_*
	 * @param	string		$callingExtKey: Extension key of the calling script.
	 * @return	void
	 * @access	public
	 */
	public function transaction_init($action, $paymentMethod, $gatewayMode, $callingExtKey)	{
			if (!$this->supportsGatewayMode ($gatewayMode)) return FALSE;

			$this->action = $action;
			$this->paymentMethod = $paymentMethod;
			$this->gatewayMode = $gatewayMode;
			$this->callingExtension = $callingExtKey;
			return true;
		}

	/**
	 * Sets the payment details. Which fields can be set usually depends on the
	 * chosen / supported gateway mode. TX_PAYMENTLIB_GATEWAYMODE_FORM does not
	 * allow setting credit card data for example.
	 *
	 * @param	array		$detailsArr: The payment details array
	 * @return	boolean		Returns TRUE if all required details have been set
	 * @access	public
	 */
	public function transaction_setDetails ($detailsArr)	{
		$ok = false;

		if ($this->gatewayMode  == TX_PAYMENTLIB_GATEWAYMODE_FORM)	{
			switch($this->paymentMethod)	{
				case 'paypal_webpayment_standard':
				case 'paypal_webpayment_euro':
				case 'paypal_webpayment_international':
				case 'paypal_webpayment_poundssterling':
					$ok = (
						is_array ($detailsArr['transaction']) &&
							intval($detailsArr['transaction']['amount']) &&
							strlen($detailsArr['transaction']['currency']) &&
							strlen($detailsArr['transaction']['orderuid'])
						);
					if ($ok)	{
						$this->total = $detailsArr['total'];	// retrieve the current total sums
						$this->address = $detailsArr['address'];	// retrieve the current invoice address
						$this->basket = $detailsArr['basket'];	// retrieve the current basket
						// We use the 'custom'-var to authenticate our transaction
						$this->orderId = strval($detailsArr['transaction']['orderuid']);
						$this->conf['custom']=$this->transactionId=
							$this->libObj->createUniqueID(strval($detailsArr['transaction']['orderuid']), $this->callingExtension);
						$this->conf['currency_code'] = $detailsArr['transaction']['currency'];
						if (ord($this->conf['currency_code']) == 128)	{ // 'euro symbol'
							$this->conf['currency_code'] = 'EUR';
						}

						$this->conf['return'] = ($detailsArr['transaction']['successlink'] ? $detailsArr['transaction']['successlink'] : $this->conf['return']);
						$this->conf['cancel_return'] = ($detailsArr['transaction']['returi'] ? $detailsArr['transaction']['returi'] : $this->conf['cancel_return']);

						// Store order id in database
						if ($this->getTransaction($this->transactionId) === FALSE)	{
							$dataArr = array(
								'crdate' => time(),
								'gatewayid' => $this->providerKey,
								'ext_key' => $this->callingExtension,
								'reference' => $this->transactionId,
								'state' => TRANSACTION_NOPROCESS,
								'amount' => $detailsArr['transaction']['amount'],
								'currency' => $detailsArr['transaction']['currency'],
								'paymethod_key' => $this->providerKey,
								'paymethod_method' => $this->paymentMethod,
								'message' => TRANSACTION_NOT_PROCESSED_MSG
							);

							$res = $this->db->exec_DELETEquery('tx_paymentlib_transactions', 'gatewayid ="'.$this->providerKey.'" AND amount LIKE "0.00" AND message LIKE "s:25:\"Transaction not processed\";"');

							if ($this->getTransaction($this->transactionId) === FALSE)	{
								$res = $this->db->exec_INSERTquery('tx_paymentlib_transactions', $dataArr);
							} else {
								$res = $this->db->exec_UPDATEquery('tx_paymentlib_transactions', "extreference LIKE '".$transactionId."'", $dataArr);
							}
							if (!$res)	{
								$ok = $res;
							}
						}
					}
					break;
				default:
					$ok = false;
				break;
			}
		}

		return $ok;
	}


	/**
	 * Validates the transaction data which was set by transaction_setDetails().
	 * $level determines how strong the check is, 1 only checks if the data is
	 * formally correct while level 2 checks if the credit card or bank account
	 * really exists.
	 *
	 * This method is not available in mode TX_PAYMENTLIB_GATEWAYMODE_FORM!
	 *
	 * @param	integer		$level: Level of validation, depends on implementation
	 * @return	boolean		Returns TRUE if validation was successful, FALSE if not
	 * @access	public
	 */

	public function transaction_validate ($level=1) 	{
		return false;
	}

	/**
	 * Submits the prepared transaction to the payment gateway
	 *
	 * This method is not available in mode TX_PAYMENTLIB_GATEWAYMODE_FORM, you'll have
	 * to render and submit a form instead.
	 *
	 * @return	boolean		TRUE if transaction was successul, FALSE if not. The result can be accessed via transaction_getResults()
	 * @access	public
	 */
	public function transaction_process ()	{
		return false;
	}

	/**
	 * Returns the form action URI to be used in mode TX_PAYMENTLIB_GATEWAYMODE_FORM.
	 *
	 * @return	string		Form action URI
	 * @access	public
	 */
	public function transaction_formGetActionURI ()	{
		if ($this->gatewayMode != TX_PAYMENTLIB_GATEWAYMODE_FORM) return FALSE;

		return $this->formActionURI;
	}

	/**
	* Returns any extra parameter for the form tag to be used in mode TX_PAYMENTLIB_GATEWAYMODE_FORM.
	*
	* @return  string      Form tag extra parameters
	* @access  public
	*/
	public function transaction_formGetFormParms ()	{
		return '';
	}

	/**
	* Returns any extra parameter for the form submit button to be used in mode TX_PAYMENTLIB_GATEWAYMODE_FORM.
	*
	* @return  string      Form submit button extra parameters
	* @access  public
	*/
	public function transaction_formGetSubmitParms ()	{
		return '';
	}

	/**
	 * Returns an array of field names and values which must be included as hidden
	 * fields in the form you render use mode TX_PAYMENTLIB_GATEWAYMODE_FORM.
	 *
	 * @return	array		Field names and values to be rendered as hidden fields
	 * @access	public
	 */
	public function transaction_formGetHiddenFields ()	{
		global $TSFE;

		$fieldsArr = array();
		$fieldsArr = $this->conf;
		$fieldsArr['upload']='1';

		foreach($fieldsArr as $paypalField => $value)	{
			if (is_string($value) && $value == '')	{
				unset($fieldsArr[$paypalField]);
			}
		}

		$invoice = &$this->address;
		$total = &$this->total;
		$basket = &$this->basket;

		// ********************************************************
		// First set basket wide vars
		// ********************************************************
		$fieldsArr['business'] = $this->conf['business'];
		$fieldsArr['return'] = $this->conf['return'];
		$fieldsArr['cancel_return'] = $this->conf['cancel_return'];
		$fieldsArr['invoice'] = $this->performMarker($this->orderId, $this->conf['invoice']);

		if ($this->useBasketCharset)	{
			$fieldsArr['charset'] = $total['charset'];
		}

		// *******************************************************
		// Set article vars if selected
		// *******************************************************
		$modSourceArray = array_keys($this->modifyRules);
		$modDestinationArray = array_values($this->modifyRules);

		if ($this->usesBasket() && is_array($basket))	{
			$sendFields = t3lib_div::trimExplode(',',$this->articleFields,1);
			$count = 0;

			foreach($basket as $sort => $items)	{
				foreach($items as $k1 => $item)	{
					$count++;
					foreach($sendFields as $paypalField)	{
						$value = preg_replace($modSourceArray, $modDestinationArray, $item[$paypalField]);
						$fieldsArr[$paypalField.'_'.$count] = $value;
					}
				}
			}
		}  else {
			$fieldsArr['amount'] = $total['amountnotax'];
	
			if ($this->setShipping)	{
				$fieldsArr['shipping'] = $total['shippingnotax'];
			}
	
			if ($this->setHandlingFee)	{
				$fieldsArr['handling_cart'] = $total['handlingnotax'];
			}
	
			if ($this->setTax)	{
				$fieldsArr['tax_cart'] = $total['totaltax'];
			}
		}

		if (($this->sendInvoiceAddress || $this->createPaypalUser) && is_array($invoice['person']))	{
			foreach ($invoice['person'] as $k => $v)	{
				$value = preg_replace($modSourceArray, $modDestinationArray, $v);
				$fieldsArr[$k] = $value;
			}
			$fieldsArr['payer'] = $invoice['person']['email'];
		}

		return $fieldsArr;
	}

	public function transaction_formGetVisibleFields () 	{
		return false;
	}


	/**
	 * Sets the URI which the user should be redirected to after a successful payment/transaction
	 * If your provider/gateway implementation only supports one redirect URI, set okpage and
	 * errorpage to the same URI
	 * 
	 * @return void
	 * @access public
	 */
	public function transaction_setOkPage ($uri)	{
		$this->conf['return'] = $uri;
	}

	/**
	 * Sets the URI which the user should be redirected to after a failed payment/transaction
	 * If your provider/gateway implementation only supports one redirect URI, set okpage and
	 * errorpage to the same URI
	 * 
	 * @return void
	 * @access public 
	 */
	public function transaction_setErrorPage ($uri)	{
		$this->conf['cancel_return'] = $uri;
	}

	/**
	 * Returns the results of a processed transaction
	 *
	 * @param	string		$reference
	 * @return	array		Results of a processed transaction
	 * @access	public
	 */
	public function transaction_getResults ($reference)	{

		if ($this->authToken != '')	{
			if ($this->gatewayMode == TX_PAYMENTLIB_GATEWAYMODE_FORM)	{
				$detailsArr = array();
				$detailsArr = $this->requestPDT(t3lib_div::_GP('tx'), $reference);
				return $detailsArr;
			}	
		}
		
		return false;
	}

	public function transaction_succeded ($resultsArr)	{
		if ($resultsArr['status'] == TRANSACTION_SUCCESS)
			return true;


		return false;
	}

	public function transaction_failed($resultsArr)	{

		if ($resultsArr['status'] == TRANSACTION_FAILED)
			return true;

		return false;
	}

	public function transaction_message ($resultsArr)	{
		return $resultsArr['errmsg'];
	}

	// *****************************************************************************
	// Helpers
	// *****************************************************************************
	
	public function requestPDT($tx, $reference)	{
		$ok = false;
		$detailsArr = array();

		$row = array(
			'gatewayid' => '',
			'reference' => $reference,
			'currency' => $this->conf['currency_code'],
			'amount' => '0.00',
			'state' => '',
			'state_time' => time(),
			'message' => TRANSACTION_NOT_PROCESSED,
			'ext_key' => $this->callingExtension,
			'paymethod_key' => $this->providerKey,
			'paymethod_method' => $this->paymentMethod,
			'user' => $this->orderId
		);

		$transactionId = t3lib_div::_GP('cm');
		$dbRow = $this->getTransaction($transactionId);
		if (is_array($dbRow))	{
			$row = array_merge($row, $dbRow);
		}

		if ($tx !='' && $row['status']==TRANSACTION_NOPROCESS)	{
			// Perform transaction check
			$uriSplittet = parse_url($this->syncScheme.'://'.$this->syncURI);
			$port = 80;
			if ($uriSplittet['scheme']=='https')	{
				$uriSplittet['host'] = 'ssl://'.$uriSplittet['host'];
				$port = 443;
			}

			$req="cmd=_notify-synch&tx=$tx&at={$this->authToken}";
			$header .= "POST ".$uriSplittet['path']." HTTP/1.0\r\n";
			$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

			$fp = fsockopen($uriSplittet['host'],$port, $errno, $errstr, 30);

			if ($fp)	{
				fputs($fp, $header.$req);	// Post request command

				// Parse received data
				$res = '';
				$headerdone = false;
				while (!feof($fp)) {
					$line = fgets ($fp, 1024);
					if (strcmp($line, "\r\n") == 0) 
						$headerdone = true;
					else if ($headerdone)
						$res .= $line;
				}

				$lines = explode("\n", $res);
				// Check if transaction was successfull
				if (strcmp ($lines[0], "SUCCESS") == 0) 	{
					for ($i=1; $i<count($lines);$i++)	{
						list($key,$val) = explode("=", $lines[$i]);
						$detailsArr[trim(urldecode($key))] = trim(urldecode($val));
					}

					if ($transactionId == $detailsArr['custom'])	{
						// Transaction was processed correct
						$row['message']=$detailsArr['payment_status'];
						$row['user'] .= ':'.$detailsArr['payment_type'];
						$row['state'] = TX_PAYMENTLIB_TRANSACTION_STATE_AUTHORIZED;
						$row['invoicetext'] = $detailsArr['invoice'];
						$row['gatewayid'] = $detailsArr['txn_id'].': '.$detailsArr['txn_type'].' '.$detailsArr['payment_date'];
					} else {
						// Received wrong transaction
						$row['message']=WRONG_TRANSACTION;
						$row['user'] .= ':'.WRONG_TRANSACTION_MSG;
						$row['state'] = TX_PAYMENTLIB_TRANSACTION_STATE_AUTHORIZE_FAILED;
						$row['invoicetext'] = $detailsArr['invoice'];
						$row['gatewayid'] = $detailsArr['txn_id'].': '.$detailsArr['txn_type'].' '.$detailsArr['payment_date'];
					}
				} else {
					// Something goes wrong
					$row['message'] = $detailsArr['payment_status'];
					$row['user'] .= ':'.$detailsArr['payment_type'];
					$row['state'] = TX_PAYMENTLIB_TRANSACTION_STATE_AUTHORIZE_FAILED;
					$row['invoicetext'] = $detailsArr['invoice'];
//					$detailsArr['response']=$lines;			// returns the transaction response as array
				}
			} else {
				// Fileerror
				$row['message'] = $errno;
				$row['user'] .= ':'.$errstr;
				$row['state'] = TX_PAYMENTLIB_TRANSACTION_STATE_AUTHORIZE_FAILED;
			}

			$res = $this->db->exec_UPDATEquery('tx_paymentlib_transactions', "reference = '".$transactionId."'", $row);

			//$row['transactionDetails']=$lines;
		}
		return $row;
	}

	function getTransaction($transactionId)	{
		$rc = FALSE;
		$res = $this->db->exec_SELECTquery('*', 'tx_paymentlib_transactions', 'reference = "'.$transactionId.'"');

		if ($transactionId !='' && $res)	{
			$rc = $this->db->sql_fetch_assoc($res);
		}

		return $rc;
	}

	function performMarker($invoice, $invMarker)	{
		$mapArr = array(
			'###ORDERID###' => $invoice,
			'###FULLNAME###' => $this->address['fullname'],
			'###FIRSTNAME###' => $this->address['firstname'],
			'###LASTNAME###' => $this->address['lastname'],
			'###EMAIL###' => $this->address['email'],
		);

		// Perform dateoperations
		$dateExpr = '(%[A-Za-z%])';
		$exprArr = array();
		if (($count = preg_match_all($dateExpr, $invMarker, $exprArr, PREG_PATTERN_ORDER))!=false && $count >0)	{
			foreach($exprArr[0] as $val)	{
				$invMarker = str_replace($val, strftime($val,time()), $invMarker);
			}
		}

		foreach($mapArr as $marker => $replace)	{
			$invMarker = str_replace($marker, $replace, $invMarker);
		}

		return $invMarker;
	}
}
?>