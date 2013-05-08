<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Channel API Module Config File
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Module
 * @author		Jared Burke
 * @link		http://paramoredigital.com
 */

class Channel_api_config {

    /**
     * @var string
     */
    public $auth_req_type = 'blacklist'; /* <blacklist|whitelist> */

    /**
     * @var array
     * Case-sensitive
     */
    public $auth_channels = array(
        'photos' => array('post', 'get')
    );

}

?>