<?php  if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package     ExpressionEngine
 * @author      ExpressionEngine Dev Team
 * @copyright   Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license     http://expressionengine.com/user_guide/license.html
 * @link        http://expressionengine.com
 * @since       Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Channel API Module Front End File
 *
 * @package     ExpressionEngine
 * @subpackage  Addons
 * @category    Module
 * @author      Jared Burke
 * @link        http://paramoredigital.com
 * @since       EE Version 2.2.0
 */

class Channel_api_config
{
    /**
     * @var string
     */
    public $assets_keyword = 'assets';

    /**
     * @var array
     * Case-sensitive
     */
    public $auth_channels = array(
        'members' => array('post'),
        'assets' => array('post')
    );

    /**
     * @var string
     */
    public $auth_keyword = 'auth';

    /**
     * @var string
     */
    public $auth_req_type = 'blacklist'; /* <blacklist|whitelist> */

    /**
     * @var string
     */
    public $default_auth_service = 'native';

    /**
     * @var string
     */
    public $default_author_id = '8';

    /**
     * @var string
     */
    public $default_new_entry_status = 'open';

    /**
     * @var string
     */
    public $post_entry_field_prefix = 'ee_';

    /**
     * @var string
     */
    public $service_header = 'Auth-service';

    /**
     * @var string
     */
    public $token_header = 'Api-access-token';

    /**
     * @var string
     */
    public $upload_entry_detail_base_url = 'http://bluechairbayrum.com/gallery/';

}