<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CorsHook
{
    public function handle()
    {
        // Get the CI instance
        $CI =& get_instance();

        // Define your allowed origin
        $origin = 'https://uzapointerp.uzahost.com';

        // Set the headers
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");

        // Handle the preflight OPTIONS request
        if ($CI->input->method() === 'options') {
            // Send a 200 OK response and exit
            exit;
        }
    }
}