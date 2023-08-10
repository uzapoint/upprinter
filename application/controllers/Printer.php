<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Printer extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            die();
        }
    }

    public function proforma()
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            die();
        }

        $this->load->library('printer_lib');
        $request = $this->input->post();
        $this->printer_lib->bill($request);

        set_status_header(200);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
        header('Content-Type: application/json;charset=UTF-8');
        exit(json_encode([
            "message" => "Successfully printed receipt"
        ]));
    }

    public function receipt()
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            die();
        }

        $data = json_decode($this->input->raw_input_stream, true);
        $this->load->library('printer_lib');
        $this->printer_lib->print_receipt($data);
        set_status_header($http_code);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
        header('Content-Type: application/json;charset=UTF-8');
        exit(json_encode($output));
    }

    public function captain()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->captain($request);
    }
    public function payTypes(){
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->payTypes($request);

    }
    public function shift()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->shift($request);
    }
    public function droppayment(){
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->dropPayment($request);
    }
    public function deliveryNote()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->deliveryNote($request);
    }
    public function creditNote()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->creditNote($request);
    }
    public function ecommerceCaptain()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->ecommerceCaptain($request);
    }
    public function onesourceEsdSignature()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();
        $request['request_method'] = $this->input->method();

        $this->printer_lib->onesourceEsdSignature($request);
    }

    public function test()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();
        $request['request_method'] = $this->input->method();

        $this->printer_lib->test($request);
    }
    public function saveTextReceipt(){

        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->saveTextReceipt($request);
    }

    public function timsEtrTypeB()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->timsEtrTypeB($request);
    }
    public function ecommerceSaleReceipt()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->ecommerceSaleReceipt($request);
    }
}
