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

Class Members_api
{
    protected $EE;
    private $token;
    private $token_field_column;
    /**
     * @var string
     */
    private $token_field_name = 'api_token';

    public function __construct()
    {
        $this->EE =& get_instance();
        $this->EE->load->library('auth');
        $this->EE->load->model('api_model');
        $this->set_token_field_column();
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
     * @param array $needle
     * @return string
     */
    public function get_api_token($needle = array())
    {
        if ($member = $this->lookup_member($needle))
            return $member[$this->token_field_column];

        else return NULL;
    }

    /**
     * @param string $token
     * @return bool
     */
    public function lookup_api_token($token)
    {
        $sql = "SELECT member_id FROM exp_member_data WHERE {$this->token_field_column} = '{$token}'";
        $query = $this->EE->db->query($sql);

        return ($query->num_rows() > 0);
    }

    /**
     * @param array $member
     * @return array
     * @throws Exception
     */
    public function set_api_token($member)
    {
        if (! isset($this->token_field_column))
            throw new Exception("Unable to set member token.", 403);

        $token = $this->EE->security->xss_hash();

        $this->EE->db->update(
            'exp_member_data',
            array($this->token_field_column => $token),
            "member_id = {$member['member_id']}"
        );

        if ($this->EE->db->affected_rows() > 0) {
            $member['token'] = $token;
            return $member;

        } else
            throw new Exception("Unable to set member token.", 403);
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
     * @return void
     */
    private function set_token_field_column()
    {
        $this->token_field_column = $this->lookup_custom_field_column($this->token_field_name);
    }



    /**
     * @param string|array $needle
     * @return array|null
     */
    public function lookup_member($needle)
    {
        if (! is_array($needle))
            $needle = array('username' => $needle);

        $sql = "SELECT m.member_id, m.group_id, m.username, m.screen_name, m.email, md.*
          FROM exp_members AS m
          JOIN exp_member_data AS md on md.member_id = m.member_id";

        foreach ($needle as $col => $val)
            $sql .= " WHERE m.{$col} = '{$val}'";

        $sql .= " LIMIT 1";

        $query = $this->EE->db->query($sql);

        return ($query->num_rows() > 0)
          ? $query->row_array()
          : NULL;
    }

    public function create_member($data)
    {
        $this->EE->api_model->channel_post('members', NULL, NULL, $data);

        return $this->lookup_member(array('email' => $data['email']));
    }
}
/* End of file members_api.php */
