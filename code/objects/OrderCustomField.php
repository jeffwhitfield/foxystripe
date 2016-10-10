<?php
/**
 *
 * @package FoxyStripe
 *
 */

class OrderCustomField extends DataObject{

	private static $db = array(
		'FieldName' => 'Text',
		'FieldValue' => 'Text'
	);

  private static $has_one = array(
    'Order' => 'Order'
  );

	private static $summary_fields = array(
    'FieldName' => 'Field Name',
    'FieldValue' => 'Field Value'
	);

	public function canView($member = false) {
		return true;
	}

	public function canEdit($member = null) {
		return Permission::check('Product_CANCRUD');
	}

	public function canDelete($member = null) {
		return Permission::check('Product_CANCRUD');
	}

	public function canCreate($member = null) {
		return Permission::check('Product_CANCRUD');
	}

}
