<?php
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

define("AUTHORIZENET_LOG_FILE", "authorizenet.log");

class FoxyStripe_Controller extends Page_Controller {

	const URLSegment = 'foxystripe';

	public function getURLSegment() {
		return self::URLSegment;
	}

	static $allowed_actions = array(
		'index',
        'sso'
	);

	public function index() {
	    // handle POST from FoxyCart API transaction
		if ((isset($_POST["FoxyData"]) OR isset($_POST['FoxySubscriptionData']))) {
			$FoxyData_encrypted = (isset($_POST["FoxyData"])) ?
                urldecode($_POST["FoxyData"]) :
                urldecode($_POST["FoxySubscriptionData"]);
			$FoxyData_decrypted = rc4crypt::decrypt(FoxyCart::getStoreKey(),$FoxyData_encrypted);
			self::handleDataFeed($FoxyData_encrypted, $FoxyData_decrypted);

			// extend to allow for additional integrations with Datafeed
			$this->extend('addIntegrations', $FoxyData_encrypted);

			return 'foxy';

		} else {

			return "No FoxyData or FoxySubscriptionData received.";

		}
	}

    public function handleDataFeed($encrypted, $decrypted){
        //handle encrypted & decrypted data
        $orders = new SimpleXMLElement($decrypted);

        // loop over each transaction to find FoxyCart Order ID
        foreach ($orders->transactions->transaction as $order) {

            if (isset($order->id)) {
                ($transaction = Order::get()->filter('Order_ID', $order->id)->First()) ?
                    $transaction :
                    $transaction = Order::create();
            }

            // save base order info
            $transaction->Order_ID = (int) $order->id;
            $transaction->Response = $decrypted;

            // record transaction as order
            $transaction->write();

            // parse order
            $this->parseOrder($order->id);

			if($order->payment_gateway_type == 'authorize'){
				$transArr = array();
				$trans_id = explode(':', (string)$order->processor_response);
				$transArr['trans_id'] = $trans_id[1];
				$transArr['trans_name'] = (string)$order->customer_first_name." ".$order->customer_last_name;
				$transArr['trans_email'] = (string)$order->customer_email;
				$transArr['trans_customer_id'] = (int)$order->customer_id;
				$this->createCustomerProfileFromTransaction($transArr);
			}
        }
    }

    public function parseOrder($Order_ID) {

        $transaction = Order::get()->filter(array('Order_ID' => $Order_ID))->First();

        if ($transaction) {
            // grab response, parse as XML
            $orders = new SimpleXMLElement($transaction->Response);

            $this->parseOrderInfo($orders, $transaction);
            $this->parseOrderCustomer($orders, $transaction);
            $this->parseOrderCustomFields($orders, $transaction);
            // record transaction so user info can be accessed from parseOrderDetails()
            $transaction->write();
            $this->parseOrderDetails($orders, $transaction);

            // record transaction as order
            $transaction->write();
        }
    }

    public function parseOrderInfo($orders, $transaction) {

        foreach ($orders->transactions->transaction as $order) {

            // Record transaction data from FoxyCart Datafeed:
            $transaction->Store_ID = (int)$order->store_id;
            $transaction->TransactionDate = (string)$order->transaction_date;
            $transaction->ProductTotal = (float)$order->product_total;
            $transaction->TaxTotal = (float)$order->tax_total;
            $transaction->ShippingTotal = (float)$order->shipping_total;
            $transaction->ShippingMethod = (string)$order->shipto_shipping_service_description;
            $transaction->OrderTotal = (float)$order->order_total;
            $transaction->ReceiptURL = (string)$order->receipt_url;
            $transaction->OrderStatus = (string)$order->status;
        }
    }

