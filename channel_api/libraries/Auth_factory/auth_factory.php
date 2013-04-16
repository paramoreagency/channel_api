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

class Auth_factory
{
    /**
     * @var CI_Controller
     */
    public $EE;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->EE =& get_instance();
    }

    /**
     * @param string $service_name
     * @return Auth_service
     * @throws Exception
     */
    public function instantiate_service($service_name)
    {
        $service_name = strtolower($service_name);
        $service_path = __DIR__ . "/services/{$service_name}/auth_service_{$service_name}.php";

        if (! file_exists($service_path)) {
            throw new Exception("Auth service {$service_name} does not exist.", 401);
        }

        require_once($service_path);

        $service_class = "Auth_service_{$service_name}";

        if (! class_exists($service_class)) {
            throw new Exception("Cannot load the {$service_name} auth service.", 401);
        }

        return new $service_class();
    }
}

/* End of file auth_factory.php */
