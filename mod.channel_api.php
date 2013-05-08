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
 * @author      Ben Wilkins
 * @link        http://paramoredigital.com
 * @since       EE Version 2.2.0
 */

class Channel_api
{
    /**
     * @var CI_Controller
     */
    public $EE;

    /**
     * @var string
     */
    public $return_data = array(
        'errors' => array(),
        'results' => array()
    );

    /**
     * @var string
     */
    private $token_header = 'Api-access-token';

    /**
     * @var string
     */
    private $service_header = 'Auth-service';

    /**
     * @var string
     */
    private $auth_keyword = 'auth';

    /**
     * @var string
     */
    private $assets_keyword = 'assets';

    /**
     * @var string
     */
    private $default_auth_service = 'native';

    /**
     * @var string
     */
    protected $verb;

	/**
	 * @var string
	 */
	protected $auth_config_req_type = 'blacklist'; /* <blacklist|whitelist> */

	/**
	 * @var array
	 * Case-sensitive
	 */
	protected $auth_config_channels = array(
		'members' => array('post'),
        'assets' => array('post')
	);

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->EE =& get_instance();
        $this->EE->lang->loadfile('channel_api');
        $this->EE->load->driver('auth_factory');
        $this->EE->load->model('error_response');
        $this->EE->load->model('api_model');
        $this->EE->load->model('members_api');
    }

    /**
     * @return mixed
     */
    public function run()
    {
        $this->set_verb();

        //this tricks the output class into NOT sending its own headers
        $this->EE->TMPL->template_type = 'cp_asset';
        $this->set_response_headers();

        // return no content for CORS request.
        if ($this->verb == 'options') return '';

        $this->route_request();

        $this->return_data['errors'] = $this->EE->error_response->send_error_response();

        return json_encode($this->return_data);
    }

    /**
     * @return void
     */
    private function set_response_headers()
    {
        $this->EE->output->set_header('Content-Type: application/json; charset=utf-8');
        $this->EE->output->set_header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        $this->EE->output->set_header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, HEAD, OPTIONS');
        $this->EE->output->set_header('Access-Control-Allow-Headers: Authorization, X-Requested-With, Origin, Content-Type, Accept, '
          . $this->service_header . ', ' . $this->token_header);
        $this->EE->output->set_header('Access-Control-Max-Age: 604800');
        $this->EE->output->set_header('Access-Control-Allow-Credentials: true');
    }

    /**
     * @return void
     */
    private function route_request()
    {
        switch ($this->EE->uri->segment(1)) {
            case $this->auth_keyword:
                $this->login_member();
                break;

            case $this->assets_keyword:
                if ($this->authenticate_request())
                    $this->upload_to_assets();
                break;

            default:
                if ($this->authenticate_request())
                    $this->execute_request();

                break;
        }
    }

    /**
     * @return void
     */
    private function upload_to_assets()
    {
    	/* perform upload */
    	$upload_opts = array(
    		'upload_path' => $this->EE->api_model->get_upload_path(
    			$this->EE->input->post('upload_path_id')
    		)
		);

    	$this->return_data = $this->EE->api_model->upload_to_assets($upload_opts);

		return;
    }

    /**
     * @return void
     */
    private function login_member()
    {
        $username = $this->retrieve_http_basic_user();
        $password = $this->retrieve_http_basic_password();
        $service = ($this->EE->uri->segment(2)) ? $this->EE->uri->segment(2) : $this->default_auth_service;

        try{
            $auth_service = $this->EE->auth_factory->instantiate_service($service);
            $member = $auth_service->authenticate($username, $password);
            if (! is_null($member['token'])) {
                $data = array(
                    'api_token' => $member['token'],
                    'auth_service' => $service
                );

                // ----------------------------------
                // Hook: channel_api_auth_login_end
                // Do additional processing on the return data.
                if ($this->EE->extensions->active_hook('channel_api_auth_login_end') === TRUE)
                    $data = $this->EE->extensions->call('channel_api_auth_login_end', $data, $member);

                $this->return_data['results'] = $data;
            }

        } catch(Exception $e) {
            $this->EE->error_response
              ->set_http_response_code($e->getCode())
              ->set_error($e->getMessage());
        }
    }

    /**
     * @return null|string
     */
    private function retrieve_http_basic_user()
    {
        $user = NULL;

        if (isset($_SERVER['PHP_AUTH_USER']))
            $user = $_SERVER['PHP_AUTH_USER'];

        elseif (isset($_ENV['REMOTE_USER']))
            $user = $_ENV['REMOTE_USER'];

        elseif ( @getenv('REMOTE_USER'))
            $user = getenv('REMOTE_USER');

        elseif (isset($_ENV['AUTH_USER']))
            $user = $_ENV['AUTH_USER'];

        elseif ( @getenv('AUTH_USER'))
            $user = getenv('AUTH_USER');

        return $user;
    }

    /**
     * @return null|string
     */
    private function retrieve_http_basic_password()
    {
        $pass = NULL;

        if (isset($_SERVER['PHP_AUTH_PW']))
            $pass = $_SERVER['PHP_AUTH_PW'];

        elseif (isset($_ENV['REMOTE_PASSWORD']))
            $pass = $_ENV['REMOTE_PASSWORD'];

        elseif ( @getenv('REMOTE_PASSWORD'))
            $pass = getenv('REMOTE_PASSWORD');

        elseif (isset($_ENV['AUTH_PASSWORD']))
            $pass = $_ENV['AUTH_PASSWORD'];

        elseif ( @getenv('AUTH_PASSWORD'))
            $pass = getenv('AUTH_PASSWORD');

        return $pass;
    }

    /**
     * @return bool
     */
    private function authenticate_request()
    {
		if(! $this->authentication_required()) return TRUE;
	
        $auth_service_name = $this->get_auth_service();
        $token = $this->get_access_token();

        try{
            $auth_service = $this->EE->auth_factory->instantiate_service($auth_service_name);

            if ($auth_service->validate_access_token($token))
                return TRUE;

            else
                $this->EE->error_response
                  ->set_http_response_code(401)
                  ->set_error("Not authenticated via {$auth_service_name}.");

        } catch (Exception $e) {
            $this->EE->error_response
              ->set_http_response_code($e->getCode())
              ->set_error($e->getMessage());
        }

        return FALSE;
    }

	/**
	 * @return boolean 
	 */
	private function authentication_required()
	{
		$request_in_config = (bool) ( 
			isset($this->auth_config_channels[$this->EE->uri->segment(1)]) && 
			in_array($this->verb, $this->auth_config_channels[$this->EE->uri->segment(1)])
		);

		switch( $this->auth_config_req_type )
		{
			/* incoming route must be in list to require auth */
			case 'whitelist':
				return $request_in_config ? TRUE : FALSE;
				break;

			/* having this as the default will, by default, require all requests to be auth'd */
			case 'blacklist':
			default:
				/* incoming routes NOT in config require auth */
				return $request_in_config ? FALSE : TRUE;
			
		}
		
		return TRUE;
	}

    /**
     * @return void
     */
    private function execute_request()
    {
        $method = "channel_" . $this->verb;

        if (! method_exists($this->EE->api_model, $method))
            $this->EE->error_response
              ->set_http_response_code(403)
              ->set_error("Unable to process request.");

        $this->EE->api_model->set_channel($this->EE->uri->segment(1));

        if ($this->EE->uri->segment(2))
            $this->EE->api_model->set_entry_id($this->EE->uri->segment(2));

        if ($this->EE->uri->segment(3))
            $this->EE->api_model->set_field($this->EE->uri->segment(3));

        if (! empty($_GET))
            $this->EE->api_model->set_params($_GET);

        $this->return_data['total_records_in_channel'] = $this->EE->api_model->count_entries_in_channel();
        $this->return_data['results'] = $this->EE->api_model->$method();
    }

    /**
     * @return void
     */
    private function set_verb()
    {
        $this->verb = strtolower($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @return string
     */
    private function get_auth_service()
    {
        return ($this->EE->input->get_request_header($this->service_header))
          ? $this->EE->input->get_request_header($this->service_header)
          : $this->default_auth_service;
    }

    /**
     * @return string
     */
    private function get_access_token()
    {
        return $this->EE->input->get_request_header($this->token_header);
    }

}

/* End of file mod.channel_api.php */