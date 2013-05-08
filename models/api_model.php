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

class Api_model
{
    /**
     * @var CI_Controller
     */
    public $EE;

    /**
     * @var object
     */
    protected $channel = NULL;

    /**
     * @var int
     */
    protected $entry_id = NULL;

    /**
     * @var string
     */
    protected $field = NULL;

    /**
     * @var array
     */
    protected $params = array(
        'order_by' => 'entry_id',
        'sort' => 'DESC',
        'limit' => FALSE,
        'offset' => 0
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->EE =& get_instance();
        $this->EE->load->model('error_response');
        $this->EE->load->driver('channel_data');
        $this->EE->api->instantiate('channel_fields');
        $this->EE->api->instantiate('channel_entries');
    }

    /**
     * @param string $channel_name
     * @return void
     */
    public function set_channel($channel_name)
    {
        if ($channel_name)
            $this->channel = $this->EE->channel_data->get_channel_by_name($channel_name)->row();
    }

    /**
     * @param int $entry_id
     */
    public function set_entry_id($entry_id)
    {
        $this->entry_id = $entry_id;
    }

    /**
     * @param string $field
     */
    public function set_field($field)
    {
        $this->field = $field;
    }

    /**
     * @param array $params
     */
    public function set_params($params)
    {
        $this->params = $params;
    }

