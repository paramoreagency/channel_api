<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(realpath(__DIR__ . '/../auth_service.php'));

class Auth_service_facebook implements Auth_service
{
    protected $EE;

    /**
     * @var array
     */
    private $config = array(
        'appId' => '572129106139195',
        'secret' => 'baa98ca8e78b9572bd320eb9b0673a04'
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        require_once(__DIR__ . '/sdk/facebook.php');
        $this->EE =& get_instance();
        $this->EE->load->model('members_api');
    }

    /**
     * @param null $username
     * @param null $password
     * @return array
     * @throws Exception
     */
    public function authenticate($username = NULL, $password = NULL)
    {
        $facebook = new Facebook($this->config);

        if ($facebook->getUser()) {
            $profile = $facebook->api('/me','GET');

            $needle = array('email' => $profile['email']);
            $member = $this->EE->members_api->lookup_member($needle);
            if (is_null($member)) {
                $data = array(
                    'title' => $profile['name'],
                    'member_firstname' => $profile['first_name'],
                    'member_lastname' => $profile['last_name'],
                    'gender' => $profile['gender'],
                    'facebook_profile' => $profile['link'],
                    'email' => $profile['email'],
                    'email_confirm' => $profile['email'],
                    'username' => $profile['username'],
                    'password' => hash('md5', $profile['id']),
                    'password_confirm' => hash('md5', $profile['id'])
                );

                $member = $this->EE->members_api->create_member($data);
            }

            $member = $this->EE->members_api->set_api_token($member);

            return $member;

        } else
            throw new Exception("User is not logged in to Facebook.", 401);
    }

    /**
     * @param string $token
     * @return bool
     */
    public function validate_access_token($token)
    {
        // Make sure still logged into FB
        $facebook = new Facebook($this->config);

        if ($facebook->getUser()) {
            // Look up the member by email
            $profile = $facebook->api('/me', 'GET');
            $needle = array('email' => $profile['email']);

            if ($this->EE->member_api->get_api_token($needle) == $token)
                return TRUE;

            else return FALSE;

        } else return FALSE;
    }
}

/* End of file auth_service_facebook.php */