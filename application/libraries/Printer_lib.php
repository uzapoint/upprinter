<?php
defined("BASEPATH") or exit("No direct script access allowed");

require APPPATH . "third_party\\escpos-php\autoload.php";
require 'Carbon/Carbon.php';

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

        if(trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }else if(trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK'){
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }

        $printer = new Printer($connector);

        foreach ($request['receipts'] as $receipt) {

            try {

                //date and time heading
                $printer->setTextSize(1,2);
                $printer->text($receipt['customer']."      ".$receipt['pos_user']."\n");
                $printer->selectPrintMode();
                $printer->setEmphasis(true);
                $printer->text("Order No: ". $receipt['order_ref']."\n");
                $printer->setEmphasis(false);
                $printer->text(!empty($receipt['date']) ? $receipt['date'].'\n' : \Carbon\Carbon::now()->toDayDateTimeString()."\n");
                $printer->text("-------------------------------------\n");


                if(!empty($receipt['is_fire']) || $receipt['has_courses']){

                    foreach ($receipt['items'] as $courseItems) {
                        $printer->feed(1);
                        $course = $courseItems[0]['course'];
                        if($course !== 'ABC' && (int)$course > 0) {
                            $printer->text("    --------- Course " . $course . " -------------");
                            $printer->feed(2);
                        }
                        foreach ($courseItems as $item) {
                            $printer->text($item['qty'] . " X " . $item['item_name'] . "\n");
                            if (isset($item['options']) && sizeof($item['options'])) {
                                $printer->feed(2);
                                //$printer->selectPrintMode();
                                foreach ($item['options'] as $option) {
                                    $printer->text('    -> ' . $option . "\n");
                                    $printer->feed(1);
                                }
                            }
                        }

                        $printer->feed(1);
                    }

                }else {

                    foreach ($receipt['items'][$receipt['last_course']] as $item) {
                        $printer->text($item['qty'] . " X " . $item['item_name'] . "\n");
                        if (isset($item['options']) && sizeof($item['options'])) {
                            $printer->feed(1);
                            //$printer->selectPrintMode();
                            foreach ($item['options'] as $option) {
                                $printer->text('    -> ' . $option . "\n");
                                //$printer->feed(1);
                            }
                        }

                    }
                }

                $printer->feed(2);

                if(isset($receipt['order_options'])){
                    $printer->text("-------------------------------------\n");
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                    $printer->text(strtoupper($receipt['order_options'])."\n");
                    $printer->setJustification();
                }

                $printer->feed(1);
                $connector->write(chr(27) . chr(109));

                //$printer->pulse();
                $printer -> close();


            } catch (\Exception $exception) {
                return false;
            }

        }
    }

    public function bill($request = array())
    {
        /*print_r($request['LOCAL_PRINTER']);
        exit();*/

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
            $date = !empty($request['date']) ? $request['date'] : Carbon\Carbon::now()->toDayDateTimeString();
            $printer->text($date . "\n");
            $printer->feed();


            $header = sprintf("%-3s %-34s %-7s", "Qty", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header. "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $type => $items) {
                foreach ($items as $item) {
                    $myItem = sprintf("%-3s %-34s %-7s", $item['qty'], substr($item['item_name'], 0, 32).(strlen($item['item_name']) > 32 ? '..' : ''), number_format($item['total']));
                    $printer->text($myItem . "\n");
                }
                $printer->setEmphasis(true);
                $typeTotal = sprintf("%-3s %-34s %-7s", '', $type." Total", number_format($request['type_totals'][$type]));
                $printer->text($typeTotal . "\n");
                $printer->setEmphasis(false);
                $printer->feed();

            }
            $printer->feed(1);
            $printer->text("------------------------------------------------\n");


            /*$orderDueText = sprintf("%-5s %20s %15s", " ", "TOTAL : KES.", number_format((float)$request['grand_total']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------\n");*/

            $grandTotal = sprintf("%-3s %-34s %-7s", ' ', "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-3s %-34s %-7s", ' ', "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal."\n");
            $printer->text($discount."\n");

            //total indicator
            $printer->text("-----------------------------------------------\n");
            $orderDueText = sprintf("%-3s %-34s %-7s", ' ', "TOTAL ", number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();

            $printer->text("------------------------------------------------\n");
            $printer->feed(1);
            /*$totalVat = 0.16 * (float)$request['grand_total'];
            $printer->text("KSHS.   ".number_format($totalVat)."    VAT 16%\n");
            $printer->feed(1);
            $printer->text("------------------------------------------------\n");*/

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
            //$printer->pulse();
            $printer->close();

            return true;
        } catch (Exception $e) {
            // echo 'Message: ' . $e->getMessage();
            return false;
        }
    }

    public function etr($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        if(trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }else if(trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK'){
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }
        $printer = new Printer($connector);

        $variables = $request['variables'];

        try{

            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($companyName['value'] . "\n");
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value'] . "\n");
            }

            if($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value'] . "\n");
                $printer->feed(1);
            }
            $printer->setJustification();

            $printer->setEmphasis(true);
            $printer->text($request['entity']." No    :   ".$request['order_ref']."\n");
            $printer->setEmphasis(false);

            $printer->text("Served By   :   ".$request['pos_user']."\n");

            $printer->selectPrintMode();
            $printer->text("Customer    :   ".$request["customer"]."\n");
            $date = !empty($request['date']) ? $request['date'] : \Carbon\Carbon::now()->toDayDateTimeString();
            $printer->text($date."\n");
            $printer->feed();


            $header = sprintf("%-30s %-4s %-8s", "Item", "Qty", "Total");
            $printer->setEmphasis(true);
            $printer->text($header. "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $item) {
                $myItem = sprintf("%-30s %-4s %-8s", substr($item['item_name'], 0, 28), $item['qty'], number_format($item['total'], 2));
                $printer->text($myItem."\n");
            }
            $printer->feed(1);
            $printer->text("------------------------------------------------\n");


            /*$orderDueText = sprintf("%-5s %20s %15s", " ", "TOTAL : KES.", number_format((float)$request['grand_total']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------\n");*/

            $grandTotal = sprintf("%-35s %-8s", "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-35s %-8s", "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal."\n");
            $printer->text($discount."\n");
            $printer->text("-----------------------------------------------\n");

            //add taxes
            foreach ($request['taxes'] as $tax => $taxValue) {
                //$taxRow = sprintf("%-23s %-8s", ucwords($tax), number_format((float)$data['taxes'][$tax]));
                $taxRow = sprintf("%-35s %-8s", ucwords($tax), number_format((float)$request['taxes'][$tax]));
                //$receiptDesign .= $taxRow."\r\n";
                $printer->text($taxRow."\n");
            }

            //total indicator
            $printer->text("------------------------------------------------\n");
            $orderDueText = sprintf("%-35s %-8s", "TOTAL ".(!empty($request['payment_methods']) ? $request['payment_methods'] : ''), number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();

            $printer->text("------------------------------------------------\n");
            $printer->feed(1);
            /*$totalVat = 0.16 * (float)$request['grand_total'];
            $printer->text("KSHS.   ".number_format($totalVat)."    VAT 16%\n");
            $printer->feed(1);
            $printer->text("------------------------------------------------\n");*/

            if($tillNo = $this->filter_array($variables, 'till_no')) {
                $printer->text("TILL NO.    :   " . $tillNo['value'] . "\n");
            }

            if($pinNo = $this->filter_array($variables, 'pin_no')) {
                $printer->text("PIN NO.     :   " . $pinNo['value'] . "\n");
            }

            if($telephone = $this->filter_array($variables, 'telephone')) {
                $printer->text("Telephone   :   " . $telephone['value'] . "\n");
            }

            if($email = $this->filter_array($variables, 'email')) {
                $printer->text("Email       :   " . $email['value'] . "\n");
            }

            if($website = $this->filter_array($variables, 'website')) {
                $printer->text("Website     :   " . $website['value'] . "\n");
            }

            $printer->text("------------------------------------------------\n");

            //uzapoint footer
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if($line1 = $this->filter_array($variables, 'line_1')) {
                $printer->text($line1['value']."\n");
            }
            if($line2 = $this->filter_array($variables, 'line_2')) {
                $printer->text($line2['value']."\n");
            }
            if($line3 = $this->filter_array($variables, 'line_3')) {
                $printer->text($line3['value']."\n");
            }
            if($line4 = $this->filter_array($variables, 'line_4')) {
                $printer->text($line4['value']."\n");
            }
            $printer->setJustification();
            $printer->feed(1);

            $printer->feed(5);
            $connector->write(chr(27) . chr(109));
            $printer->pulse();
            $printer -> close();

        }catch (\Exception $exception){
            return false;
        }
    }

    public function endShift($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        if(trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }else if(trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK'){
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }
        $printer = new Printer($connector);

        $variables = $request['variables'];

        try{

            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($companyName['value'] . "\n");
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value'] . "\n");
            }

            if($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value'] . "\n");
                //$printer->feed(1);
            }
            $printer->text("------------------------------------------------\n");
            $printer->setJustification();

            $printer->feed();
            $printer->setTextSize(1, 2);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
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

            $header = sprintf("%-13s %-10s %-10s %-10s", "", "Actual", "Expected", "Variance");
            $printer->setEmphasis(true);
            $printer->text($header. "\n");
            $printer->setEmphasis(false);

            foreach ($request['collections'] as $index => $collection) {
                $myItem = sprintf("%-13s %-10s %-10s %-10s", $collection, $request['actual'][$index], $request['expected'][$index], $request['variance'][$index]);
                if($index === (sizeof($request['collections']) -1)){
                    $printer->setEmphasis(true);
                }
                $printer->text($myItem . "\n");
                //$printer->feed();
                $printer->setEmphasis(false);

            }

            $printer->feed(5);
            $connector->write(chr(27) . chr(109));
            //$printer->pulse();
            $printer -> close();

        }catch (\Exception $exception){
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