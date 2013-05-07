<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

interface Auth_service
{
    /**
     * @param string $username
     * @param string $password
     * @return object
     */
    public function authenticate($username = NULL, $password = NULL);

    /**
     * @param string $token
     * @return bool
     */
    public function validate_access_token($token);
}