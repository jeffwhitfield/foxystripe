<?php
/**
 *
 * @package FoxyStripe
 *
 */

class FoxyCart extends Object {

	static $foxyCartStoreName = '';
	static $storeKey = '';	// your foxy cart datafeed key

	public static function setStoreKey($key) {
		self::$storeKey = $key;
	}
	
	public static function setFoxyCartStoreName($name=null) {
		self::$foxyCartStoreName = $name;
	}
	
	public static function FormActionURL() {
		return sprintf('https://%s.foxycart.com/cart', self::$foxyCartStoreName );
	}
	
}