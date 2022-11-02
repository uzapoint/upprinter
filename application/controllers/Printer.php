<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Printer extends CI_Controller
{

    public function proforma()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->proforma($request);
    }

    public function captain()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->captain($request);
    }
    public function sale()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->sale($request);
    }
    public function shift()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->shift($request);
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
    //this function prints all users sales receipt/report
    public function userSalesReport(){
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->userSalesReport($request);
    }
}
