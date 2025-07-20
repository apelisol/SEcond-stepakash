
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Money extends CI_Controller {
    private $date;
    private $transaction_number;
    private $partner_transaction_number;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Operations');
        $this->currentDateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $this->date = $this->currentDateTime->format('Y-m-d H:i:s');
        $transaction_number = $this->GenerateNextTransaction();
        $this->transaction_number = $transaction_number;
        $partner_transaction_number = $this->GeneratePartnerNextTransaction();
        $this->partner_transaction_number = $partner_transaction_number;
    }
    
    
    public function stkresults()
    {
        // Get the raw callback data directly from M-Pesa
        $callbackData = file_get_contents('php://input');
        
        // Log the raw callback for debugging
        $logFile = "mpesac2b/callback_" . date('Y-m-d_H-i-s') . ".txt";
        file_put_contents($logFile, $callbackData);
        
        // Decode the JSON data
        $decode = json_decode($callbackData, true);
        
        // Check if JSON decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            file_put_contents("mpesac2b/error_" . date('Y-m-d_H-i-s') . ".txt", 
                "JSON decode error: " . json_last_error_msg() . "\nRaw data: " . $callbackData);
            http_response_code(400);
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON']);
            return;
        }
        
        // Log the decoded data for debugging
        file_put_contents("mpesac2b/decoded_" . date('Y-m-d_H-i-s') . ".txt", 
            print_r($decode, true));
        
        // Check if the callback contains the expected structure
        if (!isset($decode['Body']['stkCallback'])) {
            file_put_contents("mpesac2b/structure_error_" . date('Y-m-d_H-i-s') . ".txt", 
                "Missing stkCallback structure\nDecoded data: " . print_r($decode, true));
            http_response_code(400);
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback structure']);
            return;
        }
        
        $stkCallback = $decode['Body']['stkCallback'];
        $ResultCode = $stkCallback['ResultCode'];
        
        // Check if the transaction was successful
        if ($ResultCode != 0) {
            // Transaction failed - log the failure reason
            $ResultDesc = $stkCallback['ResultDesc'];
            file_put_contents("mpesac2b/failed_" . date('Y-m-d_H-i-s') . ".txt", 
                "Transaction failed - Code: $ResultCode, Desc: $ResultDesc");
            
            // Still update the database to mark as failed
            $MerchantRequestID = $stkCallback['MerchantRequestID'];
            $CheckoutRequestID = $stkCallback['CheckoutRequestID'];
            $this->updateFailedTransaction($MerchantRequestID, $CheckoutRequestID, $ResultCode, $ResultDesc);
            
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback processed']);
            return;
        }
        

        // Transaction successful - extract callback metadata
        if (!isset($stkCallback['CallbackMetadata']['Item'])) {
            file_put_contents("mpesac2b/metadata_error_" . date('Y-m-d_H-i-s') . ".txt", 
                "Missing CallbackMetadata\nCallback: " . print_r($stkCallback, true));
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Missing metadata']);
            return;
        }
        
        
        $items = $stkCallback['CallbackMetadata']['Item'];
        
        // Extract values from the callback metadata
        $callbackData = [];
        foreach ($items as $item) {
            $callbackData[$item['Name']] = $item['Value'];
        }
        
        // Get required values
        $MerchantRequestID = $stkCallback['MerchantRequestID'];
        $CheckoutRequestID = $stkCallback['CheckoutRequestID'];
        $Amount = isset($callbackData['Amount']) ? $callbackData['Amount'] : null;
        $MpesaReceiptNumber = isset($callbackData['MpesaReceiptNumber']) ? $callbackData['MpesaReceiptNumber'] : null;
        $TransactionDate = isset($callbackData['TransactionDate']) ? $callbackData['TransactionDate'] : null;
        $PhoneNumber = isset($callbackData['PhoneNumber']) ? $callbackData['PhoneNumber'] : null;
        

        // Validate required fields
        if (empty($MpesaReceiptNumber) || empty($Amount) || $Amount <= 0) {
            file_put_contents("mpesac2b/validation_error_" . date('Y-m-d_H-i-s') . ".txt", 
                "Validation failed - Receipt: $MpesaReceiptNumber, Amount: $Amount");
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid transaction data']);
            return;
        }
        
        // Process the successful transaction
        $this->SaveRequest($MerchantRequestID, $CheckoutRequestID, $MpesaReceiptNumber, 
         $Amount, $TransactionDate, $PhoneNumber);
        
        // Respond to M-Pesa with success
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully']);
    }
    
    // Method to handle failed transactions
    private function updateFailedTransaction($MerchantRequestID, $CheckoutRequestID, $ResultCode, $ResultDesc)
    {
        $condition = array(
            'MerchantRequestID' => $MerchantRequestID,
            'CheckoutRequestID' => $CheckoutRequestID
        );
        
        $data = array(
            'paid' => 0,
            'result_code' => $ResultCode,
            'result_desc' => $ResultDesc,
            'updated_on' => $this->date,
        );
        
        $this->Operations->UpdateData('mpesa_deposit', $condition, $data);
    }

    public function payment_response()
    {
        $response = $this->input->post('response');
        file_put_contents("mpesac2b/test".$this->date.".txt", $response);
    }



    // Fixed SaveRequest method with proper balance calculation
    public function SaveRequest($MerchantRequestID, $CheckoutRequestID, $MpesaReceiptNumber, $Amount, $TransactionDate, $phone)
    {
        $response = array();
        
        try {
            // Format phone number
            $moby = preg_replace('/^(?:\+?254|0)?/', '+254', $phone);
            
            if (!empty($MpesaReceiptNumber) && !empty($Amount) && $Amount > 0) {
                $condition = array(
                    'MerchantRequestID' => $MerchantRequestID,
                    'CheckoutRequestID' => $CheckoutRequestID
                );
                
                // Check if the transaction record exists
                $existingTransaction = $this->Operations->SearchByCondition('mpesa_deposit', $condition);
                if (empty($existingTransaction)) {
                    $response['status'] = 'fail';
                    $response['message'] = 'Transaction record not found';
                    file_put_contents("mpesac2b/not_found_" . date('Y-m-d_H-i-s') . ".txt", 
                        "Transaction not found: MerchantRequestID=$MerchantRequestID, CheckoutRequestID=$CheckoutRequestID");
                    return;
                }
                
                // Check if already processed
                if ($existingTransaction[0]['paid'] == 1) {
                    $response['status'] = 'success';
                    $response['message'] = 'Transaction already processed';
                    file_put_contents("mpesac2b/duplicate_" . date('Y-m-d_H-i-s') . ".txt", 
                        "Duplicate processing attempt: Receipt=$MpesaReceiptNumber");
                    return;
                }
                
                $data = array(
                    'amount' => $Amount,
                    'paid' => 1,
                    'txn' => $MpesaReceiptNumber,
                    'TransactionDate' => $TransactionDate,
                    'created_on' => $this->date,
                );
                
                $update = $this->Operations->UpdateData('mpesa_deposit', $condition, $data);
                
                if ($update === TRUE) {
                    // GET WALLET ID
                    $searchUser = $this->Operations->SearchByCondition('mpesa_deposit', $condition);
                    $wallet_id = $searchUser[0]['wallet_id'];
                    
                    // Get exchange rates
                    $buyratecondition = array('exchange_type' => 1, 'service_type' => 1);
                    $buyrate = $this->Operations->SearchByConditionBuy('exchange', $buyratecondition);
                    
                    $sellratecondition = array('exchange_type' => 2, 'service_type' => 1);
                    $sellrate = $this->Operations->SearchByConditionBuy('exchange', $sellratecondition);
                    
                    // Save to ledgers
                    $cr_dr = 'cr';
                    $transaction_id = $this->Operations->OTP(9);
                    $transaction_number = $this->transaction_number;
                    
                    $customer_ledger_data = array(
                        'transaction_id' => $transaction_id,
                        'transaction_number' => $transaction_number,
                        'receipt_no' => $this->Operations->Generator(15),
                        'description' => 'ITP',
                        'pay_method' => 'MPESA',
                        'wallet_id' => $wallet_id,
                        'trans_id' => $MpesaReceiptNumber,
                        'paid_amount' => $Amount,
                        'cr_dr' => $cr_dr,
                        'trans_date' => $this->date,
                        'currency' => 'KES',
                        'amount' => $Amount,
                        'rate' => 0,
                        'deriv' => 0,
                        'chargePercent' => 0,
                        'charge' => 0,
                        'total_amount' => $Amount,
                        'status' => 1,
                        'created_at' => $this->date,
                    );
                    
                    $save_customer_ledger = $this->Operations->Create('customer_ledger', $customer_ledger_data);
                    
                    $system_ledger_data = array(
                        'transaction_id' => $transaction_id,
                        'transaction_number' => $transaction_number,
                        'receipt_no' => $this->Operations->Generator(15),
                        'description' => 'ITP',
                        'pay_method' => 'MPESA',
                        'wallet_id' => $wallet_id,
                        'trans_id' => $MpesaReceiptNumber,
                        'paid_amount' => $Amount,
                        'cr_dr' => $cr_dr,
                        'trans_date' => $this->date,
                        'currency' => 'KES',
                        'deriv' => 0,
                        'amount' => $Amount,
                        'rate' => 0,
                        'chargePercent' => 0,
                        'charge' => 0,
                        'total_amount' => $Amount,
                        'status' => 1,
                        'created_at' => $this->date,
                    );
                    
                    $save_system_ledger = $this->Operations->Create('system_ledger', $system_ledger_data);
                    
                    if ($save_customer_ledger === TRUE && $save_system_ledger === TRUE) {
                        // Calculate current balance INCLUDING the new deposit
                        // This is the key fix - calculate balance AFTER the ledger entries are saved
                        $balance_condition = array('wallet_id' => $wallet_id, 'status' => 1);
                        $all_transactions = $this->Operations->SearchByCondition('customer_ledger', $balance_condition);
                        
                        $current_balance = 0;
                        if (!empty($all_transactions)) {
                            foreach ($all_transactions as $transaction) {
                                if ($transaction['cr_dr'] == 'cr') {
                                    $current_balance += $transaction['amount'];
                                } else if ($transaction['cr_dr'] == 'dr') {
                                    $current_balance -= $transaction['amount'];
                                }
                            }
                        }
                        
                        // Get customer details for SMS
                        $condition1 = array('wallet_id' => $wallet_id);
                        $searchuser1 = $this->Operations->SearchByCondition('customers', $condition1);
                        $mobile = $searchuser1[0]['phone'];
                        $customer_name = isset($searchuser1[0]['name']) ? $searchuser1[0]['name'] : 'Customer';
                        
                        // Format the transaction date for display
                        $formatted_date = date('d/m/Y H:i', strtotime($this->date));
                        $message = "Hi $customer_name, your STEPAKASH wallet has been credited with KES " . number_format($Amount, 2) . " (Ref: $MpesaReceiptNumber) on $formatted_date. New balance: KES " . number_format($current_balance, 2) . ".";
                        
                        // Send SMS notification
                        $sms = $this->Operations->sendSMS($mobile, $message);
                        
                        $response['status'] = 'success';
                        $response['message'] = 'Deposit processed successfully';
                        $response['mpesa_code'] = $MpesaReceiptNumber;
                        $response['amount'] = $Amount;
                        $response['balance'] = $current_balance;
                        
                        // Log successful processing with more details
                        file_put_contents("mpesac2b/success_" . date('Y-m-d_H-i-s') . ".txt", 
                            "Successfully processed:\n" .
                            "M-Pesa Receipt: $MpesaReceiptNumber\n" .
                            "Amount: KES $Amount\n" .
                            "Wallet: $wallet_id\n" .
                            "Customer: $customer_name\n" .
                            "Mobile: $mobile\n" .
                            "New Balance: KES $current_balance\n" .
                            "SMS Sent: " . ($sms ? 'Yes' : 'No'));
                            
                    } else {
                        $response['status'] = 'fail';
                        $response['message'] = 'Failed to update ledgers';
                        
                        // Log ledger failure
                        file_put_contents("mpesac2b/ledger_error_" . date('Y-m-d_H-i-s') . ".txt", 
                            "Ledger update failed for M-Pesa Receipt: $MpesaReceiptNumber");
                    }
                } else {
                    $response['status'] = 'fail';
                    $response['message'] = 'Failed to update deposit record';
                    
                    // Log deposit update failure
                    file_put_contents("mpesac2b/deposit_error_" . date('Y-m-d_H-i-s') . ".txt", 
                        "Deposit update failed for M-Pesa Receipt: $MpesaReceiptNumber");
                }
            } else {
                $response['status'] = 'fail';
                $response['message'] = 'Invalid transaction data - missing receipt number or invalid amount';
                
                // Log validation error with details
                file_put_contents("mpesac2b/validation_detailed_" . date('Y-m-d_H-i-s') . ".txt", 
                    "Validation failed:\n" .
                    "M-Pesa Receipt: " . ($MpesaReceiptNumber ?? 'NULL') . "\n" .
                    "Amount: " . ($Amount ?? 'NULL') . "\n" .
                    "Is Amount > 0: " . (($Amount > 0) ? 'Yes' : 'No'));
            }
        } catch (Exception $e) {
            $response['status'] = 'fail';
            $response['message'] = 'Processing error: ' . $e->getMessage();
            file_put_contents("mpesac2b/exception_" . date('Y-m-d_H-i-s') . ".txt", 
                "Exception occurred:\n" .
                "M-Pesa Receipt: " . ($MpesaReceiptNumber ?? 'NULL') . "\n" .
                "Error: " . $e->getMessage() . "\n" .
                "Trace: " . $e->getTraceAsString());
        }
        
        // Log the response with transaction details
        file_put_contents("mpesac2b/response_" . date('Y-m-d_H-i-s') . ".txt", 
            "Response for M-Pesa Receipt: " . ($MpesaReceiptNumber ?? 'NULL') . "\n" .
            json_encode($response, JSON_PRETTY_PRINT));
            
        return $response;
    }


   
    
   public function b2c_result()
    {
        // Get the raw callback data directly from M-Pesa
        $callbackData = file_get_contents('php://input');
        
        // Log the raw callback for debugging
        $logFile = "mpesab2c/callback_" . date('Y-m-d_H-i-s') . ".txt";
        file_put_contents($logFile, $callbackData);
        
        // Decode the JSON data
        $decode = json_decode($callbackData, true);
        
        // Check if JSON decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            file_put_contents("mpesab2c/error_" . date('Y-m-d_H-i-s') . ".txt", 
                "JSON decode error: " . json_last_error_msg() . "\nRaw data: " . $callbackData);
            http_response_code(400);
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON']);
            return;
        }
        
        // Log the decoded data for debugging
        file_put_contents("mpesab2c/decoded_" . date('Y-m-d_H-i-s') . ".txt", 
            print_r($decode, true));
        
        // Check if the callback contains the expected structure
        if (!isset($decode['Result'])) {
            file_put_contents("mpesab2c/structure_error_" . date('Y-m-d_H-i-s') . ".txt", 
                "Missing Result structure\nDecoded data: " . print_r($decode, true));
            http_response_code(400);
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback structure']);
            return;
        }
        
        $result = $decode['Result'];
        $resultCode = $result['ResultCode'];
        $resultDesc = $result['ResultDesc'];
        $originatorConversationID = $result['OriginatorConversationID'];
        $conversationID = $result['ConversationID'];
        
        // Check if the transaction was successful
        if ($resultCode != 0) {
            // Transaction failed - log the failure reason
            file_put_contents("mpesab2c/failed_" . date('Y-m-d_H-i-s') . ".txt", 
                "B2C Transaction failed - Code: $resultCode, Desc: $resultDesc\n" .
                "ConversationID: $conversationID\n" .
                "OriginatorConversationID: $originatorConversationID");
            
            // Update the database to mark as failed
            $this->updateFailedB2cTransaction($originatorConversationID, $conversationID, $resultCode, $resultDesc);
            
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback processed']);
            return;
        }
        
        // Transaction successful - extract result parameters
        if (!isset($result['ResultParameters']['ResultParameter'])) {
            file_put_contents("mpesab2c/parameters_error_" . date('Y-m-d_H-i-s') . ".txt", 
                "Missing ResultParameters\nResult: " . print_r($result, true));
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Missing parameters']);
            return;
        }
        
        $parameters = $result['ResultParameters']['ResultParameter'];
        
        // Extract values from the result parameters
        $resultData = [];
        foreach ($parameters as $param) {
            if (isset($param['Key']) && isset($param['Value'])) {
                $resultData[$param['Key']] = $param['Value'];
            }
        }
        
        // Get required values with fallback extraction method
        $MpesaReceiptNumber = $result['TransactionID'] ?? null;
        $Amount = $resultData['TransactionAmount'] ?? ($parameters[0]['Value'] ?? null);
        $transactionReceipt = $resultData['TransactionReceipt'] ?? ($parameters[1]['Value'] ?? null);
        $receiverPartyPublicName = $resultData['ReceiverPartyPublicName'] ?? ($parameters[2]['Value'] ?? null);
        $transactionCompletedDateTime = $resultData['TransactionCompletedDateTime'] ?? ($parameters[3]['Value'] ?? null);
        $b2cUtilityAccountAvailableFunds = $resultData['B2CUtilityAccountAvailableFunds'] ?? ($parameters[4]['Value'] ?? null);
        $b2cWorkingAccountAvailableFunds = $resultData['B2CWorkingAccountAvailableFunds'] ?? ($parameters[5]['Value'] ?? null);
        $b2cRecipientIsRegisteredCustomer = $resultData['B2CRecipientIsRegisteredCustomer'] ?? ($parameters[6]['Value'] ?? null);
        $b2cChargesPaidAccountAvailableFunds = $resultData['B2CChargesPaidAccountAvailableFunds'] ?? ($parameters[7]['Value'] ?? null);
        
        // Validate required fields
        if (empty($MpesaReceiptNumber) || empty($Amount) || $Amount <= 0) {
            file_put_contents("mpesab2c/validation_error_" . date('Y-m-d_H-i-s') . ".txt", 
                "B2C Validation failed - Receipt: $MpesaReceiptNumber, Amount: $Amount\n" .
                "ConversationID: $conversationID");
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid transaction data']);
            return;
        }
        
        // Process the successful transaction
        $this->SaveB2cResult($resultCode, $resultDesc, $originatorConversationID, $conversationID, 
            $MpesaReceiptNumber, $Amount, $receiverPartyPublicName, $transactionCompletedDateTime, 
            $b2cUtilityAccountAvailableFunds, $b2cWorkingAccountAvailableFunds);
        
        // Respond to M-Pesa with success
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'B2C Callback processed successfully']);
    }
    
    public function SaveB2cResult($resultCode, $resultDesc, $originatorConversationID, $conversationID,
        $MpesaReceiptNumber, $Amount, $receiverPartyPublicName, $transactionCompletedDateTime, 
        $b2cUtilityAccountAvailableFunds, $b2cWorkingAccountAvailableFunds)
    {
        $response = array();
        
        try {
            if (!empty($MpesaReceiptNumber) && !empty($Amount) && $Amount > 0) {
                $condition = array(
                    'conversationID' => $conversationID,
                    'OriginatorConversationID' => $originatorConversationID
                );
                
                // Check if the transaction record exists
                $existingTransaction = $this->Operations->SearchByCondition('mpesa_withdrawals', $condition);
                if (empty($existingTransaction)) {
                    $response['status'] = 'fail';
                    $response['message'] = 'B2C Transaction record not found';
                    file_put_contents("mpesab2c/not_found_" . date('Y-m-d_H-i-s') . ".txt", 
                        "B2C Transaction not found: ConversationID=$conversationID, OriginatorConversationID=$originatorConversationID");
                    return $response;
                }
                
                // Check if already processed
                if ($existingTransaction[0]['paid'] == 1) {
                    $response['status'] = 'success';
                    $response['message'] = 'B2C Transaction already processed';
                    file_put_contents("mpesab2c/duplicate_" . date('Y-m-d_H-i-s') . ".txt", 
                        "Duplicate B2C processing attempt: Receipt=$MpesaReceiptNumber");
                    return $response;
                }
                
                $data = array(
                    'amount' => $Amount,
                    'paid' => 1,
                    'MpesaReceiptNumber' => $MpesaReceiptNumber,
                    'receiverPartyPublicName' => $receiverPartyPublicName,
                    'b2cUtilityAccountAvailableFunds' => $b2cUtilityAccountAvailableFunds,
                    'transactionCompletedDateTime' => $transactionCompletedDateTime,
                    'currency' => 'KES',
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc,
                    'updated_on' => $this->date,
                );
                
                $update = $this->Operations->UpdateData('mpesa_withdrawals', $condition, $data);
                
                if ($update === TRUE) {
                    $searchUser = $this->Operations->SearchByCondition('mpesa_withdrawals', $condition);
                    $wallet_id = $searchUser[0]['wallet_id'];
                    $transaction_number = $searchUser[0]['transaction_number'];
                    
                    // Get customer details
                    $condition1 = array('wallet_id' => $wallet_id);
                    $searchuser1 = $this->Operations->SearchByCondition('customers', $condition1);
                    $mobile = $searchuser1[0]['phone'];
                    $customer_name = isset($searchuser1[0]['name']) ? $searchuser1[0]['name'] : 'Customer';
                    
                    // Calculate current balance after withdrawal
                    $balance_condition = array('wallet_id' => $wallet_id, 'status' => 1);
                    $all_transactions = $this->Operations->SearchByCondition('customer_ledger', $balance_condition);
                    
                    $current_balance = 0;
                    if (!empty($all_transactions)) {
                        foreach ($all_transactions as $transaction) {
                            if ($transaction['cr_dr'] == 'cr') {
                                $current_balance += $transaction['amount'];
                            } else if ($transaction['cr_dr'] == 'dr') {
                                $current_balance -= $transaction['amount'];
                            }
                        }
                    }
                    
                    // Format the transaction date for display
                    $formatted_date = date('d/m/Y H:i', strtotime($this->date));
                    $message = "Hi $customer_name, KES " . number_format($Amount, 2) . " has been withdrawn from your STEPAKASH wallet (Ref: $MpesaReceiptNumber) on $formatted_date. New balance: KES " . number_format($current_balance, 2) . ".";
                    
                    // Send SMS notification
                    $sms = $this->Operations->sendSMS($mobile, $message);
                    
                    $response['status'] = 'success';
                    $response['message'] = 'B2C withdrawal processed successfully';
                    $response['mpesa_code'] = $MpesaReceiptNumber;
                    $response['amount'] = $Amount;
                    $response['balance'] = $current_balance;
                    
                    // Log successful processing
                    file_put_contents("mpesab2c/success_" . date('Y-m-d_H-i-s') . ".txt", 
                        "Successfully processed B2C withdrawal:\n" .
                        "M-Pesa Receipt: $MpesaReceiptNumber\n" .
                        "Amount: KES $Amount\n" .
                        "Wallet: $wallet_id\n" .
                        "Customer: $customer_name\n" .
                        "Mobile: $mobile\n" .
                        "New Balance: KES $current_balance\n" .
                        "SMS Sent: " . ($sms ? 'Yes' : 'No'));
                        
                } else {
                    $response['status'] = 'fail';
                    $response['message'] = 'Failed to update B2C withdrawal record';
                    
                    file_put_contents("mpesab2c/update_error_" . date('Y-m-d_H-i-s') . ".txt", 
                        "B2C update failed for M-Pesa Receipt: $MpesaReceiptNumber");
                }
            } else {
                $response['status'] = 'fail';
                $response['message'] = 'Invalid B2C transaction data';
                
                file_put_contents("mpesab2c/validation_detailed_" . date('Y-m-d_H-i-s') . ".txt", 
                    "B2C validation failed:\n" .
                    "M-Pesa Receipt: " . ($MpesaReceiptNumber ?? 'NULL') . "\n" .
                    "Amount: " . ($Amount ?? 'NULL') . "\n" .
                    "Is Amount > 0: " . (($Amount > 0) ? 'Yes' : 'No'));
            }
        } catch (Exception $e) {
            $response['status'] = 'fail';
            $response['message'] = 'B2C processing error: ' . $e->getMessage();
            file_put_contents("mpesab2c/exception_" . date('Y-m-d_H-i-s') . ".txt", 
                "B2C Exception occurred:\n" .
                "M-Pesa Receipt: " . ($MpesaReceiptNumber ?? 'NULL') . "\n" .
                "Error: " . $e->getMessage() . "\n" .
                "Trace: " . $e->getTraceAsString());
        }
        
        // Log the response
        file_put_contents("mpesab2c/response_" . date('Y-m-d_H-i-s') . ".txt", 
            "B2C Response for M-Pesa Receipt: " . ($MpesaReceiptNumber ?? 'NULL') . "\n" .
            json_encode($response, JSON_PRETTY_PRINT));
            
        return $response;
    }

    private function updateFailedB2cTransaction($originatorConversationID, $conversationID, $resultCode, $resultDesc)
    {
        $condition = array(
            'conversationID' => $conversationID,
            'OriginatorConversationID' => $originatorConversationID
        );
        
        $data = array(
            'paid' => 0,
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'updated_on' => $this->date,
        );
        
        $this->Operations->UpdateData('mpesa_withdrawals', $condition, $data);
    }
    
    public function mpesa_c2b_results()
    {
        $stkCallbackResponse = file_get_contents('php://input');
        file_put_contents("mpesac2b/".$this->date.".txt", $stkCallbackResponse);
    }

    public function post_c2b_results()
    {
        $response = $this->input->post('response');
        file_put_contents("mpesac2b/today".$this->date.".txt", $response);
        $data = json_decode($response,true);
    }
    
    public function validation_url()
    {
        $stkCallbackResponse = file_get_contents('php://input');
        file_put_contents("mpesac2b/".$this->date.".txt", $stkCallbackResponse);
        
    }

    public function GenerateNextTransaction()
    {
        $last_id = $this->Operations->getLastTransactionId();
        $nextReceipt = $this->getNextReceipt($last_id);
        return $nextReceipt;
    }


    public function GeneratePartnerNextTransaction()
    {
        $last_id = $this->Operations->getLastPartnerTransactionId();
        $nextReceipt = $this->getNextReceipt($last_id);
        return $nextReceipt;
    }


    public function getNextReceipt($currentReceipt) {
        preg_match('/([A-Z]+)(\d+)([A-Z]*)/', $currentReceipt, $matches);
        $letters = $matches[1];
        $digits = intval($matches[2]);
        $extraLetter = isset($matches[3]) ? $matches[3] : '';
        if (!empty($extraLetter)) {
            $nextExtraLetter = chr(ord($extraLetter) + 1);
            if ($nextExtraLetter > 'Z') {
                $nextExtraLetter = 'A';
                $nextDigits = $digits + 1;
            } else {
                $nextDigits = $digits;
            }
        } else {
            $nextExtraLetter = 'A';
            $nextDigits = $digits + 1;
        }
        if ($nextDigits == 100) {
            $lettersArray = str_split($letters);
            $lastIndex = count($lettersArray) - 1;
            $lettersArray[$lastIndex] = chr(ord($lettersArray[$lastIndex]) + 1);
            $nextLetters = implode('', $lettersArray);
            $nextDigits = 1;
        } else {
            $nextLetters = $letters;
        }
        $nextDigitsStr = str_pad($nextDigits, 2, '0', STR_PAD_LEFT);
        $nextReceipt = $nextLetters . $nextDigitsStr . $nextExtraLetter;
        return $nextReceipt;
    }


    public function next_receipt()
    {
        $transaction_id =  $this->GenerateNextTransaction();
        $response['status'] = 'success';
        $response['message'] = 'next receipt';
        $response['data'] = $transaction_id;
        echo json_encode($response);
    }

    public function toa()
    {
        $response['status'] = 'success';
        $response['message'] = 'next receipt';
        $response['data'] = $this->transaction_number;
        echo json_encode($response);
    }


    public function partners_stkresults()
    {
        $response = $this->input->post('response');
        file_put_contents("mpesapartnerc2b/".$this->date.".txt", $response);
        $decode = json_decode($response,true);
        $MerchantRequestID = $decode['Body']['stkCallback']['MerchantRequestID'];
        $CheckoutRequestID = $decode['Body']['stkCallback']['CheckoutRequestID'];
        $Amount = $decode['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
        $MpesaReceiptNumber = $decode['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
        $TransactionDate = $decode['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
        $phone = $decode['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
        $ResultCode = $decode['Body']['stkCallback']['ResultCode'];
        $CustomerMessage = $decode['Body']['stkCallback']['ResultDesc'];
        if(!empty($MpesaReceiptNumber) && ($Amount > 0))
        {
            $this->SavePartnersMpesaRequest($MerchantRequestID,$CheckoutRequestID,$MpesaReceiptNumber,$Amount,$TransactionDate,$phone);
        }
    }


    public function SavePartnersMpesaRequest($MerchantRequestID,$CheckoutRequestID,$MpesaReceiptNumber,$Amount,$TransactionDate,$phone)
    {
        if(!empty($MpesaReceiptNumber) || !empty($Amount) || $Amount !=NULL || $MpesaReceiptNumber !=NULL)
        {
            $condition = array('MerchantRequestID'=>$MerchantRequestID,'CheckoutRequestID'=>$CheckoutRequestID);
            $data = array(
                'amount'=>$Amount,
                'status'=>1,
                'receipt_no'=>$MpesaReceiptNumber,
                'TransactionDate'=>$TransactionDate,
                'created_on'=>$this->date,
            );
            $update = $this->Operations->UpdateData('partner_mpesa_deposit',$condition,$data);
            $searchUser = $this->Operations->SearchByCondition('partner_mpesa_deposit',$condition);
            $partner_id = $searchUser[0]['partner_id'];
            $partner_phone_paid = $searchUser[0]['phone'];
            $cr_dr = 'cr';
            $transaction_id =  $this->Operations->OTP(9);
            $partner_transaction_number = $this->partner_transaction_number;
            $description = 'funds top up';
                $partner_ledger_data = array(
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $partner_transaction_number,
                    'receipt_no'		=>	$this->Operations->Generator(15),
                    'description'		=>	$description,
                    'pay_method' => 'MPESA',
                    'partner_id' => $partner_id,
                    'trans_id' => $MpesaReceiptNumber,
                    'trans_amount' => $Amount,
                    'cr_dr'=>$cr_dr,
                    'charge' =>0,
                    'charge_percent' =>0,
                    'currency' => 'KES',
                    'amount' => $Amount,
                    'total_amount' =>$Amount,
                    'ledger_account'=>1,
                    'status' => 1,
                    'trans_date' => $this->date,
                );
                $save_partner_ledger = $this->Operations->Create('partner_ledger',$partner_ledger_data);
                $partner_system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $partner_transaction_number,
                    'receipt_no'		=>	$this->Operations->Generator(15),
                    'description'		=>	$description,
                    'pay_method' => 'MPESA',
                    'partner_id' => $partner_id,
                    'trans_id' => $MpesaReceiptNumber,
                    'trans_amount' => $Amount,
                    'cr_dr'=>$cr_dr,
                    'charge' =>0,
                    'charge_percent' =>0,
                    'currency' => 'KES',
                    'amount' => $Amount,
                    'total_amount' =>$Amount,
                    'ledger_account'=>1,
                    'status' => 1,
                    'trans_date' => $this->date,
                );
                $save_partner_system_ledger = $this->Operations->Create('partner_system_ledger',$partner_system_ledger_data);
                $message = ''.$partner_transaction_number.' Successfully KES '.$Amount.' deposited to account for partner: '.$partner_id.'';
                if($update === TRUE && $save_partner_ledger === TRUE && $save_partner_system_ledger === TRUE)
                {
                    $sms = $this->Operations->sendSMS($partner_phone_paid, $message);
                    $response['status'] = 'success';
                    $response['message'] = $message;
                }else
                {
                    $response['status'] = 'fail';
                    $response['message'] = 'something went wrong,try again';
                }

        }
        echo json_encode($response);
    }



    public function partner_b2c_result()
    {
        $response = $this->input->post('response'); 
        file_put_contents("partnerb2c/".$this->date.".txt", $response);
        $data = json_decode($response,true);
        $resultType = $data['Result']['ResultType'];
        $resultCode = $data['Result']['ResultCode'];
        $resultDesc = $data['Result']['ResultDesc'];
        $originatorConversationID = $data['Result']['OriginatorConversationID'];
        $conversationID = $data['Result']['ConversationID'];
        $MpesaReceiptNumber = $data['Result']['TransactionID'];
        $Amount = $data['Result']['ResultParameters']['ResultParameter'][0]['Value'];
        $transactionReceipt = $data['Result']['ResultParameters']['ResultParameter'][1]['Value'];
        $receiverPartyPublicName = $data['Result']['ResultParameters']['ResultParameter'][2]['Value'];
        $transactionCompletedDateTime = $data['Result']['ResultParameters']['ResultParameter'][3]['Value'];
        $b2cUtilityAccountAvailableFunds = $data['Result']['ResultParameters']['ResultParameter'][4]['Value'];
        $b2cWorkingAccountAvailableFunds = $data['Result']['ResultParameters']['ResultParameter'][5]['Value'];
        $b2cRecipientIsRegisteredCustomer = $data['Result']['ResultParameters']['ResultParameter'][6]['Value'];
        $b2cChargesPaidAccountAvailableFunds = $data['Result']['ResultParameters']['ResultParameter'][7]['Value'];
        if(!empty($MpesaReceiptNumber) || !empty($Amount) || $Amount !=NULL || $MpesaReceiptNumber !=NULL && ($Amount > 0))
        {
        $this->Save_partner_B2cResult($resultCode,$resultDesc,$originatorConversationID,$conversationID,$MpesaReceiptNumber,
        $Amount,$receiverPartyPublicName,$transactionCompletedDateTime,$b2cUtilityAccountAvailableFunds,$b2cWorkingAccountAvailableFunds);
        }   
    }
    
    public function Save_partner_B2cResult($resultCode,$resultDesc,$originatorConversationID,$conversationID,
    $MpesaReceiptNumber,$Amount,$receiverPartyPublicName,$transactionCompletedDateTime,$b2cUtilityAccountAvailableFunds,$b2cWorkingAccountAvailableFunds)
    {
            $condition = array('conversationID'=>$conversationID,'OriginatorConversationID'=>$originatorConversationID);
            $data = array(
                'receipt_no'=>$MpesaReceiptNumber,
                'amount'=>$Amount,
                'withdraw'=>$Amount,
                'status'=>1,
                'currency'=>'KES'
            );
            $update = $this->Operations->UpdateData('partner_transfer_funds',$condition,$data);
            $searchUser = $this->Operations->SearchByCondition('partner_transfer_funds',$condition);
            $account_number = $searchUser[0]['account_number'];
            $transaction_number = $searchUser[0]['transaction_number'];
            $receipt_no = $searchUser[0]['receipt_no'];
            $message = ''.$transaction_number.', Successfully KES '.$Amount.' transfered to '.$account_number.' account';
            if($update === TRUE)
            {
                $sms = $this->Operations->sendSMS($account_number, $message);
                $response['status'] = 'success';
                $response['message'] = $message;
            }else
            {
                $response['status'] = 'fail';
                $response['message'] = 'something went wrong,try again';
            }
        echo json_encode($response);
        
    }

    public function gifting_b2c_result()
    {
        $response = $this->input->post('response'); 
        file_put_contents("partnerb2c/".$this->date.".txt", $response);
        $data = json_decode($response,true);
        $resultType = $data['Result']['ResultType'];
        $resultCode = $data['Result']['ResultCode'];
        $resultDesc = $data['Result']['ResultDesc'];
        $originatorConversationID = $data['Result']['OriginatorConversationID'];
        $conversationID = $data['Result']['ConversationID'];
        $MpesaReceiptNumber = $data['Result']['TransactionID'];
        $Amount = $data['Result']['ResultParameters']['ResultParameter'][0]['Value'];
        $transactionReceipt = $data['Result']['ResultParameters']['ResultParameter'][1]['Value'];
        $receiverPartyPublicName = $data['Result']['ResultParameters']['ResultParameter'][2]['Value'];
        $transactionCompletedDateTime = $data['Result']['ResultParameters']['ResultParameter'][3]['Value'];
        $b2cUtilityAccountAvailableFunds = $data['Result']['ResultParameters']['ResultParameter'][4]['Value'];
        $b2cWorkingAccountAvailableFunds = $data['Result']['ResultParameters']['ResultParameter'][5]['Value'];
        $b2cRecipientIsRegisteredCustomer = $data['Result']['ResultParameters']['ResultParameter'][6]['Value'];
        $b2cChargesPaidAccountAvailableFunds = $data['Result']['ResultParameters']['ResultParameter'][7]['Value'];
        if(!empty($MpesaReceiptNumber) || !empty($Amount) || $Amount !=NULL || $MpesaReceiptNumber !=NULL && ($Amount > 0))
        {
        $this->Save_gifting_B2cResult($resultCode,$resultDesc,$originatorConversationID,$conversationID,$MpesaReceiptNumber,
        $Amount,$receiverPartyPublicName,$transactionCompletedDateTime,$b2cUtilityAccountAvailableFunds,$b2cWorkingAccountAvailableFunds);
        }
    }
    
    public function Save_gifting_B2cResult($resultCode,$resultDesc,$originatorConversationID,$conversationID,
    $MpesaReceiptNumber,$Amount,$receiverPartyPublicName,$transactionCompletedDateTime,$b2cUtilityAccountAvailableFunds,$b2cWorkingAccountAvailableFunds)
    {
            $condition = array('conversationID'=>$conversationID,'OriginatorConversationID'=>$originatorConversationID);
            $data = array(
                'receipt_no'=>$MpesaReceiptNumber,
                'sent'=>$Amount,
                'status'=>1,
                'currency'=>'KES'
            );
            $update = $this->Operations->UpdateData('gifting',$condition,$data);
            $searchUser = $this->Operations->SearchByCondition('gifting',$condition);
            $phone = $searchUser[0]['phone'];
            $transaction_number = $searchUser[0]['transaction_number'];
            $message = ''.$transaction_number.', Successfully KES '.$Amount.' gifted to '.$phone.' account';
            if($update === TRUE)
            {
                // $sms = $this->Operations->sendSMS($phone, $message);
                $response['status'] = 'success';
                $response['message'] = $message;
            }else
            {
                $response['status'] = 'fail';
                $response['message'] = 'something went wrong,try again';
            }
        echo json_encode($response);
        
    }


    

}
