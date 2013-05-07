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

class Api_auth {

    /**
     * @var CI_Controller
     */
    public $EE;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $token_field_name = 'api_token';

    /**
     * @var string
     */
    private $token_field_column;

    /**
     * @var string
     */
    private $auth_service_field_name = 'auth_service';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->EE =& get_instance();
        $this->EE->load->library('auth');
        $this->set_token_field_column();
    }

    /**
     * @return object|null
     */
    private function authorize_member()
    {
        $username = (isset($_SERVER['PHP_AUTH_USER'])) ? $_SERVER['PHP_AUTH_USER'] : NULL;
        $password = (isset($_SERVER['PHP_AUTH_PW'])) ? $_SERVER['PHP_AUTH_PW'] : NULL;

        if ($member = $this->validate_auth_service($username))
            return $member;
        else
            return $this->EE->auth->authenticate_username($username, $password);
    }

    /**
     * @return void
     */
    public function set_api_token()
    {
        if (! isset($this->token_field_column)) return;

        $authed = $this->authorize_member();

        if (is_object($authed)) {
            $member_id = $authed->member('member_id');
            $token = $this->EE->security->xss_hash();

            $this->EE->db->update(
                'exp_member_data',
                array($this->token_field_column => $token),
                "member_id = {$member_id}"
            );

            if ($this->EE->db->affected_rows() > 0)
                $this->token = $token;
        }
    }

    /**
     * @param $token
     */
    public function clear_api_token($token)
    {
        $this->EE->db->update(
            'exp_member_data',
            array($this->token_field_column => ''),
            $this->token_field_column . ' = ' . $token
        );

        if ($this->EE->db->affected_rows() > 0)
            $this->token = NULL;
    }

    /**
     * @return string
     */
    public function get_api_token()
    {
        return $this->token;
    }

    /**
     * @return bool
     */
    public function is_authorized_request()
    {
        $headers = apache_request_headers();
        $token = (isset($headers['EE-ACCESS-TOKEN']) )
          ? $headers['EE-ACCESS-TOKEN']
          : NULL;

        if (! isset($this->token_field_column) OR is_null($token)) return FALSE;

        $query = $this->EE->db
          ->where($this->token_field_column, $token)
          ->get('exp_member_data');

        if ($query->num_rows() > 0) {
            $row = $query->row_array();

            return ($this->validate_auth_service(intval($row['member_id'])))
              ? TRUE
              : FALSE;

        } else return FALSE;
    }

    /**
     * @param $field_name
     * @return null|string
     */
    private function lookup_custom_field_column($field_name)
    {
        $query = $this->EE->db
          ->select('m_field_id')
          ->where('m_field_name', $field_name)
          ->limit(1)
          ->get('exp_member_fields');

        if ($query->num_rows() > 0) {
            $row = $query->row_array();

            return 'm_field_id_' . $row['m_field_id'];

        } else return NULL;
    }

    /**
     * @param int $member_id
     * @param string $username
     * @return object|null
     */
    private function validate_auth_service($member_id = NULL, $username = '')
    {
        $member = (is_null($member_id))
          ? $this->lookup_member(array('username' => $username))
          : $this->lookup_member(array('member_id' => $member_id));

        if ($auth_service_name = $this->get_auth_service_name($member->member_id)) {
            $service = $this->instantiate_service($auth_service_name);
            $is_authenticated = $service->is_authenticated();

            if ($is_authenticated AND is_null($member))
                $member = $service->create_member();

            return ($is_authenticated)
              ? $member
              : NULL;

        } else return NULL;
    }

    /**
     * @param object $member
     * @return null
     */
    private function get_auth_service_name($member)
    {
        if (is_null($member))
            return ($this->EE->uri->segment(2))
              ? $this->EE->uri->segment(2)
              : NULL;

        $column = $this->lookup_custom_field_column($this->auth_service_field_name);

        $query = $this->EE->db
          ->select($column)
          ->where('member_id', $member->id)
          ->get('exp_member_data');

        if ($query->num_rows() > 0) {
            $row = $query->row_array();

            return $row[$column];

        } else return NULL;
    }

    /**
     * @param string $service_name
     * @return Auth_service
     */
    private function instantiate_service($service_name)
    {
        $service_name = strtolower($service_name);
        $service_path = __DIR__ . "/services/{$service_name}/auth_service_{$service_name}";

        if (! file_exists($service_path)) {
            #TODO Throw error
        }

        require_once($service_path);

        $service_class = "Auth_service_{$service_name}";

        if (! class_exists($service_class)) {
            #TODO Throw error
        }

        return new $service_class();
    }

    /**
     * @param string|array $needle
     * @return object|null
     */
    private function lookup_member($needle)
    {
        if (! is_array($needle))
            $needle = array('username' => $needle);

        $query = $this->EE->db
          ->select('m.member_id, m.group_id, m.username, m.screen_name, m.email, md.*')
          ->join('exp_member_data AS md ON md.member_id = m.member_id')
          ->where($needle)
          ->limit(1)
          ->get('exp_members AS m');

        return ($query->num_rows() > 0)
          ? $query->row()
          : NULL;
    }
}

/* End of file api_auth.php */
