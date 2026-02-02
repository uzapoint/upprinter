<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Printer extends CI_Controller
{
    public function proforma()
    {
        $this->load->library('printer_lib');
        $request = $this->input->post();

		//check if payment method is dlight
		if(isset($request->payment_method) && in_array('d-light', $request->payment_method)) {
			$this->printer_lib->dlight($request);
		}

        $this->printer_lib->bill($request);
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
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type");

        // Handle the Preflight check
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit(0);
        }
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

    public function customerDisplay()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->sendToCustomerDisplay($request);
    }
}
