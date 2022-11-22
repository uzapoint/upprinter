<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Printer extends CI_Controller
{

    public function proforma()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->bill($request);
    }

    public function captain()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->captain($request);
    }

    public function etr()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->etr($request);
    }

    public function timsEtrTypeB()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->timsEtrTypeB($request);
    }

    public function endShift()
    {
        $this->load->library('printer_lib');

        $request = $this->input->post();

        $this->printer_lib->endShift($request);
    }
}