    /**
     * @param int $channel_id
     * @return int
     */
    public function count_entries_in_channel($channel_id = NULL)
    {
        if (is_null($channel_id))
            $channel_id = $this->channel->channel_id;

        return $this->EE->db
          ->query(
              "SELECT COUNT(*) AS count
             FROM `exp_channel_titles` 
             WHERE `channel_id` = {$channel_id}")
          ->row('count');
    }

    /**
     * @return bool
     */
    private function channel_exists()
    {
        return (is_object($this->channel)) ? TRUE : FALSE;
    }

    /**
     * @return array
     * @throws exception
     */
    public function channel_get()
    {
        if ($this->field)
            return $this->fetch_entry_field();

        elseif ($this->entry_id)
            return $this->fetch_entry($this->entry_id);

        elseif ($this->channel)
            return $this->list_entries();

        else
            throw new Exception($this->EE->lang->line('error_no_channel'), 400);
    }

    private function fetch_entry_field()
    {
        $field = $this->EE->channel_data->get_field_by_name($this->field)->row();

        switch ($field->field_type) {
            case 'matrix':
                $result = $this->EE->channel_data->get_matrix_data(
                    $field->field_id,
                    $this->entry_id,
                    $this->params['order_by'],
                    $this->params['sort']
                );

                foreach ($result as &$row) {
                    $row = $this->parse_third_party_field_types($row);
                }

                break;
            case 'playa':
                $result = $this->fetch_related_entries();
                break;
            default:
                $entry = $this->fetch_entry($this->entry_id);
                $result = $entry[$this->field];
        }

        return $result;
    }

    /**
     * @return array
     */
    private function fetch_related_entries()
    {
        $parent = $this->fetch_entry($this->entry_id);
        $related_entries = array();

        if (is_array($parent) AND isset($parent[$this->field])) {
            $children = $parent[$this->field];

            if (! is_array($children))
                $children = array($children);

            foreach ($children as $child)
                $related_entries[] = $this->EE->channel_data
                  ->get_channel_entry($child['entry_id'])
                  ->row_array();
        }

        return $related_entries;
    }

    /**
     * @param $entry_id
     * @param bool $search_by_channel
     * @return array
     */
    private function fetch_entry($entry_id, $search_by_channel = TRUE)
    {
        $result = array();

        if (! $this->is_valid_get_request()) return array();

        if ($search_by_channel)
            $entry = $this->EE->channel_data
              ->get_channel_entry_in_channel($entry_id, $this->channel->channel_id)
              ->row_array();
        else
            $entry = $this->EE->channel_data
              ->get_channel_entry($entry_id)
              ->row_array();

        if (! empty($entry))
            $result = $this->parse_third_party_field_types($entry);

        // -------------------------------------------
        // HOOK: channel_api_fetch_entry_end
        // -------------------------------------------
        if ($this->EE->extensions->active_hook('channel_api_fetch_entry_end') === TRUE) {
            $result = $this->EE->extensions->call('channel_api_fetch_entry_end', $entry_id, $result);
        }

        else
            $this->EE->error_response
              ->set_http_response_code(400)
              ->set_error($this->EE->lang->line('error_no_entry'));

        return $result;
    }

    /**
     * @param $entry_ids
     * @return array
     */
    private function fetch_entries_by_ids($entry_ids)
    {
        if (empty($entry_ids)) return array();

        $fields = $this->get_fields_by_site();
        $params = array();

        if ($this->params['order_by'] == 'entry_id')
            $params['order_by'] = 'entry_id';

        $select = array(
            'titles.entry_id',
            'titles.channel_id',
            'titles.title',
            'titles.url_title',
            'titles.entry_date',
            'titles.author_id'
        );

        foreach ($fields as $field) {
            if ($field->field_type == "matrix")
                $select[] = 'data.field_id_' . $field->field_id . ' as \'' . $field->field_name . '[matrix]\'';
            elseif ($field->field_type == "playa")
                $select[] = 'data.field_id_' . $field->field_id . ' as \'' . $field->field_name . '[playa]\'';
            elseif ($field->field_type == "assets")
                $select[] = 'data.field_id_' . $field->field_id . ' as \'' . $field->field_name . '[assets]\'';
            else
                $select[] = 'data.field_id_' . $field->field_id . ' as \'' . $field->field_name . '\'';

            if ($this->params['order_by'] == $field->field_name)
                $params['order_by'] = $field->field_name;
        }

        $sql  = 'SELECT ' . implode(', ', $select) . ' '
          . 'FROM exp_channel_titles titles '
          . 'JOIN exp_channel_data data ON data.entry_id = titles.entry_id '
          . 'WHERE data.entry_id IN (' . implode(', ', $entry_ids) . ')'
          . (($params['order_by']) ? ' ORDER BY ' . $params['order_by'] : '')
          . (($this->params['limit']) ? ' LIMIT ' . $this->params['limit'] : '');

        $query = $this->EE->db->query($sql);

        $entries = $query->result_array();

        foreach ($entries as &$entry) {
            $entry = $this->parse_third_party_field_types($entry);
        }

        return $entries;
    }

    /**
     * @return mixed
     */
    private function get_fields_by_site()
    {
        $site_id = $this->EE->config->item('site_id');
        $fields = $this->EE->db->query("SELECT * FROM exp_channel_fields WHERE site_id = {$site_id}")->result();

        return $fields;
    }

    /**
     * @return array
     */
    public function list_entries()
    {
        if (! $this->is_valid_get_request()) return array();

        $entries = $this->EE->channel_data
          ->get_channel_entries(
              $this->channel->channel_id,
              array(), #select (default = *)
              array(), #where
              $this->params['order_by'],
              $this->params['sort'],
              $this->params['limit'],
              $this->params['offset'])
          ->result_array();

        foreach($entries as &$entry)
            $entry = $this->parse_third_party_field_types($entry);

        return $entries;
    }

    /**
     * @param $entry
     * @return mixed
     */
    private function parse_third_party_field_types($entry)
    {
        foreach ($entry as $field => &$value) {
            if ($this->is_matrix_field($field))
                $entry = $this->parse_matrix_field($entry, $field, $value);

            if ($this->is_playa_field($field))
                $entry = $this->parse_playa_field($entry, $field, $value);

            if ($this->is_assets_field($field))
                $entry = $this->parse_assets_field($entry, $field, $value);

            if ($this->is_zoo_visitor_field($field))
                $entry = $this->parse_zoo_visitor_field($entry, $field, $value);
        }

        return $entry;
    }

    /**
     * @param $field_name
     * @return bool
     */
    private function is_matrix_field($field_name)
    {
        return (strstr($field_name, '[matrix]'))
          ? TRUE
          : FALSE;
    }

    /**
     * @param $field_name
     * @return bool
     */
    private function is_playa_field($field_name)
    {
        return (strstr($field_name, '[playa]'))
          ? TRUE
          : FALSE;
    }

    /**
     * @param $field_name
     * @return bool
     */
    private function is_assets_field($field_name)
    {
        return (strstr($field_name, '[assets]'))
          ? TRUE
          : FALSE;
    }

    private function is_zoo_visitor_field($field_name)
    {
        return (strstr($field_name, '[zoo_visitor]'))
          ? TRUE
          : FALSE;
    }

    private function parse_zoo_visitor_field($source, $key, $field_value)
    {
        $select = array('member_id', 'group_id', 'username', 'screen_name', 'email');
        $member = $this->EE->db
          ->select(implode(', ', $select))
          ->where('member_id', $field_value)
          ->get('members', 1)
          ->row_array();

        $source[substr($key, 0, - 13)] = $member;
        unset($source[$key]);

        return $source;
    }

    /**
     * @param $source
     * @param $key
     * @param $field_value
     * @return mixed
     */
    private function parse_matrix_field($source, $key, $field_value)
    {
        $real_row_key = substr($key, 0, - 8);
        $matrix_data = $this->EE->channel_data->get_matrix_data(
            $field_value,
            $source['entry_id'],
            $this->params['order_by'],
            $this->params['sort']
        );

        foreach ($matrix_data as &$matrix_row) {
            $matrix_row = $this->parse_third_party_field_types($matrix_row);
        }

        $source[$real_row_key] = $matrix_data;
        unset($source[$key]);

        return $source;
    }

    /**
     * @param $source
     * @param $key
     * @param $field_value
     * @return mixed
     */
    private function parse_playa_field($source, $key, $field_value)
    {
        $new_value = $field_value;
        $entry_ids = $this->extract_ids_from_playa_field_value($field_value);

        if (! empty($entry_ids))
            $new_value = $this->fetch_entries_by_ids($entry_ids);

        $source[substr($key, 0, - 7)] = $new_value;
        unset($source[$key]);

        return $source;
    }

    private function parse_assets_field($source, $key, $field_value)
    {
        $assets_array = array_filter(preg_split('/[\r\n]/', $field_value));

        if (! empty($assets_array))
            foreach ($assets_array as &$asset)
                $asset = $this->parse_file_dir($asset);

        $source[substr($key, 0, -8)] = $assets_array;
        unset($source[$key]);

        return $source;
    }

    private function parse_file_dir($asset)
    {
        require_once PATH_THIRD.'assets/helper.php';
        $helper = get_assets_helper();

        $helper->parse_filedir_path($asset, $file_dir, $file);

        return $file_dir->url . $file;
    }

    public function get_upload_dir($upload_pref_id=0)
    {
    	$this->EE->db->where('id', $upload_pref_id);
		$this->EE->db->limit(1);
    	$query = $this->EE->db->get('upload_prefs');

    	if($query->num_rows()) 
    	{
    		return current($query->result_array());
    	}	 
    	else
    	{
    		return null;
    	}
    }

    /**
     * @param string $field_value The current value for the field from channel_data
     * @return array
     */
    private function extract_ids_from_playa_field_value($field_value)
    {
        $entry_ids = array();

        if (preg_match_all("/\\[(\\d+)\\]/ui", $field_value, $matches) > 0)
            $entry_ids = $matches[1];

        return $entry_ids;
    }

    /**
     * @param array $input_data
     * @return string
     */
    public function channel_post($input_data = NULL)
    {
        // -------------------------------------------
        // HOOK: channel_api_post_start
        // -------------------------------------------
        if ($this->EE->extensions->active_hook('channel_api_post_start') === TRUE) {
            $input_data = $this->EE->extensions->call('channel_api_post_start', $input_data);
        }

        $fields = $this->EE->channel_data
          ->get_channel_fields($this->channel->channel_id)
          ->result_array();

        if (! is_array($input_data)) {
            parse_str(file_get_contents('php://input'), $input_data);
            $_POST = $input_data;
        }

        if (! $this->is_valid_post_request($fields, $input_data))
            $this->EE->error_response
              ->set_http_response_code(400)
              ->set_error($this->EE->lang->line('error_generic'));

        // -------------------------------------------
        // HOOK: channel_api_post_ready
        // -------------------------------------------
        if ($this->EE->extensions->active_hook('channel_api_post_ready') === TRUE) {
			$hook_result = $this->EE->extensions->call('channel_api_post_ready', $input_data);

            if ($hook_result !== TRUE) {
	            $this->EE->error_response
	              ->set_http_response_code(400)
	              ->set_error($hook_result);

                return NULL;
			}
        }

        $data = $this->build_entry_data($fields, $input_data);

        $this->EE->api_channel_fields->setup_entry_settings($this->channel->channel_id, $data);

        if ($this->EE->api_channel_entries->submit_new_entry($this->channel->channel_id, $data)) {
            $new_entry_id = $this->EE->api_channel_entries->entry_id;

            // -------------------------------------------
            // HOOK: channel_api_post_end
            // -------------------------------------------
            if ($this->EE->extensions->active_hook('channel_api_post_end') === TRUE) {
                $this->EE->extensions->call('channel_api_post_end', $new_entry_id);
            }

            return $new_entry_id;
        }

        else
            $this->EE->error_response
              ->set_http_response_code(400)
              ->set_error($this->EE->lang->line('error_generic'));
    }

    /**
     * @param $fields
     * @param array $input_data
     * @return array
     */
    private function build_entry_data($fields, $input_data)
    {
        $data = ($this->entry_id)
          ? array_merge($this->fetch_entry($this->entry_id), $input_data)
          : $input_data;
        
        $data['entry_date'] = $this->EE->localize->now;

        foreach ($fields as $field) {
            if ($input_data[$field['field_name']])
                $data['field_id_' . $field['field_id']] = $input_data[$field['field_name']];
        }

        return $data;
    }

    /**
     * @param array $input_data
     * @return int
     */
    public function channel_put($input_data = NULL)
    {
        // -------------------------------------------
        // HOOK: channel_api_put_start
        // -------------------------------------------
        if ($this->EE->extensions->active_hook('channel_api_put_start') === TRUE) {
            $input_data = $this->EE->extensions->call('channel_api_put_start', $input_data);
        }

        $fields = $this->EE->channel_data
          ->get_channel_fields($this->channel->channel_id)
          ->result_array();

        if (! is_array($input_data))
            parse_str(file_get_contents('php://input'), $input_data);

        if (! $this->is_valid_put_request($fields, $this->entry_id, $input_data))
            return;

        $_POST = $input_data; // hack.
        $data = $this->build_entry_data($fields, $input_data);
        $data['channel_id'] = $this->channel->channel_id;
        $data['entry_date'] = $this->EE->localize->now;

        // -------------------------------------------
        // HOOK: channel_api_put_ready
        // -------------------------------------------
        if ($this->EE->extensions->active_hook('channel_api_put_ready') === TRUE) {
            $hook_result = $this->EE->extensions->call('channel_api_put_ready', $this->entry_id, $input_data);

            if ($hook_result !== TRUE) {
                $this->EE->error_response
                  ->set_http_response_code(400)
                  ->set_error($hook_result);

                return NULL;
            }
        }


        if ($this->update_entry($this->entry_id, $this->channel->channel_id, $data)) {
            // -------------------------------------------
            // HOOK: channel_api_put_end
            // -------------------------------------------
            if ($this->EE->extensions->active_hook('channel_api_put_end') === TRUE) {
                $this->EE->extensions->call('channel_api_put_end', $this->entry_id);
            }

            return $this->entry_id;
        }

        else
            $this->EE->error_response
              ->set_http_response_code(400)
              ->set_error($this->EE->lang->line('error_generic'));

        return FALSE;
    }

    /**
     * @param $entry_id
     * @param $channel_id
     * @param $data
     * @return bool
     */
    private function update_entry($entry_id, $channel_id, $data)
    {
        $this->EE->api_channel_fields->setup_entry_settings($channel_id, $data);
        return $this->EE->api_channel_entries->update_entry(intval($entry_id), $data);
    }

    /**
     * @return int
     */
    public function channel_delete()
    {
        // -------------------------------------------
        // HOOK: channel_api_delete_start
        // -------------------------------------------
        if ($this->EE->extensions->active_hook('channel_api_delete_start') === TRUE) {
            $this->EE->extensions->call('channel_api_delete_start', $this->entry_id);
        }

        if (! $this->is_valid_delete_request($this->entry_id))
            return NULL;

        $delete_entry = $this->EE->api_channel_entries->delete_entry($this->entry_id);

        if ($delete_entry) {
            // -------------------------------------------
            // HOOK: channel_api_delete_end
            // -------------------------------------------
            if ($this->EE->extensions->active_hook('channel_api_delete_end') === TRUE) {
                $this->EE->extensions->call('channel_api_delete_end', $this->entry_id);
            }

            return $this->entry_id;
        }

        else
            $this->EE->error_response
              ->set_http_response_code(400)
              ->set_error($this->EE->lang->line('error_generic'));

        return FALSE;
    }

    /**
     * @return array
     */
    public function upload_to_assets($upload_opts=array())
    {
		/* upload photo */
		$this->EE->load->library(
			'upload', 
			array_merge(
				array(
					'upload_path'   => null, /* must be set in $upload_opts */
					'allowed_types' => 'jpg|gif|png',
					'file_name'		=> '',
					'overwrite'     => FALSE,
					'max_size'      => '500', /* .5MB */
					'encrypt_name'	=> FALSE,
					'remove_spaces'	=> TRUE
				),
				$upload_opts
			)
		);

		$this->EE->upload->do_upload('file');

		/* there was an error in the file upload */
		if($error_message = strip_tags($this->EE->upload->display_errors()))
		{
			return array(
				'success' => FALSE,
				'error_message' => $error_message
			);
		}
		else 
		{
			return array(
				'success' => TRUE,
				'upload_result_data' => $this->EE->upload->data()
			);
		}

		return;
    }

    /**
     * @return bool
     */
    private function is_valid_get_request()
    {
        if ($this->is_valid_channel())
            return TRUE;

        return FALSE;
    }

    /**
     * @param array $fields
     * @param array $input_data
     * @return bool
     */
    private function is_valid_post_request($fields, $input_data)
    {
        if ($this->is_valid_channel()
          AND $this->is_valid_field_requirements($fields, $input_data))
            return TRUE;

        else return FALSE;
    }

    /**
     * @param array $fields
     * @param $entry_id
     * @param $data
     * @return bool
     */
    private function is_valid_put_request($fields, $entry_id, $data)
    {
        if ($this->is_valid_channel()
          AND $this->is_valid_entry_id($entry_id)
            AND $this->is_valid_field_requirements($fields, $data)
        )
            return TRUE;

        else return FALSE;
    }

    /**
     * @param $entry_id
     * @return bool
     */
    private function is_valid_delete_request($entry_id)
    {
        if ($this->is_valid_entry_id($entry_id))
            return TRUE;

        else return FALSE;
    }

    /**
     * @return bool
     */
    private function is_valid_channel()
    {
        if ($this->channel_exists())
            return TRUE;

        else {
            $this->EE->error_response
              ->set_http_response_code(400)
              ->set_error($this->EE->lang->line('error_no_channel'));

            return FALSE;
        }
    }

    /**
     * @param int $entry_id
     * @return bool
     */
    private function is_valid_entry_id($entry_id)
    {
        $entry = $this->EE->channel_data
          ->get_channel_entry_in_channel($entry_id, $this->channel->channel_id)
          ->row_array();

        if (! empty($entry))
            return TRUE;

        else {
            $this->EE->error_response
              ->set_http_response_code(400)
              ->set_error($this->EE->lang->line('error_no_entry'));

            return FALSE;
        }
    }

    /**
     * @param array $fields
     * @param array $input_data
     * @return bool
     */
    private function is_valid_field_requirements($fields, $input_data)
    {
        foreach ($fields as $field) {
            if ($field['field_required'] == 'y') {
                if (! $input_data[$field['field_name']]) {
                    $this->EE->error_response
                      ->set_http_response_code(400)
                      ->set_error($this->EE->lang->line('error_required_fields'));

                    return FALSE;
                }
            }
        }

        return TRUE;
    }
}
/* End of file api_model.php */
