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

class Error_response {

    /**
     * @var int
     */
    private $http_response_code;

    /**
     * @var array
     */
    private $errors = array();

    /**
     * Constructor
     */
    public function __construct()
    {}

    /**
     * @param int $http_response_code
     * @return $this
     */
    public function set_http_response_code($http_response_code)
    {
        $this->http_response_code = $http_response_code;
        return $this;
    }

    /**
     * @param string $error
     * @return $this
     */
    public function set_error($error)
    {
        $this->errors[] = array('error_message' => $error);
        return $this;
    }

    /**
     * @return array
     */
    public function get_errors()
    {
        return $this->errors;
    }

    /**
     * @return string
     */
    public function send_error_response()
    {
        http_response_code($this->http_response_code);

        if (count($this->errors) > 0)
            return $this->errors;

        else
            return '';
    }
}

/* End of file error_response.php */