    public function parseOrderCustomFields($orders, $transaction) {

      foreach ($orders->transactions->transaction as $order) {

        // Loop through all custom fields
        foreach ($order->custom_fields->custom_field as $field) {

          if(!empty($field->custom_field_value)){
            $CustomField = OrderCustomField::create();

            // set name and value
            $CustomField->FieldName = (string)$field->custom_field_name;
            $CustomField->FieldValue = (string)$field->custom_field_value;

            // associate with this order
            $CustomField->OrderID = $transaction->ID;

            // write
            $CustomField->write();
          }

        }

        // Loop through tax rates and add them in as custom fields
        foreach ($order->taxes->tax as $tax) {

          if(!empty($tax->tax_rate)){
            $CustomField = OrderCustomField::create();

            // set name and value
            $CustomField->FieldName = 'Tax_Rate';
            $CustomField->FieldValue = (float)$tax->tax_rate;

            // associate with this order
            $CustomField->OrderID = $transaction->ID;

            // write
            $CustomField->write();
          }

        }

      }

    }

    public function parseOrderCustomer($orders, $transaction) {

        foreach ($orders->transactions->transaction as $order) {

            // if not a guest transaction in FoxyCart
            if (isset($order->customer_email) && $order->is_anonymous == 0) {

                // if Customer is existing member, associate with current order
                if(Member::get()->filter('Email', $order->customer_email)->First()) {

                    $customer = Member::get()->filter('Email', $order->customer_email)->First();

                } else {

                    // set PasswordEncryption to 'none' so imported, encrypted password is not encrypted again
                    Config::inst()->update('Security', 'password_encryption_algorithm', 'none');

                    // create new Member, set password info from FoxyCart
                    $customer = Member::create();
                    $customer->Customer_ID = (int)$order->customer_id;
                    $customer->FirstName = (string)$order->customer_first_name;
                    $customer->Surname = (string)$order->customer_last_name;
                    $customer->Email = (string)$order->customer_email;
                    $customer->Password = (string)$order->customer_password;
                    $customer->Salt = (string)$order->customer_password_salt;
                    $customer->PasswordEncryption = 'none';

                    // record member record
                    $customer->write();
                }

                // set Order MemberID
                $transaction->MemberID = $customer->ID;

            }

            // Save Customer information
            if (isset($order->customer_email)) {
              $customer = Customer::create();
              $customer->FirstName = (string)$order->customer_first_name;
              $customer->LastName = (string)$order->customer_last_name;
              $customer->Company = (string)$order->customer_company;
              $customer->Phone = (string)$order->customer_phone;
              $customer->Email = (string)$order->customer_email;
              $customer->Address = (string)$order->customer_address1;
              $customer->Address2 = (string)$order->customer_address2;
              $customer->City = (string)$order->customer_city;
              $customer->State = (string)$order->customer_state;
              $customer->Postcode = (string)$order->customer_postal_code;
              $customer->Country = (string)$order->customer_country;
              $customer->OrderID = $transaction->ID;
              $customer->write();
              $transaction->CustomerID = $customer->ID;
            }
            // Save Shipping information
            if (isset($order->shipping_address1)) {
              $shipping = Shipping::create();
              $shipping->FirstName = (string)$order->shipping_first_name;
              $shipping->LastName = (string)$order->shipping_last_name;
              $shipping->Company = (string)$order->shipping_company;
              $shipping->Phone = (string)$order->shipping_phone;
              $shipping->Address = (string)$order->shipping_address1;
              $shipping->Address2 = (string)$order->shipping_address2;
              $shipping->City = (string)$order->shipping_city;
              $shipping->State = (string)$order->shipping_state;
              $shipping->Postcode = (string)$order->shipping_postal_code;
              $shipping->Country = (string)$order->shipping_country;
              $shipping->OrderID = $transaction->ID;
              $shipping->write();
              $transaction->ShippingID = $shipping->ID;
            }
        }
    }

