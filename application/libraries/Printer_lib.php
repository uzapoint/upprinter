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
                    $printer->selectPrintMode();
                    $printer->text('       ->' . implode(", ", $item['options']) . "\n");
                }
                $printer->feed(1);
                //$printer->setTextSize(1, 2);
            }

            //add order options, if there is any
            if(!empty($request['order_options']) && sizeof($request['order_options'])){
                $printer->text("------------------------------------------------\n");
                $printer->feed();
                $printer->text("    ORDER OPTIONS\n");
                $printer->text('       ' . implode(", ", $request['order_options']) . "\n");
            }

            if(!empty($request['order_delivery_method'])){
                $printer->text("------------------------------------------------\n");
                $printer->feed();
                $printer->text("DELIVERY DETAILS\n\n");
                if($request['order_delivery_method'] == 'delivery') $printer->text('Customer: ' . $receipt['customer'] . "\n");
                if($request['order_delivery_method'] == 'delivery' && !empty($request['order_customer_contact'])) $printer->text('Phone: ' . $request['order_customer_contact'] . "\n");
                $printer->text('Delivery: ' . ($request['order_delivery_method'] == 'pickup' ? 'Pickup Order' : 'Delivery Order') . "\n");
                if($request['order_delivery_method'] == 'delivery' && !empty($request['order_delivery_location'])) $printer->text('Location: ' . $request['order_delivery_location'] . "\n");
                if($request['order_delivery_method'] == 'delivery' && !empty($request['order_delivery_address'])) $printer->text('Address: ' . $request['order_delivery_address'] . "\n");
                //if(!empty($request['order_delivery_cost'])) $printer->text('Delivery Cost: ' . $request['order_delivery_cost'] . "\n");
                if($request['order_delivery_method'] == 'pickup' && !empty($request['order_pickup_details'])) $printer->text('Other Details: ' . $request['order_pickup_details'] . "\n");
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

        /*
         * FOR BUSINESSES WHICH USE ONE-SOURCE ESD, GENERATE THE ESD SIGNATURE
         * */
        $ESD_SIGNATURE = null;
        if(!empty($request['CAN_ESD_SIGN']) && $this->generateOneSourceESDSignature($request['items'])){
            sleep(2);
            $ESD_SIGNATURE = $this->readOneSourceESDSignature();
        }

        $variables = $request['variables'];
        $receiptCopies = 1;
        if(!empty($request['sale_receipt_copies'])) $receiptCopies = (int)$request['sale_receipt_copies'];
        for($copy = 1; $copy <= $receiptCopies; $copy++) {

            //set header
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

            //add receipt title
            if(!empty($request['receipt_name'])){
                $printer->feed();
                $printer->setTextSize(1, 2);
                $printer->text($request['receipt_name']."\n");
                $printer->selectPrintMode();
                $printer->feed();
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
                $printer->text($item['item_name'] . "\n");
                if(!empty($item['serial_number'])) $printer->text($item['serial_number'] . "\n");
                $printer->text(
                    sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2)) . "\n"
                );
                $printer->text("\n");
            }
            //$printer->feed(1);
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
            //check if should add customer charge details
            if (!empty($request['customer_charge'])) {
                $customerChargeText = sprintf("%-30s %-7s", "Customer Charge", $request['customer_charge']);
                $printer->text($customerChargeText . "\n\n");
            }

            //add sale taxes
            if (!empty($request['sale_tax_breakdown'])) {
                $printer->text("------------------------------------------------\n");
                $printer->feed();
                foreach ($request['sale_tax_breakdown'] as $tax) {
                    $taxEntry = sprintf("%-30s %-7s", $tax['tax_name'], $tax['tax_value_formatted']);
                    $printer->text($taxEntry . "\n");
                }
            }

            //total indicator
            $printer->text("------------------------------------------------\n");

            //check if should add delivery cost
            if (!empty($request['delivery_cost'])) {
                $deliveryCostText = sprintf("%-30s %-7s", "Delivery", $request['delivery_cost']);
                $printer->text($deliveryCostText . "\n\n");
            }

            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable'], 2));
            //$printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            //$printer->selectPrintMode();

            $printer->text("------------------------------------------------\n");
            //$printer->feed(1);

            //$amountGiven = sprintf("%-30s %-7s", "Amount Given (" . $request['payment_methods_string'] . ")", $request['amount_given']);
            $amountToPay = sprintf("%-30s %-7s", "Amount to pay", $request['amount_to_pay']);
            $balance = sprintf("%-5s %-24s %-7s", "", $request['balance_name'], $request['balance']);
            if (!empty($request['amount_to_pay'])) $printer->text($amountToPay . "\n");
            if (!empty($request['amount_given'])) $printer->text("Amount Given\n");
            if(!empty($request['payments'])){
                foreach ($request['payments'] as $payment) {
                    $paymentEntry = $amountGiven = sprintf("%-5s %-24s %-7s", "", $payment['payment'], $payment['total_formatted']);
                    $printer->text($paymentEntry."\n");
                }
            }
            if (!empty($request['balance_name'])) $printer->text($balance . "\n");

            $shouldShowPaymentsSection = !empty($request['amount_given']) || !empty($request['amount_to_pay']) || !empty($request['balance_name']);
            if ($shouldShowPaymentsSection) {
                //$printer->feed(1);
                $printer->text("------------------------------------------------\n");
            }

            //check if has details about loyalty points that has to be displayed
            $hasLoyaltyPointsDetails = !empty($request['loyalty_points_balance'])
                || !empty($request['loyalty_points_before'])
                || !empty($request['gained_loyalty_points'])
                || !empty($request['redeemed_loyalty_points']);
            if($hasLoyaltyPointsDetails){
                if (!empty($request['loyalty_points_before'])) {
                    $pointsBeforeText = sprintf("%-30s %-7s", "Loyalty points before", $request['loyalty_points_before']);
                    $printer->text($pointsBeforeText . "\n");
                }
                if (!empty($request['redeemed_loyalty_points'])) {
                    $pointsRedeemedText = sprintf("%-30s %-7s", "Loyalty points redeemed", $request['redeemed_loyalty_points']);
                    $printer->text($pointsRedeemedText . "\n");
                }
                if (!empty($request['gained_loyalty_points'])) {
                    $pointsGainedText = sprintf("%-30s %-7s", "Loyalty points gained", $request['gained_loyalty_points']);
                    $printer->text($pointsGainedText . "\n");
                }
                if (!empty($request['loyalty_points_balance'])) {
                    $pointsBalanceText = sprintf("%-30s %-7s", "Loyalty points balance", $request['loyalty_points_balance']);
                    $printer->text($pointsBalanceText . "\n");
                }
            }

            $printer->selectPrintMode();
            if ($hasLoyaltyPointsDetails) $printer->text("------------------------------------------------\n");

            if (!empty($request['sale_notes'])) {
                //$printer->feed();

                foreach ($request['sale_notes'] as $sale_note) {
                    $printer->text($sale_note['heading'] . ": " . $sale_note['content'] . "\n");
                }

                $printer->text("------------------------------------------------\n");
                //$printer->feed();
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

            //check if has receipt footer notes
            if (!empty($request['footer_notes'])) {
                //$printer->feed(2);
                $printer->text("------------------------------------------------\n");
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                }
                foreach ($request['footer_notes']['footer_notes'] as $footer_note) {
                    $printer->text($footer_note . "\n");
                }
                if ($request['footer_notes']['footer_notes_alignment'] == 'center') {
                    $printer->setJustification();
                }
            }

            //uzapoint footer
            //$printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if ($line1 = $this->filter_array($variables, 'line_1')) {
                $printer->text("------------------------------------------------\n");
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

            /*
             * CHECK IF ONE-SOURCE ESD SIGNATURE IS AVAILABLE AND ADD IT
             * */
            if(!empty($ESD_SIGNATURE)){
                $printer->feed();
                $printer->text($ESD_SIGNATURE . "\n");
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
        }catch (\Exception $exception){
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
        if(file_exists($filename)) {
            //READ THE SIGNATURE
            $signature = trim(file_get_contents($filename));
            //DELETE THE FILE FOR THE NEXT SIGNATURE
            @unlink($filename);
        }
        //RETURN SIGNATURE
        return $signature;
    }
}