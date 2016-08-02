<?php

class Customer extends DataObject {

	private static $singular_name = 'Customer';
	private static $plural_name = 'Customers';
	private static $description = '';

	private static $db = array(
    'FirstName' => 'Varchar(255)',
    'LastName' => 'Varchar(255)',
    'Company' => 'Varchar(255)',
    'Phone' => 'Varchar(255)',
    'Email' => 'Varchar(255)',
    'Address'  => 'Varchar(255)',
		'Address2'  => 'Varchar(255)',
		'City'   => 'Varchar(64)',
		'State'    => 'Varchar(64)',
		'Postcode' => 'Varchar(10)',
		'Country'  => 'Varchar(2)'
  );

	private static $has_one = array(
    'Order' => 'Order'
  );

	private static $summary_fields = array(
    'FirstName',
    'LastName',
    'Email',
    'City',
    'State'
  );

	public function canView($member = false) {
		return Permission::check('Product_ORDERS');
	}

	public function canEdit($member = null) {
    return false;
	}

	public function canDelete($member = null) {
		return Permission::check('Product_ORDERS');
	}

	public function canCreate($member = null) {
		return false;
	}

}
