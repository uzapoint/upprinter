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

    public function captainMinifiedDesign($request = array())
    {
        //design receipt for minified design
        $printerID = "POSPRINTER";
        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];
        $connector = new WindowsPrintConnector($printerID);
        $printer = new Printer($connector);
        //set header
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if (!empty($request['business_name'])) {
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($request['business_name'] . "\n");
            $printer->setEmphasis(false);
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------\n");
        }

        $printer->text("Captain Order:  " . $request["order_ref"] . "\n");
        $dateText = $request['captain_date'] . " ". $request['captain_time'];
        $printer->text($dateText . "\n");

        $printer->text($request['customer'] . "\n");
        $printer->feed(1);

        //add items heading
        $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
        $printer->setEmphasis(true);
        $printer->text("------------------------------------------------\n");
        $printer->text($header . "\n");
        $printer->text("------------------------------------------------\n");
        $printer->setEmphasis(false);
        //add the items
        foreach ($request['items'] as $item) {
            $myItem = sprintf("%-28s %-5s %-9s", substr($item['item_name_only'], 0, 27), $item['qty'], number_format((float)$item['total'], 2));
            $printer->text($myItem . "\n");
        }
         //add order options, if there is any
         if (!empty($request['order_options']) && sizeof($request['order_options'])) {
            $printer->text("------------------------------------------------\n");
            $printer->feed();
            $printer->text("    ORDER OPTIONS\n");
            $printer->text('       ' . implode(", ", $request['order_options']) . "\n");
        }
        $printer->text("------------------------------------------------\n");

        if(!empty($request['till_no'])){
            $printer->text("TILL NO: " . $request["till_no"] . "\n");
        }

        //add foooter
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Served By: " . $request["pos_user"] . "\n");


        $printer->feed(5);
        $connector->write(chr(27) . chr(109));
        $printer->close();


    }
    public function captain($request = array())
    {

        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        // check if receipt design is minified design
        if ($request['receipt_design'] == "minified_items")
            return $this->captainMinifiedDesign($request);

        //print standard design
        $printerID = "POSPRINTER";
        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];

        $connector = new WindowsPrintConnector($printerID);
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
        $printer->setTextSize(1, 2);
        $printer->setEmphasis(true);
        $printer->text("Captain Order:" . " " . $request['order_ref'] . "\n");
        $printer->selectPrintMode();
        $printer->feed(1);

        $printer->text($request['customer'] . "\n");
        $printer->feed(1);
        $printer->text($request['pos_user'] . "\n");
        $printer->feed(2);
        $printer->setJustification();
        $printer->setEmphasis(false);

        foreach ($request['items'] as $item) {
            $printer->setTextSize(1, 2);
            $printer->text('    ' . $item['qty'] . " X " . $item['item_name'] . "\n");
            if (!empty($item['options']) && sizeof($item['options'])) {
                $printer->selectPrintMode();
                $printer->text('       ->' . implode(", ", $item['options']) . "\n");
            }
            $printer->feed(1);
            $printer->selectPrintMode();
        }

        //add order options, if there is any
        if (!empty($request['order_options']) && sizeof($request['order_options'])) {
            $printer->text("------------------------------------------------\n");
            $printer->feed();
            $printer->text("    ORDER OPTIONS\n");
            $printer->text('       ' . implode(", ", $request['order_options']) . "\n");
        }


        $printer->feed(5);
        $connector->write(chr(27) . chr(109));
        $printer->close();

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


        $printer->text($request['customer'] . "\n");
        //$printer->feed(1);
        if (!empty($request['order_no'])) $printer->text("Order No : " . $request['order_no'] . "\n");
        if (!empty($request['payment_method'])) {
            $printer->text("Paid via " . $request['payment_method']
                . (!empty($request['reference_code']) ? " (" . $request["reference_code"] . ")" : "") . "\n");
        }
        $printer->feed(2);
        $printer->setJustification();
        $printer->setEmphasis(false);

        foreach ($request['items'] as $item) {
            $printer->text('    ' . $item['qty'] . " X " . $item['item_name'] . "\n");
            if (!empty($item['options']) && sizeof($item['options'])) {
                $printer->selectPrintMode();
                $printer->text('       ->' . implode(", ", $item['options']) . "\n");
            }
            $printer->feed(1);
            //$printer->setTextSize(1, 2);
        }

        //add order options, if there is any
        if (!empty($request['order_options']) && sizeof($request['order_options'])) {
            $printer->text("------------------------------------------------\n");
            $printer->feed();
            $printer->text("    ORDER OPTIONS\n");
            $printer->text('       ' . implode(", ", $request['order_options']) . "\n");
        }

        $printer->feed(5);
        $connector->write(chr(27) . chr(109));
        $printer->close();
    }

    public function proformaMinifiedDesign($request = array())
    {
        //print standard design
        $printerID = "POSPRINTER";
        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];

        $connector = new WindowsPrintConnector($printerID);
        $printer = new Printer($connector);

        $receiptCopies = 1;
        if (!empty($request['proforma_copies'])) $receiptCopies = (int)$request['proforma_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (!empty($request['company_name'])) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($request['company_name'] . "\n");
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if (!empty($request['contact_1'])) {
                $printer->text($request['contact_1'] . "\n");
            }

            if (!empty($request['contact_2'])) {
                $printer->text($request['contact_2'] . "\n");
            }
            if (!empty($request['pin_no'])) {
                $printer->text("PIN NO : " . $request['pin_no'] . "\n");
            }
            if (!empty($request['telephone'])) {
                $printer->text("TEL NO : " . $request['telephone'] . "\n");
            }
            $printer->setJustification();

            $printer->setEmphasis(true);
            $printer->text("Customer Bill #" . $request['order_ref'] . "\n");
            $printer->text("Customer: " . $request['customer'] . "\n");
            $date = !empty($request['receipt_date']) ? $request['receipt_date'] : Carbon::now()->toDayDateTimeString();
            $printer->text($date . "\n");
            $printer->setEmphasis(false);

            $printer->feed();

            $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $type => $items) {
                foreach ($items as $item) {
                    $itemName = $item['item_name'] . (!empty($item['uom_label']) ? (" (" . $item['uom_label'] . ")") : "");
                    $myItem = sprintf("%-28s %-5s %-9s", substr($itemName, 0, 27), $item['qty_raw'], number_format($item['total'], 2));
                    $printer->text($myItem . "\n");
                }
            }
            $printer->text("------------------------------------------------\n");


            $grandTotal = sprintf("%-34s %-7s", "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-34s %-7s", "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal . "\n");
            $printer->text($discount . "\n");

            //total indicator
            $printer->text("------------------------------------------------\n");
            $orderDueText = sprintf("%-34s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();

            $printer->text("------------------------------------------------\n");

            // if (!empty($request['order_payment_details']) && $request['order_payment_details']['has_partial_payments']) {
            //     $printer->setEmphasis(true);
            //     $printer->text("Order Payments\n");
            //     $printer->selectPrintMode();

            //     foreach ($request['order_payment_details']['payments'] as $payment) {
            //         $printer->text($payment['payment_method'] . ": " . $payment['amount_display'] . " (" . $payment['paid_at'] . ")\n");
            //     }
            //     $printer->text("\n");
            //     $printer->text("Amount Paid: " . $request['order_payment_details']['amount_paid_display'] . "\n");
            //     $printer->text("Balance: " . $request['order_payment_details']['amount_due_display'] . "\n");
            //     $printer->text("------------------------------------------------\n");
            // }


            $showBodySection = true;
            if (isset($request['hide_receipt_body'])) {
                $showBodySection = !$request['hide_receipt_body'];
            }

            if (!empty($request['till_no'])) {
                $printer->text("TILL NO : " . $request['till_no'] . "\n");
                $printer->feed(1);
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->feed();
            $printer->text("Served By  " . explode(" ", $request['pos_user'])[0] . "\n");

            //uzapoint footer
            $printer->feed(1);
            if (!empty($request['line_1'])) {
                $printer->text($request['line_1'] . "\n");
            }
            if (!empty($request['line_3'])) {
                $printer->text($request['line_1'] . "\n");
            }
            $printer->setJustification();
            $printer->feed(5);
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
        $printerID = "POSPRINTER";
        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];

        $connector = new WindowsPrintConnector($printerID);
        $printer = new Printer($connector);


        $receiptCopies = 1;
        if (!empty($request['proforma_copies'])) $receiptCopies = (int)$request['proforma_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {
            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if (!empty($request['company_name'])) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($request['company_name'] . "\n");
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if (!empty($request['contact_1'])) {
                $printer->text($request['contact_1'] . "\n");
            }

            if (!empty($request['contact_2'])) {
                $printer->text($request['contact_2'] . "\n");
                $printer->feed(1);
            }

            $printer->setJustification();

            $printer->selectPrintMode();
            $printer->text("Table    :   " . $request["customer"] . "\n");
            $date = !empty($request['receipt_date']) ? $request['receipt_date'] : Carbon::now()->toDayDateTimeString();
            $printer->text($date . "\n");
            $printer->feed();

            $printer->text("Served By   :   " . $request['pos_user'] . "\n");

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref'] . "\n");
            $printer->setEmphasis(false);

            $printer->feed();
            //$header = sprintf("%-3s %-29s %-7s", "Qty", "Item", "Total");
            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $type => $items) {
                foreach ($items as $item) {
                    $printer->text($item['item_name'] . "\n");
                    $printer->text(
                        sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2)) . "\n"
                    );
                    $printer->text("\n");
                }
                $printer->setEmphasis(true);
                $typeTotal = sprintf("%-30s %-7s", $type . " Total", number_format($request['type_totals'][$type], 2));
                $printer->text($typeTotal . "\n");
                $printer->setEmphasis(false);
                $printer->feed();

            }
            $printer->text("-------------------------------------\n");


            /*$orderDueText = sprintf("%-5s %20s %15s", " ", "TOTAL : KES.", number_format((float)$request['grand_total']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------\n");*/

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal . "\n");
            $printer->text($discount . "\n");

            //add sale taxes
            if (!empty($request['sale_tax_breakdown'])) {
                $printer->text("-------------------------------------\n");
                $printer->feed();
                foreach ($request['sale_tax_breakdown'] as $tax) {
                    $taxEntry = sprintf("%-30s %-7s", $tax['tax_name'], $tax['tax_value_formatted']);
                    $printer->text($taxEntry . "\n");
                }
            }

            //total indicator
            $printer->text("-------------------------------------\n");
            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();

            if (!empty($request['amount_given'])) {
                $printer->feed();
                $printer->text("-------------------------------------\n");
                $amountToPay = sprintf("%-30s %-7s", "Total Amount to pay", number_format((float)$request['amount_payable']));
                $printer->text($amountToPay . "\r\n");

                $amountGiven = sprintf("%-30s %-7s", "Total Amount given", number_format((float)$request['amount_given']));
                $printer->text($amountGiven . "\r\n");

                if ((float)$request['balance'] > 0) {
                    $change = sprintf("%-30s %-7s", $request['balance_name'], number_format($request['balance']));
                    $printer->text($change . "\r\n");
                }
                if (!empty($request['overpayments'])) {
                    foreach ($request['overpayments'] as $overpayment) {
                        $change = sprintf("%-30s %-7s", $overpayment['balance_name'], number_format($overpayment['balance']));
                        $printer->text($change . "\r\n");
                    }
                }

                if (!empty($request['tip'])) {
                    $tip = sprintf("%-30s %-7s", "Gratuity", number_format($request['tip']));
                    $printer->text($tip . "\r\n");
                }
            }

            $printer->text("-------------------------------------\n");
            $printer->feed(1);
            /*$totalVat = 0.16 * (float)$request['grand_total'];
            $printer->text("KSHS.   ".number_format($totalVat)."    VAT 16%\n");
            $printer->feed(1);
            $printer->text("------------------------------------------------\n");*/

            if (!empty($request['till_no'])) {
                $printer->text("TILL NO : " . $request['till_no'] . "\n");
                $printer->feed(1);
            }

            if (!empty($request['pin_no'])) {
                $printer->text("PIN NO : " . $request['pin_no'] . "\n");
                $printer->feed(1);
            }
            if (!empty($request['telephone'])) {
                $printer->text("TEL NO : " . $request['telephone'] . "\n");
                $printer->feed(1);
            }
            if (!empty($request['email'])) {
                $printer->text("Email : " . $request['email'] . "\n");
                $printer->feed(1);
            }
            if (!empty($request['website'])) {
                $printer->text("Website : " . $request['website'] . "\n");
                $printer->feed(1);
            }

            $printer->text("----------------------------------\n");

            //uzapoint footer
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (!empty($request['line_1'])) {
                $printer->text($request['line_1'] . "\n");
            }
            if (!empty($request['line_2'])) {
                $printer->text($request['line_2'] . "\n");
            }
            if (!empty($request['line_3'])) {
                $printer->text($request['line_3'] . "\n");
            }
            if (!empty($request['line_4'])) {
                $printer->text($request['line_4'] . "\n");
            }
            $printer->setJustification();
            $printer->feed(5);
            $connector->write(chr(27) . chr(109));
        }

        $printer->pulse();
        $printer->close();

        return true;
    }
    public function saleMinifiedDesign($request = array()){
        $printerID = "POSPRINTER";
        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];

        $connector = new WindowsPrintConnector($printerID);
        $printer = new Printer($connector);

        $receiptCopies = 1;
        if (!empty($request['proforma_copies'])) $receiptCopies = (int)$request['proforma_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (!empty($request['company_name'])) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($request['company_name'] . "\n");
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if (!empty($request['contact_1'])) {
                $printer->text($request['contact_1'] . "\n");
            }

            if (!empty($request['contact_2'])) {
                $printer->text($request['contact_2'] . "\n");
            }
            if (!empty($request['pin_no'])) {
                $printer->text("PIN NO : " . $request['pin_no'] . "\n");
            }
            if (!empty($request['telephone'])) {
                $printer->text("TEL NO : " . $request['telephone'] . "\n");
            }
            $printer->setJustification();

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref'] . "\n");
            $printer->text("Table: " . $request['customer'] . "\n");
            $dateText = $request['receipt_date'] . " ". $request['receipt_time'];
            $printer->text($dateText . "\n");
            $printer->setEmphasis(false);

            $printer->feed();

            $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $type => $items) {
                foreach ($items as $item) {
                    $itemName = $item['item_name'] . (!empty($item['uom_label']) ? (" (" . $item['uom_label'] . ")") : "");
                    $myItem = sprintf("%-28s %-5s %-9s", substr($itemName, 0, 27), $item['qty_raw'], number_format($item['total'], 2));
                    $printer->text($myItem . "\n");
                }
            }
            $printer->text("------------------------------------------------\n");


            $grandTotal = sprintf("%-34s %-7s", "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-34s %-7s", "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal . "\n");
            $printer->text($discount . "\n");

            //total indicator
            $printer->text("------------------------------------------------\n");
            $orderDueText = sprintf("%-34s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();

            $printer->text("------------------------------------------------\n");


            $showBodySection = true;
            if (isset($request['hide_receipt_body'])) {
                $showBodySection = !$request['hide_receipt_body'];
            }

            if (!empty($request['till_no'])) {
                $printer->text("TILL NO : " . $request['till_no'] . "\n");
                $printer->feed(1);
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->feed();
            $printer->text("Served By  " . explode(" ", $request['pos_user'])[0] . "\n");

            //uzapoint footer
            $printer->feed(1);
            if (!empty($request['line_1'])) {
                $printer->text($request['line_1'] . "\n");
            }
            if (!empty($request['line_3'])) {
                $printer->text($request['line_1'] . "\n");
            }
            $printer->setJustification();
            $printer->feed(5);
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
        if (!empty($request['LOCAL_PRINTER_ID'])) $printerID = $request['LOCAL_PRINTER_ID'];

        $connector = new WindowsPrintConnector($printerID);
        $printer = new Printer($connector);


        $receiptCopies = 1;
        if (!empty($request['proforma_copies'])) $receiptCopies = (int)$request['proforma_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {
            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if (!empty($request['company_name'])) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($request['company_name'] . "\n");
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if (!empty($request['contact_1'])) {
                $printer->text($request['contact_1'] . "\n");
            }

            if (!empty($request['contact_2'])) {
                $printer->text($request['contact_2'] . "\n");
                $printer->feed(1);
            }

            $printer->setJustification();

            $printer->selectPrintMode();
            $printer->text("Table    :   " . $request["customer"] . "\n");
            $dateText = $request['receipt_date'] . " ". $request['receipt_time'];
            $printer->text($dateText . "\n");
            $printer->feed();

            $printer->text("Cashed By   :   " . $request['pos_user'] . "\n");

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref'] . "\n");
            $printer->setEmphasis(false);

            $printer->feed();
            //$header = sprintf("%-3s %-29s %-7s", "Qty", "Item", "Total");
            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $type => $items) {
                foreach ($items as $item) {
                    $printer->text($item['item_name'] . "\n");
                    $printer->text(
                        sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2)) . "\n"
                    );
                    $printer->text("\n");
                }
                $printer->setEmphasis(true);
                $typeTotal = sprintf("%-30s %-7s", $type . " Total", number_format($request['type_totals'][$type], 2));
                $printer->text($typeTotal . "\n");
                $printer->setEmphasis(false);
                $printer->feed();

            }
            $printer->text("-------------------------------------\n");


            /*$orderDueText = sprintf("%-5s %20s %15s", " ", "TOTAL : KES.", number_format((float)$request['grand_total']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------\n");*/

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total']));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount']));
            $printer->text($grandTotal . "\n");
            $printer->text($discount . "\n");

            //add sale taxes
            if (!empty($request['sale_tax_breakdown'])) {
                $printer->text("------------------------------------------------\n");
                $printer->feed();

                $taxHeader = sprintf("%-7s %-8s %-15s %-15s", "Code", "Rate", "Vatable", "VAT Amt");
                $printer->setEmphasis(true);
                $printer->text($taxHeader . "\n");
                $printer->setEmphasis(false);

                foreach ($request['sale_tax_breakdown'] as $tax) {
                    //$taxEntry = sprintf("%-34s %-7s", $tax['tax_name'], $tax['tax_value_formatted']);
                    $taxEntry = sprintf("%-7s %-8s %-15s %-15s", $tax['tax_name_only'], number_format((float)$tax["tax_percentage"], 1), number_format((!empty($data['amount_before_tax']) ? $data['amount_before_tax'] : 0), 2), $tax['tax_value_formatted']);
                    $printer->text($taxEntry . "\n");
                }
            }

            //total indicator
            $printer->text("-------------------------------------\n");
            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();

            if (!empty($request['amount_given'])) {
                $printer->feed();
                $printer->text("-------------------------------------\n");
                $amountToPay = sprintf("%-30s %-7s", "Total Amount to pay", number_format((float)$request['amount_payable']));
                $printer->text($amountToPay . "\r\n");

                $amountGiven = sprintf("%-30s %-7s", "Total Amount given", number_format((float)$request['amount_given']));
                $printer->text($amountGiven . "\r\n");

                if ((float)$request['balance'] > 0) {
                    $change = sprintf("%-30s %-7s", $request['balance_name'], number_format($request['balance']));
                    $printer->text($change . "\r\n");
                }
                // if (!empty($request['overpayments'])) {
                //     foreach ($request['overpayments'] as $overpayment) {
                //         $change = sprintf("%-30s %-7s", $overpayment['balance_name'], number_format($overpayment['balance']));
                //         $printer->text($change . "\r\n");
                //     }
                // }

                if (!empty($request['tip'])) {
                    $tip = sprintf("%-30s %-7s", "Gratuity", number_format($request['tip']));
                    $printer->text($tip . "\r\n");
                }
            }

            $printer->text("-------------------------------------\n");
            $printer->feed(1);
            /*$totalVat = 0.16 * (float)$request['grand_total'];
            $printer->text("KSHS.   ".number_format($totalVat)."    VAT 16%\n");
            $printer->feed(1);
            $printer->text("------------------------------------------------\n");*/

            if (!empty($request['till_no'])) {
                $printer->text("TILL NO : " . $request['till_no'] . "\n");
                $printer->feed(1);
            }

            if (!empty($request['pin_no'])) {
                $printer->text("PIN NO : " . $request['pin_no'] . "\n");
                $printer->feed(1);
            }
            if (!empty($request['telephone'])) {
                $printer->text("TEL NO : " . $request['telephone'] . "\n");
                $printer->feed(1);
            }
            if (!empty($request['email'])) {
                $printer->text("Email : " . $request['email'] . "\n");
                $printer->feed(1);
            }
            if (!empty($request['website'])) {
                $printer->text("Website : " . $request['website'] . "\n");
                $printer->feed(1);
            }

            $printer->text("----------------------------------\n");

            //uzapoint footer
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (!empty($request['line_1'])) {
                $printer->text($request['line_1'] . "\n");
            }
            if (!empty($request['line_2'])) {
                $printer->text($request['line_2'] . "\n");
            }
            if (!empty($request['line_3'])) {
                $printer->text($request['line_3'] . "\n");
            }
            if (!empty($request['line_4'])) {
                $printer->text($request['line_4'] . "\n");
            }
            $printer->setJustification();
            $printer->feed(5);
            $connector->write(chr(27) . chr(109));
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
            $printer->text("\n");

            //set header
            if ($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->text($companyName['value'] . "\n");
            }
            $printer->setEmphasis(false);
            $printer->selectPrintMode();

            if ($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value'] . "\n");
            }

            if ($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value'] . "\n");
            }
            $printer->text("\n");
            $printer->setJustification();


            $printer->text("Served By   :   " . $request['pos_user'] . "\n");

            $printer->selectPrintMode();
            $printer->text("Customer    :   " . $request["customer"] . "\n");

            $printer->text($request['receipt_date']);
            $printer->text("\n");
            $printer->text("\n");


            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $key => $item) {
                $printer->text($item['item_name'] . "\n");
                $printer->text(
                    sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2)) . "\n"
                );
                $printer->text("\n");
            }
            $printer->text("------------------------------------------------");

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total'], 2));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount'], 2));
            $printer->text($grandTotal . "\n");
            $printer->text($discount . "\n");


            //total indicator
            $printer->text("------------------------------------------------\n");

            //check if should add delivery cost
            if (!empty($request['delivery_cost'])) {
                $deliveryCostText = sprintf("%-30s %-7s", "Delivery", $request['delivery_cost']);
                $printer->text($deliveryCostText . "\n");
            }

            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable'], 2));
            //$printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");

            $printer->text("------------------------------------------------\n");

            $amountGiven = sprintf("%-30s %-7s", "Amount Given (" . $request['payment_methods_string'] . ")", $request['amount_given']);
            $amountToPay = sprintf("%-30s %-7s", "Amount to pay", $request['amount_to_pay']);
            $balance = sprintf("%-30s %-7s", $request['balance_name'], $request['balance']);
            if (!empty($request['amount_given'])) {
                $printer->text($amountGiven . "\n");
            }
            if (!empty($request['amount_to_pay'])) {
                $printer->text($amountToPay . "\n");
            }
            if (!empty($request['balance_name'])) {
                $printer->text($balance . "\n");
            }

            $shouldShowPaymentsSection = !empty($request['amount_given']) || !empty($request['amount_to_pay']) || !empty($request['balance_name']);
            //if ($shouldShowPaymentsSection) $connector->write(self::ESC . "d" . chr(1));
            $printer->selectPrintMode();
            if ($shouldShowPaymentsSection) {
                $printer->text("------------------------------------------------\n");
            }

            if (!empty($request['sale_notes'])) {
                $printer->feed();

                foreach ($request['sale_notes'] as $sale_note) {
                    $printer->text($sale_note['heading'] . ": " . $sale_note['content'] . "\n");
                }
                $printer->text("------------------------------------------------\n\n");
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

            $printer->text("------------------------------------------------");

            //check if has receipt footer notes
            if (!empty($request['footer_notes'])) {
                $printer->feed(2);
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                }
                foreach ($request['footer_notes']['footer_notes'] as $footer_note) {
                    $printer->text($footer_note . "\n");
                }
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification();
                }
                $printer->text("------------------------------------------------");
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
            $connector->write(self::ESC . "d" . chr(1));
            $connector->write(self::ESC . "d" . chr(1));

            //set header
            if ($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->text($companyName['value']);
                $connector->write(self::ESC . "d" . chr(1));
                $connector->write(self::ESC . "d" . chr(1));
            }
            $printer->setEmphasis(false);
            $printer->selectPrintMode();

            if ($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            if ($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }
            $connector->write(self::ESC . "d" . chr(1));
            $printer->setJustification();

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref']);
            $connector->write(self::ESC . "d" . chr(1));
            $printer->setEmphasis(false);

            $printer->text("Served By   :   " . $request['pos_user']);
            $connector->write(self::ESC . "d" . chr(1));

            $printer->selectPrintMode();
            $printer->text("Customer    :   " . $request["customer"]);
            $connector->write(self::ESC . "d" . chr(1));

            $printer->text($request['receipt_date']);
            $connector->write(self::ESC . "d" . chr(1));
            $connector->write(self::ESC . "d" . chr(1));


            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header);
            $connector->write(self::ESC . "d" . chr(1));
            $printer->setEmphasis(false);

            foreach ($request['items'] as $key => $item) {
                $printer->text($item['item_name']);
                $connector->write(self::ESC . "d" . chr(1));
                $printer->text(
                    sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2)) . "\n"
                );
                $connector->write(self::ESC . "d" . chr(1));
                $connector->write(self::ESC . "d" . chr(1));
            }
            $connector->write(self::ESC . "d" . chr(1));
            $printer->text("------------------------------------------------");
            $connector->write(self::ESC . "d" . chr(1));

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total'], 2));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount'], 2));
            $printer->text($grandTotal);
            $connector->write(self::ESC . "d" . chr(1));
            $printer->text($discount);
            $connector->write(self::ESC . "d" . chr(1));

            //total indicator
            $printer->text("------------------------------------------------");
            $connector->write(self::ESC . "d" . chr(1));

            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable'], 2));
            //$printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $connector->write(self::ESC . "d" . chr(1));
            //$printer->selectPrintMode();

            $printer->text("------------------------------------------------");
            $connector->write(self::ESC . "d" . chr(1));
            $connector->write(self::ESC . "d" . chr(1));
            //$printer->feed(1);

            //check if has receipt footer notes
            if (!empty($request['footer_notes'])) {
                $printer->feed(2);
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                }
                foreach ($request['footer_notes']['footer_notes'] as $footer_note) {
                    $printer->text($footer_note);
                    $connector->write(self::ESC . "d" . chr(1));
                }
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification();
                }
                $printer->text("------------------------------------------------");
                $connector->write(self::ESC . "d" . chr(1));
            }

            //uzapoint footer
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if ($line1 = $this->filter_array($variables, 'line_1')) {
                $printer->text($line1['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }
            if ($line2 = $this->filter_array($variables, 'line_2')) {
                $printer->text($line2['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }
            if ($line3 = $this->filter_array($variables, 'line_3')) {
                $printer->text($line3['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }
            if ($line4 = $this->filter_array($variables, 'line_4')) {
                $printer->text($line4['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }
            $printer->setJustification();
            $printer->feed(5);
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
                $printer->text($request['company_name'] . "\n");
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if (!empty($request['contact_1'])) {
                $printer->text($request['contact_1'] . "\n");
            }

            if (!empty($request['contact_2'])) {
                $printer->text($request['contact_2'] . "\n");
                $printer->feed(1);
            }

            $printer->feed();
            $printer->setTextSize(1, 2);
            $printer->text("END SHIFT REPORT \n");
            $printer->selectPrintMode();
            $printer->setJustification();
            $printer->feed();

            $printer->selectPrintMode();
            $printer->text("Opened:   " . $request["opened_by"] . "\n");
            $printer->text("          " . $request["opened_at"] . "\n");
            $printer->feed();
            $printer->text("Closed:   " . $request["closed_by"] . "\n");
            $printer->text("          " . $request["closed_at"] . "\n");

            $printer->feed(2);

            $header = sprintf("%-16s %-8s %-8s %-8s", "", "Actual", "Expected", "Variance");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['collections'] as $index => $collection) {
                $myItem = sprintf("%-18s %-8s %-8s %-8s", $collection, $request['actual'][$index], $request['expected'][$index], $request['variance'][$index]);
                if ($index === (sizeof($request['collections']) - 1)) {
                    $printer->setEmphasis(true);
                }
                $printer->text($myItem . "\n");
                //$printer->feed();
                $printer->setEmphasis(false);

            }

            //Print out Expenses section
            if (!empty($request['shift_expenses'])) {
                $printer->feed(2);
                $printer->setTextSize(1, 2);
                $printer->text("SHIFT EXPENSES \n");
                $printer->selectPrintMode();
                $printer->setJustification();
                $printer->feed();

                foreach ($request['shift_expenses'] as $expense) {
                    $myItem = sprintf("%-15s %-20s", $expense['method'] . ":", number_format((float)$expense['amount'], 2));
                    $printer->text($myItem . "\n");
                }
                $printer->feed();
            }

            $printer->feed(5);
            $connector->write(chr(27) . chr(109));


        } catch (\Exception $exception) {
            $printer->feed(2);
            $printer->text("ERROR ENCOUNTERED WHILE PRINTING....\n\n");

            $printer->feed(5);
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
    public function userSalesReport($request = array()){
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
            $printer->text($request['company_name'] . "\n");
            $printer->setEmphasis(false);

            $printer->selectPrintMode();
        }
        if (!empty($request['heading1'])) {
            $printer->text($request['heading1'] . "\n");
        }
        if (!empty($request['heading2'])) {
            $printer->text($request['heading2'] . "\n");
        }
        if (!empty($request['pin_no'])) {
            $printer->text("PIN NO: " .$request['pin_no'] . "\n");
        }


        $printer->selectPrintMode();
        $printer->setEmphasis(true);
        $printer->text("USER SALES REPORT\n");
        $printer->setEmphasis(false);
        $printer->feed();

        //reset center justification
        $printer->setJustification();

        $printer->selectPrintMode();
        $printer->text("User : " . $request["report_user"] . "\n");
        $printer->text("Shift : " . $request["shift_period"] . "\n");

        $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
        $printer->setEmphasis(true);
        $printer->text("------------------------------------------------\n");
        $printer->text($header. "\n");
        $printer->text("------------------------------------------------\n");
        $printer->setEmphasis(false);

        foreach ($request['items'] as $item) {
            $myItem = sprintf("%-28s %-5s %-9s", substr($item['item_name'], 0, 26), $item['item_quantity'], $item['item_total']);
            $printer->text($myItem . "\n");
        }
        $printer->feed(1);
        $printer->text("------------------------------------------------\n");
        //add the subtotal
        $subTotal = sprintf("%-33s %-9s", "Sub Total", $request['subtotal']);
        $printer->text($subTotal . "\n");
        $printer->text("------------------------------------------------\n");

        //add discount
        if(!empty($request['discounts'])){
            $discount = sprintf("%-33s %-9s", "Discounts", $request['discounts']);
            $printer->text($discount . "\n");
            $printer->text("------------------------------------------------\n");
        }

        //add total Row
        $totalRow = sprintf("%-33s %-9s", "TOTAL", $request['total']);
        $printer->text($totalRow . "\n");
        $printer->text("------------------------------------------------\n");



        $printer->text("Printed By : ".$request['printed_by'] . "\n");
        $printer->text("Printed At : ".$request['printed_at'] . "\n");


        $printer->feed(5);
        $connector->write(chr(27) . chr(109));
        $printer->close();



    }

    public function allSalesReport($request = array()){

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
            $printer->text($request['company_name'] . "\n");
            $printer->setEmphasis(false);

            $printer->selectPrintMode();
        }
        if (!empty($request['heading1'])) {
            $printer->text($request['heading1'] . "\n");
        }
        if (!empty($request['heading2'])) {
            $printer->text($request['heading2'] . "\n");
        }
        if (!empty($request['pin_no'])) {
            $printer->text("PIN NO: " .$request['pin_no'] . "\n");
        }


        $printer->selectPrintMode();
        $printer->setEmphasis(true);
        $printer->text("CASHIER SALES REPORT\n");
        $printer->setEmphasis(false);
        $printer->feed();

        //reset center justification
        $printer->setJustification();

        $printer->selectPrintMode();
        $printer->text("Shift ID : " . $request["shift_id"] . "\n");
        $printer->text($request["shift_period"] . "\n");

        $header = sprintf("%-28s %-5s %-9s", "Item", "Qty", "Total");
        $printer->setEmphasis(true);
        $printer->text("------------------------------------------------\n");
        $printer->text($header. "\n");
        $printer->text("------------------------------------------------\n");
        $printer->setEmphasis(false);

        foreach ($request['items'] as $item) {
            $myItem = sprintf("%-28s %-5s %-9s", substr($item['item_name'], 0, 26), $item['item_quantity'], $item['item_total']);
            $printer->text($myItem . "\n");
        }
        $printer->feed(1);
        $printer->text("------------------------------------------------\n");
        //add the subtotal
        $subTotal = sprintf("%-33s %-9s", "Sub Total", $request['subtotal']);
        $printer->text($subTotal . "\n");
        $printer->text("------------------------------------------------\n");

        //add discount
        if(!empty($request['discounts'])){
            $discount = sprintf("%-33s %-9s", "Discounts", $request['discounts']);
            $printer->text($discount . "\n");
            $printer->text("------------------------------------------------\n");
        }

        //add total Row
        $totalRow = sprintf("%-33s %-9s", "TOTAL", $request['total']);
        $printer->text($totalRow . "\n");
        $printer->text("------------------------------------------------\n");



        $printer->text("Printed By : ".$request['printed_by'] . "\n");
        $printer->text("Printed At : ".$request['printed_at'] . "\n");

        $printer->feed(5);
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
}