    public function parseOrderDetails($orders, $transaction) {

        // remove previous OrderDetails so we don't end up with duplicates
        foreach ($transaction->Details() as $detail) {
            $detail->delete();
        }

        foreach ($orders->transactions->transaction as $order) {

            // Associate ProductPages, Options, Quanity with Order
            foreach ($order->transaction_details->transaction_detail as $product) {

                $OrderDetail = OrderDetail::create();

                // set Quantity
                $OrderDetail->Quantity = (int)$product->product_quantity;

                // set calculated price (after option modifiers)
                $OrderDetail->Price = (float)$product->product_price;

                // Find product via product_id custom variable
                foreach ($product->transaction_detail_options->transaction_detail_option as $productID) {
                    if ($productID->product_option_name == 'product_id') {

                        $OrderProduct = ProductPage::get()
                            ->filter('ID', (int)$productID->product_option_value)
                            ->First();

                        // if product could be found, then set Option Items
                        if ($OrderProduct) {

                            // set ProductID
                            $OrderDetail->ProductID = $OrderProduct->ID;

                            // loop through all Product Options
                            foreach ($product->transaction_detail_options->transaction_detail_option as $option) {

                                $OptionItem = OptionItem::get()->filter(array(
                                    'ProductID' => (string)$OrderProduct->ID,
                                    'Title' => (string)$option->product_option_value
                                ))->First();

                                if ($OptionItem) {
                                    $OrderDetail->Options()->add($OptionItem);

                                    // modify product price
                                    if ($priceMod = $option->price_mod) {
                                        $OrderDetail->Price += $priceMod;
                                    }
                                }
                            }
                        }
                    }

                    // associate with this order
                    $OrderDetail->OrderID = $transaction->ID;

                    // extend OrderDetail parsing, allowing for recording custom fields from FoxyCart
                    $this->extend('handleOrderItem', $decrypted, $product, $OrderDetail);

                    // Update inventory
                    if($ProductPage = DataObject::get_one('ProductPage',array('Code' => (string)$product->product_code))){
                      $ProductPage->Inventory = $ProductPage->Inventory - $OrderDetail->Quantity;
                      $ProductPage->write();
                      $ProductPage->publish("Live", "Stage");
                    }

                    // write
                    $OrderDetail->write();

                }
            }
        }
    }



	// Single Sign on integration with FoxyCart
    public function sso() {

	    // GET variables from FoxyCart Request
        $fcsid = $this->request->getVar('fcsid');
        $timestampNew = strtotime('+30 days');

        // get current member if logged in. If not, create a 'fake' user with Customer_ID = 0
        // fake user will redirect to FC checkout, ask customer to log in
        // to do: consider a login/registration form here if not logged in
        if($Member = Member::currentUser()) {
            $Member = Member::currentUser();
        } else {
            $Member = new Member();
            $Member->Customer_ID = 0;
        }

        $auth_token = sha1($Member->Customer_ID . '|' . $timestampNew . '|' . FoxyCart::getStoreKey());

        $redirect_complete = 'https://' . FoxyCart::getFoxyCartStoreName() . '.foxycart.com/checkout?fc_auth_token=' . $auth_token .
            '&fcsid=' . $fcsid . '&fc_customer_id=' . $Member->Customer_ID . '&timestamp=' . $timestampNew;

	    $this->redirect($redirect_complete);

    }


	public function createCustomerProfileFromTransaction($transaction)
	{
		/* Create a merchantAuthenticationType object with authentication details
		retrieved from the constants file */
		$merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
		$merchantAuthentication->setName('5MW2v8fC');
		$merchantAuthentication->setTransactionKey('6u78H3Yru99JF5P4');

		// Set the transaction's refId
		$refId = 'ref' . time();

		$customerProfile = new AnetAPI\CustomerProfileBaseType();
		$customerProfile->setMerchantCustomerId($transaction['trans_customer_id']);
		$customerProfile->setEmail($transaction['trans_email']);
		$customerProfile->setDescription($transaction['trans_name']);

		$request = new AnetAPI\CreateCustomerProfileFromTransactionRequest();
		$request->setMerchantAuthentication($merchantAuthentication);
		$request->setTransId($transaction['trans_id']);

		// You can either specify the customer information in form of customerProfileBaseType object
		$request->setCustomer($customerProfile);
		//  OR
		// You can just provide the customer Profile ID
			//$request->setCustomerProfileId("123343");

		$controller = new AnetController\CreateCustomerProfileFromTransactionController($request);

		$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);

		if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") ) {
//			echo "SUCCESS: PROFILE ID : " . $response->getCustomerProfileId() . "\n";
			$response = true;
		} else {
//			echo "ERROR :  Invalid response\n";
//			$errorMessages = $response->getMessages()->getMessage();
//			echo "Response : " . $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText() . "\n";
			$response = false;
		}
		return $response;
	}

}
