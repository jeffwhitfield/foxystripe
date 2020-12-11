<?php

define('MODULE_FOXYSTRIPE_DIR', basename(dirname(__FILE__)));

if (!class_exists('SS_Object')) class_alias('Object', 'SS_Object');

/**
 * FoxyStripe config - Change password encryption to something compatible with FoxyCart
 */

Config::inst()->update('Security', 'password_encryption_algorithm', 'sha1_v2.4');
