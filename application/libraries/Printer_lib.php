<?php
defined("BASEPATH") or exit("No direct script access allowed");

require APPPATH . "third_party\\escpos-php\autoload.php";
require 'Carbon\Carbon.php';

use Carbon\Carbon;

//use App\DB\Pos\PosReceiptVariable;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;

/**
 * Printer_lib
 *
 */
class Printer_lib
{

    const ESC = "\x1b";

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

    public function newLine($printer, $connector, $noOfLines = 1){
        if(file_exists(__DIR__.'/../assets/TEP300.txt')){
            for($i = 0; $i < $noOfLines; $i++){
                $connector->write(self::ESC . "d" . chr(1));
            }
        }else{
            $printer->feed($noOfLines);
        }
    }

    public function captainMinifiedDesign($request = array())
    {
        /*
         * loop through the printer data to print the captain order at several printers that may be connected
         * */
        foreach($request['receipts'] as $index => $receipt) {

            /*
             * Check the local adapter being used
             * */

            if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
                $networkPrinterIP = !empty($receipt['printer']) && !empty($receipt['printer']['ip']) ?
                    trim($receipt['printer']['ip'])
                    : trim($request['LOCAL_PRINTER']['id']);
                $connector = new NetworkPrintConnector($networkPrinterIP, 9100);
            } else {
                $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
            }

            $printer = new Printer($connector);
            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if (!empty($request['business_name'])) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($request['business_name']);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);
                $printer->selectPrintMode();
                $printer->text("------------------------------------------------");
                $this->newLine($printer, $connector);
            }

            $printer->text("Captain Order:  " . $receipt["order_ref"]);
            $this->newLine($printer, $connector);
            $dateText = $request['captain_date'] . " " . $request['captain_time'];
            $printer->text($dateText);
            $this->newLine($printer, $connector);

            $printer->text($receipt['customer']);
            $this->newLine($printer, $connector);
            $this->newLine($printer, $connector);

            //add items heading
            $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
            $printer->setEmphasis(true);
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);
            $printer->text($header);
            $this->newLine($printer, $connector);
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);
            //add the items
            foreach ($receipt['items'] as $index => $items) {
                foreach($items as $item) {
                    $myItem = sprintf("%-28s %-5s %-9s", substr($item['item_name_only'], 0, 27), $item['qty'], number_format((float)$item['total'], 2));
                    $printer->text($myItem);
                    $this->newLine($printer, $connector);
                }
            }
            //add order options, if there is any
            if (!empty($receipt['order_options']) && sizeof($receipt['order_options'])) {
                $printer->text("------------------------------------------------");
                $this->newLine($printer, $connector);
                $this->newLine($printer, $connector);
                $printer->text("    ORDER OPTIONS");
                $this->newLine($printer, $connector);
                $printer->text('       ' . implode(", ", $request['order_options']));
                $this->newLine($printer, $connector);
            }
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);

            if (!empty($request['till_no'])) {
                $printer->text("TILL NO: " . $request["till_no"]);
                $this->newLine($printer, $connector);
            }

            //add foooter
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Served By: " . $request["pos_user"]);
            $this->newLine($printer, $connector);


            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));
            $printer->cut();
            $printer->close();
        }
    }

    public function captain($request = array())
    {

        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        // check if receipt design is minified design
        if (trim($request['receipt_design']) == "minified_items")
            return $this->captainMinifiedDesign($request);

        //print standard design
        /*
         * loop through the printer data so as to print the captain order at several printers that may be connected
         * */

        foreach($request['receipts'] as $index => $receipt) {

            /*
             * Check the local adapter being used
             * */

            if ($request['LOCAL_PRINTER']['adapter'] === 'NETWORK') {
                $networkPrinterIP = !empty($receipt['printer']) && !empty($receipt['printer']['ip'])
                    ? trim($receipt['printer']['ip'])
                    : trim($request['LOCAL_PRINTER']['id']);
                $connector = new NetworkPrintConnector($networkPrinterIP, 9100);
            } else {
                $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
            }

            $printer = new Printer($connector);

            //date and time heading
            $datetimeheading = sprintf("%-15s %-5s %-15s", "DATE: " . $request['captain_date'], ' ', "TIME: " . $request['captain_time']);
            $printer->text($datetimeheading);
            $this->newLine($printer, $connector, 2);

            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);

            if (!empty($request['business_name'])) {
                $printer->text($request['business_name']);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);
                $this->newLine($printer, $connector);
            }
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text("Captain Order:" . " " . $receipt['order_ref']);
            $this->newLine($printer, $connector);
            $printer->selectPrintMode();
            $this->newLine($printer, $connector);

            $printer->setTextSize(1, 2);
            $printer->text($receipt['pos_user']);
            $this->newLine($printer, $connector, 2);
            $printer->text($receipt['customer']);
            $this->newLine($printer, $connector, 2);
            $printer->setJustification();
            $printer->setEmphasis(false);

            if(!empty($receipt['is_fire']) || $receipt['has_courses']){
                foreach ($receipt['items'] as $course => $courseItems) {
                    $this->newLine($printer, $connector);
                    if($course !== 'ABC' && (int)$course > 0) {
                        $printer->text("    --------- Course " . $course . " -------------");
                        $this->newLine($printer, $connector, 2);
                    }
                    foreach ($courseItems as $item) {
                        $printer->text('        ' . $item['qty'] . " X " . $item['item_name']);
                        $this->newLine($printer, $connector);

                        if (isset($item['options']) && sizeof($item['options'])) {
                            $this->newLine($printer, $connector, 2);
                            //$printer->selectPrintMode();
                            foreach ($item['options'] as $option) {
                                $printer->text('            -> ' . $option);
                                $this->newLine($printer, $connector, 2);
                            }
                        }
                        $printer->setTextSize(1, 2);
                    }

                    $this->newLine($printer, $connector);
                }
            } else {
                foreach ($receipt['items'][$receipt['last_course']] as $item) {
                    $printer->text('    ' . $item['qty'] . " X " . $item['item_name']);
                    $this->newLine($printer, $connector);
                    if (sizeof($item['options'])) {
                        $this->newLine($printer, $connector);
                        //$printer->selectPrintMode();
                        foreach ($item['options'] as $option) {
                            $printer->text('            -> ' . $option);
                            $this->newLine($printer, $connector, 2);
                        }
                    }
                    $printer->setTextSize(1, 2);
                    $this->newLine($printer, $connector);
                }
            }

            //add order options, if there is any
            if (!empty($request['order_options']) && sizeof($request['order_options'])) {
                $printer->text("------------------------------------------------");
                $this->newLine($printer, $connector, 2);
                $printer->text("    ORDER OPTIONS");
                $this->newLine($printer, $connector, 2);
                $printer->text('       ' . implode(", ", $request['order_options']));
                $this->newLine($printer, $connector);
            }


            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));
            $printer->close();
        }

    }

    public function ecommerceCaptain($request = array())
    {

        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        if (trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        } else if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }

        $printer = new Printer($connector);

        //date and time heading
        $datetimeheading = sprintf("%-15s %-5s %-15s", "DATE: " . $request['captain_date'], ' ', "TIME: " . $request['captain_time']);
        $printer->text($datetimeheading);
        $this->newLine($printer, $connector, 2);

        //set header
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);

        if (!empty($request['business_name'])) {
            $printer->text($request['business_name']);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);
            $this->newLine($printer, $connector);
        }


        $printer->text($request['customer']);
        $this->newLine($printer, $connector);
        if (!empty($request['order_no'])){
            $printer->text("Order No : " . $request['order_no']);
            $this->newLine($printer, $connector);
        }

        if (!empty($request['payment_method'])) {
            $printer->text("Paid via " . $request['payment_method']
                . (!empty($request['reference_code']) ? " (" . $request["reference_code"] . ")" : ""));
            $this->newLine($printer, $connector);
        }
        $this->newLine($printer, $connector, 2);
        $printer->setJustification();
        $printer->setEmphasis(false);

        foreach ($request['items'] as $item) {
            $printer->text('    ' . $item['qty'] . " X " . $item['item_name']);
            $this->newLine($printer, $connector);

            if (!empty($item['options']) && sizeof($item['options'])) {
                $printer->selectPrintMode();
                $printer->text('       ->' . implode(", ", $item['options']));
                $this->newLine($printer, $connector);
            }
            $this->newLine($printer, $connector);
            //$printer->setTextSize(1, 2);
        }

        //add order options, if there is any
        if (!empty($request['order_options']) && sizeof($request['order_options'])) {
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector, 2);
            $printer->text("    ORDER OPTIONS");
            $this->newLine($printer, $connector);
            $printer->text('       ' . implode(", ", $request['order_options']));
            $this->newLine($printer, $connector);
        }

        $this->newLine($printer, $connector, 5);
        $connector->write(chr(27) . chr(109));
        $printer->close();
    }

    public function proformaMinifiedDesign($request = array())
    {
        /*
         * Check the local adapter being used
         * */

        $localPrinter = $request['LOCAL_PRINTER'];
        if ($localPrinter['adapter'] === 'NETWORK' && !empty($request['printer_ip'])) {
            $connector = new NetworkPrintConnector($request['printer_ip'], 9100);
        } elseif ($localPrinter['adapter'] === 'USB') {
            $printerID = $localPrinter['id'];
            $connector = new WindowsPrintConnector($printerID);
        }

        $printer = new Printer($connector);

        $variables = $request['variables'];

        $receiptCopies = 1;
        if (!empty($request['proforma_copies'])) $receiptCopies = (int)$request['proforma_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if ($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($companyName['value']);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            $printer->setEmphasis(false);
            $printer->selectPrintMode();

            if ($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value']);
                $this->newLine($printer, $connector);
            }

            if ($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value']);
                $this->newLine($printer, $connector);
            }

            if ($pin = $this->filter_array($variables, 'pin no')) {
                $printer->text("PIN NO : " . $pin['value']);
                $this->newLine($printer, $connector);
            }

            if ($telephone = $this->filter_array($variables, 'telephone')) {
                $printer->text("TEL NO : " . $telephone['value']);
                $this->newLine($printer, $connector);
            }

            $this->newLine($printer, $connector);
            $printer->setJustification();

            if (!empty($request['telephone'])) {
                $printer->text("TEL NO : " . $request['telephone']);
                $this->newLine($printer, $connector);
            }
            $printer->setJustification();

            $printer->setEmphasis(true);
            $printer->text("Customer Bill #" . $request['order_ref']);
            $this->newLine($printer, $connector);
            $printer->text("Customer: " . $request['customer']);
            $this->newLine($printer, $connector);
            $date = !empty($request['receipt_date']) ? $request['receipt_date'] : Carbon::now()->toDayDateTimeString();
            $printer->text($date);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            $this->newLine($printer, $connector);

            $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
            $printer->setEmphasis(true);
            $printer->text($header);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            foreach ($request['items'] as $type => $items) {
                foreach ($items as $item) {
                    $itemName = $item['item_name'] . (!empty($item['uom_label']) ? (" (" . $item['uom_label'] . ")") : "");
                    $myItem = sprintf("%-28s %-5s %-9s", substr($itemName, 0, 27), $item['qty_raw'], number_format($item['total'], 2));
                    $printer->text($myItem);
                    $this->newLine($printer, $connector);
                }
            }
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);


            $grandTotal = sprintf("%-34s %-7s", "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-34s %-7s", "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal);
            $this->newLine($printer, $connector);
            $printer->text($discount);
            $this->newLine($printer, $connector);

            //total indicator
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);
            $orderDueText = sprintf("%-34s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $this->newLine($printer, $connector);
            $printer->selectPrintMode();

            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);

            // if (!empty($request['order_payment_details']) && $request['order_payment_details']['has_partial_payments']) {
            //     $printer->setEmphasis(true);
            //     $printer->text("Order Payments");
            //     $this->$this->newLine($printer, $connector;
            //     $printer->selectPrintMode();

            //     foreach ($request['order_payment_details']['payments'] as $payment) {
            //         $printer->text($payment['payment_method'] . ": " . $payment['amount_display'] . " (" . $payment['paid_at'] . ")");
            //         $this->$this->newLine($printer, $connector;
            //     }
            //     $this->$this->newLine($printer, $connector;
            //     $printer->text("Amount Paid: " . $request['order_payment_details']['amount_paid_display']);
            //     $this->$this->newLine($printer, $connector;
            //     $printer->text("Balance: " . $request['order_payment_details']['amount_due_display']);
            //     $this->$this->newLine($printer, $connector;
            //     $printer->text("------------------------------------------------");
            //     $this->$this->newLine($printer, $connector;
            // }


            $showBodySection = true;
            if (isset($request['hide_receipt_body'])) {
                $showBodySection = !$request['hide_receipt_body'];
            }

            if (!empty($request['till_no'])) {
                $printer->text("TILL NO : " . $request['till_no']);
                $this->newLine($printer, $connector, 2);
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->newLine($printer, $connector);
            $printer->text("Served By  " . explode(" ", $request['pos_user'])[0]);
            $this->newLine($printer, $connector);

            //uzapoint footer
            $this->newLine($printer, $connector);
            if (!empty($request['line_1'])) {
                $printer->text($request['line_1']);
                $this->newLine($printer, $connector);
            }
            if (!empty($request['line_3'])) {
                $printer->text($request['line_1']);
                $this->newLine($printer, $connector);
            }
            $printer->setJustification();
            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));

        }
        $printer->pulse();
        $printer->close();

        return true;

    }

    public function proforma($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if ($request['receipt_design'] == "minified_items") {
            return $this->proformaMinifiedDesign($request);
        }

        //print standard design
        /*
         * Check the local adapter being used
         * */

        $localPrinter = $request['LOCAL_PRINTER'];
        if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
            $connector = new NetworkPrintConnector($request['printer_ip'], 9100);
        } else if(trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $printerID = trim($request['LOCAL_PRINTER']['id']);
            $connector = new WindowsPrintConnector($printerID);
        }

        $printer = new Printer($connector);

        $variables = $request['variables'];

        $receiptCopies = 1;
        if (!empty($request['proforma_copies'])) $receiptCopies = (int)$request['proforma_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {
            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if (($companyName = $this->filter_array($variables, 'company_name')) && $companyName != null) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($companyName['value']);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            $printer->setEmphasis(false);
            $printer->selectPrintMode();

            if (($heading1 = $this->filter_array($variables, 'contact_1')) && $heading1 != null) {
                $printer->text($heading1['value']);
                $this->newLine($printer, $connector);
            }

            if (($heading2 = $this->filter_array($variables, 'contact_2')) && $heading2 != null) {
                $printer->text($heading2['value']);
                $this->newLine($printer, $connector);
            }

            if (($pin = $this->filter_array($variables, 'pin_no')) && $pin != null) {
                $printer->text("PIN NO : " . $pin['value']);
                $this->newLine($printer, $connector);
            }

            if (($telephone = $this->filter_array($variables, 'telephone')) && $telephone != null) {
                $printer->text("TEL NO : " . $telephone['value']);
                $this->newLine($printer, $connector);
            }

            $this->newLine($printer, $connector);
            $printer->setJustification();

            if (!empty($request['telephone'])) {
                $printer->text("TEL NO : " . $request['telephone']);
                $this->newLine($printer, $connector);
            }

            $printer->setJustification();

            $printer->selectPrintMode();
            $printer->text("Table    :   " . $request["customer"]);
            $this->newLine($printer, $connector);
            $date = !empty($request['receipt_date']) ? $request['receipt_date'] : Carbon::now()->toDayDateTimeString();
            $printer->text($date);
            $this->newLine($printer, $connector, 2);

            $printer->text("Served By   :   " . $request['pos_user']);
            $this->newLine($printer, $connector);

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref']);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            $this->newLine($printer, $connector);
            //$header = sprintf("%-3s %-29s %-7s", "Qty", "Item", "Total");
            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            foreach ($request['items'] as $type => $items) {
                foreach ($items as $item) {
                    $printer->text($item['item_name']);
                    $this->newLine($printer, $connector);
                    $printer->text(
                        sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2))
                    );
                    $this->newLine($printer, $connector, 2);
                }
                $printer->setEmphasis(true);
                $typeTotal = sprintf("%-30s %-7s", $type . " Total", number_format($request['type_totals'][$type], 2));
                $printer->text($typeTotal);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);
                $this->newLine($printer, $connector);

            }
            $printer->text("-------------------------------------");
            $this->newLine($printer, $connector);


            /*$orderDueText = sprintf("%-5s %20s %15s", " ", "TOTAL : KES.", number_format((float)$request['grand_total']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $this->$this->newLine($printer, $connector;
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------");
            $this->$this->newLine($printer, $connector;*/

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal);
            $this->newLine($printer, $connector);
            $printer->text($discount);
            $this->newLine($printer, $connector);

            //add sale taxes
            if (!empty($request['sale_tax_breakdown'])) {
                $printer->text("-------------------------------------");
                $this->newLine($printer, $connector, 2);
                foreach ($request['sale_tax_breakdown'] as $tax) {
                    $taxEntry = sprintf("%-30s %-7s", $tax['tax_name'], $tax['tax_value_formatted']);
                    $printer->text($taxEntry);
                    $this->newLine($printer, $connector);
                }
            }

            //total indicator
            $printer->text("-------------------------------------");
            $this->newLine($printer, $connector);
            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $this->newLine($printer, $connector);
            $printer->selectPrintMode();

            if (!empty($request['amount_given'])) {
                $this->newLine($printer, $connector);
                $printer->text("-------------------------------------");
                $this->newLine($printer, $connector);
                $amountToPay = sprintf("%-30s %-7s", "Total Amount to pay", number_format((float)$request['amount_payable']));
                $printer->text($amountToPay);
                $this->newLine($printer, $connector);

                $amountGiven = sprintf("%-30s %-7s", "Total Amount given", number_format((float)$request['amount_given']));
                $printer->text($amountGiven);
                $this->newLine($printer, $connector);

                if ((float)$request['balance'] > 0) {
                    $change = sprintf("%-30s %-7s", $request['balance_name'], number_format($request['balance']));
                    $printer->text($change);
                    $this->newLine($printer, $connector);
                }
                if (!empty($request['overpayments'])) {
                    foreach ($request['overpayments'] as $overpayment) {
                        $change = sprintf("%-30s %-7s", $overpayment['balance_name'], number_format($overpayment['balance']));
                        $printer->text($change);
                        $this->newLine($printer, $connector);
                    }
                }

                if (!empty($request['tip'])) {
                    $tip = sprintf("%-30s %-7s", "Gratuity", number_format($request['tip']));
                    $printer->text($tip);
                    $this->newLine($printer, $connector);
                }
            }

            $printer->text("-------------------------------------");
            $this->newLine($printer, $connector, 2);
            /*$totalVat = 0.16 * (float)$request['grand_total'];
            $printer->text("KSHS.   ".number_format($totalVat)."    VAT 16%");
            $this->$this->newLine($printer, $connector);
            $printer->text("------------------------------------------------");
            $this->$this->newLine($printer, $connector;*/

            if (($tillNo = $this->filter_array($variables, 'till_no')) && $tillNo != null) {
                $printer->text("TILL NO : " . $tillNo['value']);
                $this->newLine($printer, $connector, 2);
            }

            if (($pinNo = $this->filter_array($variables, 'pin_no')) && $telephone != null) {
                $printer->text("PIN NO : " . $pinNo['value']);
                $this->newLine($printer, $connector, 2);
            }
            if (($telephone = $this->filter_array($variables, 'telephone')) && $telephone != null) {
                $printer->text("TEL NO : " . $telephone['value']);
                $this->newLine($printer, $connector, 2);
            }
            if (($email = $this->filter_array($variables, 'email')) && $email != null) {
                $printer->text("Email : " . $email['value']);
                $this->newLine($printer, $connector, 2);
            }
            if (($website = $this->filter_array($variables, 'website')) && $website != null) {
                $printer->text("Website : " . $website['value']);
                $this->newLine($printer, $connector, 2);
            }

            $printer->text("----------------------------------");
            $this->newLine($printer, $connector);

            //uzapoint footer
            $this->newLine($printer, $connector);
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (($line_1 = $this->filter_array($variables, 'line_1')) && $line_1 != null) {
                $printer->text($line_1['value']);
                $this->newLine($printer, $connector);
            }
            if (($line_2 = $this->filter_array($variables, 'line_2')) && $line_2 != null) {
                $printer->text($line_2['value']);
                $this->newLine($printer, $connector);
            }
            if (($line_3 = $this->filter_array($variables, 'line_3')) && $line_3 != null) {
                $printer->text($line_3['value']);
                $this->newLine($printer, $connector);
            }
            if (($line_4 = $this->filter_array($variables, 'line_4')) && $line_4 != null) {
                $printer->text($line_4['value']);
                $this->newLine($printer, $connector);
            }
            $printer->setJustification();
            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));
        }

        $printer->pulse();
        $printer->close();

        return true;
    }

    /**
     * @throws Exception
     */
    public function saleMinifiedDesign($request = array())
    {
        //print standard design
        $printerID = "POSPRINTER";
//        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];
        /*
         * Check the local adapter being used
         * */

        if ($request['PRINT_METHOD'] === 'LOCAL_PRINTING') {
            $localPrinter = $request['LOCAL_PRINTER'];
            if ($localPrinter['adapter'] === 'NETWORK' && !empty($request['printer_ip'])) {
                $connector = new NetworkPrintConnector($request['printer_ip'], 9100);
            } elseif ($localPrinter['adapter'] === 'USB' && !empty($localPrinter['id'])) {
                $printerID = $localPrinter['id'];
                $connector = new WindowsPrintConnector($printerID);
            }
        }

        $printer = new Printer($connector);

        $receiptCopies = 1;
        if (!empty($request['proforma_copies'])) $receiptCopies = (int)$request['proforma_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (!empty($request['company_name'])) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($request['company_name']);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if (!empty($request['contact_1'])) {
                $printer->text($request['contact_1']);
                $this->newLine($printer, $connector);
            }

            if (!empty($request['contact_2'])) {
                $printer->text($request['contact_2']);
                $this->newLine($printer, $connector);
            }
            if (!empty($request['pin_no'])) {
                $printer->text("PIN NO : " . $request['pin_no']);
                $this->newLine($printer, $connector);
            }
            if (!empty($request['telephone'])) {
                $printer->text("TEL NO : " . $request['telephone']);
                $this->newLine($printer, $connector);
            }
            $printer->setJustification();

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref']);
            $this->newLine($printer, $connector);
            $printer->text("Table: " . $request['customer']);
            $this->newLine($printer, $connector);
            $dateText = $request['receipt_date'] . " " . $request['receipt_time'];
            $printer->text($dateText);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            $this->newLine($printer, $connector);

            $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
            $printer->setEmphasis(true);
            $printer->text($header);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            foreach ($request['items'] as $type => $items) {
                foreach ($items as $item) {
                    $itemName = $item['item_name'] . (!empty($item['uom_label']) ? (" (" . $item['uom_label'] . ")") : "");
                    $myItem = sprintf("%-28s %-5s %-9s", substr($itemName, 0, 27), $item['qty'], number_format($item['total'], 2));
                    $printer->text($myItem);
                    $this->newLine($printer, $connector);
                }
            }
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);


            $grandTotal = sprintf("%-34s %-7s", "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-34s %-7s", "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal);
            $this->newLine($printer, $connector);
            $printer->text($discount);
            $this->newLine($printer, $connector);

            //total indicator
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);
            $orderDueText = sprintf("%-34s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $this->newLine($printer, $connector);
            $printer->selectPrintMode();

            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);


            $showBodySection = true;
            if (isset($request['hide_receipt_body'])) {
                $showBodySection = !$request['hide_receipt_body'];
            }

            if (!empty($request['till_no'])) {
                $printer->text("TILL NO : " . $request['till_no']);
                $this->newLine($printer, $connector, 2);
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->newLine($printer, $connector);
            $printer->text("Served By  " . explode(" ", $request['pos_user'])[0]);
            $this->newLine($printer, $connector);

            //uzapoint footer
            $this->newLine($printer, $connector);
            if (!empty($request['line_1'])) {
                $printer->text($request['line_1']);
                $this->newLine($printer, $connector);
            }
            if (!empty($request['line_3'])) {
                $printer->text($request['line_1']);
                $this->newLine($printer, $connector);
            }

            /*
             * CHECK IF NEW TIMS ETR SIGNATURE DETAILS EXIST, PRINT QR Code
             * */
            if (!empty($request['signed_invoice_details'])) {
                //$signedInvoiceDetails = json_decode($request['signed_invoice_details'], true);
                $signedInvoiceDetails = $request['signed_invoice_details'];
                $this->newLine($printer, $connector, 2);
                $printer->text("CU Invoice No.: " . $signedInvoiceDetails['invoice_number']);
                $this->newLine($printer, $connector);
                $printer->text("CU Serial No.: " . $signedInvoiceDetails['control_code']);
                $this->newLine($printer, $connector);
                $this->newLine($printer, $connector);
                $printer->selectPrintMode();
                $printer->qrCode($signedInvoiceDetails['qr_code_url'], Printer::QR_ECLEVEL_L, 6);
            }

            $printer->setJustification();
            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));

        }
        $printer->pulse();
        $printer->close();

        return true;
    }

    public function sale($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if ($request['receipt_design'] == "minified_items")
            return $this->saleMinifiedDesign($request);

        //print standard design
        $printerID = "POSPRINTER";
//        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];
        /*
         * Check the local adapter being used
         * */
        if ($request['PRINT_METHOD'] === 'LOCAL_PRINTING') {
            $localPrinter = $request['LOCAL_PRINTER'];
            if ($localPrinter['adapter'] === 'NETWORK' && !empty($request['printer_ip'])) {
                $connector = new NetworkPrintConnector($request['printer_ip'], 9100);
            } elseif ($localPrinter['adapter'] === 'USB' && !empty($localPrinter['id'])) {
                $printerID = $localPrinter['id'];
                $connector = new WindowsPrintConnector($printerID);
            }
        }

        $printer = new Printer($connector);


        $receiptCopies = 1;
        if (!empty($request['proforma_copies'])) $receiptCopies = (int)$request['proforma_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {
            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if (!empty($request['company_name'])) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($request['company_name']);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if (!empty($request['contact_1'])) {
                $printer->text($request['contact_1']);
                $this->newLine($printer, $connector);
            }

            if (!empty($request['contact_2'])) {
                $printer->text($request['contact_2']);
                $this->newLine($printer, $connector, 2);
            }

            $printer->setJustification();

            $printer->selectPrintMode();
            $printer->text("Table    :   " . $request["customer"]);
            $this->newLine($printer, $connector);
            $dateText = $request['receipt_date'] . " " . $request['receipt_time'];
            $printer->text($dateText);
            $this->newLine($printer, $connector, 2);

            $printer->text("Cashed By   :   " . $request['pos_user']);
            $this->newLine($printer, $connector);

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref']);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            $this->newLine($printer, $connector);
            //$header = sprintf("%-3s %-29s %-7s", "Qty", "Item", "Total");
            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            foreach ($request['items'] as $type => $items) {
                foreach ($items as $item) {
                    $printer->text($item['item_name']);
                    $this->newLine($printer, $connector);
                    $printer->text(
                        sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2))
                    );
                    $this->newLine($printer, $connector, 2);
                }
                $printer->setEmphasis(true);
                $typeTotal = sprintf("%-30s %-7s", $type . " Total", number_format($request['type_totals'][$type], 2));
                $printer->text($typeTotal);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);
                $this->newLine($printer, $connector);

            }
            $printer->text("-------------------------------------");
            $this->newLine($printer, $connector);


            /*$orderDueText = sprintf("%-5s %20s %15s", " ", "TOTAL : KES.", number_format((float)$request['grand_total']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $this->$this->newLine($printer, $connector;
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------");
            $this->$this->newLine($printer, $connector;*/

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal);
            $this->newLine($printer, $connector);
            $printer->text($discount);
            $this->newLine($printer, $connector);

            //add sale taxes
            if (!empty($request['sale_tax_breakdown'])) {
                $printer->text("------------------------------------------------");
                $this->newLine($printer, $connector, 2);

                $taxHeader = sprintf("%-7s %-8s %-15s %-15s", "Code", "Rate", "Vatable", "VAT Amt");
                $printer->setEmphasis(true);
                $printer->text($taxHeader);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);

                foreach ($request['sale_tax_breakdown'] as $tax) {
                    //$taxEntry = sprintf("%-34s %-7s", $tax['tax_name'], $tax['tax_value_formatted']);
                    $taxEntry = sprintf("%-7s %-8s %-15s %-15s", $tax['tax_name_only'], number_format((float)$tax["tax_percentage"], 1), number_format((!empty($data['amount_before_tax']) ? $data['amount_before_tax'] : 0), 2), $tax['tax_value_formatted']);
                    $printer->text($taxEntry);
                    $this->newLine($printer, $connector);
                }
            }

            //total indicator
            $printer->text("-------------------------------------");
            $this->newLine($printer, $connector);
            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $this->newLine($printer, $connector);
            $printer->selectPrintMode();

            if (!empty($request['amount_given'])) {
                $this->newLine($printer, $connector);
                $printer->text("-------------------------------------");
                $this->newLine($printer, $connector);
                $amountToPay = sprintf("%-30s %-7s", "Total Amount to pay", number_format((float)$request['amount_payable']));
                $printer->text($amountToPay);
                $this->newLine($printer, $connector);

                $amountGiven = sprintf("%-30s %-7s", "Total Amount given", number_format((float)$request['amount_given']));
                $printer->text($amountGiven);
                $this->newLine($printer, $connector);

                if ((float)$request['balance'] > 0) {
                    $change = sprintf("%-30s %-7s", $request['balance_name'], number_format($request['balance']));
                    $printer->text($change);
                    $this->newLine($printer, $connector);
                }
                // if (!empty($request['overpayments'])) {
                //     foreach ($request['overpayments'] as $overpayment) {
                //         $change = sprintf("%-30s %-7s", $overpayment['balance_name'], number_format($overpayment['balance']));
                //         $printer->text($change);
                //         $this->$this->newLine($printer, $connector;
                //     }
                // }

                if (!empty($request['tip'])) {
                    $tip = sprintf("%-30s %-7s", "Gratuity", number_format($request['tip']));
                    $printer->text($tip);
                    $this->newLine($printer, $connector);
                }
            }

            $printer->text("-------------------------------------");
            $this->newLine($printer, $connector, 2);
            /*$totalVat = 0.16 * (float)$request['grand_total'];
            $printer->text("KSHS.   ".number_format($totalVat)."    VAT 16%");
            $this->$this->newLine($printer, $connector);
            $printer->text("------------------------------------------------");
            $this->$this->newLine($printer, $connector;*/

            if (!empty($request['till_no'])) {
                $printer->text("TILL NO : " . $request['till_no']);
                $this->newLine($printer, $connector, 2);
            }

            if (!empty($request['pin_no'])) {
                $printer->text("PIN NO : " . $request['pin_no']);
                $this->newLine($printer, $connector, 2);
            }
            if (!empty($request['telephone'])) {
                $printer->text("TEL NO : " . $request['telephone']);
                $this->newLine($printer, $connector, 2);
            }
            if (!empty($request['email'])) {
                $printer->text("Email : " . $request['email']);
                $this->newLine($printer, $connector, 2);
            }
            if (!empty($request['website'])) {
                $printer->text("Website : " . $request['website']);
                $this->newLine($printer, $connector, 2);
            }

            $printer->text("----------------------------------");
            $this->newLine($printer, $connector);

            //uzapoint footer
            $this->newLine($printer, $connector);
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (!empty($request['line_1'])) {
                $printer->text($request['line_1']);
                $this->newLine($printer, $connector);
            }
            if (!empty($request['line_2'])) {
                $printer->text($request['line_2']);
                $this->newLine($printer, $connector);
            }
            if (!empty($request['line_3'])) {
                $printer->text($request['line_3']);
                $this->newLine($printer, $connector);
            }
            if (!empty($request['line_4'])) {
                $printer->text($request['line_4']);
                $this->newLine($printer, $connector);
            }
            $printer->setJustification();
            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));

            /*
             * CHECK IF NEW TIMS ETR SIGNATURE DETAILS EXIST, PRINT QR Code
             * */
            if (!empty($request['signed_invoice_details'])) {
                //$signedInvoiceDetails = json_decode($request['signed_invoice_details'], true);
                $signedInvoiceDetails = $request['signed_invoice_details'];
                $this->newLine($printer, $connector, 2);
                $printer->text("CU Invoice No.: " . $signedInvoiceDetails['invoice_number']);
                $this->newLine($printer, $connector);
                $printer->text("CU Serial No.: " . $signedInvoiceDetails['control_code']);
                $this->newLine($printer, $connector);
                $this->newLine($printer, $connector);
                $printer->selectPrintMode();
                $printer->qrCode($signedInvoiceDetails['qr_code_url'], Printer::QR_ECLEVEL_L, 6);
            }
        }

        $printer->pulse();
        $printer->close();

        return true;
    }

    public function deliveryNote($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if (trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        } else if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }
        $printer = new Printer($connector);

        $variables = $request['variables'];

        $receiptCopies = 1;
        if (!empty($request['sale_receipt_copies'])) $receiptCopies = (int)$request['sale_receipt_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {

            //put heading
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text("DELIVERY NOTE");
            $this->newLine($printer, $connector);

            //set header
            if ($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->text($companyName['value']);
                $this->newLine($printer, $connector);
            }
            $printer->setEmphasis(false);
            $printer->selectPrintMode();

            if ($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value']);
                $this->newLine($printer, $connector);
            }

            if ($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value']);
                $this->newLine($printer, $connector);
            }

            $this->newLine($printer, $connector);
            $printer->setJustification();


            $printer->text("Served By   :   " . $request['pos_user']);
            $this->newLine($printer, $connector);

            $printer->selectPrintMode();
            $printer->text("Customer    :   " . $request["customer"]);
            $this->newLine($printer, $connector);

            $printer->text($request['receipt_date']);
            $this->newLine($printer, $connector, 2);


            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            foreach ($request['items'] as $key => $item) {
                $printer->text($item['item_name']);
                $this->newLine($printer, $connector);
                $printer->text(
                    sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2))
                );
                $this->newLine($printer, $connector);
            }
            $printer->text("------------------------------------------------");

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total'], 2));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount'], 2));
            $printer->text($grandTotal);
            $this->newLine($printer, $connector);
            $printer->text($discount);
            $this->newLine($printer, $connector);


            //total indicator
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);

            //check if should add delivery cost
            if (!empty($request['delivery_cost'])) {
                $deliveryCostText = sprintf("%-30s %-7s", "Delivery", $request['delivery_cost']);
                $printer->text($deliveryCostText);
                $this->newLine($printer, $connector);
            }

            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable'], 2));
            //$printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $this->newLine($printer, $connector);

            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);

            $amountGiven = sprintf("%-30s %-7s", "Amount Given (" . $request['payment_methods_string'] . ")", $request['amount_given']);
            $amountToPay = sprintf("%-30s %-7s", "Amount to pay", $request['amount_to_pay']);
            $balance = sprintf("%-30s %-7s", $request['balance_name'], $request['balance']);
            if (!empty($request['amount_given'])) {
                $printer->text($amountGiven);
                $this->newLine($printer, $connector);
            }
            if (!empty($request['amount_to_pay'])) {
                $printer->text($amountToPay);
                $this->newLine($printer, $connector);
            }
            if (!empty($request['balance_name'])) {
                $printer->text($balance);
                $this->newLine($printer, $connector);
            }

            $shouldShowPaymentsSection = !empty($request['amount_given']) || !empty($request['amount_to_pay']) || !empty($request['balance_name']);
            //if ($shouldShowPaymentsSection) $connector->write(self::ESC . "d" . chr(1));
            $printer->selectPrintMode();
            if ($shouldShowPaymentsSection) {
                $printer->text("------------------------------------------------");
                $this->newLine($printer, $connector);
            }

            if (!empty($request['sale_notes'])) {
                $this->newLine($printer, $connector);

                foreach ($request['sale_notes'] as $sale_note) {
                    $printer->text($sale_note['heading'] . ": " . $sale_note['content']);
                    $this->newLine($printer, $connector);
                }
                $printer->text("------------------------------------------------");
                $this->newLine($printer, $connector, 2);
            }


            if ($tillNo = $this->filter_array($variables, 'till_no')) {
                $printer->text("TILL NO.    :   " . $tillNo['value']);
                $this->newLine($printer, $connector);
            }

            if ($pinNo = $this->filter_array($variables, 'pin_no')) {
                $printer->text("PIN NO.     :   " . $pinNo['value']);
                $this->newLine($printer, $connector);
            }

            if ($telephone = $this->filter_array($variables, 'telephone')) {
                $printer->text("Telephone   :   " . $telephone['value']);
                $this->newLine($printer, $connector);
            }

            if ($email = $this->filter_array($variables, 'email')) {
                $printer->text("Email       :   " . $email['value']);
                $this->newLine($printer, $connector);
            }

            if ($website = $this->filter_array($variables, 'website')) {
                $printer->text("Website     :   " . $website['value']);
                $this->newLine($printer, $connector);
            }

            $printer->text("------------------------------------------------");

            //check if has receipt footer notes
            if (!empty($request['footer_notes'])) {
                $this->newLine($printer, $connector, 2);
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                }
                foreach ($request['footer_notes']['footer_notes'] as $footer_note) {
                    $printer->text($footer_note);
                    $this->newLine($printer, $connector);
                }
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification();
                }
                $printer->text("------------------------------------------------");
            }

            //uzapoint footer
            $this->newLine($printer, $connector);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if ($line1 = $this->filter_array($variables, 'line_1')) {
                $printer->text($line1['value']);
                $this->newLine($printer, $connector);
            }
            if ($line2 = $this->filter_array($variables, 'line_2')) {
                $printer->text($line2['value']);
                $this->newLine($printer, $connector);
            }
            if ($line3 = $this->filter_array($variables, 'line_3')) {
                $printer->text($line3['value']);
                $this->newLine($printer, $connector);
            }
            if ($line4 = $this->filter_array($variables, 'line_4')) {
                $printer->text($line4['value']);
                $this->newLine($printer, $connector);
            }
            $printer->setJustification();
            $this->newLine($printer, $connector, 6);
        }

        $printer->pulse();
        $printer->close();

        return true;
    }

    public function creditNote($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if (trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        } else if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }
        $printer = new Printer($connector);

        $variables = $request['variables'];

        $receiptCopies = 1;
        if (!empty($request['sale_receipt_copies'])) $receiptCopies = (int)$request['sale_receipt_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {

            //put heading
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text("CREDIT NOTE");
            $this->newLine($printer, $connector, 2);

            //set header
            if ($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->text($companyName['value']);
                $this->newLine($printer, $connector, 2);
            }
            $printer->setEmphasis(false);
            $printer->selectPrintMode();

            if ($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value']);
                $this->newLine($printer, $connector);
            }

            if ($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value']);
                $this->newLine($printer, $connector);
            }
            $this->newLine($printer, $connector);
            $printer->setJustification();

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref']);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            $printer->text("Served By   :   " . $request['pos_user']);
            $this->newLine($printer, $connector);

            $printer->selectPrintMode();
            $printer->text("Customer    :   " . $request["customer"]);
            $this->newLine($printer, $connector);

            $printer->text($request['receipt_date']);
            $this->newLine($printer, $connector, 2);


            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            foreach ($request['items'] as $key => $item) {
                $printer->text($item['item_name']);
                $this->newLine($printer, $connector);
                $printer->text(
                    sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2))
                );
                $this->newLine($printer, $connector, 3);
            }

            $this->newLine($printer, $connector);
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total'], 2));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount'], 2));
            $printer->text($grandTotal);
            $this->newLine($printer, $connector);
            $printer->text($discount);
            $this->newLine($printer, $connector);

            //total indicator
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);

            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable'], 2));
            //$printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $this->newLine($printer, $connector);
            //$printer->selectPrintMode();

            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector, 2);

            //check if has receipt footer notes
            if (!empty($request['footer_notes'])) {
                $this->newLine($printer, $connector, 2);
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                }
                foreach ($request['footer_notes']['footer_notes'] as $footer_note) {
                    $printer->text($footer_note);
                    $this->newLine($printer, $connector);
                }
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification();
                }
                $printer->text("------------------------------------------------");
                $this->newLine($printer, $connector);
            }

            //uzapoint footer
            $this->newLine($printer, $connector);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if ($line1 = $this->filter_array($variables, 'line_1')) {
                $printer->text($line1['value']);
                $this->newLine($printer, $connector);
            }
            if ($line2 = $this->filter_array($variables, 'line_2')) {
                $printer->text($line2['value']);
                $this->newLine($printer, $connector);
            }
            if ($line3 = $this->filter_array($variables, 'line_3')) {
                $printer->text($line3['value']);
                $this->newLine($printer, $connector);
            }
            if ($line4 = $this->filter_array($variables, 'line_4')) {
                $printer->text($line4['value']);
                $this->newLine($printer, $connector);
            }
            $printer->setJustification();
            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));
        }

        $printer->pulse();
        $printer->close();

        return true;
    }

    public function shift($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        $printerID = "POSPRINTER";
        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];

        $connector = new WindowsPrintConnector($printerID);
        $printer = new Printer($connector);


        try {

            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if (!empty($request['company_name'])) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($request['company_name']);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if (!empty($request['contact_1'])) {
                $printer->text($request['contact_1']);
                $this->newLine($printer, $connector);
            }

            if (!empty($request['contact_2'])) {
                $printer->text($request['contact_2']);
                $this->newLine($printer, $connector, 2);
            }

            $this->newLine($printer, $connector);
            $printer->setTextSize(1, 2);
            $printer->text("END SHIFT REPORT");
            $this->newLine($printer, $connector);
            $printer->selectPrintMode();
            $printer->setJustification();
            $this->newLine($printer, $connector);

            $printer->selectPrintMode();
            $printer->text("Opened:   " . $request["opened_by"]);
            $this->newLine($printer, $connector);
            $printer->text("          " . $request["opened_at"]);
            $this->newLine($printer, $connector, 2);
            $printer->text("Closed:   " . $request["closed_by"]);
            $this->newLine($printer, $connector);
            $printer->text("          " . $request["closed_at"]);

            $this->newLine($printer, $connector, 3);

            $header = sprintf("%-16s %-8s %-8s %-8s", "", "Actual", "Expected", "Variance");
            $printer->setEmphasis(true);
            $printer->text($header);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            foreach ($request['collections'] as $index => $collection) {
                $myItem = sprintf("%-18s %-8s %-8s %-8s", $collection, $request['actual'][$index], $request['expected'][$index], $request['variance'][$index]);
                if ($index === (sizeof($request['collections']) - 1)) {
                    $printer->setEmphasis(true);
                }
                $printer->text($myItem);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);

            }

            //Print out Expenses section
            if (!empty($request['shift_expenses'])) {
                $this->newLine($printer, $connector, 2);
                $printer->setTextSize(1, 2);
                $printer->text("SHIFT EXPENSES");
                $this->newLine($printer, $connector);
                $printer->selectPrintMode();
                $printer->setJustification();
                $this->newLine($printer, $connector);

                foreach ($request['shift_expenses'] as $expense) {
                    $myItem = sprintf("%-15s %-20s", $expense['method'] . ":", number_format((float)$expense['amount'], 2));
                    $printer->text($myItem);
                    $this->newLine($printer, $connector);
                }
                $this->newLine($printer, $connector);
            }

            //Print out amount due
            if(!empty($request['total_due_amount'])){
                $this->newLine($printer, $connector, 2);
                $printer->text("Total Due: ".$request['total_due_amount']);
                $this->newLine($printer, $connector, 2);
            }

            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));


        } catch (\Exception $exception) {
            $this->newLine($printer, $connector, 2);
            $printer->text("ERROR ENCOUNTERED WHILE PRINTING....");

            $this->newLine($printer, $connector, 7);

            $connector->write(chr(27) . chr(109));


        } finally {
            $printer->close();
        }

    }

    public function stockTransfer($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if (trim($request['printer']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['printer']['id']));
        } else if (trim($request['printer']['adapter']) === 'NETWORK') {
            $connector = new NetworkPrintConnector(trim($request['printer_ip']));
        }

        $printer = new Printer($connector);

        $variables = $request['data'];

        try {
            // Receipt Header
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (!empty($variables['name'])) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($variables['name']);
                $this->newLine($printer, $connector);
                $printer->setEmphasis(false);
                $printer->selectPrintMode();
            }

            if (!empty($variables['address'])) {
                $printer->text($variables['address']);
                $this->newLine($printer, $connector);
            }

            if (!empty($variables['physical_address'])) {
                $printer->text($variables['physical_address']);
                $this->newLine($printer, $connector);
            }

            if (!empty($variables['email'])) {
                $printer->text($variables['email']);
                $this->newLine($printer, $connector);
            }

            if (!empty($variables['phone'])) {
                $printer->text($variables['phone']);
                $this->newLine($printer, $connector);
            }

            $this->newLine($printer, $connector);
            $printer->setTextSize(1, 2);
            $printer->text("STOCK TRANSFER NOTE");
            $this->newLine($printer, $connector);
            $printer->selectPrintMode();
            $printer->setJustification();
            $this->newLine($printer, $connector);

            $printer->setEmphasis(true);
            $printer->text("Reference Code: " . $variables['reference_code']);
            $this->newLine($printer, $connector);
            $printer->text("User: " . $variables['user']);
            $this->newLine($printer, $connector, 2);
            $printer->setEmphasis(false);
            $printer->text("Store From: " . $variables['source_store_name']);
            $this->newLine($printer, $connector);
            $printer->text("Store To: " . $variables['destination_store_name']);
            $this->newLine($printer, $connector);
            $printer->text("Issue Date: " . $variables['issue_date']);
            $this->newLine($printer, $connector);


            $this->newLine($printer, $connector, 2);

            $header = sprintf("%-10s %-18s %-8s %-10s", "Code", "Product", "Quantity", "UOM");
            $printer->setEmphasis(true);
            $printer->text($header);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);
            /*
             * Print the issue items
             * */
            foreach ($variables['items'] as $index => $item) {
                $myItem = sprintf("%-9s %-22s %-4s %-6s", $item['productCode'], $item['product_label'], $item['quantity'], $item['uom_label']);
                $printer->text($myItem);
                $this->newLine($printer, $connector);
            }

            /*
             * Signatories
             * */
            $this->newLine($printer, $connector, 4);
            $printer->text('Sign: ________________________________');

            /*
             * Uzapoint footer
             * */
            $this->newLine($printer, $connector, 2);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text('Powered by Uzapoint');

            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));
        } catch (\Exception $exception) {
            $this->newLine($printer, $connector, 2);
            $printer->text("ERROR ENCOUNTERED WHILE PRINTING....");
            $this->newLine($printer, $connector, 2);

            $this->newLine($printer, $connector, 5);
            $connector->write(chr(27) . chr(109));
        } finally {
            $printer->close();
        }
    }

    public function onesourceEsdSignature($request = array())
    {
        /*
         * This method attempts to generate a signature from the OneSource ESD and send it back to the person requesting
         * */
        $ESD_SIGNATURE = null;
        if (!empty($request['request_method']) && ($request['request_method'] == 'post' || $request['request_method'] == 'post')) {
            if ($this->generateOneSourceESDSignature($request)) {
                sleep(2);
                $ESD_SIGNATURE = $this->readOneSourceESDSignature();
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        //add headers to avoid CORS exception
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,POST,PUT");
        header("Access-Control-Allow-Headers: Access-Control-Allow-Headers, Origin,Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers");

        echo json_encode([
            "data" => $ESD_SIGNATURE,
            "method" => $request['request_method']
        ]);
    }

    public function userSalesReport($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        //print standard design
        $printerID = "POSPRINTER";
        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];

        $connector = new WindowsPrintConnector($printerID);
        $printer = new Printer($connector);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if (!empty($request['company_name'])) {
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($request['company_name']);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            $printer->selectPrintMode();
        }
        if (!empty($request['heading1'])) {
            $printer->text($request['heading1']);
            $this->newLine($printer, $connector);
        }
        if (!empty($request['heading2'])) {
            $printer->text($request['heading2']);
            $this->newLine($printer, $connector);
        }
        if (!empty($request['pin_no'])) {
            $printer->text("PIN NO: " . $request['pin_no']);
            $this->newLine($printer, $connector);
        }


        $printer->selectPrintMode();
        $printer->setEmphasis(true);
        $printer->text("USER SALES REPORT");
        $this->newLine($printer, $connector);
        $printer->setEmphasis(false);
        $this->newLine($printer, $connector);

        //reset center justification
        $printer->setJustification();

        $printer->selectPrintMode();
        $printer->text("User : " . $request["report_user"]);
        $this->newLine($printer, $connector);
        $printer->text("Shift : " . $request["shift_period"]);
        $this->newLine($printer, $connector);

        $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
        $printer->setEmphasis(true);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);
        $printer->text($header);
        $this->newLine($printer, $connector);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);
        $printer->setEmphasis(false);

        foreach ($request['items'] as $item) {
            $myItem = sprintf("%-28s %-5s %-9s", substr($item['item_name'], 0, 26), $item['item_quantity'], $item['item_total']);
            $printer->text($myItem);
            $this->newLine($printer, $connector);
        }
        $this->newLine($printer, $connector);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);
        //add the subtotal
        $subTotal = sprintf("%-33s %-9s", "Sub Total", $request['subtotal']);
        $printer->text($subTotal);
        $this->newLine($printer, $connector);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);

        //add discount
        if (!empty($request['discounts'])) {
            $discount = sprintf("%-33s %-9s", "Discounts", $request['discounts']);
            $printer->text($discount);
            $this->newLine($printer, $connector);
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);
        }

        //add total Row
        $totalRow = sprintf("%-33s %-9s", "TOTAL", $request['total']);
        $printer->text($totalRow);
        $this->newLine($printer, $connector);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);


        $printer->text("Printed By : " . $request['printed_by']);
        $this->newLine($printer, $connector);
        $printer->text("Printed At : " . $request['printed_at']);
        $this->newLine($printer, $connector);


        $this->newLine($printer, $connector, 5);
        $connector->write(chr(27) . chr(109));
        $printer->close();


    }

    public function allSalesReport($request = array())
    {

        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        //print standard design
        $printerID = "POSPRINTER";
        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];

        $connector = new WindowsPrintConnector($printerID);
        $printer = new Printer($connector);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if (!empty($request['company_name'])) {
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($request['company_name']);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);

            $printer->selectPrintMode();
        }
        if (!empty($request['heading1'])) {
            $printer->text($request['heading1']);
            $this->newLine($printer, $connector);
        }
        if (!empty($request['heading2'])) {
            $printer->text($request['heading2']);
            $this->newLine($printer, $connector);
        }
        if (!empty($request['pin_no'])) {
            $printer->text("PIN NO: " . $request['pin_no']);
            $this->newLine($printer, $connector);
        }


        $printer->selectPrintMode();
        $printer->setEmphasis(true);
        $printer->text("CASHIER SALES REPORT");
        $this->newLine($printer, $connector);
        $printer->setEmphasis(false);
        $this->newLine($printer, $connector);

        //reset center justification
        $printer->setJustification();

        $printer->selectPrintMode();
        $printer->text("Shift ID : " . $request["shift_id"]);
        $this->newLine($printer, $connector);
        $printer->text($request["shift_period"]);
        $this->newLine($printer, $connector);

        $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
        $printer->setEmphasis(true);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);
        $printer->text($header);
        $this->newLine($printer, $connector);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);
        $printer->setEmphasis(false);

        foreach ($request['items'] as $item) {
            $myItem = sprintf("%-28s %-5s %-9s", substr($item['item_name'], 0, 26), $item['item_quantity'], $item['item_total']);

            // An aggregation item
            if(isset($item['category']) && $item['category'] == ''){
                $printer->setEmphasis(true);
            }

            $printer->text($myItem);
            $this->newLine($printer, $connector);
            $printer->setEmphasis(false);
        }
        $this->newLine($printer, $connector);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);
        //add the subtotal
        $subTotal = sprintf("%-33s %-9s", "Sub Total", $request['subtotal']);
        $printer->text($subTotal);
        $this->newLine($printer, $connector);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);

        //add discount
        if (!empty($request['discounts'])) {
            $discount = sprintf("%-33s %-9s", "Discounts", $request['discounts']);
            $printer->text($discount);
            $this->newLine($printer, $connector);
            $printer->text("------------------------------------------------");
            $this->newLine($printer, $connector);
        }

        //add total Row
        $totalRow = sprintf("%-33s %-9s", "TOTAL", $request['total']);
        $printer->text($totalRow);
        $this->newLine($printer, $connector);
        $printer->text("------------------------------------------------");
        $this->newLine($printer, $connector);


        $printer->text("Printed By : " . $request['printed_by']);
        $this->newLine($printer, $connector);
        $printer->text("Printed At : " . $request['printed_at']);
        $this->newLine($printer, $connector);

        $this->newLine($printer, $connector, 5);
        $connector->write(chr(27) . chr(109));
        $printer->close();

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

    private function generateOneSourceESDSignature($receiptData)
    {
        /*
         * THIS IS HOW WE GET A SIGNATURE FROM ONE-SOURCE ESD
         * - WE CREATE A TEXT FILE IN THE SOURCE DIRECTORY, WHERE ESD LISTENS TO
         * - THE ESD READS THE TEXT FILE, GENERATES A SIGNATURE AND WRITES THAT SIGNATURE IN TO THE FILE WE HAVE SPECIFIED
         * */

        try {
            //in text file content
            $fileContent = json_encode($receiptData);
            //define the in file
            $receiptName = "C:/xampp/htdocs/upprinter/application/assets/in/ONESOURCE_ESD_IN_FILE.txt";
            //clear any previous file
            @unlink($receiptName);
            //create the same file afresh
            $myfile = fopen($receiptName, "x+") or die("Unable to open file!");
            fwrite($myfile, $fileContent);
            fclose($myfile);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    private function readOneSourceESDSignature()
    {
        //DEFINE SIGNATURE FILE TO READ
        //$filename = "C:/out/SIGNATURE.txt";
        $filename = "C:/xampp/htdocs/upprinter/application/assets/out/ONESOURCE_SIGNATURE.txt";
        //DEFINE VARIABLE TO HOLD SIGNATURE
        $signature = "";
        //READ SIGNATURE FROM THE FILE, IF IT EXISTS
        if (file_exists($filename)) {
            //READ THE SIGNATURE
            $signature = trim(file_get_contents($filename));
            //DELETE THE FILE FOR THE NEXT SIGNATURE
            @unlink($filename);
        }
        //RETURN SIGNATURE
        return $signature;
    }

    public function timsEtrTypeB($request){
        $pdfContent = file_get_contents(trim($request['receipt_path']));
        return file_put_contents(trim($request['local_path']).DIRECTORY_SEPARATOR.trim($request["filename"]), $pdfContent);
    }
}

