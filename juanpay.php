<?php

defined('_JEXEC') or die('Restricted access');

/**
 *
 * JuanPay payment plugin
 *
 * @author JuanPay Dev Team
 * @version $Id: juanpay.php 7352 2013-11-08 13:06:41Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * Copyright (C) 2004-2013 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 */
if (!class_exists('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentJuanPay extends vmPSPlugin {

	function __construct(& $subject, $config) {

		parent::__construct($subject, $config);

		$this->_loggable = TRUE;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id'; //virtuemart_juanpay_id';
		$this->_tableId = 'id'; //'virtuemart_juanpay_id';
		$varsToPush = array('juanpay_merchant_email' => array('', 'char'),
            'juanpay_api_key' => array('', 'char'),
			'juanpay_verified_only' => array('', 'int'),
			'payment_currency' => array('', 'int'),
			'email_currency' => array('', 'int'),
			'log_ipn' => array('', 'int'),
			'sandbox' => array(0, 'int'),
			'sandbox_merchant_email' => array('', 'char'),
            'sandbox_api_key' => array('', 'char'),
			'payment_logos' => array('', 'char'),
			'debug' => array(0, 'int'),
			'status_paid' => array('', 'char'),
			'status_confirmed' => array('', 'char'),
			'status_underpaid' => array('', 'char'),
			'status_shipped' => array('', 'char'),
			'countries' => array('', 'char'),
			'min_amount' => array('', 'float'),
			'max_amount' => array('', 'float'),
			'secure_post' => array('', 'int'),
			'ipn_test' => array('', 'int'),
			'no_shipping' => array('', 'int'),
			'address_override' => array('', 'int'),
			'cost_per_transaction' => array('', 'int'),
			'cost_percent_total' => array('', 'int'),
			'tax_id' => array(0, 'int')
		);

		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

	}

	/**
	 * @return string
	 */
	public function getVmPluginCreateTableSQL() {

		return $this->createTableSQL('Payment JuanPay Table');
	}

	/**
	 * @return array
	 */
	function getTableSQLFields() {

		$SQLfields = array(
			'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(1) UNSIGNED',
			'order_number' => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name' => 'varchar(5000)',
			'payment_order_total' => 'decimal(15,5) NOT NULL',
			'payment_currency' => 'smallint(1)',
			'email_currency' => 'smallint(1)',
			'cost_per_transaction' => 'decimal(10,2)',
			'cost_percent_total' => 'decimal(10,2)',
			'tax_id' => 'smallint(1)',
			'juanpay_custom' => 'varchar(255)',
			'juanpay_response_mc_gross' => 'decimal(10,2)',
			'juanpay_response_mc_currency' => 'char(10)',
			'juanpay_response_invoice' => 'char(32)',
			'juanpay_response_protection_eligibility' => 'char(128)',
			'juanpay_response_payer_id' => 'char(13)',
			'juanpay_response_tax' => 'decimal(10,2)',
			'juanpay_response_payment_date' => 'char(28)',
			'juanpay_response_payment_status' => 'char(50)',
			'juanpay_response_pending_reason' => 'char(50)',
			'juanpay_response_mc_fee' => 'decimal(10,2)',
			'juanpay_response_payer_email' => 'char(128)',
			'juanpay_response_last_name' => 'char(64)',
			'juanpay_response_first_name' => 'char(64)',
			'juanpay_response_business' => 'char(128)',
			'juanpay_response_receiver_email' => 'char(128)',
			'juanpay_response_transaction_subject' => 'char(128)',
			'juanpay_response_residence_country' => 'char(2)',
			'juanpay_response_txn_id' => 'char(32)',
			'juanpay_response_txn_type' => 'char(32)', //The kind of transaction for which the IPN message was sent
			'juanpay_response_parent_txn_id' => 'char(32)',
			'juanpay_response_case_creation_date' => 'char(32)',
			'juanpay_response_case_id' => 'char(32)',
			'juanpay_response_case_type' => 'char(32)',
			'juanpay_response_reason_code' => 'char(32)',
			'juanpayresponse_raw' => 'varchar(512)',
		);
		return $SQLfields;
	}

	function juanpay_hash($params, $method) {
	   $API_Key = $this->_getAPIKey($method);
	   $md5HashData = $API_Key;
	   $hashedvalue = '';
	   foreach($params as $key => $value) {
		if ($key<>'hash' && strlen($value) > 0) {
		   $md5HashData .= $value;
	  	 }
	   }
	   if (strlen($API_Key) > 0) {
 	      $hashedvalue .= strtoupper(md5($md5HashData));
	   }
	   return $hashedvalue; 
	}


	/**
	 * @param $cart
	 * @param $order
	 * @return bool|null
	 */
	function plgVmConfirmedOrder($cart, $order) {

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		$session = JFactory::getSession();
		$return_context = $session->getId();
		$this->_debug = $method->debug;
		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}	
		if (!class_exists('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		}

		$address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

		if (!class_exists('TableVendors')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'tables' . DS . 'vendors.php');
		}
		$vendorModel = VmModel::getModel('Vendor');
		$vendorModel->setId(1);
		$vendor = $vendorModel->getVendor();
		$vendorModel->addImages($vendor, 1);
		$this->getPaymentCurrency($method);
		$email_currency = $this->getEmailCurrency($method);
		$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');

		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);
		$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
		if ($totalInPaymentCurrency <= 0) {
			vmInfo(JText::_('VMPAYMENT_JUANPAY_PAYMENT_AMOUNT_INCORRECT'));
			return FALSE;
		}
		$merchant_email = $this->_getMerchantEmail($method);
		if (empty($merchant_email)) {
			vmInfo(JText::_('VMPAYMENT_JUANPAY_MERCHANT_EMAIL_NOT_SET'));
			return FALSE;
		}
		$quantity = 0;
		foreach ($cart->products as $key => $product) {
			$quantity = $quantity + $product->quantity;
		}

		$post_variables = Array(
			'email' => $merchant_email, //Primary email address of the payment recipient (i.e., the merchant
			'order_number' => $order['details']['BT']->order_number,
			'confirm_form_option' => "NONE",
			'item_name_1' => JText::_('VMPAYMENT_JUANPAY_ORDER_NUMBER') . ': ' . $order['details']['BT']->order_number,
			"price_1" => $totalInPaymentCurrency['value'],
			"buyer_first_name" => $address->first_name,
			"buyer_last_name" => $address->last_name,
			"buyer_email" => $order['details']['BT']->email,
			"buyer_cell_number" => $address->phone_1,
			"notify_url"       => substr(JURI::root(false,''),0,-1) . JROUTE::_('index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component', false),
			"return_url" => substr(JURI::root(false,''),0,-1) . JROUTE::_( 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt('Itemid'), false)
			);

			// Keep this line, needed when testing
			//"return" => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component'),
			//"notify_url"       => substr(JURI::root(false,''),0,-1) . JROUTE::_('index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component', false),

		$hash = $this->juanpay_hash($post_variables, $method);
		$post_variables['hash'] = $hash;
		// Prepare data that should be stored in the database
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->renderPluginName($method, $order);
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['juanpay_custom'] = $return_context;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $method->payment_currency;
		$dbValues['email_currency'] = $email_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData($dbValues);

		$url = $this->_getJuanPayUrlHttps($method);

		// add spin image
		$html = '<html><head><title>Redirection</title></head><body><div style="margin: auto; text-align: center;">';
		$html .= '<form action="' . "http://" . $url . '" method="post" name="vm_juanpay_form"  accept-charset="UTF-8">';
		$html .= '<input type="submit"  value="' . JText::_('VMPAYMENT_JUANPAY_REDIRECT_MESSAGE') . '" />';
		foreach ($post_variables as $name => $value) {
			$html .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
		}
		$html .= '</form></div>';
		$html .= ' <script type="text/javascript">';
		$html .= ' document.vm_juanpay_form.submit();';
		$html .= ' </script></body></html>';

		// 	2 = don't delete the cart, don't send email and don't redirect
		$cart->_confirmDone = FALSE;
		$cart->_dataValidated = FALSE;
		$cart->setCartIntoSession();
		JRequest::setVar('html', $html);

	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
	 */
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
	 */
	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		if (empty($payments[0]->email_currency)) {
			$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
			$db = JFactory::getDBO();
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery($q);
			$emailCurrencyId = $db->loadResult();
		} else {
			$emailCurrencyId = $payments[0]->email_currency;
		}

	}

	/**
	 * @param $html
	 * @return bool|null|string
	 */
	function plgVmOnPaymentResponseReceived(&$html) {

		if (!class_exists('VirtueMartCart')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists('shopFunctionsF')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		//vmdebug('JUANPAY plgVmOnPaymentResponseReceived', $juanpay_data);
		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
		$order_number = JRequest::getString('on', 0);

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return NULL;
		}

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
			return NULL;
		}
		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		$payment_name = $this->renderPluginName($method);
		$html = $this->_getPaymentResponseHtml($paymentTable, $payment_name);
		//We delete the old stuff
		// get the correct cart / session
		$cart = VirtueMartCart::getCart();
		$cart->emptyCart();
		return TRUE;
	}

	/**
	 * @return bool|null
	 */
	function plgVmOnUserPaymentCancel() {

		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		$order_number = JRequest::getString('on', '');
		$virtuemart_paymentmethod_id = JRequest::getInt('pm', '');
		if (empty($order_number) or empty($virtuemart_paymentmethod_id) or !$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
			return NULL;
		}
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
			return NULL;
		}
		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
			return NULL;
		}

		VmInfo(Jtext::_('VMPAYMENT_JUANPAY_PAYMENT_CANCELLED'));
		$session = JFactory::getSession();
		$return_context = $session->getId();
		if (strcmp($paymentTable->juanpay_custom, $return_context) === 0) {
			$this->handlePaymentUserCancel($virtuemart_order_id);
		}
		return TRUE;
	}

	/*
		 *   plgVmOnPaymentNotification() - This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
		 * Return:
		 * Parameters:
		 *  None
		 *  @author Valerie Isaksen
		 */

	/**
	 * @return bool|null
	 */
	function plgVmOnPaymentNotification() {
		$this->_debug = true;
		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		$juanpay_data = JRequest::get('post');

		if (!isset($juanpay_data['order_number'])) {
			return FALSE;
		}

		$order_number = $juanpay_data['order_number'];
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($juanpay_data['order_number']))) {
			return FALSE;
		}

		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			return FALSE;
		}
		$method = $this->getVmPluginMethod($payments[0]->virtuemart_paymentmethod_id);

		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		$this->_debug = $method->debug;
		$this->logInfo('juanpay_data ' . implode('   ', $juanpay_data), 'message');

		if (!$this->_processIPN($juanpay_data, $method)) {
			$this->logInfo('juanpay_data _processIPN FALSE', 'message');
			return FALSE;
		}

		$modelOrder = VmModel::getModel('orders');
		$order = array();

		$order['customer_notified'] = 1;
        $this->logInfo('Status ' . $juanpay_data['status'], 'message');


		if (strcmp($juanpay_data['status'], 'Paid') == 0 or strcmp($juanpay_data['status'], 'Overpaid') == 0 ) {
 			$order['order_status'] = $method->status_paid;
		} elseif (strcmp($juanpay_data['status'], 'Underpaid') == 0) {
			$order['order_status'] = $method->status_underpaid;
		} elseif (strcmp($juanpay_data['status'], 'Confirmed') == 0) {
            $order['order_status'] = $method->status_confirmed;
        } elseif (strcmp($juanpay_data['status'], 'Shipped') == 0) {
			$order['order_status'] = $method->status_shipped;
		} else {
			$order['customer_notified'] = 0;
		}
        $order['comments'] = 'Status = '.$juanpay_data['status'] . 'Ref. # = ' . $juanpay_data['ref_number'] . 'Message ID = ' . $juanpay_data['message_id'];
		$this->_storeJuanPayInternalData($method, $juanpay_data, $virtuemart_order_id, $payments[0]->virtuemart_paymentmethod_id);
		$this->logInfo('plgVmOnPaymentNotification return new_status:' . $order['order_status'], 'message');

		$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, TRUE);
		if (isset($juanpay_data['return_url'])) {
			$this->emptyCart($juanpay_data['return_url'], $order_number);
		}
	}


	/**
	 * @param $method
	 * @param $juanpay_data
	 * @param $virtuemart_order_id
	 */
	function _storeJuanPayInternalData($method, $juanpay_data, $virtuemart_order_id, $virtuemart_paymentmethod_id) {

		// get all know columns of the table
		$db = JFactory::getDBO();
		$query = 'SHOW COLUMNS FROM `' . $this->_tablename . '` ';
		$db->setQuery($query);
		$columns = $db->loadResultArray(0);
		$post_msg = '';
		foreach ($juanpay_data as $key => $value) {
			$post_msg .= $key . "=" . $value . "<br />";
			$table_key = 'juanpay_response_' . $key;
			if (in_array($table_key, $columns)) {
				$response_fields[$table_key] = $value;
			}
		}

		//$response_fields[$this->_tablepkey] = $this->_getTablepkeyValue($virtuemart_order_id);
		$response_fields['payment_name'] = $this->renderPluginName($method);
		$response_fields['juanpayresponse_raw'] = $post_msg;
		$response_fields['order_number'] = $juanpay_data['invoice'];
		$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
		$response_fields['virtuemart_paymentmethod_id'] = $virtuemart_paymentmethod_id;
		$response_fields['juanpay_custom'] = $juanpay_data['custom'];

		//$preload=true   preload the data here too preserve not updated data
		$this->storePSPluginInternalData($response_fields);
	}

	/**
	 * Display stored payment data for an order
	 *
	 * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {

		if (!$this->selectedThisByMethodId($payment_method_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}

		$html = '<table class="adminlist" width="50%">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$code = "juanpay_response_";
		$first = TRUE;
		foreach ($payments as $payment) {
			$html .= '<tr class="row1"><td>' . JText::_('VMPAYMENT_JUANPAY_DATE') . '</td><td align="left">' . $payment->created_on . '</td></tr>';
			// Now only the first entry has this data when creating the order
			if ($first) {
				$html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $payment->payment_name);
				// keep that test to have it backwards compatible. Old version was deleting that column  when receiving an IPN notification
				if ($payment->payment_order_total and  $payment->payment_order_total != 0.00) {
					$html .= $this->getHtmlRowBE('JUANPAY_PAYMENT_ORDER_TOTAL', $payment->payment_order_total . " " . shopFunctions::getCurrencyByID($payment->payment_currency, 'currency_code_3'));
				}
				if ($payment->email_currency and  $payment->email_currency != 0) {
					$html .= $this->getHtmlRowBE('JUANPAY_PAYMENT_EMAIL_CURRENCY', shopFunctions::getCurrencyByID($payment->email_currency, 'currency_code_3'));
				}
				$first = FALSE;
			}
			foreach ($payment as $key => $value) {
				// only displays if there is a value or the value is different from 0.00 and the value
				if ($value) {
					if (substr($key, 0, strlen($code)) == $code) {
						$html .= $this->getHtmlRowBE($key, $value);
					}
				}
			}

		}
		$html .= '</table>' . "\n";
		return $html;
	}



	/**
	 * Get ipn data, send verification to JuanPay, run corresponding handler
	 *
	 * @param array $data
	 * @return string Empty string if data is valid and an error message otherwise
	 * @access protected
	 */
	private function _processIPN($juanpay_data, $method) {
      $hashedvalue = $this->juanpay_hash($juanpay_data, $method);
	  if ($hashedvalue!=$juanpay_data['hash']) {
	    $this->logInfo('Invalid Hash '.$hashedvalue, 'message');
        die('invalid hash');
      }

      $req = '';
	  if(function_exists('get_magic_quotes_gpc')) {
	    $get_magic_quotes_exists = true;
	  } 
	  foreach ($juanpay_data as $key => $value) {
	    if($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
	       $value = urlencode(stripslashes($value));
	    } else {
	       $value = urlencode($value);
	    }
	    $req .= "$key=$value&";
	  }

      $juanpay_url = $this->_getJuanPayUrl($method)."/dpn/validate";
      $ch = curl_init($juanpay_url);
      if ($ch == FALSE) {
         return FALSE;
      }

	  curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
	  curl_setopt($ch, CURLOPT_POST, 1);
	  curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	  curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
	  curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

      $res = curl_exec($ch);
      if (curl_errno($ch) != 0) // cURL error
	  {
	    $this->logInfo('CURL Error ', 'message');		
	    curl_close($ch);
	    exit;
      } else {
	    curl_close($ch);
      }

      if (strcmp ($res, "VERIFIED") == 0) {
	    $this->logInfo('Message Verified', 'message');
        return true;
      } else if (strcmp ($res, "INVALID") == 0) {
	    $this->logInfo('Message Invalid', 'message');
        return true;
      }

	}


	/**
	 * @param $method
	 * @return mixed
	 */
	function _getMerchantEmail($method) {

		return $method->sandbox ? $method->sandbox_merchant_email : $method->juanpay_merchant_email;
	}

    /**
     * @param $method
     * @return mixed
     */
    function _getAPIKey($method) {

        return $method->sandbox ? $method->sandbox_api_key : $method->juanpay_api_key;
    }


    /**
	 * @param $method
	 * @return string
	 */
	function _getJuanPayUrl($method) {

		$url = $method->sandbox ? 'localhost:3000' : 'www.juanpay.ph';

		return $url;
	}

	/**
	 * @param $method
	 * @return string
	 */
	function _getJuanPayUrlHttps($method) {

		$url = $this->_getJuanPayUrl($method);
		$url = $url . '/checkout';

		return $url;
	}

	/**
	 * @param $juanpayTable
	 * @param $payment_name
	 * @return string
	 */
	function _getPaymentResponseHtml($juanpayTable, $payment_name) {
		VmConfig::loadJLang('com_virtuemart');

		$html = '<table>' . "\n";
		$html .= $this->getHtmlRow('COM_VIRTUEMART_PAYMENT_NAME', $payment_name);
		if (!empty($juanpayTable)) {
			$html .= $this->getHtmlRow('JUANPAY_ORDER_NUMBER', $juanpayTable->order_number);
			//$html .= $this->getHtmlRow('JUANPAY_AMOUNT', $juanpayTable->payment_order_total. " " . $juanpayTable->payment_currency);
		}
		$html .= '</table>' . "\n";

		return $html;
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 *
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions($cart, $method, $cart_prices) {

		$this->convert_condition_amount($method);
		$amount = $this->getCartAmount($cart_prices);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));

		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}
		// probably did not gave his BT:ST address
		if (!is_array($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
			if ($amount_cond) {
				return TRUE;
			}
		}

		return FALSE;
	}



	/**
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {

		return $this->OnSelectCheck($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE($cart, $selected, $htmlIn);
	}


	/**
	 * @param VirtueMartCart $cart
	 * @param array $cart_prices
	 * @param                $cart_prices_name
	 * @return bool|null
	 */
	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}


	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}


	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {

		return $this->onShowOrderPrint($order_number, $method_id);
	}


	function plgVmDeclarePluginParamsPayment($name, $id, &$data) {

		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	/**
	 * @param $name
	 * @param $id
	 * @param $table
	 * @return bool
	 */
	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {

		return $this->setOnTablePluginParams($name, $id, $table);
	}

}

// No closing tag
