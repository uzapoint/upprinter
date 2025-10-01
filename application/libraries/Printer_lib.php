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
            $printer->text($datetimeheading);
            $connector->write(self::ESC."d".chr(1));
            $connector->write(self::ESC."d".chr(1));

            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);

            if (!empty($request['business_name'])) {
                $printer->text($request['business_name']);
                $connector->write(self::ESC."d".chr(1));
                $printer->setEmphasis(false);
                $connector->write(self::ESC."d".chr(1));
            }


            $printer->text($receipt['customer']);
            $connector->write(self::ESC."d".chr(1));
            $connector->write(self::ESC."d".chr(1));
            $printer->text($receipt['pos_user']);
            $connector->write(self::ESC."d".chr(1));
            $printer->feed(2);
            $printer->setJustification();
            $printer->setEmphasis(false);

            foreach ($receipt['items'] as $item) {
                $printer->text('    ' . $item['qty'] . " X " . $item['item_name']);
                $connector->write(self::ESC."d".chr(1));
                if (!empty($item['options']) && sizeof($item['options'])) {
                    $printer->selectPrintMode();
                    $printer->text('       ->' . implode(", ", $item['options']));
                    $connector->write(self::ESC."d".chr(1));
                }
                $connector->write(self::ESC."d".chr(1));
                //$printer->setTextSize(1, 2);
            }

            //add order options, if there is any
            if(!empty($request['order_options']) && sizeof($request['order_options'])){
                $printer->text("------------------------------------------------");
                $connector->write(self::ESC."d".chr(1));
                $connector->write(self::ESC."d".chr(1));
                $printer->text("    ORDER OPTIONS\n");
                $printer->text('       ' . implode(", ", $request['order_options']));
                $connector->write(self::ESC."d".chr(1));
            }

            if(!empty($request['order_delivery_method'])){
                $printer->text("------------------------------------------------");
                $connector->write(self::ESC."d".chr(1));
                $printer->feed();
                $printer->text("DELIVERY DETAILS");
                $connector->write(self::ESC."d".chr(1));
                $connector->write(self::ESC."d".chr(1));
                if($request['order_delivery_method'] == 'delivery') {
                    $printer->text('Customer: ' . $receipt['customer']);
                    $connector->write(self::ESC."d".chr(1));
                }
                if($request['order_delivery_method'] == 'delivery' && !empty($request['order_customer_contact'])) {
                    $printer->text('Phone: ' . $request['order_customer_contact']);
                    $connector->write(self::ESC."d".chr(1));
                }
                $printer->text('Delivery: ' . ($request['order_delivery_method'] == 'pickup' ? 'Pickup Order' : 'Delivery Order'));
                $connector->write(self::ESC."d".chr(1));
                if($request['order_delivery_method'] == 'delivery' && !empty($request['order_delivery_location'])) {
                    $printer->text('Location: ' . $request['order_delivery_location']);
                    $connector->write(self::ESC."d".chr(1));
                }
                if($request['order_delivery_method'] == 'delivery' && !empty($request['order_delivery_address'])) {
                    $printer->text('Address: ' . $request['order_delivery_address']);
                    $connector->write(self::ESC."d".chr(1));
                }
                //if(!empty($request['order_delivery_cost'])) $printer->text('Delivery Cost: ' . $request['order_delivery_cost'] . "\n");
                if($request['order_delivery_method'] == 'pickup' && !empty($request['order_pickup_details'])) {
                    $printer->text('Other Details: ' . $request['order_pickup_details']);
                    $connector->write(self::ESC."d".chr(1));
                }
            }

            $printer->feed(5);
            $connector->write(chr(27) . chr(109));
            //$printer->cut();
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

        $receiptCopies = 1;
        if(!empty($request['sale_receipt_copies'])) $receiptCopies = (int)$request['sale_receipt_copies'];
        for($copy = 1; $copy <= $receiptCopies; $copy++) {

            //check if a logo for this installation has been put in assets directory
            $logoPath = getcwd() . "/application/assets/receipt_logo.png";
            if (file_exists($logoPath) && is_readable($logoPath)) {
                $img = \Mike42\Escpos\EscposImage::load($logoPath);
                $printer->bitImage($img);
                $printer->text("\n");
            }

            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if ($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->setTextSize(1, 2);
                $printer->setEmphasis(true);
                $printer->text($companyName['value']);
                $connector->write(self::ESC . "d" . chr(1));
                $connector->write(self::ESC . "d" . chr(1));
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

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
            }
            $printer->text("------------------------------------------------");

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total'], 2));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount'], 2));
            $printer->text($grandTotal);
            $connector->write(self::ESC . "d" . chr(1));
            $printer->text($discount);
            $connector->write(self::ESC . "d" . chr(1));

            //add sale taxes
            if (!empty($request['sale_tax_breakdown'])) {
                $printer->text("------------------------------------------------");
                //$printer->feed();
                foreach ($request['sale_tax_breakdown'] as $tax) {
                    $taxEntry = sprintf("%-30s %-7s", $tax['tax_name'], $tax['tax_value_formatted']);
                    $printer->text($taxEntry);
                    $connector->write(self::ESC . "d" . chr(1));
                }
            }

            //total indicator
            $printer->text("------------------------------------------------");
            $connector->write(self::ESC . "d" . chr(1));

            //check if should add delivery cost
            if (!empty($request['delivery_cost'])) {
                $deliveryCostText = sprintf("%-30s %-7s", "Delivery", $request['delivery_cost']);
                $printer->text($deliveryCostText);
                $connector->write(self::ESC . "d" . chr(1));
            }

            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable'], 2));
            $printer->setEmphasis(true);
            $printer->text($orderDueText);
            $connector->write(self::ESC . "d" . chr(1));

            $printer->text("------------------------------------------------");
            $connector->write(self::ESC . "d" . chr(1));

            $amountGiven = sprintf("%-30s %-7s", "Amount Given (" . $request['payment_methods_string'] . ")", $request['amount_given']);
            $amountToPay = sprintf("%-30s %-7s", "Amount to pay", $request['amount_to_pay']);
            $balance = sprintf("%-30s %-7s", $request['balance_name'], $request['balance']);
            if (!empty($request['amount_given'])) {
                $printer->text($amountGiven);
                $connector->write(self::ESC . "d" . chr(1));
            }
            if (!empty($request['amount_to_pay'])) {
                $printer->text($amountToPay);
                $connector->write(self::ESC . "d" . chr(1));
            }
            if (!empty($request['balance_name'])) {
                $printer->text($balance);
                $connector->write(self::ESC . "d" . chr(1));
            }

            $shouldShowPaymentsSection = !empty($request['amount_given']) || !empty($request['amount_to_pay']) || !empty($request['balance_name']);
            //if ($shouldShowPaymentsSection) $connector->write(self::ESC . "d" . chr(1));
            $printer->selectPrintMode();
            if ($shouldShowPaymentsSection) {
                $printer->text("------------------------------------------------");
                $connector->write(self::ESC . "d" . chr(1));
            }

            if (!empty($request['sale_notes'])) {
                //$printer->feed();

                foreach ($request['sale_notes'] as $sale_note) {
                    $printer->text($sale_note['heading'] . ": " . $sale_note['content']);
                    $connector->write(self::ESC . "d" . chr(1));
                }

                $printer->text("------------------------------------------------");
                $connector->write(self::ESC . "d" . chr(1));
            }


            if ($tillNo = $this->filter_array($variables, 'till_no')) {
                $printer->text("TILL NO.    :   " . $tillNo['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            if ($pinNo = $this->filter_array($variables, 'pin_no')) {
                $printer->text("PIN NO.     :   " . $pinNo['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            if ($telephone = $this->filter_array($variables, 'telephone')) {
                $printer->text("Telephone   :   " . $telephone['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            if ($email = $this->filter_array($variables, 'email')) {
                $printer->text("Email       :   " . $email['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            if ($website = $this->filter_array($variables, 'website')) {
                $printer->text("Website     :   " . $website['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            $printer->text("------------------------------------------------");
            $connector->write(self::ESC . "d" . chr(1));

            //check if has receipt footer notes
            if (!empty($request['footer_notes'])) {
                //$printer->feed(2);
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
            //$printer->feed(1);
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
            //$printer->cut();
        }

        $printer->pulse();
        $printer->close();

        return true;
    }
    public function deliveryNote($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if(trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }else if(trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK'){
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }
        $printer = new Printer($connector);

        $variables = $request['variables'];

        $receiptCopies = 1;
        if(!empty($request['sale_receipt_copies'])) $receiptCopies = (int)$request['sale_receipt_copies'];
        for($copy = 1; $copy <= $receiptCopies; $copy++) {

            //put heading
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text("DELIVERY NOTE");
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

            /*$printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref']);
            $connector->write(self::ESC."d".chr(1));
            $printer->setEmphasis(false);*/

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

            //add sale taxes
            /*if(!empty($request['sale_tax_breakdown'])) {
                $printer->text("------------------------------------------------");
                $connector->write(self::ESC."d".chr(1));
                $printer->feed();
                foreach ($request['sale_tax_breakdown'] as $tax) {
                    $taxEntry = sprintf("%-30s %-7s", $tax['tax_name'], $tax['tax_value_formatted']);
                    $printer->text($taxEntry);
                    $connector->write(self::ESC."d".chr(1));
                }
            }*/

            //total indicator
            $printer->text("------------------------------------------------");
            $connector->write(self::ESC . "d" . chr(1));

            //check if should add delivery cost
            if (!empty($request['delivery_cost'])) {
                $deliveryCostText = sprintf("%-30s %-7s", "Delivery", $request['delivery_cost']);
                $printer->text($deliveryCostText);
                $connector->write(self::ESC . "d" . chr(1));
            }

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

            $amountGiven = sprintf("%-30s %-7s", "Amount Given (" . $request['payment_methods_string'] . ")", $request['amount_given']);
            $amountToPay = sprintf("%-30s %-7s", "Amount to pay", $request['amount_to_pay']);
            $balance = sprintf("%-30s %-7s", $request['balance_name'], $request['balance']);
            if (!empty($request['amount_given'])) {
                $printer->text($amountGiven);
                $connector->write(self::ESC . "d" . chr(1));
            }
            if (!empty($request['amount_to_pay'])) {
                $printer->text($amountToPay);
                $connector->write(self::ESC . "d" . chr(1));
            }
            if (!empty($request['balance_name'])) {
                $printer->text($balance);
                $connector->write(self::ESC . "d" . chr(1));
            }

            $shouldShowPaymentsSection = !empty($request['amount_given']) || !empty($request['amount_to_pay']) || !empty($request['balance_name']);
            if ($shouldShowPaymentsSection) $connector->write(self::ESC . "d" . chr(1));
            $printer->selectPrintMode();
            if ($shouldShowPaymentsSection) {
                $printer->text("------------------------------------------------");
                $connector->write(self::ESC . "d" . chr(1));
            }

            if (!empty($request['sale_notes'])) {
                $printer->feed();

                foreach ($request['sale_notes'] as $sale_note) {
                    $printer->text($sale_note['heading'] . ": " . $sale_note['content']);
                    $connector->write(self::ESC . "d" . chr(1));
                }

                $printer->text("------------------------------------------------");
                $connector->write(self::ESC . "d" . chr(1));
                $connector->write(self::ESC . "d" . chr(1));
            }


            if ($tillNo = $this->filter_array($variables, 'till_no')) {
                $printer->text("TILL NO.    :   " . $tillNo['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            if ($pinNo = $this->filter_array($variables, 'pin_no')) {
                $printer->text("PIN NO.     :   " . $pinNo['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            if ($telephone = $this->filter_array($variables, 'telephone')) {
                $printer->text("Telephone   :   " . $telephone['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            if ($email = $this->filter_array($variables, 'email')) {
                $printer->text("Email       :   " . $email['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            if ($website = $this->filter_array($variables, 'website')) {
                $printer->text("Website     :   " . $website['value']);
                $connector->write(self::ESC . "d" . chr(1));
            }

            $printer->text("------------------------------------------------");
            $connector->write(self::ESC . "d" . chr(1));

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
    public function creditNote($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if(trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }else if(trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK'){
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }
        $printer = new Printer($connector);

        $variables = $request['variables'];

        $receiptCopies = 1;
        if(!empty($request['sale_receipt_copies'])) $receiptCopies = (int)$request['sale_receipt_copies'];
        for($copy = 1; $copy <= $receiptCopies; $copy++) {

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
                $printer->text($companyName['value']);
                $connector->write(self::ESC."d".chr(1));
                $connector->write(self::ESC."d".chr(1));
                $printer->setEmphasis(false);

                $printer->selectPrintMode();
            }

            if ($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value']);
                $connector->write(self::ESC."d".chr(1));
            }

            if ($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value']);
                $connector->write(self::ESC."d".chr(1));
                $connector->write(self::ESC."d".chr(1));
            }

            $printer->feed();
            $printer->setTextSize(1, 2);
            $printer->text("END SHIFT REPORT");
            $connector->write(self::ESC."d".chr(1));
            $printer->selectPrintMode();
            $printer->setJustification();
            $printer->feed();

            $printer->selectPrintMode();
            $printer->text("Opened:   ".$request["opened_by"]);
            $connector->write(self::ESC."d".chr(1));
            $printer->text("          ".$request["opened_at"]);
            $connector->write(self::ESC."d".chr(1));
            $printer->feed();
            $printer->text("Closed:   ".$request["closed_by"]);
            $connector->write(self::ESC."d".chr(1));
            $printer->text("          ".$request["closed_at"]);
            $connector->write(self::ESC."d".chr(1));

            $printer->feed(2);

            $header = sprintf("%-16s %-8s %-8s %-8s", "", "Actual", "Expected", "Variance");
            $printer->setEmphasis(true);
            $printer->text($header);
            $connector->write(self::ESC."d".chr(1));
            $printer->setEmphasis(false);

            foreach ($request['collections'] as $index => $collection) {
                $myItem = sprintf("%-18s %-8s %-8s %-8s", $collection, $request['actual'][$index], $request['expected'][$index], $request['variance'][$index]);
                if($index === (sizeof($request['collections']) -1)){
                    $printer->setEmphasis(true);
                }
                $printer->text($myItem);
                $connector->write(self::ESC."d".chr(1));
                //$printer->feed();
                $printer->setEmphasis(false);

            }
            $printer->feed(5);

            $connector->write(chr(27) . chr(109));
            //$printer->cut();
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