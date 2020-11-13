<?php
defined("BASEPATH") or exit("No direct script access allowed");

require APPPATH . "third_party\\escpos-php\autoload.php";
require 'Carbon\Carbon.php';

//use Carbon\Carbon;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

/**
 * Printer_lib
 *
 */
class Printer_lib
{

    /**
     * Codeigniter instance
     *
     * @var object
     */
    private $CI;

    /**
     * Printer_lib constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function captain($request = array())
    {

        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        foreach ($request['receipts'] as $index => $receipt) {
            if(trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
                $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
            }else if(trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK'){
                $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
            }

            $printer = new Printer($connector);

            //date and time heading
            $datetimeheading = sprintf("%-15s %-5s %-15s", "DATE: " . $request['captain_date'], ' ', "TIME: " . $request['captain_time']);
            $printer->text($datetimeheading . "\n");
            $printer->feed(1);

            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);

            if (!empty($request['business_name'])) {
                $printer->text($request['business_name'] . "\n");
                $printer->setEmphasis(false);
                $printer->feed(1);
            }


            $printer->text($receipt['customer'] . "\n");
            $printer->feed(1);
            $printer->text($receipt['pos_user'] . "\n");
            $printer->feed(2);
            $printer->setJustification();
            $printer->setEmphasis(false);

            foreach ($receipt['items'] as $item) {
                $printer->text('    ' . $item['qty'] . " X " . $item['item_name'] . "\n");
                if (!empty($item['options']) && sizeof($item['options'])) {
                    $printer->feed(1);
                    $printer->selectPrintMode();
                    foreach ($item['options'] as $option) {
                        $printer->text('            -> ' . $option . "\n");
                        $printer->feed(1);
                    }
                }
                //$printer->setTextSize(1, 2);
            }

            $printer->feed(5);
            $connector->write(chr(27) . chr(109));
            $printer->close();
        }
    }

    public function bill($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if(trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }else if(trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK'){
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }
        $printer = new Printer($connector);

        $variables = $request['variables'];


        try {

            //set header
            //$printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if ($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($companyName['value'] . "\n");
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if ($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value'] . "\n");
            }

            if ($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value'] . "\n");
                $printer->feed(1);
            }
            $printer->setJustification();

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref'] . "\n");
            $printer->setEmphasis(false);

            $printer->text("Served By   :   " . $request['pos_user'] . "\n");

            $printer->selectPrintMode();
            $printer->text("Customer    :   " . $request["customer"] . "\n");
            //$date = \Carbon\Carbon::now()->toDayDateTimeString();
            $printer->text($request['receipt_date'] . "\n");
            $printer->feed();


            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $key => $item) {
                $printer->text($item['item_name']."\n");
                $printer->text(
                    sprintf("%-30s %-7s", ($item['qty'].' x '.$item['item_price']), number_format((float)$item['total'], 2))."\n"
                );
                $printer->text("\n");
            }
            $printer->feed(1);
            $printer->text("------------------------------------------------\n");


            /*$orderDueText = sprintf("%-5s %20s %15s", " ", "TOTAL : KES.", number_format((float)$request['grand_total']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------\n");*/

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total'], 2));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount'], 2));
            $printer->text($grandTotal . "\n");
            $printer->text($discount . "\n");

            //add sale taxes
            if(!empty($request['sale_tax_breakdown'])) {
                $printer->text("------------------------------------------------\n");
                $printer->feed();
                foreach ($request['sale_tax_breakdown'] as $tax) {
                    $taxEntry = sprintf("%-30s %-7s", $tax['tax_name'], $tax['tax_value_formatted']);
                    $printer->text($taxEntry . "\n");
                }
            }

            //total indicator
            $printer->text("------------------------------------------------\n");
            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable'], 2));
            //$printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            //$printer->selectPrintMode();

            $printer->text("------------------------------------------------\n");
            $printer->feed(1);

            $amountGiven = sprintf("%-30s %-7s", "Amount Given (".$request['payment_methods_string'].")", $request['amount_given']);
            $amountToPay = sprintf("%-30s %-7s", "Amount to pay", $request['amount_to_pay']);
            $balance = sprintf("%-30s %-7s", $request['balance_name'], $request['balance']);
            $printer->text($amountGiven . "\n");
            $printer->text($amountToPay . "\n");
            $printer->text($balance . "\n");
            $printer->feed(1);
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------\n");

            if(!empty($request['sale_notes'])){
                $printer->feed();

                foreach ($request['sale_notes'] as $sale_note) {
                    $printer->text($sale_note['heading'].": " . $sale_note['content'] . "\n");
                }

                $printer->text("------------------------------------------------\n");
                $printer->feed();
            }


            if ($tillNo = $this->filter_array($variables, 'till_no')) {
                $printer->text("TILL NO.    :   " . $tillNo['value'] . "\n");
            }

            if ($pinNo = $this->filter_array($variables, 'pin_no')) {
                $printer->text("PIN NO.     :   " . $pinNo['value'] . "\n");
            }

            if ($telephone = $this->filter_array($variables, 'telephone')) {
                $printer->text("Telephone   :   " . $telephone['value'] . "\n");
            }

            if ($email = $this->filter_array($variables, 'email')) {
                $printer->text("Email       :   " . $email['value'] . "\n");
            }

            if ($website = $this->filter_array($variables, 'website')) {
                $printer->text("Website     :   " . $website['value'] . "\n");
            }

            $printer->text("------------------------------------------------\n");

            //check if has receipt footer notes
            if(!empty($request['footer_notes'])){
                $printer->feed(2);
                if($request['footer_notes']['footer_notes_alignment'] == 'center'){
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                }
                foreach ($request['footer_notes']['footer_notes'] as $footer_note) {
                    $printer->text($footer_note."\n");
                }
                if($request['footer_notes']['footer_notes_alignment'] == 'center'){
                    $printer->setJustification();
                }
                $printer->text("------------------------------------------------\n");
            }

            //uzapoint footer
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if ($line1 = $this->filter_array($variables, 'line_1')) {
                $printer->text($line1['value'] . "\n");
            }
            if ($line2 = $this->filter_array($variables, 'line_2')) {
                $printer->text($line2['value'] . "\n");
            }
            if ($line3 = $this->filter_array($variables, 'line_3')) {
                $printer->text($line3['value'] . "\n");
            }
            if ($line4 = $this->filter_array($variables, 'line_4')) {
                $printer->text($line4['value'] . "\n");
            }
            $printer->setJustification();
            $printer->feed(5);

            $connector->write(chr(27) . chr(109));
            $printer->pulse();
            $printer->close();

            return true;
        } catch (Exception $e) {
            // echo 'Message: ' . $e->getMessage();
            return false;
        }
    }

    public function shift($request = array()){
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if(trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }else if(trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK'){
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }
        $printer = new Printer($connector);

        $variables = $request['variables'];


        try {

            //set header
            //$printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if ($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($companyName['value'] . "\n");
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if ($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value'] . "\n");
            }

            if ($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value'] . "\n");
                $printer->feed(1);
            }

            $printer->feed();
            $printer->setTextSize(1, 2);
            $printer->text("END SHIFT REPORT \n");
            $printer->selectPrintMode();
            $printer->setJustification();
            $printer->feed();

            $printer->selectPrintMode();
            $printer->text("Opened:   ".$request["opened_by"]."\n");
            $printer->text("          ".$request["opened_at"]."\n");
            $printer->feed();
            $printer->text("Closed:   ".$request["closed_by"]."\n");
            $printer->text("          ".$request["closed_at"]."\n");

            $printer->feed(2);

            $header = sprintf("%-16s %-8s %-8s %-8s", "", "Actual", "Expected", "Variance");
            $printer->setEmphasis(true);
            $printer->text($header. "\n");
            $printer->setEmphasis(false);

            foreach ($request['collections'] as $index => $collection) {
                $myItem = sprintf("%-18s %-8s %-8s %-8s", $collection, $request['actual'][$index], $request['expected'][$index], $request['variance'][$index]);
                if($index === (sizeof($request['collections']) -1)){
                    $printer->setEmphasis(true);
                }
                $printer->text($myItem . "\n");
                //$printer->feed();
                $printer->setEmphasis(false);

            }
            $printer->feed(5);

            $connector->write(chr(27) . chr(109));
            $printer->close();

            return true;
        } catch (Exception $e) {
            // echo 'Message: ' . $e->getMessage();
            return false;
        }
    }

    private function filter_array($array, $key)
    {
        foreach ($array as $array_key => $variable) {
            if (trim($variable['key']) == $key) {
                return $variable;
            }
        }

        return null;
    }
}