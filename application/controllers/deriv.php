<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Deriv extends CI_Controller {
    private $date;
    private $currentDateTime;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Operations');
        $this->currentDateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $this->date = $this->currentDateTime->format('Y-m-d H:i:s');
    }

    /**
     * Handle Deriv deposit callback
     */
    public function deposit_callback()
    {
        // Get the raw callback data
        $callbackData = file_get_contents('php://input');
        
        // Log the raw callback for debugging
        $logFile = "deriv/deposit_callback_" . date('Y-m-d_H-i-s') . ".txt";
        file_put_contents($logFile, $callbackData);
        
        // Decode the JSON data
        $decode = json_decode($callbackData, true);
        
        // Check if JSON decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            file_put_contents("deriv/error_" . date('Y-m-d_H-i-s') . ".txt", 
                "JSON decode error: " . json_last_error_msg() . "\nRaw data: " . $callbackData);
            http_response_code(400);
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON']);
            return;
        }
        
        // Log the decoded data for debugging
        file_put_contents("deriv/decoded_" . date('Y-m-d_H-i-s') . ".txt", 
            print_r($decode, true));
        
        // Check if this is a paymentagent_transfer callback
        if (!isset($decode['paymentagent_transfer'])) {
            file_put_contents("deriv/structure_error_" . date('Y-m-d_H-i-s') . ".txt", 
                "Missing paymentagent_transfer structure\nDecoded data: " . print_r($decode, true));
            http_response_code(400);
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback structure']);
            return;
        }
        
        $transferData = $decode['paymentagent_transfer'];
        
        // Process the successful transfer
        $this->processDerivDeposit($transferData);
        
        // Respond to Deriv with success
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully']);
    }

    /**
     * Process successful Deriv deposit
     */
    private function processDerivDeposit($transferData)
    {
        try {
            $transaction_id = $transferData['transaction_id'] ?? null;
            $amount = $transferData['amount'] ?? null;
            $currency = $transferData['currency'] ?? 'USD';
            $client_to_loginid = $transferData['client_to_loginid'] ?? null; // CR number
            $client_to_full_name = $transferData['client_to_full_name'] ?? null;
            
            // Validate required fields
            if (empty($transaction_id) || empty($amount) || empty($client_to_loginid)) {
                file_put_contents("deriv/validation_error_" . date('Y-m-d_H-i-s') . ".txt", 
                    "Validation failed - TransactionID: $transaction_id, Amount: $amount, CR: $client_to_loginid");
                return;
            }
            
            // Find the deposit request
            $condition = array('cr_number' => $client_to_loginid, 'status' => 0);
            $depositRequest = $this->Operations->SearchByCondition('deriv_deposit_request', $condition);
            
            if (empty($depositRequest)) {
                file_put_contents("deriv/not_found_" . date('Y-m-d_H-i-s') . ".txt", 
                    "Deposit request not found for CR: $client_to_loginid");
                return;
            }
            
            $request = $depositRequest[0];
            $wallet_id = $request['wallet_id'];
            $request_id = $request['transaction_id'];
            
            // Update the deposit request
            $updateCondition = array('transaction_id' => $request_id);
            $updateData = array(
                'status' => 1,
                'deposited' => $amount,
                'deriv_transaction_id' => $transaction_id,
                'processed_date' => $this->date,
                'client_name' => $client_to_full_name
            );
            
            $update = $this->Operations->UpdateData('deriv_deposit_request', $updateCondition, $updateData);
            
            if ($update === TRUE) {
                // Get user details for notification
                $userCondition = array('wallet_id' => $wallet_id);
                $user = $this->Operations->SearchByCondition('customers', $userCondition);
                
                if (!empty($user)) {
                    $phone = $user[0]['phone'];
                    $customer_name = $user[0]['name'] ?? 'Customer';
                    
                    // Format message
                    $formatted_amount = number_format($amount, 2);
                    $message = "Hi $customer_name, your Deriv deposit of $formatted_amount $currency to account $client_to_loginid has been processed successfully. Transaction ID: $transaction_id";
                    
                    // Send SMS notification
                    $this->Operations->sendSMS($phone, $message);
                    
                    // Also notify admin
                    $adminMessage = "Deriv Deposit Success: $formatted_amount $currency to $client_to_loginid (Txn: $transaction_id)";
                    $adminPhones = ['0703416091'];
                    foreach ($adminPhones as $adminPhone) {
                        $this->Operations->sendSMS($adminPhone, $adminMessage);
                    }
                }
                
                file_put_contents("deriv/success_" . date('Y-m-d_H-i-s') . ".txt", 
                    "Successfully processed Deriv deposit:\n" .
                    "Transaction ID: $transaction_id\n" .
                    "Amount: $amount $currency\n" .
                    "CR Number: $client_to_loginid\n" .
                    "Wallet: $wallet_id");
            } else {
                file_put_contents("deriv/update_error_" . date('Y-m-d_H-i-s') . ".txt", 
                    "Failed to update deposit record for Transaction ID: $transaction_id");
            }
        } catch (Exception $e) {
            file_put_contents("deriv/exception_" . date('Y-m-d_H-i-s') . ".txt", 
                "Exception processing Deriv deposit:\n" .
                "Error: " . $e->getMessage() . "\n" .
                "Trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Handle Deriv transfer errors
     */
    public function transfer_error()
    {
        // Get the raw callback data
        $callbackData = file_get_contents('php://input');
        
        // Log the raw callback for debugging
        $logFile = "deriv/transfer_error_" . date('Y-m-d_H-i-s') . ".txt";
        file_put_contents($logFile, $callbackData);
        
        // Decode the JSON data
        $decode = json_decode($callbackData, true);
        
        // Check if this is an error callback
        if (isset($decode['error'])) {
            $error = $decode['error'];
            $code = $error['code'] ?? 'UNKNOWN';
            $message = $error['message'] ?? 'No error message';
            
            // Log the error
            file_put_contents("deriv/error_log_" . date('Y-m-d_H-i-s') . ".txt", 
                "Deriv Transfer Error:\nCode: $code\nMessage: $message");
            
            // You might want to look up the failed transaction and update its status
            // This would depend on how you track pending transfers
            
            // Notify admin
            $adminMessage = "Deriv Transfer Failed:\nCode: $code\nMessage: $message";
            $adminPhones = ['0703416091'];
            foreach ($adminPhones as $adminPhone) {
                $this->Operations->sendSMS($adminPhone, $adminMessage);
            }
        }
        
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Error logged']);
    }

    /**
     * Handle Deriv balance updates (for agent balance monitoring)
     */
    public function balance_update()
    {
        // Get the raw callback data
        $callbackData = file_get_contents('php://input');
        
        // Log the raw callback for debugging
        $logFile = "deriv/balance_update_" . date('Y-m-d_H-i-s') . ".txt";
        file_put_contents($logFile, $callbackData);
        
        // Decode the JSON data
        $decode = json_decode($callbackData, true);
        
        if (isset($decode['balance'])) {
            $balance = $decode['balance'];
            $current_balance = $balance['balance'] ?? 0;
            $currency = $balance['currency'] ?? 'USD';
            
            // Log the balance update
            file_put_contents("deriv/balance_log_" . date('Y-m-d') . ".txt", 
                date('H:i:s') . " - Balance: $current_balance $currency\n", 
                FILE_APPEND);
            
            // You might want to store this in database for monitoring
            $data = array(
                'balance' => $current_balance,
                'currency' => $currency,
                'update_time' => $this->date
            );
            $this->Operations->Create('deriv_balance_log', $data);
            
            // Alert if balance is low
            if ($current_balance < 1000) { // Example threshold
                $adminMessage = "Deriv Balance Alert: $current_balance $currency (Low Balance)";
                $adminPhones = ['0703416091'];
                foreach ($adminPhones as $adminPhone) {
                    $this->Operations->sendSMS($adminPhone, $adminMessage);
                }
            }
        }
        
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Balance update received']);
    }

    public function derivWithdrawalCallback()
    {
        $callbackData = file_get_contents('php://input');
        $data = json_decode($callbackData, true);
        
        // Log the callback
        $logFile = "deriv_callbacks/withdrawal_" . date('Y-m-d_H-i-s') . ".json";
        file_put_contents($logFile, json_encode($data, JSON_PRETTY_PRINT));

        if (isset($data['withdraw'])) {
            $withdrawal = $data['withdraw'];
            $referenceId = $withdrawal['reference_id'];
            $status = $withdrawal['status']; // 'success', 'pending', 'rejected'
            $transactionId = $withdrawal['transaction_id'] ?? null;
            
            // Find the withdrawal request
            $condition = array('deriv_reference_id' => $referenceId);
            $request = $this->Operations->SearchByCondition('deriv_withdraw_request', $condition);
            
            if (!empty($request)) {
                $request = $request[0];
                $updateData = array(
                    'deriv_verification_status' => $status,
                    'deriv_transaction_id' => $transactionId
                );
                
                if ($status === 'success') {
                    $updateData['deriv_verified_at'] = $this->date;
                    $updateData['status'] = 1;
                    $updateData['withdraw'] = $request['amount'];
                    
                    // Get user details for notification
                    $user = $this->Operations->SearchByCondition('customers', 
                        array('wallet_id' => $request['wallet_id']));
                    
                    if (!empty($user)) {
                        $phone = $user[0]['phone'];
                        $message = "Your withdrawal of {$request['amount']} USD has been completed. Transaction ID: {$transactionId}";
                        $this->Operations->sendSMS($phone, $message);
                    }
                }
                
                $this->Operations->UpdateData('deriv_withdraw_request', $condition, $updateData);
            }
        }
        
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback processed']);
    }
}