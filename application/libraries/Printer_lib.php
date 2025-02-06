<?php
defined("BASEPATH") or exit("No direct script access allowed");

require APPPATH . "third_party/escpos-php/autoload.php";
require 'Carbon/Carbon.php';

//use Carbon\Carbon;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
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

    public function captain($request = array())
    {

        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        foreach ($request['receipts'] as $index => $receipt) {
            if (trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
                $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
            } else if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
                $networkPrinterIP = !empty($receipt['printer']) && !empty($receipt['printer']['ip']) ?
                    trim($receipt['printer']['ip'])
                    : trim($request['LOCAL_PRINTER']['id']);
                $connector = new NetworkPrintConnector($networkPrinterIP, '9100');
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
                $printer->feed(1);
            }


            //print Printer Name
            if (!empty($receipt['order_ref'])) {
                $printer->text($receipt['order_ref'] . "\n\n");
            }
            $printer->setEmphasis(false);

            /*$printer->text($receipt['customer'] . "\n");
            $printer->feed(1);*/
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
            if (!empty($request['order_options']) && sizeof($request['order_options'])) {
                $printer->text("------------------------------------------------\n");
                $printer->feed();
                $printer->text("    ORDER OPTIONS\n");
                $printer->text('       ' . implode(", ", $request['order_options']) . "\n");
            }

            if (!empty($request['order_delivery_method'])) {
                $printer->text("------------------------------------------------\n");
                $printer->feed();
                $printer->text("DELIVERY DETAILS\n\n");
                if ($request['order_delivery_method'] == 'delivery') $printer->text('Customer: ' . $receipt['customer'] . "\n");
                if ($request['order_delivery_method'] == 'delivery' && !empty($request['order_customer_contact'])) $printer->text('Phone: ' . $request['order_customer_contact'] . "\n");
                $printer->text('Delivery: ' . ($request['order_delivery_method'] == 'pickup' ? 'Pickup Order' : 'Delivery Order') . "\n");
                if ($request['order_delivery_method'] == 'delivery' && !empty($request['order_delivery_location'])) $printer->text('Location: ' . $request['order_delivery_location'] . "\n");
                if ($request['order_delivery_method'] == 'delivery' && !empty($request['order_delivery_address'])) $printer->text('Address: ' . $request['order_delivery_address'] . "\n");
                //if(!empty($request['order_delivery_cost'])) $printer->text('Delivery Cost: ' . $request['order_delivery_cost'] . "\n");
                if ($request['order_delivery_method'] == 'pickup' && !empty($request['order_pickup_details'])) $printer->text('Other Details: ' . $request['order_pickup_details'] . "\n");
            }

            $printer->feed(5);
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

    public function bill($request = array())
    {
        /*var_dump($request);
        die();*/
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if (trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        } else if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
            $networkPrinterIP = !empty($request['printer_ip']) ? trim($request['printer_ip']) : trim($request['LOCAL_PRINTER']['id']);
            $connector = new NetworkPrintConnector($networkPrinterIP, '9100');
        }
        $printer = new Printer($connector);

        /*
         * FOR BUSINESSES WHICH USE ONE-SOURCE ESD, GENERATE THE ESD SIGNATURE
         * */
        $ESD_SIGNATURE = null;
        if (!empty($request['CAN_ESD_SIGN']) && $this->generateOneSourceESDSignature($request['items'])) {
            sleep(2);
            $ESD_SIGNATURE = $this->readOneSourceESDSignature();
        }

        $variables = $request['variables'];
        $receiptCopies = 1;
        if (!empty($request['sale_receipt_copies'])) $receiptCopies = (int)$request['sale_receipt_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {

            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            //check if a logo for this installation has been put in assets directory
            $logoPath = getcwd() . "/application/assets/receipt_logo.png";
            if (file_exists($logoPath) && is_readable($logoPath)) {
                $img = \Mike42\Escpos\EscposImage::load($logoPath);
                $printer->bitImage($img);
                $printer->text("\n");
            }

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
            if (!empty($request['receipt_name'])) {
                //$printer->feed();
                $printer->setTextSize(1, 2);
                $printer->text($request['receipt_name'] . "\n");
                $printer->selectPrintMode();
            }
            //Added receipt status
            if (!empty($request['receipt_type'])) {
                $printer->text($request['receipt_type'] . "\n");
                //$printer->feed();
            }
            $printer->feed();

            $printer->setJustification();

            if (empty($request['receipt_other_details']) || $request['receipt_other_details']['SALE_NO'] == "true") {
                $printer->setEmphasis(true);
                $printer->text($request['entity'] . " No     :   " . $request['order_ref'] . "\n");
                $printer->setEmphasis(false);
            }

            if (empty($request['receipt_other_details']) || $request['receipt_other_details']['SERVED_BY'] == "true") $printer->text("Served By   :   " . $request['pos_user'] . "\n");

            $printer->selectPrintMode();
            if (empty($request['receipt_other_details']) || $request['receipt_other_details']['CUSTOMER_NAME'] == "true") {
                $printer->text("Customer    :   " . $request["customer"] . "\n");
                if (!empty($request['customer_phone'])) $printer->text("PHONE NO.     :   " . $request["customer_phone"] . "\n");
                if (!empty($request['customer_pin'])) $printer->text("PIN NO.     :   " . $request["customer_pin"] . "\n");
            }

            if (empty($request['receipt_other_details']) || $request['receipt_other_details']['RECEIPT_DATE'] == "true") $printer->text($request['receipt_date'] . "\n");
            $printer->feed();


            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $key => $item) {
                $printer->text($item['item_name'] . "\n");
                if (!empty($item['serial_number'])) $printer->text($item['serial_number'] . "\n");
                $printer->text(
                    sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2)) . "\n"
                );
                $printer->text("\n");
            }
            //$printer->feed(1);
            $printer->text("------------------------------------------\n");


            /*$orderDueText = sprintf("%-5s %20s %15s", " ", "TOTAL : KES.", number_format((float)$request['grand_total']));
            $printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            $printer->selectPrintMode();
            $printer->text("------------------------------------------------\n");*/

            $grandTotal = sprintf("%-30s %-7s", "SubTotal", number_format((float)$request['grand_total'], 2));
            $discount = sprintf("%-30s %-7s", (!empty($request['discount_name']) ? $request['discount_name'] : "Discount"), number_format((float)$request['discount'], 2));
            $printer->text($grandTotal . "\n");
            $printer->text($discount . "\n");
            //check if should add customer charge details
            if (!empty($request['customer_charge'])) {
                $customerChargeText = sprintf("%-30s %-7s", "Customer Charge", $request['customer_charge']);
                $printer->text($customerChargeText . "\n\n");
            }

            //add sale taxes
            if (!empty($request['sale_tax_breakdown'])) {
                $printer->text("------------------------------------------\n");
                $printer->feed();
                /*foreach ($request['sale_tax_breakdown'] as $tax) {
                    $taxEntry = sprintf("%-30s %-7s", $tax['tax_name'], $tax['tax_value_formatted']);
                    $printer->text($taxEntry . "\n");
                }*/

                //UPDATE 17TH AUGUST 2022 - SALE TAXES IN A TABULAR FORMAT
                $taxHeader = sprintf("%-7s %-8s %-15s %-15s", "Code", "Rate", "Taxable", "Tax Amt");
                $printer->setEmphasis(true);
                $printer->text($taxHeader . "\n");
                $printer->setEmphasis(false);

                foreach ($request['sale_tax_breakdown'] as $tax) {
                    $taxEntry = sprintf("%-7s %-8s %-15s %-15s", $tax['tax_label'], $tax["tax_percentage"], $tax['taxable_amount_formatted'], $tax['tax_value_formatted']);
                    $printer->text($taxEntry . "\n");
                }
            }

            //total indicator
            $printer->text("------------------------------------------\n");

            //check if should add delivery cost
            if (!empty($request['delivery_cost'])) {
                $deliveryCostText = sprintf("%-30s %-7s", "Delivery", $request['delivery_cost']);
                $printer->text($deliveryCostText . "\n\n");
            }

            $orderDueText = sprintf("%-30s %-7s", "TOTAL (" . ($request['business_currency_code'] ?? 'KES') . ")", number_format((float)$request['amount_payable'], 2));

            //$printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            //$printer->selectPrintMode();

            $printer->text("------------------------------------------\n");

            //Display sale items count, where necessary
            if (!empty($request['sale_items_count'])) {
                $printer->text("Total Items" . ": " . $request['sale_items_count'] . "\n");
                $printer->feed();
            }

            //$amountGiven = sprintf("%-30s %-7s", "Amount Given (" . $request['payment_methods_string'] . ")", $request['amount_given']);
            $amountToPay = sprintf("%-30s %-7s", "Amount to pay", $request['amount_to_pay']);
            $balance = sprintf("%-5s %-24s %-7s", "", $request['balance_name'], $request['balance']);
            if (!empty($request['amount_to_pay'])) $printer->text($amountToPay . "\n");
            if (!empty($request['amount_given'])) $printer->text("Amount Given\n");
            if (!empty($request['payments'])) {
                foreach ($request['payments'] as $payment) {

                    $defaultTransactionCodes = ['mpesa', 'cash', 'credit_card', 'voucher', 'cheque', 'bank_transfer', 'customer_account', 'credit'];

                    // If the transaction code is not provided, or the transaction code is among those defaulted to
                    // when one does not add the transaction code
                    if (
                        isset($payment['transaction_code']) &&
                        (
                            in_array($payment['transaction_code'], $defaultTransactionCodes) ||
                            $payment['transaction_code'] == ''
                        ) && empty($payment['transactions'])) {
                        $paymentEntry = sprintf("%-5s %-24s %-7s", "", $payment['payment'], $payment['total_formatted']);
                        $printer->text($paymentEntry . "\n");
                    }

                    // if transaction code is provided
                    if (
                        isset($payment['transaction_code']) &&
                        (
                            !in_array($payment['transaction_code'], $defaultTransactionCodes) &&
                            $payment['transaction_code'] != ''
                        ) && empty($payment['transactions'])) {
                        $transactionCode = $payment['transaction_code'];
                        $paymentMethodWithCode = $payment['payment'] . ' (' . $transactionCode . ')';
                        $paymentEntry = sprintf("%-5s %-24s %-7s", "", $paymentMethodWithCode, $payment['total_formatted']);
                        $printer->text($paymentEntry . "\n");
                    }

                    // check if has transactions(multiple transactions for one payment method) for reprinting receipt
                    if (!empty($payment['transactions'])) {
                        foreach ($payment['transactions'] as $transaction) {
                            $transactionCode = $transaction['transaction_code'];

                            if (in_array($transactionCode, $defaultTransactionCodes) || $transactionCode == '') {
                                $paymentEntry = sprintf("%-5s %-24s %-7s", "", $transaction['payment'], $transaction['amount']);
                                $printer->text($paymentEntry . "\n");
                            }

                            if (!in_array($transactionCode, $defaultTransactionCodes) && $transactionCode != '') {
                                $paymentMethodWithCode = $transaction['payment'] . ' (' . $transactionCode . ')';
                                $paymentEntry = sprintf("%-5s %-24s %-7s", "", $paymentMethodWithCode, $transaction['amount']);
                                $printer->text($paymentEntry . "\n");
                            }
                        }
                    }
                }
            }
            //display cash change
            if (!empty($request['cash_change'])) $printer->text(sprintf("%-5s %-24s %-7s", "", "Change", $request['cash_change']) . "\n");
            if (!empty($request['balance_name'])) $printer->text($balance . "\n");

            $shouldShowPaymentsSection = !empty($request['amount_given']) || !empty($request['amount_to_pay']) || !empty($request['balance_name']);
            if ($shouldShowPaymentsSection) {
                //$printer->feed(1);
                $printer->text("------------------------------------------\n");
            }
            //Added a check for customer balance
            if (!empty($request['customer_receivables_balance'])) {
                $printer->setTextSize(1, 2);
                $customer_balance = sprintf("%-30s %-7s", "Customer Balance", $request['customer_receivables_balance']);
                $printer->text($customer_balance . "\n");
                $printer->selectPrintMode();
                $printer->text("------------------------------------------\n");
            }

            $printer->selectPrintMode();
            //check if has details about loyalty points that has to be displayed
            if (!empty($request['has_loyalty_program']) && $request['has_loyalty_program']) {
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
            if (!empty($request['has_loyalty_program']) && $request['has_loyalty_program']) $printer->text("------------------------------------------\n");
            
            if (!empty($request['sale_notes'])) {
                //$printer->feed();

                foreach ($request['sale_notes'] as $sale_note) {
                    $printer->text($sale_note['heading'] . ": " . $sale_note['content'] . "\n");
                }

                $printer->text("------------------------------------------\n");
                //$printer->feed();
            }
            
            //show delivery location, customer name and address on sales receipt
            if (!empty($request['order_delivery_method'])) {
                $printer->feed();
                $printer->setEmphasis(true);
                $printer->text("DELIVERY DETAILS\n\n");
                $printer->setEmphasis(false);
                if ($request['order_delivery_method'] == 'delivery') $printer->text("Customer    :   " . $request["customer"] . "\n");
                if ($request['order_delivery_method'] == 'delivery' && !empty($request['order_delivery_location'])) $printer->text('Location    :   ' . $request['order_delivery_location'] . "\n");
                if ($request['order_delivery_method'] == 'delivery' && !empty($request['order_delivery_address'])) $printer->text('Address     :   ' . $request['order_delivery_address'] . "\n");
                if(!empty($request['order_delivery_cost'])) $printer->text('Delivery Cost   :   ' . $request['order_delivery_cost'] . "\n");
                $printer->text("------------------------------------------------\n");
            }                

            // Added A Setting to display Bold on Till No
            if ($tillNo = $this->filter_array($variables, 'till_no')) {
                if ($tillNo['is_bold']) $printer->setTextSize(1, 2);
                if ($tillNo['is_bold']) $printer->setEmphasis(true);
                $printer->text("TILL NO.    :   " . $tillNo['value'] . "\n");
                $printer->setEmphasis(false);
                $printer->selectPrintMode();
            }
            if ($paybillBusinessNo = $this->filter_array($variables, 'paybill_no')) {
                if ($paybillBusinessNo['is_bold']) $printer->setTextSize(1, 2);
                if ($paybillBusinessNo['is_bold']) $printer->setEmphasis(true);
                $printer->text("Paybill NO. :   " . $paybillBusinessNo['value'] . "\n");
                $printer->setEmphasis(false);
                $printer->selectPrintMode();
            }
            if ($paybillAccountNo = $this->filter_array($variables, 'paybill_account_no')) {
                if ($paybillAccountNo['is_bold']) $printer->setTextSize(1, 2);
                if ($paybillAccountNo['is_bold']) $printer->setEmphasis(true);
                $printer->text("Account NO. :   " . $paybillAccountNo['value'] . "\n");
                $printer->setEmphasis(false);
                $printer->selectPrintMode();
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
                $printer->text("------------------------------------------\n");
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
            $printer->text("------------------------------------------\n");
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

            /*
             * CHECK IF ONE-SOURCE ESD SIGNATURE IS AVAILABLE AND ADD IT
             * */
            if (!empty($ESD_SIGNATURE)) {
                $printer->feed();
                $printer->text($ESD_SIGNATURE . "\n");
            }
            /*
             * CHECK IF NEW TIMS ETR SIGNATURE DETAILS EXIST, PRINT QR Code
             * */
            if (!empty($request['signed_invoice_details'])) {
                //$signedInvoiceDetails = json_decode($request['signed_invoice_details'], true);
                $signedInvoiceDetails = $request['signed_invoice_details'];
                $printer->feed(2);
                $printer->text("CU Invoice No.: " . $signedInvoiceDetails['invoice_number'] . "\n");
                $printer->text("CU Serial No.: " . $signedInvoiceDetails['control_code'] . "\n");
                $printer->selectPrintMode();
                $printer->qrCode($signedInvoiceDetails['qr_code_url'], Printer::QR_ECLEVEL_L, 6);
            }
            /*
             * CHECK IF DIGITAX ETIMS DETAILS ARE PROVIDED, PRINT QR Code
             * */
            if (!empty($request['digitax_etims_details'])) {
                $digitaxEtimsDetails = $request['digitax_etims_details'];
                $printer->feed(2);
                $printer->text("Serial No: " . $digitaxEtimsDetails['serial_number'] . "\n");
                $printer->text("Signature: " . $digitaxEtimsDetails['signature'] . "\n");
                $printer->selectPrintMode();
                $printer->qrCode($digitaxEtimsDetails['etims_url'], Printer::QR_ECLEVEL_L, 6);
            }

            $printer->setJustification();
            $printer->feed(5);
            $connector->write(chr(27) . chr(109));
        }

        $printer->pulse();
        $printer->close();

        return true;
    }

    public function ecommerceSaleReceipt($request = array())
    {

        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if (trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        } else if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
            $networkPrinterIP = !empty($request['printer_ip']) ? trim($request['printer_ip']) : trim($request['LOCAL_PRINTER']['id']);
            $connector = new NetworkPrintConnector($networkPrinterIP, '9100');
        }
        $printer = new Printer($connector);

        $variables = $request['variables'];
        $receiptCopies = 1;
        if (!empty($request['sale_receipt_copies'])) $receiptCopies = (int)$request['sale_receipt_copies'];
        for ($copy = 1; $copy <= $receiptCopies; $copy++) {

            //set header
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            //check if a logo for this installation has been put in assets directory
            $logoPath = getcwd() . "/application/assets/receipt_logo.png";
            if (file_exists($logoPath) && is_readable($logoPath)) {
                $img = \Mike42\Escpos\EscposImage::load($logoPath);
                $printer->bitImage($img);
                $printer->text("\n");
            }

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
            if (!empty($request['receipt_name'])) {
                //$printer->feed();
                $printer->setTextSize(1, 2);
                $printer->text($request['receipt_name'] . "\n");
                $printer->selectPrintMode();
            }
            //Added receipt status
            if (!empty($request['receipt_type'])) {
                $printer->text($request['receipt_type'] . "\n");
                //$printer->feed();
            }
            $printer->feed();

            $printer->setJustification();

            if (empty($request['receipt_other_details']) || $request['receipt_other_details']['SALE_NO'] == "true") {
                $printer->setEmphasis(true);
                $printer->text($request['entity'] . " No     :   " . $request['order_ref'] . "\n");
                $printer->setEmphasis(false);
            }

            if (empty($request['receipt_other_details']) || $request['receipt_other_details']['SERVED_BY'] == "true") $printer->text("Served By   :   " . $request['pos_user'] . "\n");

            $printer->selectPrintMode();
            if (empty($request['receipt_other_details']) || $request['receipt_other_details']['CUSTOMER_NAME'] == "true") {
                $printer->text("Customer    :   " . $request["customer"] . "\n");
                if (!empty($request['customer_pin'])) $printer->text("PIN NO.     :   " . $request["customer_pin"] . "\n");
            }

            if (empty($request['receipt_other_details']) || $request['receipt_other_details']['RECEIPT_DATE'] == "true") $printer->text($request['receipt_date'] . "\n");
            $printer->feed();


            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $key => $item) {
                $printer->text($item['item_name'] . "\n");
                if (!empty($item['serial_number'])) $printer->text($item['serial_number'] . "\n");
                $printer->text(
                    sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2)) . "\n"
                );
                $printer->text("\n");
            }
            //$printer->feed(1);
            $printer->text("------------------------------------------\n");

            $grandTotal = sprintf("%-30s %-7s", "SubTotal", number_format((float)$request['grand_total'], 2));
            $discount = sprintf("%-30s %-7s", (!empty($request['discount_name']) ? $request['discount_name'] : "Discount"), number_format((float)$request['discount'], 2));
            $printer->text($grandTotal . "\n");
            $printer->text($discount . "\n");
            //check if should add customer charge details
            if (!empty($request['customer_charge'])) {
                $customerChargeText = sprintf("%-30s %-7s", "Customer Charge", $request['customer_charge']);
                $printer->text($customerChargeText . "\n\n");
            }

            //add sale taxes
            if (!empty($request['sale_tax_breakdown'])) {
                $printer->text("------------------------------------------\n");
                $printer->feed();


                //UPDATE 17TH AUGUST 2022 - SALE TAXES IN A TABULAR FORMAT
                $taxHeader = sprintf("%-7s %-8s %-15s %-15s", "Code", "Rate", "Taxable", "Tax Amt");
                $printer->setEmphasis(true);
                $printer->text($taxHeader . "\n");
                $printer->setEmphasis(false);

                foreach ($request['sale_tax_breakdown'] as $tax) {
                    $taxEntry = sprintf("%-7s %-8s %-15s %-15s", $tax['tax_label'], $tax["tax_percentage"], $tax['taxable_amount_formatted'], $tax['tax_value_formatted']);
                    $printer->text($taxEntry . "\n");
                }
            }

            //total indicator
            $printer->text("------------------------------------------\n");

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

            $printer->text("------------------------------------------\n");

            //Display sale items count, where necessary
            if (!empty($request['sale_items_count'])) {
                $printer->text("Total Items" . ": " . $request['sale_items_count'] . "\n");
                $printer->feed();
            }


            //$amountGiven = sprintf("%-30s %-7s", "Amount Given (" . $request['payment_methods_string'] . ")", $request['amount_given']);
            $amountToPay = sprintf("%-30s %-7s", "Amount to pay", $request['amount_to_pay']);
            $balance = sprintf("%-5s %-24s %-7s", "", $request['balance_name'], $request['balance']);
            if (!empty($request['amount_to_pay'])) $printer->text($amountToPay . "\n");
            if (!empty($request['amount_given'])) $printer->text("Amount Given\n");
            if (!empty($request['payments'])) {
                foreach ($request['payments'] as $payment) {
                    $paymentEntry = $amountGiven = sprintf("%-5s %-24s %-7s", "", $payment['payment'], $payment['total_formatted']);
                    $printer->text($paymentEntry . "\n");
                }
            }
            //display cash change
            if (!empty($request['cash_change'])) $printer->text(sprintf("%-5s %-24s %-7s", "", "Change", $request['cash_change']) . "\n");
            if (!empty($request['balance_name'])) $printer->text($balance . "\n");

            $shouldShowPaymentsSection = !empty($request['amount_given']) || !empty($request['amount_to_pay']) || !empty($request['balance_name']);
            if ($shouldShowPaymentsSection) {
                //$printer->feed(1);
                $printer->text("------------------------------------------\n");
            }
            //Added a check for customer balance
            if (!empty($request['customer_receivables_balance'])) {
                $printer->setTextSize(1, 2);
                $customer_balance = sprintf("%-30s %-7s", "Customer Balance", $request['customer_receivables_balance']);
                $printer->text($customer_balance . "\n");
                $printer->selectPrintMode();
                $printer->text("------------------------------------------\n");
            }

            //check if has details about loyalty points that has to be displayed
            $hasLoyaltyPointsDetails = !empty($request['loyalty_points_balance'])
                || !empty($request['loyalty_points_before'])
                || !empty($request['gained_loyalty_points'])
                || !empty($request['redeemed_loyalty_points']);
            if ($hasLoyaltyPointsDetails) {
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
            if ($hasLoyaltyPointsDetails) $printer->text("------------------------------------------\n");

            if (!empty($request['sale_notes'])) {
                //$printer->feed();

                foreach ($request['sale_notes'] as $sale_note) {
                    $printer->text($sale_note['heading'] . ": " . $sale_note['content'] . "\n");
                }

                $printer->text("------------------------------------------\n");
                //$printer->feed();
            }
            // // Added A Setting to display Bold on Till No
            // if ($tillNo = $this->filter_array($variables, 'till_no')) {
            //     if($tillNo['is_bold'] ) $printer->setTextSize(1, 2);
            //     if($tillNo['is_bold'] ) $printer->setEmphasis(true);
            //     $printer->text("TILL NO.    :   " . $tillNo['value'] . "\n");
            //     $printer->setEmphasis(false);
            //     $printer->selectPrintMode();
            // }

            // if ($pinNo = $this->filter_array($variables, 'pin_no')) {
            //     $printer->text("PIN NO.     :   " . $pinNo['value'] . "\n");
            // }

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
                $printer->text("------------------------------------------\n");
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
            $printer->text("------------------------------------------\n");
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

            /*
             * CHECK IF ONE-SOURCE ESD SIGNATURE IS AVAILABLE AND ADD IT
             * */
            if (!empty($ESD_SIGNATURE)) {
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
            $printer->text("CREDIT NOTE\n");

            //set header
            if ($companyName = $this->filter_array($variables, 'company_name')) {
                $printer->text($companyName['value']);
                $printer->feed();
            }
            $printer->setEmphasis(false);
            $printer->selectPrintMode();

            if ($heading1 = $this->filter_array($variables, 'contact_1')) {
                $printer->text($heading1['value'] . "\n");
            }

            if ($heading2 = $this->filter_array($variables, 'contact_2')) {
                $printer->text($heading2['value'] . "\n");
            }
            $printer->feed(2);
            $printer->setJustification();

            $printer->setEmphasis(true);
            $printer->text($request['entity'] . " No    :   " . $request['order_ref'] . "\n");
            $printer->setEmphasis(false);

            $printer->text("Served By   :   " . $request['pos_user'] . "\n");

            $printer->selectPrintMode();
            $printer->text("Customer    :   " . $request["customer"] . "\n");

            $printer->text($request['receipt_date'] . "\n\n");


            $header = sprintf("%-30s %-7s", "Item", "Total");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->setEmphasis(false);

            foreach ($request['items'] as $key => $item) {
                $printer->text($item['item_name'] . "\n");
                $printer->text(
                    sprintf("%-30s %-7s", ($item['qty'] . ' x ' . $item['item_price']), number_format((float)$item['total'], 2)) . "\n"
                );
                $printer->feed();
            }
            $printer->text("------------------------------------------------\n");

            $grandTotal = sprintf("%-30s %-7s", "Total", number_format((float)$request['grand_total'], 2));
            $discount = sprintf("%-30s %-7s", "Discount", number_format((float)$request['discount'], 2));
            $printer->text($grandTotal . "\n");
            $printer->text($discount . "\n");

            //total indicator
            $printer->text("------------------------------------------------.\n");

            $orderDueText = sprintf("%-30s %-7s", "TOTAL (KES)", number_format((float)$request['amount_payable'], 2));
            //$printer->setTextSize(1, 2);
            $printer->setEmphasis(true);
            $printer->text($orderDueText . "\n");
            //$printer->selectPrintMode();

            $printer->text("------------------------------------------------\n\n");
            //$printer->feed(1);

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

            /*
             * CHECK IF NEW TIMS ETR SIGNATURE DETAILS EXIST, PRINT QR Code
             * */
            if (!empty($request['signed_credit_note_details'])) {
                $signedCreditNoteDetails = $request['signed_credit_note_details'];
                $printer->feed(2);
                $printer->text("CU Invoice No.: " . $signedCreditNoteDetails['invoice_number'] . "\n");
                $printer->text("CU Serial No.: " . $signedCreditNoteDetails['control_code'] . "\n");
                $printer->selectPrintMode();
                $printer->feed();
                $printer->qrCode($signedCreditNoteDetails['qr_code_url'], Printer::QR_ECLEVEL_L, 6);
            }

            $printer->setJustification();
            $printer->feed(3);
            $printer->cut();
        }

        $printer->pulse();
        $printer->close();

        return true;
    }

    public function payTypes($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        if (trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        } else if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
            $connector = new NetworkPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        }

        $printer = new Printer($connector);


        try {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $printer->text(strtoupper($request['BUSINESS_NAME']));
            $printer->feed(3);

            $printer->text("SHIFT PAY TYPES");
            $printer->setEmphasis();
            $printer->feed();
            $printer->selectPrintMode();
            $printer->setJustification();
            $printer->feed();

            $printer->selectPrintMode();
            $printer->text("Opened:   " . $request["opened_by"] . "\n");
            $printer->text("          " . $request["opened_at"] . "\n");
            $printer->feed();

            foreach ($request['payments'] as $payment) {

                $printer->feed();
                $printer->setEmphasis(true);
                $printer->text(strtoupper($payment['method']) . "\n");
                $printer->setEmphasis(false);

                $printer->text("  Total Sales: " . ($payment['sales'] ?? '0.00') . "\n");
                $printer->text("  Overpayments: " . ($payment['overpayments'] ?? '0.00') . "\n");
                $printer->text("  Tips: " . ($payment['tips'] ?? '0.00') . "\n");
                $printer->text("  Customer Deposits: " . ($payment['customer_deposits'] ?? '0.00') . "\n");
                $printer->text("  Cash Refunds: " . ($payment['cash_refunds'] ?? '0.00') . "\n");
                $printer->text("  Paid Invoices: " . ($payment['paid_invoices'] ?? '0.00') . "\n");
                $printer->text("  Expenses: " . ($payment['expenses'] ?? '0.00') . "\n");
                $printer->text("  Purchases: " . ($payment['purchases'] ?? '0.00') . "\n");
                $printer->text("  Total: " . ($payment['total'] ?? '0.00') . "\n");
            }

            $printer->feed(5);

            $connector->write(chr(27) . chr(109));
            $printer->close();
        } catch (Exception $e) {

            return false;
        }
    }

    public function shift($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        if (trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
            $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
        } else if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
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
            if (!empty($request["terminal_name"])) {
                $printer->text("Terminal:   " . $request["terminal_name"] . "\n");
                $printer->feed();
            }

            $printer->text("Opened:   " . $request["opened_by"] . "\n");
            $printer->text("          " . $request["opened_at"] . "\n");
            $printer->feed();
            $printer->text("Closed:   " . $request["closed_by"] . "\n");
            $printer->text("          " . $request["closed_at"] . "\n");

            $printer->feed(2);

            //adding shift opening amount
            $shiftOpeningAmountEntry = sprintf("%-18s %-8s %-8s %-8s", "Opening Amount", "", $request['opening_amount'], "");
            $printer->feed();
            $printer->setEmphasis(true);
            $printer->text($shiftOpeningAmountEntry . "\n");
            $printer->setEmphasis(false);
            $printer->feed();


            if ($request['has_breakdown'] != "true") {
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
                /*
                 *  Display Expenses Incurred
                 */
                $shiftExpenseEntry = sprintf("%-18s", "Expenses Incurred");
                $printer->feed();
                $printer->setEmphasis(true);
                $printer->text($shiftExpenseEntry);
                $printer->feed();
                $printer->setEmphasis(false);
                if (!empty($request['expenses'])) {
                    foreach ($request['expenses'] as $type => $amount) {
                        $myExpense = sprintf("%-18s %-8s", $type, $amount);
                        $printer->text($myExpense . "\n");
                    }
                }
            } else {
                foreach ($request['payments'] as $payment) {

                    $printer->feed();
                    $printer->setEmphasis(true);
                    $printer->text(strtoupper($payment['method']) . "\n");
                    $printer->setEmphasis(false);

                    $printer->text("  Sales: " . ($payment['sales'] ?? '0.00') . "\n");
                    $printer->text("  Overpayments: " . ($payment['overpayments'] ?? '0.00') . "\n");
                    $printer->text("  Tips: " . ($payment['tips'] ?? '0.00') . "\n");
                    $printer->text("  Customer Deposits: " . ($payment['customer_deposits'] ?? '0.00') . "\n");
                    $printer->text("  Cash Refunds: " . ($payment['cash_refunds'] ?? '0.00') . "\n");
                    $printer->text("  Paid Invoices: " . ($payment['paid_invoices'] ?? '0.00') . "\n");
                    $printer->text("  Expenses: " . ($payment['expenses'] ?? '0.00') . "\n");
                    $printer->text("  Purchases: " . ($payment['purchases'] ?? '0.00') . "\n");
                    if (strtoupper($payment['method']) == 'TOTAL') $printer->setEmphasis(true);
                    $printer->text("  Total Expected: " . ($payment['expected'] ?? '0.00') . "\n");
                    $printer->text("  Total Actual: " . ($payment['actual'] ?? '0.00') . "\n");
                    $printer->text("  Total Variance: " . ($payment['variance'] ?? '0.00') . "\n");
                }

                $printer->feed();
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

    public function droppayment($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);

        $copies = intval($request['receipt_copies'] ?? '1');

        for ($i = 0; $i < $copies; $i++) {
            if (trim($request['LOCAL_PRINTER']['adapter']) === 'USB') {
                $connector = new WindowsPrintConnector(trim($request['LOCAL_PRINTER']['id']));
            } else if (trim($request['LOCAL_PRINTER']['adapter']) === 'NETWORK') {
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
                $printer->text("DROP PAYMENT RECEIPT \n");
                $printer->selectPrintMode();
                $printer->setJustification();
                $printer->feed();

                $printer->selectPrintMode();
                $printer->text("Terminal    :   " . $request["terminal"] . "\n");
                $printer->text("Time        :   " . $request["drop_time"] . "\n");
                $printer->text("Amount      :   " . $request["amount"] . "\n");
                $printer->text("Reference   :   " . $request["ref"] . "\n");

                $printer->feed();
                $printer->text("-------------------------------------------\n");
                $printer->feed();

                $printer->text("Cashier:   " . $request["pos_user"] . "\n\n");
                $printer->text("Sign:________________________\n\n\n");

                $printer->text("Dropped By:   " . $request["dropped_by"] . "\n\n");
                $printer->text("Sign:________________________\n");

                $printer->feed(5);
                $connector->write(chr(27) . chr(109));

                $printer->close();
            } catch (Exception $e) {
                // echo 'Message: ' . $e->getMessage();
                return false;
            }
        }

        return true;
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

    //Method to download sale receipt as text file
    public function saveTextReceipt($request = array())
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        $path = $request['receipt_path'];
        $textFileName = $request['receipt_filename'];
        $request = (isset($request['enc']) && $request['enc']) ? urldecode($request['receipt_contents']) : $request['receipt_contents'];
        /*echo urldecode($request);
        die();*/

        $receiptName = $path . '/' . $textFileName;
        $myfile = fopen($receiptName, "x+") or die("Unable to open file!");
        fwrite($myfile, $request);
        fclose($myfile);
    }

    public function timsEtrTypeB($request)
    {
        $pdfContent = file_get_contents(trim($request['receipt_path']));
        file_put_contents(trim($request['local_path']) . DIRECTORY_SEPARATOR . trim($request["filename"]), $pdfContent);


        if (!empty($request['etr_driver']) && trim($request['etr_driver']) == 'adva') {
            $printCommand = '"' . trim($request['java_path']) . '"' . '\java.exe -classpath ' . getcwd() . '\application\assets\pdfbox-app-1.7.1.jar org.apache.pdfbox.PrintPDF -silentPrint -printerName '
                . trim($request['etr_printer']) . ' '
                . trim($request['local_path']) . DIRECTORY_SEPARATOR . trim($request["filename"]);

            $output = null;
            $retval = null;

            exec($printCommand, $output, $retval);
        }

        return "Successfully saved ETR receipt";
    }

    public function sendToCustomerDisplay($request)
    {
        $request = filter_var($request, \FILTER_CALLBACK, ['options' => 'trim']);
        $displayText = trim($request['display_text']);
        if (empty($displayText)) return;
        $comPort = trim($request['com_port']);
        $baudRate = trim($request['baud_rate']) ?? '9600';

        $clearScreenCommand = getcwd() . '/application/assets/SerialSend.exe /baudrate ' . $baudRate . ' /hex "\x0C" /devnum ' . $comPort;
        $displayTextCommand = getcwd() . '/application/assets/SerialSend.exe /baudrate ' . $baudRate . ' /hex ' . $displayText . ' /devnum ' . $comPort;
        exec($clearScreenCommand);
        exec($displayTextCommand);

        return;
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
