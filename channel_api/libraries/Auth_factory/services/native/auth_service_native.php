<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(realpath(__DIR__ . '/../auth_service.php'));

class Auth_service_native implements Auth_service
{
    protected $EE;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->EE =& get_instance();
        $this->EE->load->model('members_api');
        $this->EE->load->library('auth');
    }

    /**
     * @param null $username
     * @param null $password
     * @return array
     * @throws Exception
     */
    public function authenticate($username = NULL, $password = NULL)
    {
        if ( ! isset ($username) OR ! isset($password) OR (empty($username) && empty($password)))
            throw new Exception("A username and password is required", 401);

        if ($this->EE->session->check_password_lockout($username) === TRUE)
            throw new Exception("The user is locked out.", 401);

        $authed = $this->EE->auth->authenticate_username($username, $password);

        if ($authed
          AND $member = $this->EE->members_api->lookup_member(array('username' => $authed->member('username')))
        )
            return $this->EE->members_api->set_api_token($member);

        else
            throw new Exception("The username or password is incorrect.", 401);
    }

    /**
     * @param string $token
     * @return bool
     */
    public function validate_access_token($token)
    {
        return $this->EE->members_api->lookup_api_token($token);
    }
}

/* End of file auth_service_native.php */