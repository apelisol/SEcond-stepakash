<?php
defined('BASEPATH') OR exit('No direct script access allowed');
use WebSocket\Client;
class Main extends CI_Controller {
    
    private $transaction_id;
    private $transaction_number;
    private $partner_transaction_number;
    private $currentDateTime;
    private $date;
    private $timeframe;
    
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Operations');
        $this->load->library('session');
        $this->currentDateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $this->date  = $this->currentDateTime->format('Y-m-d H:i:s');
        $this->timeframe = 600;
        $transaction_id = $this->session->userdata('transaction_id');
        $time_frame = $this->session->userdata('time_frame');
        $valid_time_frame = $time_frame && (time() - $time_frame <= 30);
        if (!$transaction_id || !$valid_time_frame) {
            $transaction_id = $this->Operations->OTP(6);
            $transaction_number =  $this->GenerateNextTransaction();
            $this->transaction_number = $transaction_number;
            $time_frame = time();
            $this->session->set_userdata('transaction_id', $transaction_id);
            $this->session->set_userdata('time_frame', $time_frame);
        }
        $this->transaction_id = $transaction_id;
        $partner_transaction_number =  $this->GeneratePartnerNextTransaction();
        $this->partner_transaction_number = $partner_transaction_number;
       header('Content-Type: application/json');
       header("Access-Control-Allow-Origin: * ");
       header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
       header('Access-Control-Allow-Headers: Content-Type');
       header('Access-Control-Max-Age: 86400');
    }

	public function index()
	{
		$this->load->view('login');
	}
	
    public function home()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $session_table = 'login_session';
            $session_id = $this->input->post('session_id');
            $session_condition = array('session_id' => $session_id);
            $checksession = $this->Operations->SearchByCondition($session_table, $session_condition);
            $loggedtime = $checksession[0]['created_on'];
            $currentTime = $this->date;
            $loggedTimestamp = strtotime($loggedtime);
            $currentTimestamp = strtotime($currentTime);
            $timediff = $currentTimestamp - $loggedTimestamp;
            // Check if the time difference is more than 1 minute (60 seconds)
            if (($timediff) >  $this->timeframe) {
                $response['status'] = 'fail';
                $response['message'] = 'User logged out';
                $response['data'] = '';
            }
            else if (!empty($checksession) && $checksession[0]['session_id'] == $session_id) {
                $wallet_id = $checksession[0]['wallet_id'];
                $user_details = $this->UserAccount($wallet_id);
                $user_credit =$user_details['total_credit'];
                $user_debit =$user_details['total_debit'];
                $user_balance =$user_details['total_balance'];
                $user_phone = $user_details['phone'];
                $user_wallet = $user_details['wallet_id'];
                // $user_agent = $user_details['agent'];
                $summary = $this->Operations->customer_transection_summary($wallet_id);
                $condition = array('wallet_id' => $wallet_id);
                $table = 'customer_ledger';
                $transactions = $this->Operations->SearchByConditionDeriv($table, $condition);
                $trans_data = [];
                foreach ($transactions as $key ) {
                    $trans_detail = $this->mapTransactionDetails($key);
                    $user_trans['transaction_type'] = $trans_detail['transaction_type'];
                    $user_trans['status_text'] = $trans_detail['status_text'];
                    $user_trans['status_color'] = $trans_detail['status_color'];
                    $user_trans['text_arrow'] = $trans_detail['text_arrow'];
                    $user_trans['transaction_number'] = $key['transaction_number'];
                    $user_trans['receipt_no'] = $key['receipt_no'];
                    $user_trans['pay_method'] = $key['pay_method'];
                    $user_trans['wallet_id'] = $key['wallet_id'];
                    $user_trans['trans_id'] = $key['trans_id'];
                    $user_trans['paid_amount'] = $key['paid_amount'];
                    $user_trans['amount'] = $key['amount'];
                    $user_trans['trans_date'] = $key['trans_date'];
                    $user_trans['currency'] = $key['currency'];
                    $user_trans['status'] = $key['status']; 
                    $user_trans['created_at'] = $key['created_at'];
                    $trans_data[] = $user_trans;
                 
                }
                //get our buy rate
                $deriv_buy_condition = array('exchange_type' => 1,'service_type'=>1);
                $buyrate = $this->Operations->SearchByConditionBuy('exchange', $deriv_buy_condition);
                //get our sell rate
                $deriv_sell_condition = array('exchange_type' => 2,'service_type'=>1);
                $sellrate = $this->Operations->SearchByConditionBuy('exchange', $deriv_sell_condition);
                $transactions = $trans_data;
                $buyrate = $buyrate[0]['kes'];
                $sellrate = $sellrate[0]['kes'];
                $dollar_rates = $this->get_rates();
                $data = array(
                    'total_credit' => $user_credit,
                    'total_debit' => $user_debit,
                    'total_balance' => $user_balance,
                    'buyrate' => $buyrate,
                    'sellrate' => $sellrate,
                    'deriv_buy' => $dollar_rates['deriv_buy'],
                    'deriv_buy_charge' => $dollar_rates['deriv_buy_charge'],
                    'deriv_buy_fee' => $dollar_rates['deriv_buy_fee'],
                    'deriv_sell' => $dollar_rates['deriv_sell'],
                    'deriv_sell_charge' => $dollar_rates['deriv_sell_charge'],
                    'deriv_sell_fee' => $dollar_rates['deriv_sell_fee'],
                    'currentTime' => $timediff,
                    // 'trans_data' => $trans_data,
                    'transactions' => $transactions,
                );
    
                $response['status'] = 'success';
                $response['message'] = 'User is logged in';
                $response['data'] = $data;
            } else {
                // User not logged in
                $response['status'] = 'fail';
                $response['message'] = 'User not logged in';
                $response['data'] = null;
            }
        } else {
            // Not a POST request
            $response['status'] = 'fail';
            $response['message'] = 'Invalid request method';
            $response['data'] = null;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function get_rates()
    {
        $rates = array();
        // Deriv buy rate
        $deriv_buy_condition = array('exchange_type' => 1, 'service_type' => 1);
        $deriv_buy_rate = $this->Operations->SearchByConditionBuy('exchange', $deriv_buy_condition);
        $rates['deriv_buy'] = $deriv_buy_rate[0]['kes'];
        $rates['deriv_buy_charge'] = $deriv_buy_rate[0]['charge'];
        $rates['deriv_buy_fee'] = $deriv_buy_rate[0]['fee'];
        // Deriv sell rate
        $deriv_sell_condition = array('exchange_type' => 2, 'service_type' => 1);
        $deriv_sell_rate = $this->Operations->SearchByConditionBuy('exchange', $deriv_sell_condition);
        $rates['deriv_sell'] = $deriv_sell_rate[0]['kes'];
        $rates['deriv_sell_charge'] = $deriv_sell_rate[0]['charge'];
        $rates['deriv_sell_fee'] = $deriv_sell_rate[0]['fee'];
        // Return the rates as JSON
        return $rates;
    }
    
    
    public function WithdrawFromDeriv()
    {
        $response = array();
        // Check if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            echo json_encode($response);
            exit();
        }

        // Retrieve data from POST request
        $session_id = $this->input->post('session_id');
        $crNumber = $this->input->post('crNumber');
        $amount = $this->input->post('amount');
        
        // Validate form data
        $this->form_validation->set_rules('session_id', 'session_id', 'required');
        $this->form_validation->set_rules('crNumber', 'crNumber', 'required');
        $this->form_validation->set_rules('amount', 'amount', 'required');
        
        if ($this->form_validation->run() == FALSE) {
            // Handle validation errors
            $response['status'] = 'fail';
            $response['message'] = 'session_id, CR number, and amount are required';
            $response['data'] = null;
        } else {
            // Check session
            $session_condition = array('session_id' => $session_id);
            $checksession = $this->Operations->SearchByCondition('login_session', $session_condition);
            $loggedtime = $checksession[0]['created_on'];
            $currentTime = $this->date;
            $loggedTimestamp = strtotime($loggedtime);
            $currentTimestamp = strtotime($currentTime);
            $timediff = $currentTimestamp - $loggedTimestamp;
            
            if (!empty($checksession) && $checksession[0]['session_id'] == $session_id) {
                // Valid session, retrieve wallet_id
                $wallet_id = $checksession[0]['wallet_id'];
                $sellratecondition = array('exchange_type' => 2,'service_type'=>1);
                $sellrate = $this->Operations->SearchByConditionBuy('exchange', $sellratecondition);
                $boughtsell = $sellrate[0]['bought_at'];
                $conversionRate = $sellrate[0]['kes'];
                $table = 'deriv_withdraw_request';
                $condition1 = array('wallet_id' => $wallet_id);
                $searchUser = $this->Operations->SearchByCondition('customers', $condition1);
                $phone = $searchUser[0]['phone'];
                $userName = $searchUser[0]['name'] ?? $searchUser[0]['fullname'] ?? 'N/A';
                $userEmail = $searchUser[0]['email'] ?? 'N/A';
                $transaction_number = $this->transaction_number;
                
                // Calculate KES equivalent
                $kesAmount = $amount * $conversionRate;
                
                $data = array(
                    'wallet_id' => $wallet_id,
                    'cr_number' => $crNumber,
                    'amount' => $amount,
                    'rate' => $conversionRate,
                    'status' => 0,
                    'withdraw' => 0,
                    'bought_at'=>$boughtsell,
                    'request_date' => $this->date,
                );

                $save = $this->Operations->Create($table, $data);
                $paymethod = 'STEPAKASH';
                $description = 'Withdrawal from deriv';
                $currency = 'USD';
                $dateTime = $this->date;
                
                if ($save === TRUE) {
                    // User confirmation message
                    $message = 'Your withdrawal request of ' . $amount . ' USD (KES ' . number_format($kesAmount, 2) . ') from your Deriv account (' . $crNumber . ') has been received and is being processed. You will receive confirmation once completed. Ref: ' . $transaction_number . '.';
                    $sms = $this->Operations->sendSMS($phone, $message);
                    
        
                    // Detailed admin notification
                    $adminMessage = "DERIV WITHDRAWAL REQUEST\n";
                    $adminMessage .= "User: " . $userName . "\n";
                    $adminMessage .= "Phone: " . $phone . "\n";
                    $adminMessage .= "Email: " . $userEmail . "\n";
                    $adminMessage .= "CR Number: " . $crNumber . "\n";
                    $adminMessage .= "Amount: $" . $amount . " USD\n";
                    $adminMessage .= "KES Equiv: KES " . number_format($kesAmount, 2) . "\n";
                    $adminMessage .= "Rate: " . $conversionRate . "\n";
                    $adminMessage .= "Wallet ID: " . $wallet_id . "\n";
                    $adminMessage .= "Date: " . $this->date . "\n";
                    $adminMessage .= "Action: Process withdrawal ASAP";

                    // Send to multiple admins
                    $adminPhones = ['0703416091', '0710964626', '0726627688']; 

                    foreach ($adminPhones as $adminPhone) {
                        $this->Operations->sendSMS($adminPhone, $adminMessage);
                    }

                    $response['status'] = 'success';
                    $response['message'] = $message;
                    $response['data'] = $data; // Include data key
                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Unable to process your request now, try again';
                    $response['data'] = null;
                }
                
            } else {
                // User not logged in
                $response['status'] = 'fail';
                $response['message'] = 'User not logged in';
                $response['data'] = null;
            }
        }

        echo json_encode($response);
    }
        

    public function DepositToDeriv() 
    {
        $response = array();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            echo json_encode($response);
            exit();
        }

        // Fetch inputs
        $crNumber = $this->input->post('crNumber');
        $crNumber = str_replace(' ', '', $crNumber);
        $amount = $this->input->post('amount');
        $session_id = $this->input->post('session_id');
        $transaction_id = $this->input->post('transaction_id');
        
        // Form validation
        $this->form_validation->set_rules('crNumber', 'crNumber', 'required');
        $this->form_validation->set_rules('amount', 'amount', 'required|numeric|greater_than[0]');
        $this->form_validation->set_rules('session_id', 'session_id', 'required');
        $this->form_validation->set_rules('transaction_id', 'transaction_id', 'required');
        
        if ($this->form_validation->run() == FALSE) {
            $response['status'] = 'fail';
            $response['message'] = 'crNumber, amount, transaction_id and session_id required';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }

        // Validate session - with extended timeout for Deriv transactions
        $session_table = 'login_session';
        $session_condition = array('session_id' => $session_id);
        $checksession = $this->Operations->SearchByCondition($session_table, $session_condition);
        
        if (empty($checksession)) {
            $response['status'] = 'fail';
            $response['message'] = 'Session expired or invalid';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }

        // Extend session validity for Deriv transactions
        $loggedtime = $checksession[0]['created_on'];
        $currentTime = $this->date;
        $loggedTimestamp = strtotime($loggedtime);
        $currentTimestamp = strtotime($currentTime);
        $timediff = $currentTimestamp - $loggedTimestamp;
        
        // Use longer timeout for Deriv transactions (e.g., 30 minutes instead of 10)
        $deriv_timeframe = 1800; // 30 minutes in seconds
        
        if ($timediff > $deriv_timeframe) {
            $response['status'] = 'fail';
            $response['message'] = 'Session expired for Deriv transaction';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }

        $wallet_id = $checksession[0]['wallet_id'];
        $summary = $this->Operations->customer_transection_summary($wallet_id);
        
        // Get our buy rate
        $buyratecondition = array('exchange_type'=>1,'service_type'=>1);
        $buyrate = $this->Operations->SearchByConditionBuy('exchange',$buyratecondition);
        
        // Calculate balances
        $total_credit = (float) str_replace(',', '', $summary[0][0]['total_credit']);
        $total_debit = (float) str_replace(',', '', $summary[1][0]['total_debit']);
        $total_balance_kes = $total_credit - $total_debit;
        $conversionRate = $buyrate[0]['kes'];
        $boughtbuy = $buyrate[0]['bought_at'];
        $total_balance_usd = $total_balance_kes / $conversionRate;
        $amountUSD = round($amount / $conversionRate, 2);

        // Validate amount
        if ($amountUSD < 1.5) {
            $response['status'] = 'error';
            $response['message'] = 'The amount must be greater than $1.50.';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }

        if ($total_balance_usd < $amountUSD) {
            $response['status'] = 'error';
            $response['message'] = 'You dont have sufficient funds in your wallet';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }

        // Get user details for admin notification
        $condition1 = array('wallet_id' => $wallet_id);
        $searchUser = $this->Operations->SearchByCondition('customers', $condition1);
        $phone = $searchUser[0]['phone'];
        $userName = $searchUser[0]['name'] ?? $searchUser[0]['fullname'] ?? 'N/A';
        $userEmail = $searchUser[0]['email'] ?? 'N/A';

        // Prepare transaction data
        $transaction_number = $this->transaction_number;
        $mycharge = ($buyrate[0]['kes'] - $boughtbuy);
        $newcharge = (float)$mycharge * $amountUSD;

        // Create deposit request record
        $table = 'deriv_deposit_request';
        $data = array(
            'transaction_id' => $transaction_id,
            'transaction_number' => $transaction_number,
            'wallet_id' => $wallet_id,
            'cr_number' => $crNumber,
            'amount' => $amountUSD,
            'rate' => $conversionRate,
            'status' => 0, // Initial status - pending
            'deposited' => 0,
            'bought_at' => $boughtbuy,
            'request_date' => $this->date,
        );
        
        $save = $this->Operations->Create($table, $data);

        // Create ledger entries
        $paymethod = 'STEPAKASH';
        $description = 'Deposit to deriv';
        $currency = 'USD';
        $cr_dr = 'dr';
        $totalAmountKES = $amount + ($amountUSD * $newcharge);

        $customer_ledger_data = array(
            'transaction_id' => $transaction_id,
            'transaction_number' => $transaction_number,
            'description' => $description,
            'pay_method' => $paymethod,
            'wallet_id' => $wallet_id,
            'paid_amount' => $amount,
            'cr_dr' => $cr_dr,
            'deriv' => 1,
            'trans_date' => $this->date,
            'currency' => $currency,
            'amount' => $amountUSD,
            'rate' => $conversionRate,
            'chargePercent' => 0,
            'charge' => $newcharge,
            'total_amount' => $totalAmountKES,
            'status' => 1, // Mark as completed in ledger
            'created_at' => $this->date,
        );
        
        $save_customer_ledger = $this->Operations->Create('customer_ledger', $customer_ledger_data);
        $save_system_ledger = $this->Operations->Create('system_ledger', $customer_ledger_data);

        if ($save === TRUE && $save_customer_ledger === TRUE && $save_system_ledger === TRUE) {
            // Attempt auto-deposit
            $transferResult = $this->processAutoDeposit($transaction_id, $amountUSD, $crNumber, $wallet_id, $transaction_number);
            
            if ($transferResult['status'] === 'success') {
                // Auto-deposit successful
                $message = 'Txn ID: ' . $transaction_number . ', a deposit of ' . $amountUSD . ' USD has been successfully processed.';
                $response['status'] = 'success';
                $response['message'] = $message;
                $response['data'] = array(
                    'auto_deposit' => true,
                    'deriv_transaction_id' => $transferResult['transaction_id'],
                    'session_id' => $session_id,
                    'time_frame' => time() 
                );
                
                // Detailed admin notification for successful auto-deposit
                $adminMessage = "DERIV DEPOSIT - AUTO SUCCESS\n";
                $adminMessage .= "User: " . $userName . "\n";
                $adminMessage .= "Phone: " . $phone . "\n";
                $adminMessage .= "Email: " . $userEmail . "\n";
                $adminMessage .= "CR Number: " . $crNumber . "\n";
                $adminMessage .= "Amount: $" . $amountUSD . " USD\n";
                $adminMessage .= "KES Paid: KES " . number_format($amount, 2) . "\n";
                $adminMessage .= "Rate: " . $conversionRate . "\n";
                $adminMessage .= "Charge: KES " . number_format($newcharge, 2) . "\n";
                $adminMessage .= "Total KES: KES " . number_format($totalAmountKES, 2) . "\n";
                $adminMessage .= "Txn ID: " . $transaction_number . "\n";
                $adminMessage .= "Deriv Txn ID: " . $transferResult['transaction_id'] . "\n";
                $adminMessage .= "Wallet ID: " . $wallet_id . "\n";
                $adminMessage .= "Date: " . $this->date . "\n";
                $adminMessage .= "Status: COMPLETED AUTOMATICALLY";
                
            } else {
                // Auto-deposit failed - fall back to manual processing
                $message = 'Txn ID: ' . $transaction_number . ', a deposit of ' . $amountUSD . ' USD is currently being processed.';
                $response['status'] = 'success';
                $response['message'] = $message;
                $response['data'] = array(
                    'auto_deposit' => false,
                    'manual_processing' => true,
                    'session_id' => $session_id,
                    'time_frame' => time() 
                );
                
                // Detailed admin notification for manual processing
                $adminMessage = "DERIV DEPOSIT - MANUAL REQUIRED\n";
                $adminMessage .= "User: " . $userName . "\n";
                $adminMessage .= "Phone: " . $phone . "\n";
                $adminMessage .= "Email: " . $userEmail . "\n";
                $adminMessage .= "CR Number: " . $crNumber . "\n";
                $adminMessage .= "Amount: $" . $amountUSD . " USD\n";
                $adminMessage .= "KES Paid: KES " . number_format($amount, 2) . "\n";
                $adminMessage .= "Rate: " . $conversionRate . "\n";
                $adminMessage .= "Charge: KES " . number_format($newcharge, 2) . "\n";
                $adminMessage .= "Total KES: KES " . number_format($totalAmountKES, 2) . "\n";
                $adminMessage .= "Txn ID: " . $transaction_number . "\n";
                $adminMessage .= "Wallet ID: " . $wallet_id . "\n";
                $adminMessage .= "Date: " . $this->date . "\n";
                $adminMessage .= "Auto-deposit failed: " . $transferResult['message'] . "\n";
                $adminMessage .= "Action: PROCESS DEPOSIT MANUALLY";
            }
            
            // Send user notification
            $sms = $this->Operations->sendSMS($phone, $message);
            
            // Send detailed admin notifications
            $adminPhones = ['0703416091', '0710964626', '0726627688'];
            foreach ($adminPhones as $adminPhone) {
                $this->Operations->sendSMS($adminPhone, $adminMessage);
            }

            // Update session timestamp to prevent timeout
            $this->Operations->UpdateData('login_session', 
                array('session_id' => $session_id), 
                array('created_on' => $this->date)
            );
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'Unable to process your request now try again';
            $response['data'] = null;
        }

        echo json_encode($response);
    }

    private function processAutoDeposit($transaction_id, $amount, $crNumber, $wallet_id, $transaction_number)
    {
        // 1. Check agent balance first
        $balanceCheck = $this->checkAgentBalance();
        
        if (!$balanceCheck['success'] || $balanceCheck['balance'] < $amount) {
            return array(
                'status' => 'error',
                'message' => 'Insufficient agent balance or balance check failed'
            );
        }

        // 2. Perform the transfer
        $transferDescription = "Deposit to account " . $crNumber . " - Txn: " . $transaction_number;
        $transferResult = $this->performDerivTransfer($amount, 'USD', $crNumber, $transferDescription, $transaction_id);
        
        if (!$transferResult['success']) {
            return array(
                'status' => 'error',
                'message' => $transferResult['error']
            );
        }

        // 3. Update database if transfer successful
        $table = 'deriv_deposit_request';
        $condition = array('transaction_id' => $transaction_id);
        $data = array(
            'status' => 1,
            'deposited' => $amount,
            'deriv_transaction_id' => $transferResult['transaction_id'],
            'processed_date' => $this->date
        );
        
        $this->Operations->UpdateData($table, $condition, $data);

        return array(
            'status' => 'success',
            'transaction_id' => $transferResult['transaction_id'],
            'client_to_full_name' => $transferResult['client_to_full_name']
        );
    }

	
	public function initiate()
	{
	    $url = APP_INST.'home.php';
	    $body = array('req_id'=>$this->transaction_id);
	    $apiResponse = $this->Operations->CurlPost($url,$body);
	    //print_r($apiResponse);
	    echo $apiResponse;
	   
	    
	}
	
	public function balance()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Origin: https://api.stepakash.com");
        header("Access-Control-Allow-Methods: POST");
        header("Access-Control-Allow-Headers: Content-Type");
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
        // Encode the PHP array to a JSON string
        $json_string = json_encode($data, JSON_PRETTY_PRINT);
        $req_id = '';
        if ($data['balance']['req_id']) {
            $req_id = $data['balance']['req_id'];
        }
        $file_path = 'derivtransactions/derivdepositing' . $this->date . '.json';
        // Write the JSON string to the file
        file_put_contents($file_path, $json_string);
        $process = $this->process_request($req_id);
        echo json_encode($process);
    }
	
	public function balanceTest()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST");
        header("Access-Control-Allow-Headers: Content-Type");
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
        // Encode the PHP array to a JSON string
        $json_string = json_encode($data, JSON_PRETTY_PRINT);
        $file_path = 'derivtransactions/derivdepositing' . $this->date . '.json';
        // Write the JSON string to the file
        // file_put_contents($file_path, $json_string);
        $req_id = '';
        $check = 0;
        if (($data['balance']['req_id']) || ($data['balance']['transaction_id']) && ($data['balance']['paymentagent_transfer'] == 1)) {
            $req_id = $data['balance']['req_id'];
            $process = $this->process_request($req_id);
            $check = 1;
        }
        $responseData = array('message' => 'Success! ' . $check . '');
        echo json_encode($responseData);
    }

    public function process_request($request_id)
    {
        if (empty($request_id)) {
            $response = [
                'status' => 'fail',
                'message' => 'Request ID is empty.',
                'data' => null
            ];
            echo json_encode($response);
            exit();
        }

        $table = 'deriv_deposit_request';
        $condition = ['transaction_id' => $request_id];

        $search = $this->Operations->SearchByCondition($table, $condition);
        $amount = $search[0]['amount'];
        $cr_number = $search[0]['cr_number'];
        $wallet_id = $search[0]['wallet_id'];
        $transaction_number = $search[0]['transaction_number'];

        $data = ['status' => 1, 'deposited' => $amount];
        $update = $this->Operations->UpdateData($table, $condition, $data);

        $userCondition = ['wallet_id' => $wallet_id];
        $searchuser = $this->Operations->SearchByCondition('customers', $userCondition);

        $mobile = $searchuser[0]['phone'];
        $phone = preg_replace('/^(?:\+?254|0)?/', '254', $mobile);

        if ($update === TRUE) {
            $message = "$transaction_number processed, $amount USD has been successfully deposited to your Deriv account $cr_number.";

            // Send SMS to user
            $this->Operations->sendSMS($phone, $message);

            // Send to admins
            $adminPhones = ['0703416091', '0710964626', '0726627688'];
            foreach ($adminPhones as $adminPhone) {
                $this->Operations->sendSMS($adminPhone, $message);
            }

            $response = [
                'status' => 'success',
                'message' => $message,
                'data' => null
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Unable to process request now, try again',
                'data' => null
            ];
        }

        return $response;
    }

	
	public function process_deporequest()
    {
        $response = array();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response['status'] = 'error';
            $response['message'] = 'Invalid request method. Only POST requests are allowed.';
            $response['data'] = null;
            echo json_encode($response);
            return;
        }
        
        $request_id = $this->input->post('request_id');
        
        if (empty($request_id)) {
            $response['status'] = 'error';
            $response['message'] = 'Request ID is empty.';
            $response['data'] = null;
            echo json_encode($response);
            return;
        }
        
        $table = 'deriv_deposit_request';
        $condition = array('transaction_id' => $request_id);
        $search = $this->Operations->SearchByCondition($table, $condition);
        
        if (empty($search)) {
            $response['status'] = 'error';
            $response['message'] = 'Request not found.';
            $response['data'] = null;
            echo json_encode($response);
            return;
        }
        
        // Check if already processed
        if ($search[0]['status'] == 1) {
            $response['status'] = 'error';
            $response['message'] = 'Request already processed.';
            $response['data'] = null;
            echo json_encode($response);
            return;
        }
        
        $amount = $search[0]['amount'];
        $cr_number = $search[0]['cr_number'];
        $wallet_id = $search[0]['wallet_id'];
        $transaction_number = $search[0]['transaction_number'];
        
        // Check agent balance first
        $balanceCheck = $this->checkAgentBalance();
        
        if (!$balanceCheck['success']) {
            $response['status'] = 'error';
            $response['message'] = 'Failed to check agent balance: ' . $balanceCheck['error'];
            $response['data'] = null;
            echo json_encode($response);
            return;
        }
        
        // Check if agent has sufficient balance
        if ($balanceCheck['balance'] < $amount) {
            $response['status'] = 'error';
            $response['message'] = 'Insufficient agent balance. Available: $' . number_format($balanceCheck['balance'], 2) . ', Required: $' . number_format($amount, 2);
            $response['data'] = null;
            
            // Notify admin about insufficient balance
            $adminMessage = "ALERT: Insufficient agent balance for transfer. Available: $" . number_format($balanceCheck['balance'], 2) . ", Required: $" . number_format($amount, 2) . " for transaction " . $transaction_number;
            $adminPhones = ['0703416091', '0710964626', '0726627688'];
            
            foreach ($adminPhones as $phone) {
                $this->Operations->sendSMS($phone, $adminMessage);
            }
            
            echo json_encode($response);
            return;
        }
        
        // Perform the actual transfer to Deriv
        $transferDescription = "Deposit to account " . $cr_number . " - Txn: " . $transaction_number;
        $transferResult = $this->performDerivTransfer($amount, 'USD', $cr_number, $transferDescription, $request_id);
        
        if (!$transferResult['success']) {
            $response['status'] = 'error';
            $response['message'] = 'Transfer to Deriv failed: ' . $transferResult['error'];
            $response['data'] = null;
            echo json_encode($response);
            return;
        }
        
        // Update request status to processed
        $data = array(
            'status' => 1,
            'deposited' => $amount,
            'deriv_transaction_id' => $transferResult['transaction_id'],
            'processed_date' => $this->date
        );
        
        $update = $this->Operations->UpdateData($table, $condition, $data);
        
        if ($update === TRUE) {
            // Get user details
            $condition1 = array('wallet_id' => $wallet_id);
            $searchuser = $this->Operations->SearchByCondition('customers', $condition1);
            $mobile = $searchuser[0]['phone'];
            $phone = preg_replace('/^(?:\+?254|0)?/', '254', $mobile);
            
            // Send success message to user
            $message = $transaction_number . ' processed successfully. $' . number_format($amount, 2) . ' USD has been deposited to your Deriv account ' . $cr_number . '. Transaction ID: ' . $transferResult['transaction_id'];
            
            // Send SMS to user
            $sms = $this->Operations->sendSMS($phone, $message);
            
            // Send confirmation to admin
            $adminMessage = "SUCCESS: Deposit processed - $" . number_format($amount, 2) . " USD transferred to " . $cr_number . " (Txn: " . $transaction_number . ")";
            $adminPhones = ['0703416091'];
            
            foreach ($adminPhones as $adminPhone) {
                $this->Operations->sendSMS($adminPhone, $adminMessage);
            }
            
            $response['status'] = 'success';
            $response['message'] = $message;
            $response['data'] = array(
                'transaction_id' => $transferResult['transaction_id'],
                'client_full_name' => $transferResult['client_to_full_name'],
                'amount_transferred' => $amount,
                'currency' => 'USD',
                'recipient_account' => $cr_number
            );
            
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Database update failed after successful transfer. Please contact support.';
            $response['data'] = null;
        }
        
        echo json_encode($response);
    }


    public function getAgentBalance()
    {
        $response = array();
        
        $balanceCheck = $this->checkAgentBalance();
        
        if ($balanceCheck['success']) {
            $response['status'] = 'success';
            $response['message'] = 'Agent balance retrieved successfully';
            $response['data'] = array(
                'balance' => $balanceCheck['balance'],
                'currency' => $balanceCheck['currency'],
                'formatted_balance' => number_format($balanceCheck['balance'], 2) . ' ' . $balanceCheck['currency']
            );
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Failed to retrieve agent balance: ' . $balanceCheck['error'];
            $response['data'] = null;
        }
        
        echo json_encode($response);
    }

    public function checkAgentBalance()
    {
        $appId = 76420;
        $endpoint = 'ws.derivws.com';
        $url = "wss://{$endpoint}/websockets/v3?app_id={$appId}";
        $token = 'DidPRclTKE0WYtT';
        
        try {
            // Use the WebSocket Client that's already imported
            $client = new \WebSocket\Client($url, [
                'timeout' => 10,
                'headers' => []
            ]);
            
            // Send authorization request
            $authRequest = json_encode([
                "authorize" => $token,
                "req_id" => 1
            ]);
            
            $client->send($authRequest);
            $authResponse = $client->receive();
            $authData = json_decode($authResponse, true);
            
            if (isset($authData['error'])) {
                throw new Exception("Authorization failed: " . $authData['error']['message']);
            }
            
            // Get balance
            $balanceRequest = json_encode([
                "balance" => 1,
                "req_id" => 2
            ]);
            
            $client->send($balanceRequest);
            $balanceResponse = $client->receive();
            $balanceData = json_decode($balanceResponse, true);
            
            $client->close();
            
            if (isset($balanceData['error'])) {
                throw new Exception("Balance check failed: " . $balanceData['error']['message']);
            }
            
            return [
                'success' => true,
                'balance' => $balanceData['balance']['balance'],
                'currency' => $balanceData['balance']['currency']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function performDerivTransfer($amount, $currency, $transferTo, $description, $requestId)
    {
        $appId = 76420;
        $endpoint = 'ws.derivws.com';
        $url = "wss://{$endpoint}/websockets/v3?app_id={$appId}";
        $token = 'DidPRclTKE0WYtT';
        
        try {
            $client = new \WebSocket\Client($url, [
                'timeout' => 15,
                'headers' => []
            ]);
            
            // Send authorization request
            $authRequest = json_encode([
                "authorize" => $token,
                "req_id" => 1
            ]);
            
            $client->send($authRequest);
            $authResponse = $client->receive();
            $authData = json_decode($authResponse, true);
            
            if (isset($authData['error'])) {
                throw new Exception("Authorization failed: " . $authData['error']['message']);
            }
            
            // Perform payment agent transfer
            $transferRequest = json_encode([
                "paymentagent_transfer" => 1,
                "amount" => $amount,
                "currency" => $currency,
                "transfer_to" => $transferTo,
                "description" => $description,
                "req_id" => $requestId
            ]);
            
            $client->send($transferRequest);
            $transferResponse = $client->receive();
            $transferData = json_decode($transferResponse, true);
            
            $client->close();
            
            if (isset($transferData['error'])) {
                throw new Exception("Transfer failed: " . $transferData['error']['message']);
            }
            
            return [
                'success' => true,
                'transaction_id' => $transferData['paymentagent_transfer']['transaction_id'],
                'client_to_full_name' => $transferData['paymentagent_transfer']['client_to_full_name'],
                'client_to_loginid' => $transferData['paymentagent_transfer']['client_to_loginid'],
                'paymentagent_transfer' => $transferData['paymentagent_transfer']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function depositsrequest()
    {
        $response = array();
        $search = $this->Operations->SearchJoin();
        if (!empty($search)) {
            $response['status'] = 'success';
            $response['message'] = 'Deposits data retrieved successfully';
            $response['data'] = $search;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'No deposit data found';
            $response['data'] = null;
        }
        // Send JSON response
        echo json_encode($response);
    }
    
    public function withdrawalrequest()
    {
        $response = array();

    
        $requests = $this->Operations->SearchWithdrawalRequest();
  
        if (!empty($requests)) {
            $response['status'] = 'success';
            $response['message'] = 'Withdrawal requests retrieved successfully';
            $response['data'] = $requests;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'No withdrawal requests found';
            $response['data'] = null;
        }
    
        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    
    
    public function process_withdrawalrequest()
    {
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response['status'] = 'fail';
            $response['message'] = 'Invalid request method. Only POST requests are allowed.';
            $response['data'] = null;
            //exit(); 
        }
        else
        {
            $request_id = $this->input->post('request_id');
            
            $checkcondition = array('status' => 1,'id' => $request_id);
            $confirmcondition = $this->Operations->SearchByCondition('deriv_withdraw_request', $checkcondition);
            
            $checkcondition2 = array('status' => 0,'id' => $request_id);
            $confirmcondition2 = $this->Operations->SearchByCondition('deriv_withdraw_request', $checkcondition2);
                
            $response = array();
            if (!$request_id || empty($request_id)) {
                $response['status'] = 'fail';
                $response['message'] = 'Request ID required';
                $response['data'] = null;
            }
            else if($confirmcondition)
            {
                $response['status'] = 'success';
                $response['message'] = 'Similar request already approved';
                $response['data'] = null;
            }
            else if($confirmcondition2)
            {
                //check if already processed
               
         
                    //get our sell rate
                    $sellratecondition = array('exchange_type' => 2,'service_type'=>1);
                    $sellrate = $this->Operations->SearchByConditionBuy('exchange', $sellratecondition);
                    
                    $boughtsell = $sellrate[0]['bought_at'];
                    
                
                    
                    $table = 'deriv_withdraw_request';
                    $condition = array('id' => $request_id);
                    $search = $this->Operations->SearchByCondition($table, $condition);
                
                    $amount = $search[0]['amount'];
                    $cr_number = $search[0]['cr_number'];
                    $wallet_id = $search[0]['wallet_id'];

                
                    $data = array('status' => 1, 'withdraw' => $amount);
                    $update = $this->Operations->UpdateData($table, $condition, $data);
                
                    $condition1 = array('wallet_id' => $wallet_id);
                    $searchuser = $this->Operations->SearchByCondition('customers', $condition1);
                    $mobile = $searchuser[0]['phone'];
                    $phone = preg_replace('/^(?:\+?254|0)?/', '254', $mobile);
                
                    $paymethod = 'STEPAKASH';
                    $description = 'Withdraw from deriv';
                    $currency = 'USD';
                    $dateTime = $this->date;
                    $cr_dr = 'cr';
                    $conversionRate = $sellrate[0]['kes'];
                    $chargePercent = 0;
                    $chargeAmount = (float)($amount * $chargePercent);
                    $amountKESAfterCharge = ((float) $amount * (float) $conversionRate);
                    $finalCharge = ((float) $chargeAmount * (float) $conversionRate);
                    $totalAmt = ((float) $finalCharge + (float) $amountKESAfterCharge);
                    
                    $mycharge = ($boughtsell - $sellrate[0]['kes']);
                    $newcharge = (float)$mycharge * $amount;

           
                    $transaction_number =  $this->transaction_number;
                    $transaction_id = $this->Operations->OTP(9);
                    
                
                    $customer_ledger_data = array(
                        'transaction_id' => $transaction_id,
                        'transaction_number'=>$transaction_number,
                        'receipt_no' => $this->Operations->Generator(15),
                        'description' => 'ITP',
                        'pay_method' => $paymethod,
                        'wallet_id' => $wallet_id,
                        'paid_amount' => $amountKESAfterCharge,
                        'cr_dr' => $cr_dr,
                        'currency' => $currency,
                        'amount' => $amount,
                        'deriv' => 1,
                        'rate' => $conversionRate,
                        'chargePercent' => $chargePercent,
                        'charge' => $newcharge,
                        'total_amount' => $totalAmt,
                        'status' => 1,
                        'created_at' => $this->date,
                    );
                
                    $save_customer_ledger = $this->Operations->Create('customer_ledger', $customer_ledger_data);
                
                    $system_ledger_data = array(
                        'transaction_id' => $transaction_id,
                        'transaction_number' => $transaction_number,
                        'receipt_no' => $this->Operations->Generator(15),
                        'description' => 'ITP',
                        'pay_method' => $paymethod,
                        'wallet_id' => $wallet_id,
                        'paid_amount' => $amountKESAfterCharge,
                        'cr_dr' => $cr_dr,
                        'currency' => $currency,
                        'amount' => $amount,
                        'deriv' => 1,
                        'rate' => $conversionRate,
                        'chargePercent' => $chargePercent,
                        'charge' => $newcharge,
                        'total_amount' => $totalAmt,
                        'status' => 1,
                        'created_at' => $this->date,
                    );
                
                    $save_system_ledger = $this->Operations->Create('system_ledger', $system_ledger_data);
                
                    if ($update === TRUE && $save_system_ledger === TRUE && $save_customer_ledger === TRUE) {
                        $message = ''.$transaction_number.', ' . $amount .'USD has been successfully withdraw from your deriv account ' . $cr_number . '';
                
                        //SEND USER APP NOTIFICATION 
                        $sms = $this->Operations->sendSMS($phone, $message);
     
                
                        $response['status'] = 'success';
                        $response['message'] = $message;
                        $response['data'] = null;
                        
                    } else {
                        $response['status'] = 'error';
                        $response['message'] = 'Unable to process request now, try again';
                        $response['data'] = null;
                        
                    }
                 
                
            }
            else
            {
                $response['status'] = 'error';
                $response['message'] = 'Something went wrong, try again';
                $response['data'] = null;
            }
        }
        
    
        
         echo json_encode($response);
    }
	
	
	
	
	
	//ADMIN
	public function adminhome()
    {
        $userschart = $this->Operations->getMonthlyUserRegistrations();
        $earningChart = $this->Operations->getMonthlyEarnings();
        $appusers = $this->Operations->Count('customers');
        $dailyChart =  $this->Operations->getDailyEarnings();
        $thismonthEarnings = $this->Operations->thisMonthlyEarnings();
        $dailyDeposits =  $this->Operations->getDailyDeposits();
        $dailyWithdrawals =  $this->Operations->getDailyWithdrawals();
        $totaldeposits = $this->Operations->getOverallDeposit();
        $totaldeposits = number_format($totaldeposits, 2, '.', ',');
        $totalwithdrawals = $this->Operations->getOverallWithdrawal();
        $totalwithdrawals = number_format($totalwithdrawals, 2, '.', ',');
        $active_users = $this->Operations->ActiveUsers();
        $total_gifting = $this->Operations->total_gifting();


        $stepakash_total_transfers = $this->Operations->StepakashTotalTransfers();
        
    
        
        $deposits = $this->Operations->getSumOfAmount();
        $deposits = (float)sprintf("%.2f", $deposits);
        
        $earnings = $this->Operations->GetEarnings();
        $earnings = (float)sprintf("%.2f", $earnings);
        
        $condition = array('status' => 0);
        $deriv_deposit_request = $this->Operations->CountWithCondition('deriv_deposit_request', $condition);
        $deriv_withdraw_request = $this->Operations->CountWithCondition('deriv_withdraw_request', $condition);
        $requests = ((int) $deriv_deposit_request + (int) $deriv_withdraw_request);

        $crypto_table = 'crypto_requests';
        $binance_condition1 = array('status' => 0,'crypto_type'=>11,'cr_dr'=>'dr');
        $binance_deposit_request = $this->Operations->CountWithCondition($crypto_table, $binance_condition1);
        $binance_condition2 = array('status' => 0,'crypto_type'=>11,'cr_dr'=>'cr');
        $binance_withdraw_request = $this->Operations->CountWithCondition($crypto_table, $binance_condition2);
 

        $bitcoin_condition1 = array('status' => 0,'crypto_type'=>3,'cr_dr'=>'dr');
        $bitcoin_deposit_request = $this->Operations->CountWithCondition($crypto_table, $bitcoin_condition1);
        $bitcoin_condition2 = array('status' => 0,'crypto_type'=>3,'cr_dr'=>'cr');
        $bitcoin_withdraw_request = $this->Operations->CountWithCondition($crypto_table, $bitcoin_condition2);


        $ethereum_condition1 = array('status' => 0,'crypto_type'=>4,'cr_dr'=>'dr');
        $ethereum_deposit_request = $this->Operations->CountWithCondition($crypto_table, $ethereum_condition1);
        $ethereum_condition2 = array('status' => 0,'crypto_type'=>4,'cr_dr'=>'cr');
        $ethereum_withdraw_request = $this->Operations->CountWithCondition($crypto_table, $ethereum_condition2);


        $tether_condition1 = array('status' => 0,'crypto_type'=>5,'cr_dr'=>'dr');
        $tether_deposit_request = $this->Operations->CountWithCondition($crypto_table, $tether_condition1);
        $tether_condition2 = array('status' => 0,'crypto_type'=>5,'cr_dr'=>'cr');
        $tether_withdraw_request = $this->Operations->CountWithCondition($crypto_table, $tether_condition2);

        $tether2_condition1 = array('status' => 0,'crypto_type'=>6,'cr_dr'=>'dr');
        $tether2_deposit_request = $this->Operations->CountWithCondition($crypto_table, $tether2_condition1);
        $tether2_condition2 = array('status' => 0,'crypto_type'=>6,'cr_dr'=>'cr');
        $tether2_withdraw_request = $this->Operations->CountWithCondition($crypto_table, $tether2_condition2);

        $total_withdraw_req = (int)($tether_withdraw_request) + (int)$tether2_withdraw_request;

        $total_depo_req = (int)($tether_deposit_request) + (int)$tether2_deposit_request;


        $skrill_condition = array('status' => 0,'crypto_type'=>7,'cr_dr'=>'dr');
        $skrill_deposit_request = $this->Operations->CountWithCondition($crypto_table, $skrill_condition);
        $skrill_condition2 = array('status' => 0,'crypto_type'=>7,'cr_dr'=>'cr');
        $skrill_withdraw_request = $this->Operations->CountWithCondition($crypto_table, $skrill_condition2);

        $neteller_condition = array('status' => 0,'crypto_type'=>8,'cr_dr'=>'dr');
        $neteller_deposit_request = $this->Operations->CountWithCondition($crypto_table, $neteller_condition);
        $neteller_condition2 = array('status' => 0,'crypto_type'=>8,'cr_dr'=>'cr');
        $neteller_withdraw_request = $this->Operations->CountWithCondition($crypto_table, $neteller_condition2);
    
        $data = array(
            'total_gifting' =>$total_gifting,
            'appusers' => $appusers,
            'deposits' => $deposits,
            'active_users'=>$active_users,
            'deriv_deposit_requests' => $deriv_deposit_request,
            'deriv_withdraw_requests' => $deriv_withdraw_request,
            'stepakash_transfer'=>$stepakash_total_transfers,
            'binance_deposit_requests' => $binance_deposit_request,
            'binance_withdraw_requests' => $binance_withdraw_request,
            'bitcoin_deposit_requests' => $bitcoin_deposit_request,
            'bitcoin_withdraw_requests' => $bitcoin_withdraw_request,
            'ethereum_deposit_requests' => $ethereum_deposit_request,
            'ethereum_withdraw_requests' => $ethereum_withdraw_request,
            'tether_deposit_requests' => $total_depo_req,
            'tether_withdraw_requests' => $total_withdraw_req,
            'skrill_deposit_requests' => $skrill_deposit_request,
            'skrill_withdraw_requests' => $skrill_withdraw_request,
            'neteller_deposit_requests' => $neteller_deposit_request,
            'neteller_withdraw_requests' => $neteller_withdraw_request,
            'earnings' => $earnings,
            'thisMonthEarnings' => $thismonthEarnings[0]->earnings,
            'chartData' => json_encode($userschart),
            'earningData' => json_encode($earningChart),
            'dailyChart' => json_encode($dailyChart),
            'dailyDeposits'=> json_encode($dailyDeposits),
            'dailyWithdrawals'=> json_encode($dailyWithdrawals),
            'totalwithdrawals'=>$totalwithdrawals,
            'totaldeposits'=>$totaldeposits,
            
        );
        
    
        $response['status'] = 'success';
        $response['message'] = 'Admin home data';
        $response['data'] = $data;
    
        // Send JSON response
        echo json_encode($response);
    }


    public function outbox()
	{
	    $table = 'outbox';
	    $outbox = $this->Operations->SearchSms($table);

	    $response['status'] = 'success';
        $response['message'] = 'sms outbox';
        $response['data'] = $outbox;
    
        echo json_encode($response);
	}



    public function DepositFromMpesa()
    {
        $response = array();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }
        $mobile = $this->input->post('phone');
        $mobile = str_replace(' ', '', $mobile);
        $phone = preg_replace('/^(?:\+?254|0)?/', '254', $mobile);
        $amount = $this->input->post('amount');
        $session_id = $this->input->post('session_id');
        $this->form_validation->set_rules('phone', 'phone', 'required');
        $this->form_validation->set_rules('amount', 'amount', 'required');
        $this->form_validation->set_rules('session_id', 'session_id', 'required');
        if ($this->form_validation->run() == FALSE) {
            $response['status'] = 'fail';
            $response['message'] = 'session_id, phone or amount required';
            $response['data'] = null;
        } else {
            $session_condition = array('session_id' => $session_id);
            $check_session = $this->Operations->SearchByCondition('login_session', $session_condition);
            $loggedtime = $checksession[0]['created_on'];
            $wallet_id = $check_session[0]['wallet_id'];
            $wallet_condition = array('wallet_id' => $wallet_id);
            $searchUser = $this->Operations->SearchByCondition('customers', $wallet_condition);
            $phone = $searchUser[0]['phone'];
            $phone = preg_replace('/^(?:\+?254|0)?/', '254', $phone);
            $currentTime = $this->date;
            $loggedTimestamp = strtotime($loggedtime);
            $currentTimestamp = strtotime($currentTime);
            $timediff = $currentTimestamp - $loggedTimestamp;
            if (empty($check_session) || $check_session[0]['session_id'] !== $session_id) {
                $response['status'] = 'fail';
                $response['message'] = 'User not logged in';
                $response['data'] = null;
            } else {
                $mpesa_consumer_key = "wC9zwOZCu2XQYAqK7xnH4eYQHfYxOZxuVZARqoONzjVUAljA";
                $mpesa_consumer_secret = "rGDcF6VKvrGE6e52gAAve9UWXBnzs1iDwUPaV2kVICLzMHiDtU5W87xJAzNg6KeA";
                $mpesapass = "ebec65af907979790f37447ef40883542f15b1f65eb42e41e8e53c0ccfae605c";
                $shortcode = "4168325";
                $systemUrl = 'https://api.stepakash.com/index.php/stkresults';
                $identifierType = "CustomerPayBillOnline";
                $invoice_number = "STEPAKASH-" . $wallet_id;
                $feedback = $this->check_transaction_api(
                    $mpesa_consumer_key,
                    $mpesa_consumer_secret,
                    $mpesapass,
                    $amount,
                    $phone,
                    $shortcode,
                    $systemUrl,
                    $identifierType,
                    $invoice_number
                );
                $response['status'] = 'success';
                $response['message'] = $feedback;
                $response['data'] = null;
            }
        }
        echo json_encode($response);
    }



    function check_transaction_api(
        $mpesa_consumer_key,
        $mpesa_consumer_secret,
        $mpesapass,
        $amount,
        $phone_no,
        $shortcode,
        $systemUrl,
        $identifierType,
        $invoice_number
    ) {
        date_default_timezone_set("Africa/Nairobi");
        $timestamp = date("Ymdhis");
        $password = base64_encode($shortcode . $mpesapass . $timestamp);
        $curl = curl_init();
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        curl_setopt($curl, CURLOPT_URL, $url);
        $credentials = base64_encode($mpesa_consumer_key . ':' . $mpesa_consumer_secret);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $curl_response = curl_exec($curl);
        $cred_password_raw = json_decode($curl_response, true);
        $cred_password = $cred_password_raw['access_token'];
        $url = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $cred_password));
        $curl_post_data = array(
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $identifierType,
            'Amount' => $amount,
            'PartyA' => $phone_no,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone_no,
            'CallBackURL' => $systemUrl,
            'AccountReference' => $invoice_number,
            'TransactionDesc' => 'not applicable',
        );
        $data_string = json_encode($curl_post_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        $curl_response = curl_exec($curl);
        $resultStatus_raw = json_decode($curl_response, true);;
        $resultStatus = $resultStatus_raw['ResponseCode'];
        $MerchantRequestID = $resultStatus_raw['MerchantRequestID'];
        $CheckoutRequestID = $resultStatus_raw['CheckoutRequestID'];
        if ($resultStatus === "0") {
            $moby = preg_replace('/^(?:\+?254|0)?/', '+254', $phone_no);
            $saving = $this->save_order($moby, $amount, $MerchantRequestID, $CheckoutRequestID);
            return $saving;
        } else {
            $error_on = "error#";
            $error = $error_on . $resultStatus_raw['errorMessage'];
            return $error;
        }
    }
    
    

    public function save_order($phone_no, $amount, $MerchantRequestID, $CheckoutRequestID)
    {
        $condition4 = array('phone' => $phone_no);
        $searchUser = $this->Operations->SearchByCondition('customers', $condition4);
        $wallet_id = $searchUser[0]['wallet_id'];
        $data = array(
            'wallet_id' => $wallet_id,
            'phone' => $phone_no,
            'amount' => $amount,
            'MerchantRequestID' => $MerchantRequestID,
            'CheckoutRequestID' => $CheckoutRequestID,
            'paid' => 0,
        );
        $save = $this->Operations->Create('mpesa_deposit', $data);
        $message = '';
        if ($save === TRUE) {
            $message = 'Please complete the deposit of KES ' . $amount . ' on your phone to update your balance.';
        } else {
            $message = 'Something went wrong,contact support';
        }
        return $message;
    }

	
	
    public function WithdrawToMpesa()
    {
        $response = array();
        $summary = 0;
        $total_credit = 0;
        $total_debit = 0;
        $total_balance_kes = 0;
        $amount = 0;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null; // Assign 'data' key as null
            echo json_encode($response);
            exit();
        }
        $session_id = $this->input->post('session_id');
        $table = 'customers';
        $mobile = $this->input->post('phone');
        $mobile = str_replace(' ', '', $mobile);
        $phone = preg_replace('/^(?:\+?254|0)?/', '254', $mobile);
        $amount = $this->input->post('amount');
        $session_table = 'login_session';
        $session_condition = array('session_id' => $session_id);
        $checksession = $this->Operations->SearchByCondition($session_table, $session_condition);
        $wallet_id = $checksession[0]['wallet_id'];
        $crNumber = $checksession[0]['crnumber'];
        $crNumber = '';
        $summary = $this->Operations->customer_transection_summary($wallet_id);
        $total_credit = (float) str_replace(',', '', $summary[0][0]['total_credit']);
        $total_debit = (float) str_replace(',', '', $summary[1][0]['total_debit']);
        $total_balance_kes = $total_credit - $total_debit;
        $loggedtime = $checksession[0]['created_on'];
        $currentTime = $this->date;
        $loggedTimestamp = strtotime($loggedtime);
        $currentTimestamp = strtotime($currentTime);
        $timediff = $currentTimestamp - $loggedTimestamp;
        if (!empty($checksession) && $checksession[0]['session_id'] == $session_id) {
            $this->form_validation->set_rules('phone', 'phone', 'required');
            $this->form_validation->set_rules('amount', 'amount', 'required');
            $this->form_validation->set_rules('session_id', 'session_id', 'required');
            if ($this->form_validation->run() == FALSE) {
                // Handle validation errors
                $response['status'] = 'fail';
                $response['message'] = 'session_id,phone or amount required';
                $response['data'] = null;
            } else {
                if (empty($phone)) {
                    $response['status'] = 'fail';
                    $response['message'] = 'Phone required';
                    $response['data'] = null;
                } elseif (empty($amount)) {
                    $response['status'] = 'fail';
                    $response['message'] = 'Amount required';
                    $response['data'] = null;
                } elseif ($amount < 2) {
                    $response['status'] = 'error';
                    $response['message'] = 'The amount must be greater than KES 10.';
                    $response['data'] = null;
                } elseif (($total_balance_kes < $amount) && ($amount > $total_balance_kes)) {
                    $response['status'] = 'error';
                    $response['message'] = 'Insufficient funds in your wallet';
                    $response['data'] = null;
                } elseif (($total_balance_kes >= $amount) && ($amount <= $total_balance_kes)) {
                    $duplicate_check = $this->checkDuplicateWithdrawal($wallet_id, $phone, $amount);
                    if ($duplicate_check['is_duplicate']) {
                        $searchUser = $this->Operations->SearchByCondition('customers', array('wallet_id' => $wallet_id));
                        $customer_name = isset($searchUser[0]['name']) ? $searchUser[0]['name'] : 'Customer';
                        $duplicate_message = "Hi $customer_name, duplicate withdrawal attempt of KES " . number_format($amount, 2) . " blocked for security. Contact support if this wasn't you. Time: " . date('d/m/Y H:i');
                        $this->Operations->sendSMS($phone, $duplicate_message);
                        $response['status'] = 'error';
                        $response['message'] = 'Duplicate transaction detected. Please wait ' . $duplicate_check['wait_time'] . ' seconds before making another withdrawal of the same amount.';
                        $response['data'] = null;
                        file_put_contents(
                            "mpesab2c/duplicate_attempt_" . date('Y-m-d_H-i-s') . ".txt",
                            "Duplicate withdrawal blocked:\n" .
                            "Wallet ID: $wallet_id\n" .
                            "Phone: $phone\n" .
                            "Amount: KES $amount\n" .
                            "Last transaction: " . $duplicate_check['last_transaction_time'] . "\n" .
                            "Wait time: " . $duplicate_check['wait_time'] . " seconds"
                        );
                    } else {
                        $table = 'mpesa_withdrawals';
                        $date = $this->date;
                        $condition1 = array('wallet_id' => $wallet_id);
                        $searchUser = $this->Operations->SearchByCondition('customers', $condition1);
                        $phone = $searchUser[0]['phone'];
                        $phone = str_replace(' ', '', $phone);
                        $phone = preg_replace('/^(?:\+?254|0)?/', '254', $phone);
                        $transaction_id = $this->Operations->Generator(9);
                        $initiate = $this->b2c($transaction_id, $wallet_id, $crNumber, $phone, $amount);
                        $response = $initiate;
                    }
                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Unable to withdraw amount' . $amount . ' now,try later';
                    $response['data'] = null; 
                }
            }
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'User not logged in';
            $response['data'] = null;
        }

        echo json_encode($response);
    }


    /**
     * Check for duplicate withdrawal transactions within a specified time frame
    */
    private function checkDuplicateWithdrawal($wallet_id, $phone, $amount)
    {
        $time_threshold = 30; // 30 seconds
        $current_time = time();
        $threshold_time = date('Y-m-d H:i:s', $current_time - $time_threshold);
        $recent_transactions = $this->db->where('wallet_id', $wallet_id)
            ->where('phone', $phone)
            ->where('amount', $amount)
            ->where('request_date >=', $threshold_time)
            ->order_by('request_date', 'DESC')
            ->get('mpesa_withdrawals')
            ->result_array();
        if (!empty($recent_transactions)) {
            $last_transaction_time = strtotime($recent_transactions[0]['request_date']);
            $time_diff = $current_time - $last_transaction_time;
            $wait_time = $time_threshold - $time_diff;
            return array(
                'is_duplicate' => true,
                'wait_time' => $wait_time,
                'last_transaction_time' => $recent_transactions[0]['request_date']
            );
        }
        return array('is_duplicate' => false);
    }
    
	 public function b2c($transaction_id, $wallet_id, $crNumber, $phone_no, $Amount)
    {
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $mpesa_consumer_key = 'wC9zwOZCu2XQYAqK7xnH4eYQHfYxOZxuVZARqoONzjVUAljA';
        $mpesa_consumer_secret = 'rGDcF6VKvrGE6e52gAAve9UWXBnzs1iDwUPaV2kVICLzMHiDtU5W87xJAzNg6KeA';
        $InitiatorName = 'STEVEWEB';
        $password = '..ken6847musyimI.';
        $ResultURL = 'https://api.stepakash.com/index.php/b2c_result';
        $QueueTimeOutURL = 'https://api.stepakash.com/index.php/b2c_result';
        $PartyA = '4168325'; 
        $PartyB = $phone_no;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $credentials = base64_encode($mpesa_consumer_key . ':' . $mpesa_consumer_secret);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $curl_response = curl_exec($curl);
        $cred_password_raw = json_decode($curl_response, true);
        $cred_password = $cred_password_raw['access_token'];
        $publicKey_path = 'cert.cer';
        $fp = fopen($publicKey_path, "r");
        $publicKey = fread($fp, 8192);
        fclose($fp);
        $plaintext = $password;
        openssl_public_encrypt($plaintext, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);
        $security_credential = base64_encode($encrypted);
        $url = 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $cred_password)); //setting custom header
        $curl_post_data = array(
            'InitiatorName' => $InitiatorName,
            'SecurityCredential' => $security_credential,
            'CommandID' => 'SalaryPayment',
            'Amount' => $Amount,
            'PartyA' => $PartyA,
            'PartyB' => $PartyB,
            'ResultURL' => $ResultURL,
            'QueueTimeOutURL' => $QueueTimeOutURL,
            'Remarks' => 'Stepakash Wallet Withdrawal',
            'Occasion' => 'Wallet Withdrawal'
        );
        $data_string = json_encode($curl_post_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        $curl_response = curl_exec($curl);
        $responseArray = json_decode($curl_response, true);
        $conversationID = $responseArray['ConversationID'];
        $originatorConversationID = $responseArray['OriginatorConversationID'];
        $responseCode = $responseArray['ResponseCode'];
        $results = $this->WithdrawMoneyRequest($transaction_id, $wallet_id, $crNumber, $Amount, $phone_no, $conversationID, $originatorConversationID, $responseCode);
        return $results;
    }

    public function WithdrawMoneyRequest($transaction_id, $wallet_id, $crNumber, $amount, $phone, $conversationID, $originatorConversationID, $ResponseCode)
    {
        $transaction_number = $this->transaction_number;
        $table = 'mpesa_withdrawals';
        $data = array(
            'transaction_id' => $transaction_id,
            'transaction_number' => $transaction_number,
            'wallet_id' => $wallet_id,
            'cr_number' => $crNumber,
            'amount' => $amount,
            'phone' => $phone,
            'conversationID' => $conversationID,
            'OriginatorConversationID' => $originatorConversationID,
            'ResponseCode' => $ResponseCode,
            'withdraw' => 0,
            'paid' => 0,
            'request_date' => $this->date,
        );
        $save = $this->Operations->Create($table, $data);
        $cr_dr = 'cr';
        $dr_cr = 'dr';
        $paymethod = 'STEPAKASH';
        $description = 'Withdrawal to M-Pesa';
        $receipt_no = $this->Operations->Generator(15);
        $trans_id = $this->Operations->Generator(8);
        $trans_date = $this->date;
        $currency = 'KES';
        $conversionRate = 0;
        $amountToCredit = $amount;
        $TotalamountToCredit = $amount;
        $senderWalletID = $wallet_id;
        $amountToDebit = $amount;
        $chargePercent = 0;
        $chargeAmt = 0;
        $TotalamountToDebit = $amountToDebit;
        $transaction_number = $this->transaction_number;
        $deduct = $this->DebitToAccount($transaction_id, $transaction_number, $receipt_no, $description, $paymethod, $senderWalletID, $trans_date, $currency, $amountToDebit, $conversionRate, $chargePercent, $chargeAmt, $TotalamountToDebit);
        $condition1 = array('wallet_id' => $wallet_id);
        $searchuser1 = $this->Operations->SearchByCondition('customers', $condition1);
        $customer_name = isset($searchuser1[0]['name']) ? $searchuser1[0]['name'] : 'Customer';
        if ($save === TRUE && $deduct === TRUE) {
            $request_message = "Hi $customer_name, your withdrawal request of KES " . number_format($amount, 2) . " is being processed. You will receive confirmation once completed. Ref: $transaction_id";
            $this->Operations->sendSMS($phone, $request_message);
            $response['status'] = 'success';
            $response['message'] = 'Withdrawal request of KES ' . $amount . ' initiated successfully. You will receive SMS confirmation once processed.';
            $response['data'] = $data;
            file_put_contents(
                "mpesab2c/request_" . date('Y-m-d_H-i-s') . ".txt",
                "Withdrawal request initiated:\n" .
                "Transaction ID: $transaction_id\n" .
                "Wallet ID: $wallet_id\n" .
                "Amount: KES $amount\n" .
                "Phone: $phone\n" .
                "Customer: $customer_name"
            );
        } else {
            $error_message = "Hi $customer_name, your withdrawal request of KES " . number_format($amount, 2) . " failed. Please contact support at 0703416091. Ref: $transaction_id";
            $this->Operations->sendSMS($phone, $error_message);
            $response['status'] = 'error';
            $response['message'] = 'Unable to process withdrawal of KES ' . $amount . '. Please contact admin 0703416091';
            $response['data'] = null;
        }
        return $response;
    }


    public function transactions()
	{
	    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $session_table = 'login_session';
            $session_id = $this->input->post('session_id');
            $session_condition = array('session_id' => $session_id);
            $checksession = $this->Operations->SearchByCondition($session_table, $session_condition);  
            $loggedtime = $checksession[0]['created_on'];
            $currentTime = $this->date;
            $loggedTimestamp = strtotime($loggedtime);
            $currentTimestamp = strtotime($currentTime);
            $timediff = $currentTimestamp - $loggedTimestamp;
            if (($timediff) >  $this->timeframe) {
                $response['status'] = 'fail';
                $response['message'] = 'User logged out';
                $response['data'] = null;
            }
            
            else if (!empty($checksession) && $checksession[0]['session_id'] == $session_id) {
                $wallet_id = $checksession[0]['wallet_id'];
                $condition = array('wallet_id' => $wallet_id);
                $table = 'customer_ledger';
                $transactions = $this->Operations->SearchByConditionDeriv($table, $condition);
                $trans_data = [];
                foreach ($transactions as $key ) {
                    $trans_detail = $this->mapTransactionDetails($key);
                    $user_trans['transaction_type'] = $trans_detail['transaction_type'];
                    $user_trans['status_text'] = $trans_detail['status_text'];
                    $user_trans['text_color'] = $trans_detail['status_color'];
                    $user_trans['text_arrow'] = $trans_detail['text_arrow'];
                    $user_trans['transaction_number'] = $key['transaction_number'];
                    $user_trans['receipt_no'] = $key['receipt_no'];
                    $user_trans['pay_method'] = $key['pay_method'];
                    $user_trans['wallet_id'] = $key['wallet_id'];
                    $user_trans['trans_id'] = $key['trans_id'];
                    $user_trans['paid_amount'] = $key['paid_amount'];
                    $user_trans['trans_date'] = $key['trans_date'];
                    $user_trans['currency'] = $key['currency'];
                    $user_trans['status'] = $key['status'];
                    $user_trans['created_at'] = $key['created_at'];
                    $trans_data[] = $user_trans;
                 
                }
    
    
                $data = array(
                    'transactions' => $trans_data,
                );
                $response['status'] = 'success';
                $response['message'] = 'User is logged in';
                $response['data'] = $data;
            } else {
                $response['status'] = 'fail';
                $response['message'] = 'User not logged in';
                $response['data'] = null;
            }
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'Invalid request method';
            $response['data'] = null;
        }
    
        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        
	}


	
    public function passwordupdate()
    {
        $response = array();
        $pass1 = $this->input->post('password');
        $pass2 = $this->input->post('confirmpassword');
        $session_id = $this->input->post('session_id');
    
        // Validation rules
        $this->form_validation->set_rules('password', 'password', 'required');
        $this->form_validation->set_rules('confirmpassword', 'confirmpassword', 'required');
        $this->form_validation->set_rules('session_id', 'session_id', 'required');
    
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate form data
            if ($this->form_validation->run() == FALSE) {
                $response['status'] = 'fail';
                $response['message'] = 'session_id, password, and confirmpassword are required';
                $response['data'] = null;
            } else {
                // Check session
                $session_condition = array('session_id' => $session_id);
                $checksession = $this->Operations->SearchByCondition('login_session', $session_condition);
                
                $loggedtime = $checksession[0]['created_on'];
            
                $currentTime = $this->date;
                
                
                $loggedTimestamp = strtotime($loggedtime);
                $currentTimestamp = strtotime($currentTime);
                $timediff = $currentTimestamp - $loggedTimestamp;
            
                // Check if the time difference is more than 1 minute (60 seconds)
                if (($timediff) >  $this->timeframe) {
                    $response['status'] = 'fail';
                    $response['message'] = 'User logged out';
                    $response['data'] = null;
                }
                elseif (!empty($checksession) && $checksession[0]['session_id'] == $session_id) {
                    $wallet_id = $checksession[0]['wallet_id'];
    
                    // Check if passwords match
                    if ($pass1 != $pass2) {
                        $response['status'] = 'fail';
                        $response['message'] = 'Passwords must match';
                        $response['data'] = null;
                    } else {
                        // Update password
                        $data = array('password' => $this->Operations->hash_password($pass2));
                        $condition = array('wallet_id' => $wallet_id);
    
                        if ($this->Operations->UpdateData('customers', $condition, $data)) {
                            $action = 'Updated account details';
                            $this->Operations->RecordAction($action);
    
                            $message = "Password updated. New Password: " . $pass2;
                            $sms = $this->Operations->sendSMS($checksession[0]['phone'], $message);
    
                            $response['status'] = 'success';
                            $response['message'] = 'Password updated successfully';
                            $response['data'] = null;
                        } else {
                            $response['status'] = 'fail';
                            $response['message'] = 'Unable to update password, try again';
                            $response['data'] = null;
                        }
                    }
                } else {
                    $response['status'] = 'fail';
                    $response['message'] = 'User not logged in';
                    $response['data'] = null;
                }
            }
        } else {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST requests are allowed';
            $response['data'] = null;
        }
    
        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    


    
    
    public function adminappusers()
    {
        $response = array();
    
        $segments = $this->uri->rsegment_array();
        // Get the last segment
        $lastSegment = end($segments);
    
        $data['title'] = $lastSegment;
        $search = $this->Operations->SearchCustomers();
        
        $appusers = [];
        
        foreach($search as $user)
        {
            $getuser = array();
            
            $wallet_id = $user['wallet_id'];
    
            $summary = $this->Operations->customer_transection_summary($wallet_id);
            
            $total_credit = isset($summary[0][0]['total_credit']) ? $summary[0][0]['total_credit'] : 0;
           // $total_credit = (float)number_format($total_credit, 2);
           $total_credit = (float)sprintf("%.2f", $total_credit);
            
            $total_debit = isset($summary[1][0]['total_debit']) ? $summary[1][0]['total_debit'] : 0;
            //$total_debit = (float)number_format($total_debit, 2);
            $total_debit = (float)sprintf("%.2f", $total_debit);
            
            //$total_balance = -$summary[1][0]['total_debit'] + $summary[0][0]['total_credit'];
            
           // $total_balance = (float)sprintf("%.2f", $total_balance);
            //$total_balance = (float)number_format($total_balance, 2);
            
            $total_balance = -$summary[1][0]['total_debit'] + $summary[0][0]['total_credit'];
            $total_balance = (float)number_format($total_balance, 2, '.', '');
            
            $getuser['id'] = $user['id'];
            $getuser['wallet_id'] = $user['wallet_id'];
            $getuser['phone'] = $user['phone'];
            $getuser['account_number'] = $user['account_number'];
            $getuser['total_credit'] = $total_credit;
            $getuser['total_debit'] = $total_debit;
            $getuser['total_balance'] = $total_balance;
            $getuser['created_on'] = $user['created_on'];
            
            $appusers[] = $getuser;
            
        }
        
        
   
    
        if (!empty($search)) {
            $response['status'] = 'success';
            $response['message'] = 'App users retrieved successfully';
            $response['data'] = $appusers;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'No app users found';
            $response['data'] = null;
        }
    
        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    
    public function adminsystemusers()
    {
        $response = array();

        $search = $this->Operations->Search('users');

    
        if (!empty($search)) {
            $response['status'] = 'success';
            $response['message'] = 'System users retrieved successfully';
            $response['data'] = $search;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'No system users found';
            $response['data'] = null;
        }
    
        echo json_encode($response);
    }



    public function viewrate()
    {
        $response = array();

        $segments = $this->uri->rsegment_array();
        // Get the last segment
        $lastSegment = end($segments);

        $search = $this->Operations->Search('exchange');

        $data['title'] = $lastSegment;

        if (!empty($search)) {
            $response['status'] = 'success';
            $response['message'] = 'Exchange rates retrieved successfully';
            $response['data'] = array();

            foreach ($search as $rate) {
                $service_type = ""; // Default value

                // Map service_type based on your list
                switch ($rate['service_type']) {
                    case 1:
                        $service_type = "Deriv";
                        break;
                    case 2:
                        $service_type = "Binance";
                        break;
                    case 3:
                        $service_type = "Bitcoin";
                        break;
                    case 4:
                        $service_type = "Ethereum";
                        break;
                    case 5:
                        $service_type = "USDT(ERC 20)";
                        break;
                    case 6:
                        $service_type = "USDT(TRC 20)";
                        break;
                    case 7:
                        $service_type = "Skrill";
                        break;
                    case 8:
                        $service_type = "Neteller";
                        break;

                    case 9:
                        $service_type = "Service Test";
                        break;
                    // Add more cases for other service types as needed
                }

                // Add service_type to the rate data
                $rate['service_type'] = $service_type;

                // Add exchange_type information
                $exchange_type_info = ($rate['exchange_type'] == 1) ? "Buying" : "Selling";
                $rate['exchange_type_info'] = $exchange_type_info;

                $response['data'][] = $rate;
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = 'No exchange rates found';
            $response['data'] = null;
        }

        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    
    
    public function setexchange()
    {
        $response = array();
    
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = '';
        } else {
            // Form validation for rate
            $service_type = $this->input->post('service_type');
            $rate = $this->input->post('rate');
            $bought_at = $this->input->post('bought_at');
            $exchange_type = $this->input->post('exchange_type');
            $charge = $this->input->post('charge');
            $fee = $this->input->post('fee');


            if (empty($service_type)) {
                $response['status'] = 'error';
                $response['message'] = 'Service type is required.';
                $response['data'] = '';
            }
            elseif (empty($rate)) {
                $response['status'] = 'error';
                $response['message'] = 'Rate is required.';
                $response['data'] = '';
            } elseif (!is_numeric($rate)) {
                $response['status'] = 'error';
                $response['message'] = 'Rate must be a numeric value.';
                $response['data'] = '';
            } 
            elseif(empty($exchange_type))
            {
                $response['status'] = 'error';
                $response['message'] = 'Exchange type is required.';
                $response['data'] = '';
            }
            elseif(empty($bought_at)) 
            {
                $response['status'] = 'error';
                $response['message'] = 'bought at required.';
                $response['data'] = '';
            }
            elseif($charge < 0) 
            {
                $response['status'] = 'error';
                $response['message'] = 'charge percentage cannot be less than 0';
                $response['data'] = '';
            }
            elseif($fee < 0) 
            {
                $response['status'] = 'error';
                $response['message'] = 'fee charge cannot be less than 0';
                $response['data'] = '';
            }
            else {
                // Form validation for exchange type
                
          
                    $table = 'exchange';
                    $condition = array(
                    'service_type'=>$service_type,
                    'exchange_type'=>$exchange_type,
                    );
                    $search = $this->Operations->SearchByCondition($table,$condition);
    
                    if ($search) {
                        $ratecondition = array('exchange_id' => $search[0]['exchange_id']);
                        $updatedata = array(
                        'service_type'=>$service_type,
                        'kes' => $rate,
                        'created_on' =>  $this->date,
                        'exchange_type' => $exchange_type,
                        'bought_at'=>$bought_at,
                        'charge'=>$charge,
                        'fee'=>$fee,
                        );
                        $update = $this->Operations->UpdateData($table, $ratecondition, $updatedata);
    
                        if ($update) {
                            $response['status'] = 'success';
                            $response['message'] = 'Exchange rate updated successfully';
                            $response['data'] = '';
                        } else {
                            $response['status'] = 'error';
                            $response['message'] = 'Failed to update exchange rate';
                            $response['data'] = '';
                        }
                    } else {
                        $data = array(
                        'service_type'=>$service_type,
                        'kes' => $rate,
                        'usd' => 1,
                        'created_on' =>  $this->date, 
                        'exchange_type' => $exchange_type,
                        'bought_at'=>$bought_at,
                        'charge'=>$charge,
                        'fee'=>$fee,

                        );
                        $save = $this->Operations->Create($table, $data);
    
                        if ($save) {
                            $response['status'] = 'success';
                            $response['message'] = 'Exchange rate saved successfully';
                            $response['data'] = '';
                        } else {
                            $response['status'] = 'error';
                            $response['message'] = 'Failed to save exchange rate';
                            $response['data'] = '';
                        }
                    }
                
            }
        }
    
        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    
    public function AdminCreateAccount()
    {
        $response = array();
    
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = '';
        } else {
            $table = 'users';
            $names = $this->input->post('names');
            $email = $this->input->post('email');
            $phone = $this->input->post('phone');
            $partner_id = $this->input->post('partner_id');
            $user_type = $this->input->post('user_type');

            $phone = $this->input->post('phone');

            $password = $this->input->post('password');
            $confirmpassword = $this->input->post('confirmpassword');
            $mobile = preg_replace('/^(?:\+?254|0)?/', '+254', $phone);
            
             // Additional checks for existing phone and email
            $p_id = $this->Operations->get_user_id_from_phone($mobile, $table);
            $ph = $this->Operations->get_user($p_id, $table);

            $email_id = $this->Operations->get_user_id_from_email($email, $table);
            $em = $this->Operations->get_user($email_id, $table);

            $partner_condition = array('partner_id'=>$partner_id);
            $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
    
            // Form validation for phone
            if (empty($phone)) {
                $response['status'] = 'error';
                $response['message'] = 'Phone number is required';
                
            }
             elseif (empty($names)) {
                $response['status'] = 'error';
                $response['message'] = 'names is required';
                
            }
            
             elseif (empty($email)) {
                $response['status'] = 'error';
                $response['message'] = 'email is required';
                
            }
            elseif (empty($partner_id)) {
                $response['status'] = 'error';
                $response['message'] = 'partner_id is required';
                
            }
            elseif(empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] == NULL || $validate_partner[0]['partner_id'] == '')
            {
                http_response_code(401); // Bad Request
                $response['status'] = 'fail';
                $response['message'] = 'partner id not valid';
            }
            
            
             elseif (empty($user_type)) {
                $response['status'] = 'error';
                $response['message'] = 'user_type is required';
                
            }
            elseif (empty($password)) {
                $response['status'] = 'error';
                $response['message'] = 'Password is required';
                
            } 
             elseif (empty($confirmpassword)) {
                $response['status'] = 'error';
                $response['message'] = 'Confirm password is required';
                
            }
            elseif ($password != $confirmpassword) {
                $response['status'] = 'error';
                $response['message'] = 'Passwords must match';
                
            }
             
            else {
                
                if ($ph) {
                $response['status'] = 'error';
                $response['message'] = 'Phone number already exists';
                
                }
                elseif ($em) {
                    $response['status'] = 'error';
                    $response['message'] = 'email already exists';
                    
                }else
                {
                    $data = array(
                        'names' => $names,
                        'email' => $email,
                        'phone' => $mobile,
                        'password' => $this->Operations->hash_password($password),
                        'wallet_id' => $this->Operations->Generator(6),
                        'partner_id'=>$partner_id,
                        'user_type'=>$user_type,
                        'created_on' => $this->date, 
                    );
    
                    if ($this->Operations->Create($table, $data)) {
                        $subject = 'Account created';
                        $message = 'Success! Your account has been created. Use the following to login:  Phone: ' . $phone . ' and Password: ' . $confirmpassword . '';
    
                        $sms = $this->Operations->sendSMS($mobile, $message);
    
                        $response['status'] = 'success';
                        $response['message'] = 'Successful account created. Login to start';
                        
                    } else {
                        $response['status'] = 'error';
                        $response['message'] = 'Unable to add now, try again';
                        
                    }
                 }
           
                
            }
        }
    
        echo json_encode($response);
    }
    
    //MPESA 
    
    public function mpesa_deposits()
    {
        $response = array();
    
        $segments = $this->uri->rsegment_array();
        // Get the last segment
        $lastSegment = end($segments);
    
        $condition = array('paid'=>1);
        $search = $this->Operations->MpesaDeposits();
    
    
        if (!empty($search)) {
            $response['status'] = 'success';
            $response['message'] = 'Mpesa retrieved successfully';
            $response['data'] = $search;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'No exchange rates found';
            $response['data'] = null;
        }
    
        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        
    }
    
    
    public function mpesa_withdrawals_transactions()
    {
        
        $response = array();
    
      
    
        $condition = array('paid'=>1);
        $search = $this->Operations->SearchByCondition('mpesa_withdrawals',$condition);
    
    
        if (!empty($search)) {
            $response['status'] = 'success';
            $response['message'] = 'Mpesa retrieved successfully';
            $response['data'] = $search;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'No transactions found';
            $response['data'] = null;
        }
    
        // Send JSON response
        echo json_encode($response);
    }
    
    
    public function reject_withdrawal_request()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = '';
        }
        else
        {
            $request_id = $this->input->post('request_id');
            $response = array();
            if (!$request_id || empty($request_id)) {
                $response['status'] = 'fail';
                $response['message'] = 'Request ID required';
                $response['data'] = null;
            }
            else
            {
                $table = 'deriv_withdraw_request';
                $condition = array('id' => $request_id);
        
            
                $data = array('status' => 2);
                $update = $this->Operations->UpdateData($table, $condition, $data);
                
                if ($update === TRUE) {
                    $message = 'Withdrawal Request Rejected';
        
                    $response['status'] = 'success';
                    $response['message'] = $message;
                    $response['data'] = null;
                    
                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Unable to process request now, try again later';
                    $response['data'] = null;
                    
                }
                
                
            }
        }
        
             echo json_encode($response);
    }



   
   
   public function StepakashP2P()
   {
       $response = array();
    
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;
           
        }
        
        
        // Fetch inputs using CodeIgniter's input class
        $recipientWalletID = $this->input->post('wallet_id');
        $amount = $this->input->post('send_amount');
        $amount = (float)$amount;
        $session_id = $this->input->post('session_id');
        
        
        
        $chargePercent = 0;
        $amountAfterCharge = $amount - ($amount * $chargePercent);
        $amountAfterCharge = str_replace(',', '', number_format($amountAfterCharge, 2));
        
        $amountToDebit = $amount + ($amount * $chargePercent);
        
        
        $chargeAmt = ($amount * $chargePercent);
        
        
        
        $session_table = 'login_session';
        $session_condition = array('session_id' => $session_id);
        $checksession = $this->Operations->SearchByCondition($session_table, $session_condition);
        
        $wallet_id = $checksession[0]['wallet_id'];
         
        $loggedtime = $checksession[0]['created_on'];
            
        $currentTime = $this->date;
        
        
        $loggedTimestamp = strtotime($loggedtime);
        $currentTimestamp = strtotime($currentTime);
        $timediff = $currentTimestamp - $loggedTimestamp;
        
        
        $user_details = $this->UserAccount($wallet_id);
        $sender_credit =$user_details['total_credit'];
        $sender_debit =$user_details['total_debit'];
        $sender_balance =$user_details['total_balance'];
        $sender_phone = $user_details['phone'];
        $sender_wallet = $user_details['wallet_id'];
        
        
        $receiver_details = $this->UserAccount($recipientWalletID);
        $receiver_credit =$receiver_details['total_credit'];
        $receiver_debit =$receiver_details['total_debit'];
        $receiver_balance =$receiver_details['total_balance'];
        $receiver_phone = $receiver_details['phone'];
        $receiver_wallet = $receiver_details['wallet_id'];
        
            
            
            // Check if the amount is greater than some threshold (adjust as needed)
        
        // Form validation
        $this->form_validation->set_rules('wallet_id', 'wallet id', 'required');
        $this->form_validation->set_rules('send_amount', 'amount', 'required|numeric|greater_than[0]');
        $this->form_validation->set_rules('session_id', 'session_id', 'required');
        
        if ($this->form_validation->run() == FALSE) {
            // Handle validation errors
            $response['status'] = 'error';
            $response['message'] = validation_errors();
            $response['data'] = null;
     
        }
        else if (empty($recipientWalletID)) {
            $response['status'] = 'error';
            $response['message'] = 'Wallet id required';
            $response['data'] = null;
       
        }
        else if (empty($amount)) {
            $response['status'] = 'error';
            $response['message'] = 'transfer amount required';
            $response['data'] = null;
       
        }
         else if (!is_numeric($amount)) {
            $response['status'] = 'error';
            $response['message'] = 'Transfer amount must be numeric';
            $response['data'] = null;
        }
        
        else if (empty($session_id)) {
            $response['status'] = 'error';
            $response['message'] = 'session required';
            $response['data'] = null;
       
        }
     
        else if (empty($checksession) || $checksession[0]['session_id'] != $session_id) {
            $response['status'] = 'error';
            $response['message'] = 'Invalid session_id or user not logged in';
            $response['data'] = null;
       
        }
        
        else if($sender_wallet == $recipientWalletID)
        {
            $response['status'] = 'error';
            $response['message'] = 'Cannot complete transaction to same Wallet ID';
            $response['data'] = null;
     
        }
        else if(empty($receiver_wallet) || $receiver_wallet == NULL || $receiver_wallet == '')
        {
            $response['status'] = 'error';
            $response['message'] = 'Cannot compelete Transaction,recepient Wallet ID not found';
            $response['data'] = null;
     
        }
        else if ($amount < 0.1) {
            
            $response['status'] = 'error';
            $response['message'] = 'The amount must be greater alteast than 0.l '.$sender_balance.'';
            $response['data'] = null;
  
        } else if($sender_balance < $amountToDebit) {
            $response['status'] = 'error';
            $response['message'] = 'You dont have sufficient funds in your wallet to transact ';
            $response['data'] = null;

        } 
        else if($sender_balance >= $amountToDebit) {
            //successfull you can transact
              // Update recipient's balance after deposit
                $newRecipientBalanceKes = $receiver_balance + $amountAfterCharge;
    
                // Update sender's balance after deducting the amount
                $newSenderBalanceKes = $sender_balance - $amountAfterCharge;

                $transaction_number = $this->transaction_number;

                $next_transaction_number = $this->getNextReceipt($transaction_number);

                
                    
            
                $cr_dr = 'cr';
                $dr_cr = 'dr';
                $paymethod = 'STEPAKASH';
                $description = 'P2P transferred to '.$recipientWalletID.'';
                $transaction_id = $this->Operations->OTP(9);

                $receipt_no = $this->Operations->Generator(15); 
                $trans_id = $this->Operations->Generator(8);
                $trans_date = date('Y-m-d');
                $currency = 'KES';
                $conversionRate = 0;
                $amountToCredit = $amount;
                $TotalamountToCredit = $amount;
                
                $TotalamountToDebit = $amountToDebit;

                
                $messo = ''.$transaction_number.' Succesfully KES '.$amount.' transferred to wallet ID '.$recipientWalletID . ' on '.$this->date.'.';
      

                $senderMessage = ''.$transaction_number.' successfully ' . $amount . ' KES transferred to wallet ID ' . $recipientWalletID . ' on '.$this->date.'';
                $recipientMessage = ''.$next_transaction_number.' received ' . $amount . ' KES from wallet ID ' . $sender_wallet . ' on '.$this->date.'';

                
                
                $saveSenderTransaction = $this->DebitP2PToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$sender_wallet,$receiver_wallet,$trans_date,$currency,$amountToDebit,
                $conversionRate,$chargePercent,$chargeAmt,$TotalamountToDebit);
                 
                $saveRecipientTransaction = $this->CreditP2PToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$receiver_wallet,$sender_wallet,$trans_date,$currency,$amountToCredit,$TotalamountToCredit);
            
                    
    
                if ($saveRecipientTransaction === TRUE && $saveSenderTransaction === TRUE ) {
                  
                    
                   
                    //SEND USER APP NOTIFICATION 
                     $sendersms = $this->Operations->sendSMS($sender_phone, $senderMessage);
                     $recieversms = $this->Operations->sendSMS($receiver_phone, $recipientMessage);
                     
                     $response['status'] = 'success'; 
                     $response['message'] = $messo;
                     $response['data'] = null; 
                } else {
                    $response['status'] = 'error';
                     $response['message'] = 'Unable to process your  request now, please try again.';
                     $response['data'] = null;
                }

        } 
        else
        {
            $response['status'] = 'error';
            $response['message'] = 'Something went wrong, please try again';
            $response['data'] = null;
        }
        
        echo json_encode($response);
   }
   
   public function UserAccount($user_wallet)
   {
       
       $senderWalletID   = $user_wallet;
       $senderSummary = $this->Operations->customer_transection_summary($senderWalletID);
        
       $senderTotalCredit = (float) str_replace(',', '', number_format($senderSummary[0][0]['total_credit'], 2));
       $senderTotalDebit = (float) str_replace(',', '', number_format($senderSummary[1][0]['total_debit'], 2));
       $senderTotalBalanceKes = $senderTotalCredit - $senderTotalDebit;
       $senderTotalBalanceKes = str_replace(',', '', number_format($senderTotalBalanceKes, 2));

       
       $condition1 = array('wallet_id'=>$senderWalletID);
       
       
       $sender_details = $this->Operations->SearchByCondition('customers',$condition1);

       $user_transactions = $this->Operations->SearchByCondition('customer_ledger',$condition1);

     

        
       $sender_phone =  $sender_details[0]['phone'];
       $sender_wallet =  $sender_details[0]['wallet_id'];
       $created_on =  $sender_details[0]['created_on'];
       $account_number =  $sender_details[0]['account_number'];
       $agent =  $sender_details[0]['agent'];



       
       return array(
           'wallet_id'=>$sender_wallet,
           'agent'=>$agent,
           'phone'=>$sender_phone,
           'created_on'=>$created_on,
           'deriv_cr_number'=>$account_number,
           'total_credit'=>$senderTotalCredit,
           'total_debit'=>$senderTotalDebit,
           'total_balance'=>$senderTotalBalanceKes,
           'transactions'=>$user_transactions,
        );

   }
   
   
   public function DeductDebitToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$senderWalletID,$trans_date,$currency,$amountToDebit,
   $conversionRate,$chargePercent,$chargeAmt,$TotalamountToDebit)
   {
       $senderTable = 'customer_ledger';
       $dr_cr = 'dr';
       $trans_id =  $this->Operations->Generator(8);
       $system_trans_id =  $this->Operations->Generator(8);
       $created_at =  $this->date;;
       
       
       $senderTransactionData = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	NULL,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $senderWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $amountToDebit,
                'cr_dr'=>$dr_cr,
                'trans_date' => $trans_date,
                'currency' => $currency,
                'amount' => ($amountToDebit - $chargeAmt),
                'rate' => $conversionRate,
                'deriv' => 0,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToDebit,
                'status' => 1,
                'created_at' => $created_at,
            );
            $saveSenderTransaction = $this->Operations->Create($senderTable, $senderTransactionData);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	NULL,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $senderWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $amountToDebit,
                    'cr_dr'=>$dr_cr,
                    'trans_date' => $trans_date, 
                    'currency' => $currency,
                    'deriv' => 0,
                    'amount' => ($amountToDebit - $chargeAmt),
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToDebit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveSenderTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }


   public function DebitP2PToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$senderWalletID,$receiverWalletID,$trans_date,$currency,$amountToDebit,
   $conversionRate,$chargePercent,$chargeAmt,$TotalamountToDebit)
   {
       $senderTable = 'customer_ledger';
       $dr_cr = 'dr';
       $trans_id =  $this->Operations->Generator(8);
       $system_trans_id =  $this->Operations->Generator(8);
       $created_at =  $this->date;;
       
       
       $senderTransactionData = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	NULL,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $senderWalletID,
                'receiver_wallet_id'=>$receiverWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $amountToDebit,
                'cr_dr'=>$dr_cr,
                'trans_date' => $trans_date,
                'currency' => $currency,
                'amount' => ($amountToDebit - $chargeAmt),
                'rate' => $conversionRate,
                'deriv' => 10,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToDebit,
                'status' => 1,
                'created_at' => $created_at,
            );
            $saveSenderTransaction = $this->Operations->Create($senderTable, $senderTransactionData);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	NULL,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $senderWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $amountToDebit,
                    'cr_dr'=>$dr_cr,
                    'trans_date' => $trans_date, 
                    'currency' => $currency,
                    'deriv' => 10,
                    'amount' => ($amountToDebit - $chargeAmt),
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToDebit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveSenderTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }

   public function debit_pay_now($transaction_number,$description,$senderWalletID,$amountToDebit,$chargePercent,$chargeAmt,$TotalamountToDebit,$partner_id,$partner_earning)
   {
       $senderTable = 'customer_ledger';
       $dr_cr = 'dr';
       $trans_id =  $this->Operations->Generator(8);
       $transaction_id =  $this->Operations->Generator(9);
       $system_trans_id =  $this->Operations->Generator(8);
       $paymethod = 'STEPAKASH';
       $created_at =  $this->date;
       $currency = 'KES';
       $conversionRate = 0;
       
       
       $senderTransactionData = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	NULL,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $senderWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $amountToDebit,
                'cr_dr'=>$dr_cr,
                'trans_date' => $created_at,
                'currency' => $currency,
                'amount' => ($amountToDebit - $chargeAmt),
                'rate' => $conversionRate,
                'deriv' => 13,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToDebit,
                'status' => 1,
                'partner_id'=>$partner_id,
                'partner_earning'=>$partner_earning,
                'created_at' => $created_at,
            );
            $saveSenderTransaction = $this->Operations->Create($senderTable, $senderTransactionData);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	NULL,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $senderWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $amountToDebit,
                    'cr_dr'=>$dr_cr,
                    'trans_date' => $created_at, 
                    'currency' => $currency,
                    'deriv' => 13,
                    'amount' => ($amountToDebit - $chargeAmt),
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToDebit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveSenderTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }

   public function credit_pay_now($transaction_number,$description,$senderWalletID,$amountToDebit,$chargePercent,$chargeAmt,$TotalamountToDebit,$partner_id,$partner_earning)
   {
       $senderTable = 'customer_ledger';
       $dr_cr = 'cr';
       $trans_id =  $this->Operations->Generator(8);
       $transaction_id =  $this->Operations->Generator(9);
       $system_trans_id =  $this->Operations->Generator(8);
       $paymethod = 'STEPAKASH';
       $created_at =  $this->date;
       $currency = 'KES';
       $conversionRate = 0;
       
       
       $senderTransactionData = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	$trans_id,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $senderWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $amountToDebit,
                'cr_dr'=>$dr_cr,
                'trans_date' => $created_at,
                'currency' => $currency,
                'amount' => ($amountToDebit - $chargeAmt),
                'rate' => $conversionRate,
                'deriv' => 10,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToDebit,
                'status' => 1,
                'partner_id'=>$partner_id,
                'partner_earning'=>$partner_earning,
                'created_at' => $created_at,
            );
            $saveSenderTransaction = $this->Operations->Create($senderTable, $senderTransactionData);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	$trans_id,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $senderWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $amountToDebit,
                    'cr_dr'=>$dr_cr,
                    'trans_date' => $created_at, 
                    'currency' => $currency,
                    'deriv' => 10,
                    'amount' => ($amountToDebit - $chargeAmt),
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToDebit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveSenderTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }
   
   public function CreditP2PToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$receiverWalletID,$senderWalletID,$trans_date,$currency,$amountToCredit,$TotalamountToCredit)
   {
       $receiverTable = 'customer_ledger';
       $cr_dr = 'cr';
       $trans_id =  $this->Operations->Generator(8);
       $system_trans_id =  $this->Operations->Generator(8);
       $created_at =  $this->date;
       $chargePercent = 0;
       $chargeAmt = 0;
       $conversionRate = 0;
       
      
       $RecipientTransaction = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	$receipt_no,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $receiverWalletID,
                'receiver_wallet_id'=>$senderWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $amountToCredit,
                'cr_dr'=>$cr_dr,
                'trans_date' => $trans_date,
                'currency' => $currency,
                'amount' => $amountToCredit,
                'rate' => $conversionRate,
                'deriv' => 10,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToCredit,
                'status' => 1,
                'created_at' => $created_at,
            );
            $saveReceiverTransaction = $this->Operations->Create($receiverTable, $RecipientTransaction);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	$receipt_no,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $receiverWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $amountToCredit,
                    'cr_dr'=>$cr_dr,
                    'trans_date' => $trans_date, 
                    'currency' => $currency,
                    'deriv' => 10,
                    'amount' => $amountToCredit,
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToCredit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveReceiverTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }

   public function DebitToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$senderWalletID,$trans_date,$currency,$amountToDebit,
   $conversionRate,$chargePercent,$chargeAmt,$TotalamountToDebit)
   {
       $senderTable = 'customer_ledger';
       $dr_cr = 'dr';
       $trans_id =  $this->Operations->Generator(8);
       $system_trans_id =  $this->Operations->Generator(8);
       $created_at =  $this->date;;
       
       
       $senderTransactionData = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	NULL,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $senderWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $amountToDebit,
                'cr_dr'=>$dr_cr,
                'trans_date' => $trans_date,
                'currency' => $currency,
                'amount' => ($amountToDebit - $chargeAmt),
                'rate' => $conversionRate,
                'deriv' => 2,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToDebit,
                'status' => 1,
                'created_at' => $created_at,
            );
            $saveSenderTransaction = $this->Operations->Create($senderTable, $senderTransactionData);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	NULL,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $senderWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $amountToDebit,
                    'cr_dr'=>$dr_cr,
                    'trans_date' => $trans_date, 
                    'currency' => $currency,
                    'deriv' => 2,
                    'amount' => ($amountToDebit - $chargeAmt),
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToDebit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveSenderTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }
   
   public function CreditToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$receiverWalletID,
   $trans_date,$currency,$amountToCredit,$TotalamountToCredit)
   {
       $receiverTable = 'customer_ledger';
       $cr_dr = 'cr';
       $trans_id =  $this->Operations->Generator(8);
       $system_trans_id =  $this->Operations->Generator(8);
       $created_at =  $this->date;
       $chargePercent = 0;
       $chargeAmt = 0;
       $conversionRate = 0;
       
      
       $RecipientTransaction = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	$receipt_no,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $receiverWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $amountToCredit,
                'cr_dr'=>$cr_dr,
                'trans_date' => $trans_date,
                'currency' => $currency,
                'amount' => $amountToCredit,
                'rate' => $conversionRate,
                'deriv' => 2,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToCredit,
                'status' => 1,
                'created_at' => $created_at,
            );
            $saveReceiverTransaction = $this->Operations->Create($receiverTable, $RecipientTransaction);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	$receipt_no,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $receiverWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $amountToCredit,
                    'cr_dr'=>$cr_dr,
                    'trans_date' => $trans_date, 
                    'currency' => $currency,
                    'deriv' => 2,
                    'amount' => $amountToCredit,
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToCredit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveReceiverTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }

   public function getUserIP()
   {
        $ip = null;

        // Check if the IP is from a shared internet connection
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } 
        // Check if the IP is from a proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } 
        // Use the remote address if available
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Additional checks for IP addresses
        if (strpos($ip, ',') !== false) {
            // If multiple IP addresses are provided (common in proxy configurations), get the first one
            $ipList = explode(',', $ip);
            $ip = trim($ipList[0]);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // If the IP is an IPv6 address, convert it to IPv4
            $ip = '::ffff:' . $ip;
        }

        return $ip;
    }
   
   
   public function Mpesa_b2c_test()
   {
        $crypto_table = 'crypto_requests';
        $binance_condition1 = array('status' => 0,'crypto_type'=>6,'cr_dr'=>'dr');
        $binance_deposit_request = $this->Operations->CountWithCondition($crypto_table, $binance_condition1);
        $binance_condition2 = array('status' => 0,'crypto_type'=>6,'cr_dr'=>'cr');
        $binance_withdraw_request = $this->Operations->CountWithCondition($crypto_table, $binance_condition2);

        print_r($binance_withdraw_request);
    
    
   }

   public function deduct_from_wallet()
   {

        $response = array();
    
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
           // http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;
           
        }
        else
        {
            // Fetch inputs using CodeIgniter's input class
            $wallet_id = $this->input->post('wallet_id');
            $amount = $this->input->post('amount');
            $amount = (float)$amount;
            $reason = $this->input->post('reason');

            $amountToDebit = $amount;


            $user_details = $this->UserAccount($wallet_id);
            $sender_credit =$user_details['total_credit'];
            $sender_debit =$user_details['total_debit'];
            $sender_balance =$user_details['total_balance'];
            $sender_phone = $user_details['phone'];
            $sender_wallet = $user_details['wallet_id'];


                // Form validation
            $this->form_validation->set_rules('wallet_id', 'wallet id', 'required');
            $this->form_validation->set_rules('amount', 'amount', 'required|numeric|greater_than');
            $this->form_validation->set_rules('reason', 'reason', 'required');
            
            if ($this->form_validation->run() == FALSE) {
                // Handle validation errors
                $response['status'] = 'error';
                $response['message'] = validation_errors();
                $response['data'] = null;
        
            }
            else if (empty($wallet_id)) {
                $response['status'] = 'error';
                $response['message'] = 'Wallet id required';
                $response['data'] = null;
        
            }
            else if (empty($amount)) {
                $response['status'] = 'error';
                $response['message'] = 'deduct amount required';
                $response['data'] = null;
        
            }
            else if (!is_numeric($amount)) {
                $response['status'] = 'error';
                $response['message'] = 'deduct amount must be numeric';
                $response['data'] = null;
            }
            else if(empty($sender_wallet) || $sender_wallet == NULL || $sender_wallet == '')
            {
                $response['status'] = 'error';
                $response['message'] = 'Cannot compelete Transaction,recepient Wallet ID not found';
                $response['data'] = null;
        
            }
            else if ($amount < 0.1) {
                
                $response['status'] = 'error';
                $response['message'] = 'The amount must be greater alteast than 0.l balance: '.$sender_balance.'';
                $response['data'] = null;
    
            }else if($sender_balance < $amountToDebit) {
                $response['status'] = 'error';
                $response['message'] = 'User doesnt have sufficient funds in their wallet to deduct';
                $response['data'] = null;

            } 
            else if($sender_balance >= $amountToDebit) {
                //successfull you can transact
                $chargePercent = 0;
                $amountAfterCharge = $amount - ($amount * $chargePercent);
                $amountAfterCharge = str_replace(',', '', number_format($amountAfterCharge, 2));
        
                    // Update sender's balance after deducting the amount
                    $newSenderBalanceKes = $sender_balance - $amountAfterCharge;

                    $dr_cr = 'dr';
                    $paymethod = 'STEPAKASH';
                    $description = 'Manual Settlement';
                    $transaction_id = $this->Operations->OTP(9);
                    $receipt_no = $this->Operations->Generator(15); 
                    $trans_id = $this->Operations->Generator(8);
                    $trans_date = date('Y-m-d');
                    $currency = 'KES';
                    $conversionRate = 0;
                    $chargePercent = 0;
                    $chargeAmt = 0;
                    $amountToCredit = $amount;
                    $TotalamountToCredit = $amount;
                    $TotalamountToDebit = $amountToDebit;
                    $transaction_number =  $this->transaction_number;
                    
                    
                    $saveSenderTransaction = $this->DeductDebitToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$sender_wallet,$trans_date,$currency,$amountToDebit,
                    $conversionRate,$chargePercent,$chargeAmt,$TotalamountToDebit);

                
                        
        
                    if ($saveSenderTransaction === TRUE ) {
                    
                        $messo = ''.$transaction_number.' Succesfully KES '.$amount.' deducted from wallet ID '.$sender_wallet . '.';
        

                        $sam_phone = '0793601418';

                        //SEND USER APP NOTIFICATION 
                        $sendersms = $this->Operations->sendSMS($sam_phone, $messo);
                        $receiversms = $this->Operations->sendSMS($sender_phone, $messo);

                        
                        $response['status'] = 'success'; 
                        $response['message'] = $messo;
                        $response['data'] = null; 
                    } else {
                        $response['status'] = 'error';
                        $response['message'] = 'Unable to process your  request now, please try again.';
                        $response['data'] = null;
                    }

            } 
            else
            {
                $response['status'] = 'error';
                $response['message'] = 'Something went wrong, please try again';
                $response['data'] = null;
            }
        }
        echo json_encode($response);
   }

   public function add_user_wallet()
   {
        $response = array();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // http_response_code(400); // Bad Request
             $response['status'] = 'fail';
             $response['message'] = 'Only POST request allowed';
             $response['data'] = null;
            
         }
         else{

            // Fetch inputs using CodeIgniter's input class
            $wallet_id = $this->input->post('wallet_id');
            $amount = $this->input->post('amount');
            $amount = (float)$amount;
            $reason = $this->input->post('reason');

            $amountToDebit = $amount;


            $user_details = $this->UserAccount($wallet_id);
            $sender_credit =$user_details['total_credit'];
            $sender_debit =$user_details['total_debit'];
            $sender_balance =$user_details['total_balance'];
            $sender_phone = $user_details['phone'];
            $sender_wallet = $user_details['wallet_id'];


                // Form validation
            $this->form_validation->set_rules('wallet_id', 'wallet id', 'required');
            $this->form_validation->set_rules('amount', 'amount', 'required|numeric|greater_than');
            $this->form_validation->set_rules('reason', 'reason', 'required');
            
            if ($this->form_validation->run() == FALSE) {
                // Handle validation errors
                $response['status'] = 'error';
                $response['message'] = validation_errors();
                $response['data'] = null;
        
            }
            else if (empty($wallet_id)) {
                $response['status'] = 'error';
                $response['message'] = 'Wallet id required';
                $response['data'] = null;
        
            }
            else if (empty($amount)) {
                $response['status'] = 'error';
                $response['message'] = 'deduct amount required';
                $response['data'] = null;
        
            }
            else if (!is_numeric($amount)) {
                $response['status'] = 'error';
                $response['message'] = 'deduct amount must be numeric';
                $response['data'] = null;
            }
            else if(empty($sender_wallet) || $sender_wallet == NULL || $sender_wallet == '')
            {
                $response['status'] = 'error';
                $response['message'] = 'Cannot compelete Transaction,recepient Wallet ID not found';
                $response['data'] = null;
        
            }
            else
            {
                $dr_cr = 'cr';
                $paymethod = 'STEPAKASH';
                $description = 'Manual Deposit';
                $transaction_id = $this->Operations->Generator(9); 
                
                $receipt_no = $this->Operations->Generator(15); 
                $trans_id = $this->Operations->Generator(8);
                $trans_date = date('Y-m-d');
                $currency = 'KES';
                $conversionRate = 0;
                $chargePercent = 0;
                $chargeAmt = 0;
                $amountToCredit = $amount;
                $TotalamountToCredit = $amount;
                $receiverWalletID = $sender_wallet;
                
                $TotalamountToDebit = $amountToDebit;

                $transaction_number =  $this->transaction_number;
                
                $credit_account = $this->CreditToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$receiverWalletID,$trans_date,$currency,$amountToCredit,$TotalamountToCredit);

                if ($credit_account === TRUE ) {
                    
                    $messo = ''.$transaction_number.' Succesfully KES '.$amount.' credited to wallet ID '.$sender_wallet . '.';
    

                    $receiversms = $this->Operations->sendSMS($sender_phone, $messo);

                    
                    $response['status'] = 'success'; 
                    $response['message'] = $messo;
                    $response['data'] = null; 
                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Unable to process your  request now, please try again.';
                    $response['data'] = null;
                }
            }
            
         }

         echo json_encode($response);
        

        
   }


   public function stepakash_debit_report()
   {
        $response = array();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;
        
        }
        else
        {
            $table = 'customer_ledger';
            $condition = array('deriv'=>10,'cr_dr'=>'dr');
            $report = $this->Operations->SearchByCondition($table,$condition);

            $response['status'] = 'success';
            $response['message'] = 'debit report';
            $response['data'] = $report;

        }

        echo json_encode($response);

   }


   public function stepakash_credit_report()
   {
        $response = array();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;
        
        }
        else
        {
            $table = 'customer_ledger';
            $condition = array('deriv'=>10,'cr_dr'=>'cr');
            $report = $this->Operations->SearchByCondition($table,$condition);

            $response['status'] = 'success';
            $response['message'] = 'credit report';
            $response['data'] = $report;

        }

        echo json_encode($response);

   }



   public function DebitToCryptoAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$senderWalletID,$trans_date,$currency,$amountToDebit,
   $conversionRate,$chargePercent,$chargeAmt,$TotalamountToDebit,$crypto_type)
   {
       $senderTable = 'customer_ledger';
       $dr_cr = 'dr';
       $trans_id =  $this->Operations->Generator(8);
       $system_trans_id =  $this->Operations->Generator(8);
       $created_at =  $this->date;;
       
       
       $senderTransactionData = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	NULL,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $senderWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $amountToDebit,
                'cr_dr'=>$dr_cr,
                'trans_date' => $trans_date,
                'currency' => $currency,
                'amount' => ($amountToDebit),
                'rate' => $conversionRate,
                'deriv' => $crypto_type,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToDebit,
                'status' => 1,
                'created_at' => $created_at,
            );
            $saveSenderTransaction = $this->Operations->Create($senderTable, $senderTransactionData);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	NULL,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $senderWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $amountToDebit,
                    'cr_dr'=>$dr_cr,
                    'trans_date' => $trans_date, 
                    'currency' => $currency,
                    'deriv' => 2,
                    'amount' => ($amountToDebit),
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToDebit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveSenderTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }
   
   public function CreditToCryptoAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$receiverWalletID,
   $trans_date,$currency,$amountToCredit,$TotalamountToCredit,$chargeAmt,$crypto_type)
   {
       $receiverTable = 'customer_ledger';
       $cr_dr = 'cr';
       $trans_id =  $this->Operations->Generator(8);
       $system_trans_id =  $this->Operations->Generator(8);
       $created_at =  $this->date;
       $chargePercent = 0;
    //    $chargeAmt = 0;
       $conversionRate = 0;
       
      
       $RecipientTransaction = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	$receipt_no,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $receiverWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $amountToCredit,
                'cr_dr'=>$cr_dr,
                'trans_date' => $trans_date,
                'currency' => $currency,
                'amount' => $amountToCredit,
                'rate' => $conversionRate,
                'deriv' => $crypto_type,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToCredit,
                'status' => 1,
                'created_at' => $created_at,
            );
            $saveReceiverTransaction = $this->Operations->Create($receiverTable, $RecipientTransaction);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	$receipt_no,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $receiverWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $amountToCredit,
                    'cr_dr'=>$cr_dr,
                    'trans_date' => $trans_date, 
                    'currency' => $currency,
                    'deriv' => $crypto_type,
                    'amount' => $amountToCredit,
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToCredit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveReceiverTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }



   public function crypto_deposit_request() 
    {
        $response = array();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;

            echo json_encode($response);
            exit();
        }

        // Fetch inputs using CodeIgniter's input class
        $wallet_id = $this->input->post('wallet_id');
        $amount = $this->input->post('amount');
        $session_id = $this->input->post('session_id');
        $address_id = $this->input->post('address_id');
        $crypto_type = $this->input->post('crypto_type'); 

        
        // Form validation
        $this->form_validation->set_rules('wallet_id', 'wallet_id', 'required');
        $this->form_validation->set_rules('amount', 'amount', 'required|numeric|greater_than[0]');
        $this->form_validation->set_rules('address_id', 'address_id', 'required');
        $this->form_validation->set_rules('crypto_type', 'crypto_type', 'required');
        $this->form_validation->set_rules('session_id', 'session_id', 'required');
        

        
        if ($this->form_validation->run() == FALSE) {
            // Handle validation errors
            $response['status'] = 'fail';
            $response['message'] = 'wallet_id,amount,address_id,crypto_type and session_id required';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }

        // Validate session_id (assuming it's coming from somewhere)

        $session_table = 'login_session';
        $session_condition = array('session_id' => $session_id);
        $checksession = $this->Operations->SearchByCondition($session_table, $session_condition);
        
        $user_wallet_id = $checksession[0]['wallet_id'];

        $user_details = $this->UserAccount($user_wallet_id);
        $user_credit =$user_details['total_credit'];
        $user_debit =$user_details['total_debit'];
        $user_balance =$user_details['total_balance'];
        $user_phone = $user_details['phone'];
        $user_wallet = $user_details['wallet_id'];
        
        $loggedtime = $checksession[0]['created_on'];
            
        $currentTime = $this->date;
        
        
        $loggedTimestamp = strtotime($loggedtime);
        $currentTimestamp = strtotime($currentTime);
        $timediff = $currentTimestamp - $loggedTimestamp;

        $timestamp = strtotime($loggedtime);

        $expirationTime = 10 * 60; // 5 minutes in seconds

        //get our buy rate 
        $buyratecondition = array('exchange_type'=>1,'service_type'=>$crypto_type);
        $buyrate = $this->Operations->SearchByConditionBuy('exchange',$buyratecondition);


        // Convert the balance to USD using the conversion rate
        $conversionRate = $buyrate[0]['kes'];
        $boughtbuy = $buyrate[0]['bought_at'];
        $chargePercent = $buyrate[0]['charge'];
        $chargeFee = $buyrate[0]['fee'];



        //USD CALCULATIONS

        $amountUSD = round($amount / $conversionRate, 2);
        $chargeAmountUSD = round($amountUSD * $chargePercent / 100,2);
        $amountUSDAfterCharge = $amountUSD - $chargeAmountUSD;
        $amountUSDAfterChargeFee = $amountUSDAfterCharge - $chargeFee;

        //KES CALCULATIONS
        $chargeAmountKES = $chargeAmountUSD * $conversionRate;
        $amountKESAfterCharge = round($amountUSDAfterChargeFee * $conversionRate,2);
        $amountToDebit = $amount; 


        $profit_margin = ($conversionRate - $boughtbuy);
        // Ensure $profit_margin is always positive 
        $profit_margin = abs($profit_margin);
        $total_profit = $profit_margin * $amountUSD;

        $Total_kes_charged = $total_profit + $chargeAmountKES;

        $Total_usd_charged = ($Total_kes_charged / $conversionRate);
     
        $TotalamountToDebit = $amountToDebit;

        $totalAmountKES = $amountKESAfterCharge + $amount;


        $total_balance_usd_formatted = round($user_balance / $conversionRate,2);


        if (empty($checksession) || $checksession[0]['session_id'] !== $session_id) {
            $response['status'] = 'error';
            $response['message'] = 'Invalid session_id or user not logged in';
            $response['data'] = null;
        
        }
        elseif ($timestamp <= $expirationTime) {
            # code...
            $response['status'] = 'error';
            $response['message'] = 'Invalid session expired';
            $response['data'] = null;
        }
        else if(($checksession[0]['wallet_id'] != $user_wallet))
        {
            $response['status'] = 'error';
            $response['message'] = 'Invalid wallet_id not same';
            $response['data'] = null;
            
        }
        else if ($amountUSDAfterChargeFee < 10) {
            $response['status'] = 'error';
            $response['message'] = 'The amount must be greater than $10 USD.';
            $response['data'] = null;

        } elseif ($total_balance_usd_formatted < $amountUSDAfterChargeFee) {
            $response['status'] = 'error';
            $response['message'] = 'You dont have sufficient funds in your wallet';
            $response['data'] = null;

        }
        else if($user_balance < $amountToDebit) {
            $response['status'] = 'error';
            $response['message'] = 'You dont have sufficient funds in your wallet to transact,balance: '.$user_balance.' ';
            $response['data'] = null;

        } 
        else if($user_balance >= $amountToDebit)
        {
            //success transact
            $table = 'crypto_requests';

             $transaction_id = $this->Operations->OTP(6);
            
            
            $receipt_no = $this->Operations->Generator(9);
            $transaction_number =  $this->transaction_number;
            

            $data = array(
                'transaction_id'=>$transaction_id,
                'wallet_id'=>$user_wallet,
                'crypto_type'=>$crypto_type,
                'crypto_address'=>$address_id,
                'amount_usd'=>$amountUSDAfterChargeFee,
                'amount_kes'=>$amountKESAfterCharge,
                'amount_deposited'=>$amount,
                'cr_dr'=>'dr',
                'rate'=>$conversionRate,
                'charge_percentage'=>$chargePercent,
                'charge_amount_kes'=>$Total_kes_charged,
                'charge_amount_usd'=>$Total_usd_charged,
                'charge_fee'=>$chargeFee,
                'status'=>0,
                'deposited'=>0,
                'bought_at'=>$boughtbuy,
                'request_date'=>$this->date,
            );
            $save = $this->Operations->Create($table, $data);

            $paymethod = 'STEPAKASH';  
            $description = 'Deposit to Crypto';
            $currency = 'KES';
            $dateTime = $this->date;

            $crypt_type= '';

            if($crypto_type == 8)
            {
                $crypt_type = 8;
            }
            elseif ($crypto_type == 7) {
                $crypt_type = 7;
            }
            elseif ($crypto_type == 6) {
                $crypt_type = 6;
            }elseif ($crypto_type == 5) {
                $crypt_type = 5;
            }elseif ($crypto_type == 4) {
                $crypt_type = 4;
            }elseif ($crypto_type == 3) {
                $crypt_type = 3;
            }
            

            $debit_wallet = $this->DebitToCryptoAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$user_wallet,$dateTime,$currency,$amountToDebit,$conversionRate,$chargePercent,$Total_kes_charged,$TotalamountToDebit,$crypt_type);
            

            if($save === TRUE && $debit_wallet === TRUE) {
                
                $message = 'Transaction ID: ' . $transaction_id. ', a deposit of ' . $amountUSDAfterChargeFee . ' USD is currently being processed.';
                
                $crypto_message = 'Crypto deposit request check system';
     

                $stevephone = '0757259996';
                $albertphone = '0727010129';
                $samphone =     '0793601418';

                $sendadminsms0 = $this->Operations->sendSMS($samphone,$message);
                $sendadminsms1 = $this->Operations->sendSMS($stevephone,$message);
                $sendadminsms2 = $this->Operations->sendSMS($albertphone,$message);



                // //SEND USER APP NOTIFICATION 
                $sms = $this->Operations->sendSMS($user_phone, $message);
                

                $response['status'] = 'success';
                $response['message'] = $message;
                $response['data'] = null;
            } else {
                $response['status'] = 'fail';
                $response['message'] = 'Unable to process your request now try again';
                $response['data'] = null;
            }
        }
        else
        {

            
        }



        echo json_encode($response);

    }



    public function crypto_withdrawal_request() 
    {
        $response = array();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;

            echo json_encode($response);
            exit();
        }

        // Fetch inputs using CodeIgniter's input class
        $wallet_id = $this->input->post('wallet_id');
        $amount = $this->input->post('amount');
        $session_id = $this->input->post('session_id');
        $address_id = $this->input->post('address_id');
        $crypto_type = $this->input->post('crypto_type'); 

        
        // Form validation
        $this->form_validation->set_rules('wallet_id', 'wallet_id', 'required');
        $this->form_validation->set_rules('amount', 'amount', 'required|numeric|greater_than[0]');
        $this->form_validation->set_rules('address_id', 'address_id', 'required');
        $this->form_validation->set_rules('crypto_type', 'crypto_type', 'required');
        $this->form_validation->set_rules('session_id', 'session_id', 'required');
        

        
        if ($this->form_validation->run() == FALSE) {
            // Handle validation errors
            $response['status'] = 'fail';
            $response['message'] = 'wallet_id,amount,address_id,crypto_type and session_id required';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }

        // Validate session_id (assuming it's coming from somewhere)

        $session_table = 'login_session';
        $session_condition = array('session_id' => $session_id);
        $checksession = $this->Operations->SearchByCondition($session_table, $session_condition);
        
        $user_wallet_id = $checksession[0]['wallet_id'];

        $user_details = $this->UserAccount($user_wallet_id);
        $user_credit =$user_details['total_credit'];
        $user_debit =$user_details['total_debit'];
        $user_balance =$user_details['total_balance'];
        $user_phone = $user_details['phone'];
        $user_wallet = $user_details['wallet_id'];
        
        $loggedtime = $checksession[0]['created_on'];
            
        $currentTime = $this->date;
        
        
        $loggedTimestamp = strtotime($loggedtime);
        $currentTimestamp = strtotime($currentTime);
        $timediff = $currentTimestamp - $loggedTimestamp;

        $timestamp = strtotime($loggedtime);

        $expirationTime = 10 * 60; // 5 minutes in seconds


          //get our sell rate
          $sellratecondition = array('exchange_type' => 2,'service_type'=>$crypto_type);
          $sellrate = $this->Operations->SearchByConditionBuy('exchange', $sellratecondition);
          
          $boughtsell = $sellrate[0]['bought_at'];

          $conversionRate = $sellrate[0]['kes'];

          $chargePercent = $sellrate[0]['charge'];

          $chargeFee = $sellrate[0]['fee'];

          $mycharge = ($boughtsell - $conversionRate);

          $chargeAmountUSDPercentage = round($amount * $chargePercent / 100,2);

          $USDchargeKES = ($chargeAmountUSDPercentage * $conversionRate);

          $DollarAfterCommissionCharge = ($amount - $chargeAmountUSDPercentage - $chargeFee);

          $DollarKESCharge = ($mycharge * $DollarAfterCommissionCharge);

          $Total_Kes_Charge = ($DollarKESCharge + $USDchargeKES);

          $Total_Usd_Charge = ($Total_Kes_Charge / $conversionRate);

          $creditAmountUSD = ($amount - $chargeAmountUSDPercentage - $chargeFee);

          $chargeAmountKES = (($creditAmountUSD * $mycharge) + $USDchargeKES);

          $chargeAmountUSD = round($chargeAmountKES / $conversionRate,2);

          $amountKes = round($creditAmountUSD * $conversionRate,2);

        

        if (empty($checksession) || $checksession[0]['session_id'] !== $session_id) {
            $response['status'] = 'error';
            $response['message'] = 'Invalid session_id or user not logged in';
            $response['data'] = null;
        
        }
        elseif ($timestamp <= $expirationTime) {
            # code...
            $response['status'] = 'error';
            $response['message'] = 'Invalid session expired';
            $response['data'] = null;
        }
        else if(($checksession[0]['wallet_id'] != $user_wallet))
        {
            $response['status'] = 'error';
            $response['message'] = 'Invalid wallet_id not same';
            $response['data'] = null;
            
        }
        else if ($amount < 10) {
            $response['status'] = 'error';
            $response['message'] = 'The amount must be greater than $10 USD.';
            $response['data'] = null;

        } 

        else
        {
            //success transact

            $table = 'crypto_requests';
            
             $transaction_id = $this->Operations->OTP(9);
            
            $receipt_no = $this->Operations->Generator(9);
            
 
            $data = array(
                'transaction_id'=>$transaction_id,
                'wallet_id'=>$user_wallet,
                'crypto_type'=>$crypto_type,
                'crypto_address'=>$address_id,
                'amount_usd'=>$creditAmountUSD,
                'amount_kes'=>$amountKes,
                'amount_deposited'=>$amountKes,
                'cr_dr'=>'cr',
                'rate'=>$conversionRate,
                'charge_percentage'=>$chargePercent,
                'charge_amount_kes'=>$Total_Kes_Charge,
                'charge_amount_usd'=>$Total_Usd_Charge,
                'charge_fee'=>$chargeFee,
                'status'=>0,
                'deposited'=>0,
                'bought_at'=>$boughtsell,
                'request_date'=>$this->date,
            );
            $save = $this->Operations->Create($table, $data);
            

            if($save === TRUE) {
                
                $message = 'Transaction ID: ' . $transaction_id . ', a withdrawal of ' . $amount . ' USD is currently being processed.';

                $stevephone = '0757259996';
                $albertphone = '0727010129';
                $samphone =     '0793601418';

                $sendadminsms0 = $this->Operations->sendSMS($samphone,$message);
                $sendadminsms1 = $this->Operations->sendSMS($stevephone,$message);
                $sendadminsms2 = $this->Operations->sendSMS($albertphone,$message);
                


                // //SEND USER APP NOTIFICATION 
                $sms = $this->Operations->sendSMS($user_phone, $message);
                

                $response['status'] = 'success';
                $response['message'] = $message;
                $response['data'] = null;
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Unable to process your request now try again';
                $response['data'] = null;
            }
        }
  



        echo json_encode($response);

    }


    public function app_audit()
    {
        $table = 'login_session';

        $get = $this->Operations->Search($table);

        $response['status'] = 'success';
        $response['message'] = 'Login reports';
        $response['data'] = $get;

        echo json_encode($response);
    }



    public function update_user_account()
    {
        $response = array();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;

            echo json_encode($response);
            exit();
        }

        // Fetch inputs using CodeIgniter's input class
        $user_id = $this->input->post('user_id');
        
        // Form validation
        $this->form_validation->set_rules('user_id', 'user_id', 'required');

        
        if ($this->form_validation->run() == FALSE) {
            // Handle validation errors
            $response['status'] = 'fail';
            $response['message'] = 'user_id required';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }
        else
        {
    
            $condition = array('id'=>$user_id);

            $userdetails = $this->Operations->SearchByCustomer($condition);
            $wallet_id = $userdetails[0]['wallet_id'];

            $account_details = $this->UserAccount($wallet_id);
            $user_credit =$account_details['total_credit'];
            $user_debit =$account_details['total_debit'];
            $user_balance =$account_details['total_balance'];
            $user_phone = $account_details['phone'];
            $user_wallet = $account_details['wallet_id'];
            $user_transactions = $account_details['transactions'];
            



            $response['status'] = 'success';
            $response['message'] = 'user information';
            $response['data'] = $userdetails;
            $response['transactions'] = $user_transactions;

        }

        echo json_encode($response);

    }


    public function get_user_account()
    {
        $response = array();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;

            echo json_encode($response);
            exit();
        }

        // Fetch inputs using CodeIgniter's input class
        $wallet_id = $this->input->post('wallet_id');
        
        // Form validation
        $this->form_validation->set_rules('wallet_id', 'wallet_id', 'required');

        
        if ($this->form_validation->run() == FALSE) {
            // Handle validation errors
            $response['status'] = 'fail';
            $response['message'] = 'wallet_id required';
            $response['data'] = null;
            echo json_encode($response);
            exit();
        }
        else
        {
 

            $account_details = $this->UserAccount($wallet_id);
            $response['status'] = 'success';
            $response['message'] = 'user information';
            $response['data'] = $account_details;


        }

        echo json_encode($response);

    }





  public function active_users()
  {
        $active = $this->Operations->ActiveUsers();
        $response['status'] = 'success';
        $response['message'] = 'active users';
        $response['data'] = $active;

        echo json_encode($response);
  }


  public function cypto_request()
  {
    $response = array();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = null;

            echo json_encode($response);
            exit();
        }

        // Fetch inputs using CodeIgniter's input class
        $crypto_type = $this->input->post('crypto_type');
        $request_type = $this->input->post('request_type');

        
        // Form validation
        $this->form_validation->set_rules('crypto_type', 'crypto_type', 'required');
        $this->form_validation->set_rules('request_type', 'request_type', 'required');


        
        if ($this->form_validation->run() == FALSE) {
            // Handle validation errors
            $response['status'] = 'fail';
            $response['message'] = 'crypto_type or request_type required';
            $response['data'] = null;
       
        }
        else
        {
    
            $condition = array('crypto_type' => $crypto_type, 'cr_dr' => $request_type);
            $table = 'crypto_requests';
            // $data = $this->Operations->SearchByCondition($table, $condition);

            $data = $this->Operations->SearchByConditionCrypto($table,$condition);

            foreach ($data as &$item) {
                $item['crypto_name'] = $this->getCryptoName($item['crypto_type']);
                $item['amount_ksh'] = $item['rate'] * $item['amount_usd'];
            }
            
            $response['status'] = 'success';
            $response['message'] = 'request information';
            $response['data'] = $data;

            

        }

        echo json_encode($response);
  }

  public function getCryptoName($crypto_type) 
  {
        switch ($crypto_type) {
            case '1':
                return 'Deriv';
            // case '2':
            //     return 'Binance';
            case '3':
                return 'Bitcoin';
            case '4':
                return 'Ethereum';
            case '5':
                return 'USDT(ERC 20)';
            case '6':
                return 'USDT(TRC 20)';
            case '7':
                return 'Skrill';
            case '8':
                return 'Neteller';
            case '9':
                return 'Service Test';
            case '10':
                return 'Stepakash P2P';
            case '11':
                return 'Binance';
            default:
                return 'Unknown';
        }
        
    }


    public function process_cypto_deposit()
    {

        $response = array();
	    
	    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response['status'] = 'error';
            $response['message'] = 'Invalid request method. Only POST requests are allowed.';
            $response['data'] = null;
            //exit(); 
        }
        else
        {
         $request_id = $this->input->post('request_id');
	    
	     if (empty($request_id)) {
	         
            $response['status'] = 'error';
            $response['message'] = 'Request ID is empty.';
            $response['data'] = null;
    
        }
        else
        {
            $table = 'crypto_requests';
	    
    	    $condition = array('id'=>$request_id);
    	    
    	    $search = $this->Operations->SearchByCondition($table,$condition);

            if($search)
            {

            $amount = $search[0]['amount_usd'];

            $txn_id = $search[0]['transaction_id'];
    	    
    	    $crypto_address = $search[0]['crypto_address'];
    	    
    	    $wallet_id= $search[0]['wallet_id'];
    	    
    	    $data = array('status'=>1,'deposited'=>$amount);
    	    
    	    $update = $this->Operations->UpdateData($table,$condition,$data);
    	    
    	    $condition1 = array('wallet_id'=>$wallet_id);
    	    
    	    $searchuser = $this->Operations->SearchByCondition('customers',$condition1);
    	    
    	    $mobile = $searchuser[0]['phone'];
    	    
    	    $phone = preg_replace('/^(?:\+?254|0)?/','254', $mobile);
    	    
    	    if($update === TRUE)
    	    {
    	        
    	        $message = ''.$txn_id.' processed, '.$amount.'USD has been successfully deposited to your account ';
                       
                //SEND USER APP NOTIFICATION 
                $sms = $this->Operations->sendSMS($phone, $message);
                 
                $response['status'] = 'success';
                $response['message'] = $message;
                $response['data'] = null;
       
    	    }
    	    else
    	    {
    	 
    	        $messo = 'Something went wrong';
    	        
    	        $response['status'] = 'error';
                $response['message'] = $messo;
                $response['data'] = null;
    	    }

            }
            else
            {
                $messo = 'Txn Not found,try again '.$request_id.'';
    	        
    	        $response['status'] = 'error';
                $response['message'] = $messo;
                $response['data'] = null;
            }
    	    
    	    
        } 
        }
	    
	 
	    echo json_encode($response);

    }
    public function process_cypto_withdraw()
    {

        $response = array();
	    
	    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response['status'] = 'error';
            $response['message'] = 'Invalid request method. Only POST requests are allowed.';
            $response['data'] = null;
            //exit(); 
        }
        else
        {
              $request_id = $this->input->post('request_id');
	    
	     if (empty($request_id)) {
	         
            $response['status'] = 'error';
            $response['message'] = 'Request ID is empty.';
            $response['data'] = null;
    
        }
        else
        {
            $table = 'crypto_requests';
	    
    	    $condition = array('id'=>$request_id);
    	    
    	    $search = $this->Operations->SearchByCondition($table,$condition);

            if($search)
            {
                
            $amount = $search[0]['amount_usd'];

            $amount_kes = $search[0]['amount_kes'];

            $rate = $search[0]['rate'];

            $txn_id = $search[0]['transaction_id'];
    	    
    	    $crypto_address = $search[0]['crypto_address'];

    	    $crypto_type = $search[0]['crypto_type'];

    	    $bought_rate = $search[0]['bought_at'];

    	    $chargePercent = $search[0]['charge_percentage'];

    	    $charge_amount_kes = $search[0]['charge_amount_kes'];

    	    $charge_amount_usd = $search[0]['charge_amount_usd'];

            $chargeAmt = $charge_amount_kes;

    	    
    	    $wallet_id= $search[0]['wallet_id'];
    	    
    	    $data = array('status'=>1,'deposited'=>$amount);
    	    
    	    $update = $this->Operations->UpdateData($table,$condition,$data);
    	    
    	    $condition1 = array('wallet_id'=>$wallet_id);
    	    
    	    $searchuser = $this->Operations->SearchByCondition('customers',$condition1);
    	    
    	    $mobile = $searchuser[0]['phone'];
    	    
    	    $phone = preg_replace('/^(?:\+?254|0)?/','254', $mobile);

            $transaction_id = $txn_id;
            $receipt_no = $this->Operations->Generator(8);
            $description = ' withdrawal';
            $paymethod = 'STEPAKASH';
            $receiverWalletID = $wallet_id;
            $trans_date = $this->date;
            $currency = 'KES';
            $amountToCredit = $amount_kes;
            $TotalamountToCredit = $amountToCredit;
            $transaction_number = $this->transaction_number;

            $crypt_type= '';

            if($crypto_type == 11)
            {
                $crypt_type = 11;
            }

            if($crypto_type == 8)
            {
                $crypt_type = 8;
            }
            elseif ($crypto_type == 7) {
                $crypt_type = 7;
            }
            elseif ($crypto_type == 6) {
                $crypt_type = 6;
            }elseif ($crypto_type == 5) {
                $crypt_type = 5;
            }elseif ($crypto_type == 4) {
                $crypt_type = 4;
            }elseif ($crypto_type == 3) {
                $crypt_type = 3;
            }


            $credit_to_account = $this->CreditToCryptoAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$receiverWalletID,$trans_date,$currency,$amountToCredit,$TotalamountToCredit,$chargeAmt,$crypt_type);
    	    
    	    if($update === TRUE && $credit_to_account === TRUE)
    	    {
    	        
                $message = '' . $transaction_id . ', request has been successfully processed. ' . $amount . ' USD has been credited to your account. Thank you for choosing our service!';

                       
                //SEND USER APP NOTIFICATION 
                $sms = $this->Operations->sendSMS($phone, $message);
                 
                $response['status'] = 'success';
                $response['message'] = $message;
                $response['data'] = null;
       
    	    }
    	    else
    	    {
    	 
    	        $messo = 'Something went wrong '.$credit_to_account.'';
    	        
    	        $response['status'] = 'error';
                $response['message'] = $messo;
                $response['data'] = null;
    	    }

            }
            else
            {
                $messo = 'Txn Not found,try again';
    	        
    	        $response['status'] = 'error';
                $response['message'] = $messo;
                $response['data'] = null;
            }
    	    
    	    
        } 
        }
	    
	 
	    echo json_encode($response);

    }


    public function reject_cypto_withdraw()
    {

        $response = array();
	    
	    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response['status'] = 'error';
            $response['message'] = 'Invalid request method. Only POST requests are allowed.';
            $response['data'] = null;
            //exit(); 
        }
        else
        {
              $request_id = $this->input->post('request_id');
	    
	     if (empty($request_id)) {
	         
            $response['status'] = 'error';
            $response['message'] = 'Request ID is empty.';
            $response['data'] = null;
    
        }
        else
        {
            $table = 'crypto_requests';
	    
    	    $condition = array('id'=>$request_id);
    	    
    	    $search = $this->Operations->SearchByCondition($table,$condition);

            if($search)
            {
                $amount = $search[0]['amount_usd'];

            $txn_id = $search[0]['transaction_id'];
    	    
    	    $crypto_address = $search[0]['crypto_address'];
    	    
    	    $wallet_id= $search[0]['wallet_id'];
    	    
    	    $data = array('status'=>2,'deposited'=>0);
    	    
    	    $update = $this->Operations->UpdateData($table,$condition,$data);
    	    
    	    $condition1 = array('wallet_id'=>$wallet_id);
    	    
    	    $searchuser = $this->Operations->SearchByCondition('customers',$condition1);
    	    
    	    $mobile = $searchuser[0]['phone'];
    	    
    	    $phone = preg_replace('/^(?:\+?254|0)?/','254', $mobile);
    	    
    	    if($update === TRUE)
    	    {
    	        $message = 'Successfully request rejected';
                 
                $response['status'] = 'success';
                $response['message'] = $message;
                $response['data'] = null;
       
    	    }
    	    else
    	    {
    	 
    	        $messo = 'Something went wrong';
    	        
    	        $response['status'] = 'error';
                $response['message'] = $messo;
                $response['data'] = null;
    	    }

            }
            else
            {
                $messo = 'Txn Not found,try again';
    	        
    	        $response['status'] = 'error';
                $response['message'] = $messo;
                $response['data'] = null;
            }
    	    
    	    
        } 
        }
	    
	 
	    echo json_encode($response);

    }


  
    public function query_receipt()
    {
        $response = array();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response['status'] = 'error';
            $response['message'] = 'Invalid request method. Only POST requests are allowed.';
            $response['data'] = null;
        } else {
            $request_id = $this->input->post('request_id');

            if (empty($request_id)) {
                $response['status'] = 'error';
                $response['message'] = 'Request ID is empty.';
                $response['data'] = null;
            } else {
                $table = 'customer_ledger';
                $condition = array('transaction_number' => $request_id);
                $search = $this->Operations->SearchByCondition($table, $condition);

                if ($search) {
           

                    $trans_detail = $this->mapTransactionDetails($search[0]);


                    $data['transaction_type'] = $trans_detail['transaction_type'];
                    $data['status_text'] = $trans_detail['status_text'];
                    $data['status_color'] = $trans_detail['status_color'];
                    $data['text_arrow'] = $trans_detail['text_arrow'];
                    $data['transaction_number'] = $search[0]['transaction_number'];
                    $data['pay_method'] = $search[0]['pay_method'];
                    $data['trans_id'] = $search[0]['trans_id'];  
                    $data['wallet_id'] = $search[0]['wallet_id'];
                    $data['cr_dr'] = $search[0]['cr_dr'];
                    $data['charge'] = $search[0]['charge']; 
                    $data['charge_percent'] = $search[0]['chargePercent'];
                    $data['currency'] = $search[0]['currency'];
                    $data['amount'] = $search[0]['amount'];
                    $data['total_amount'] = $search[0]['total_amount'];
                    $data['transaction_date'] = $search[0]['created_at'];
                    $data['transaction_type'] = $trans_detail['transaction_type'];
                    $data['status'] = $search[0]['status'];
                    $data['description'] = $search[0]['description'];


                    $response['status'] = 'success';
                    $response['message'] = 'receipt details';
                    $response['data'] = $data;

                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Receipt not found';
                    $response['data'] = null;
                }
            }
        }

        echo json_encode($response);
    }




    // Function to map transaction details
public function mapTransactionDetails($transaction)
{
    $statusData = $this->getStatusData($transaction['status']);
    $txnData = $this->getTransactionData($transaction['cr_dr'], $transaction['deriv']);

    return array_merge($transaction, $statusData, $txnData);
}

// Function to get status text, color, and transaction type data
public function getStatusData($status)
{
    switch ($status) {
        case '1':
            return array('status_text' => 'Completed');
        case '0':
            return array('status_text' => 'Incomplete');
        case '2':
            return array('status_text' => 'Pending');
        default:
            return array('status_text' => 'Unknown');
    }
}

// Function to get transaction type based on cr_dr and deriv
public function getTransactionData($cr_dr, $deriv)
{
    $text_arrow = ($cr_dr == 'cr') ? 'fa fa-arrow-up' : 'fa fa-arrow-down';
    $status_color = ($cr_dr == 'cr') ? 'success' : 'danger';

    switch ($cr_dr) {  
        case 'cr':
            switch ($deriv) {
                case 0:
                    return array('transaction_type' => 'Mpesa Deposit', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 1:
                    return array('transaction_type' => 'Deriv Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 2:
                    return array('transaction_type' => 'Mpesa Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 3:
                    return array('transaction_type' => 'Bitcoin Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 4:
                    return array('transaction_type' => 'Ethereum Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 5:
                    return array('transaction_type' => 'USDT (ERC-20) Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 6:
                    return array('transaction_type' => 'USDT (TRC-20) Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 7:
                    return array('transaction_type' => 'Skrill Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 8:
                    return array('transaction_type' => 'Neteller Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 10:
                    return array('transaction_type' => 'Stepakash p2p', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 11:
                    return array('transaction_type' => 'Binance Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);

                case 14:
                    return array('transaction_type' => 'Gifting', 'text_arrow' => 'fas fa-gift', 'status_color' => 'primary');
                default:
                    return array('transaction_type' => '', 'text_arrow' => '', 'status_color' => '');
            }
        case 'dr':
            switch ($deriv) {
                case 0:
                    return array('transaction_type' => 'Mpesa Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 1:
                    return array('transaction_type' => 'Deriv Funding', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 2:
                    return array('transaction_type' => 'Mpesa Withdraw', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 3:
                    return array('transaction_type' => 'Bitcoin Funding', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 4:
                    return array('transaction_type' => 'Ethereum Funding', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 5:
                    return array('transaction_type' => 'USDT (ERC-20) Funding', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 6:
                    return array('transaction_type' => 'USDT (TRC-20) Funding', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 7:
                    return array('transaction_type' => 'Skrill Funding', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 8:
                    return array('transaction_type' => 'Neteller Funding', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 10:
                    return array('transaction_type' => 'Stepakash p2p', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 11:
                    return array('transaction_type' => 'Binance Funding', 'text_arrow' => $text_arrow, 'status_color' => $status_color);
                case 14:
                    return array('transaction_type' => 'Gifting', 'text_arrow' => 'fas fa-gift', 'status_color' => 'primary');
                default:
                    return array('transaction_type' => '', 'text_arrow' => '', 'status_color' => '');
            }
        default:
            return array('transaction_type' => '', 'text_arrow' => '', 'status_color' => '');
    }
}



    public function testing()
    {
        // $currentReceipt = 'SAT9GAW3VA';

        // $currentReceipt = 'SK0001B';

        // Loop to generate the next 20 receipt numbers
        // for ($i = 0; $i < 1000000; $i++) {
        //     echo "Receipt Number $i: " . $currentReceipt . "\n";
        //     $currentReceipt = $this->increment_custom_receipt($currentReceipt);
        // }

        // $nextReceipt = $this->getNextReceipt($currentReceipt);

        // echo "Next Receipt: $nextReceipt";

        //get our sell rate
        $sellratecondition = array('exchange_type' => 2,'service_type'=>11);
        $sellrate = $this->Operations->SearchByConditionBuy('exchange', $sellratecondition);
        
        $boughtsell = $sellrate[0]['bought_at'];

        $conversionRate = $sellrate[0]['kes'];

        $chargePercent = $sellrate[0]['charge'];

        $chargeFee = $sellrate[0]['fee'];

        print_r($sellrate);

    }

    public function GenerateNextTransaction()
    {
        $last_id = $this->Operations->getLastTransactionId();
        // echo $last_id; 

        $nextReceipt = $this->getNextReceipt($last_id);
        return $nextReceipt;
    }


    public function increment_custom_receipt($current_receipt) {
        // Separate the letters, digits, and extra letter from the receipt number
        preg_match('/([A-Z]+)(\d+)([A-Z]*)/', $current_receipt, $matches);
        $letters = $matches[1];
        $digits = intval($matches[2]);
        $extra_letter = isset($matches[3]) ? $matches[3] : '';
    
        // Increment the extra letter if it exists, otherwise increment the digits part
        if (!empty($extra_letter)) {
            // Increment the extra letter
            $next_extra_letter = chr(ord($extra_letter) + 1);
    
            // If the extra letter rolls over to 'Z', reset it to 'A' and increment the digits part
            if ($next_extra_letter > 'Z') {
                $next_extra_letter = 'A';
                $next_digits = $digits + 1;
            } else {
                $next_digits = $digits;
            }
        } else {
            // If there is no extra letter, increment the digits part
            $next_extra_letter = 'A';
            $next_digits = $digits + 1;
        }
    
        // If the digits part rolls over to 100, adjust letters and reset digits to 1
        if ($next_digits == 100) {
            // Increment the last letter
            $letters_array = str_split($letters);
            $last_index = count($letters_array) - 1;
            $letters_array[$last_index] = chr(ord($letters_array[$last_index]) + 1);
    
            // Convert the array back to a string
            $next_letters = implode('', $letters_array);
    
            // Reset digits to 1
            $next_digits = 1;
        } else {
            $next_letters = $letters;
        }
    
        // Ensure that the digits part is formatted with leading zeros if necessary
        $next_digits_str = str_pad($next_digits, 2, '0', STR_PAD_LEFT);
    
        // Construct the next receipt number
        $next_receipt = $next_letters . $next_digits_str . $next_extra_letter;
    
        return $next_receipt;
    }

    public function getNextReceipt($currentReceipt) {
        // Separate the letters, digits, and extra letter from the receipt number
        preg_match('/([A-Z]+)(\d+)([A-Z]*)/', $currentReceipt, $matches);
        $letters = $matches[1];
        $digits = intval($matches[2]);
        $extraLetter = isset($matches[3]) ? $matches[3] : '';
    
        // Define the maximum number of digits and letters
        $maxDigits = 6; // Adjust as needed
        $maxLetters = 2; // Adjust as needed
    
        // Increment the extra letter if it exists, otherwise increment the digits part
        if (!empty($extraLetter)) {
            // Increment the extra letter
            $nextExtraLetter = chr(ord($extraLetter) + 1);
    
            // If the extra letter rolls over to 'Z', reset it to 'A' and increment the digits part
            if ($nextExtraLetter > 'Z') {
                $nextExtraLetter = 'A';
                $nextDigits = $digits + 1;
            } else {
                $nextDigits = $digits;
            }
        } else {
            // If there is no extra letter, increment the digits part
            $nextExtraLetter = 'A';
            $nextDigits = $digits + 1;
        }
    
        // If the digits part rolls over to the maximum, adjust letters and reset digits to 1
        if ($nextDigits > str_repeat('9', $maxDigits)) {
            // Increment the last letter
            $lettersArray = str_split($letters);
            $lastIndex = count($lettersArray) - 1;
            $lettersArray[$lastIndex] = chr(ord($lettersArray[$lastIndex]) + 1);
    
            // Convert the array back to a string
            $nextLetters = implode('', $lettersArray);
    
            // If all letters are exhausted, reset letters to 'A' and increment digits by 1
            if (strlen($nextLetters) > $maxLetters) {
                $nextLetters = 'A';
                $nextDigits = 1;
            }
        } else {
            $nextLetters = $letters;
        }
    
        // Ensure that the digits part is formatted with leading zeros if necessary
        $nextDigitsStr = str_pad($nextDigits, $maxDigits, '0', STR_PAD_LEFT);
    
        // Construct the next receipt number
        $nextReceipt = $nextLetters . $nextDigitsStr . $nextExtraLetter;
    
        return $nextReceipt;
    }



   
 
    public function auth_by_session()
	{
		$response = array();
    
        // Check if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
			echo json_encode($response);
           
            exit();
        }
		else 
		{
			$session_id = $this->input->post('session_id');
			// Validate form data
			$this->form_validation->set_rules('session_id', 'session_id', 'required');
			if ($this->form_validation->run() == FALSE) {
				// Handle validation errors
				$response['status'] = 'fail';
				$response['message'] = 'session_id required';
				$response['data'] = null;
			}
			else{
				$details = $this->Operations->auth_session($session_id,$this->date,$this->timeframe);

                if($details['data'] == 1)
                {
                    $response['status'] = 'success';
                    $response['message'] = 'authenticated';
                    $response['data'] = null;
                }
                elseif ($details['data'] == 0) {
                    # code...
				$response = $details;

                }
               

			}

		}

		echo json_encode($response);


		
	} 


    // GIFTING

    public function gift_request()
    {
        $response = array();
    

        $search = $this->Operations->Search_gift();

        $response['status'] = 'success';
        $response['message'] = 'gifts data';
        $response['data'] = $search;

        // Send JSON response
        echo json_encode($response);
    }



    public function send_gift()
    {
        $response = array();
    
        // Check if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
			echo json_encode($response);
           
            exit();
        }
		else 
		{
			$session_id = $this->input->post('session_id');
			$phone = str_replace(' ', '', trim($this->input->post('phone')));
            $amount = str_replace(' ', '', trim($this->input->post('amount')));
			$comment = $this->input->post('comment');


			// Validate form data
			$this->form_validation->set_rules('session_id', 'session_id', 'required');
			$this->form_validation->set_rules('phone', 'phone', 'required');
			$this->form_validation->set_rules('amount', 'amount', 'required');
			$this->form_validation->set_rules('comment', 'comment', 'required');


			if ($this->form_validation->run() == FALSE) {
				// Handle validation errors
				$response['status'] = 'error';
				$response['message'] = 'session id, amount, phone, comment fields required';
			}
			else{
				$details = $this->Operations->auth_session($session_id,$this->date,$this->timeframe);

                if($details['response'] == 1)
                {
                    //do other validations and get other settings
                    $gift_condition = array('service_id'=>3,'service_type'=>3);
                    $gift_settings = $this->Operations->SearchByCondition('service_commission',$gift_condition);

                    $send_min_amount = $gift_settings[0]['min_amount'];
                    $send_max_amount = $gift_settings[0]['max_amount'];
                    $send_charge_percent = $gift_settings[0]['charge_percent'];
                    $transaction_number = $this->transaction_number;
                    $phone = preg_replace('/^(?:\+?254|0)?/', '254', $phone);



                    $senderWalletID = $details['data']['wallet_id'];
                    $wallet_id = $senderWalletID;
                    $sender_phone = $details['data']['phone'];
                    $total_balance_kes = $details['data']['total_balance'];

                    $description = '"'.$comment.'" from '.$sender_phone.', you have received a gift Kash worth KSH '.$amount.'';

                    // $description = 'You have received gift from '.$sender_phone.', '.$comment;


                    $amount_to_debit = $amount;

                    $charge_percent = $send_charge_percent;

                    $charge_amount = ($amount * $charge_percent / 100);

                    $total_amount_to_debit = ($charge_amount + $amount);

                    $transfer_method = 'STEPAKASH';

                    $client_debit = '';
                    $partner_credit = '';

                        //success partner
                    if($amount < $send_min_amount)
                    {
                        $response['status'] = 'error';
                        $response['message'] = 'transaction failed, minimum amount '.$send_min_amount.'';
                    }
                    elseif ($amount > $send_max_amount) {
                        # code...
                        $response['status'] = 'error';
                        $response['message'] = 'transaction failed, maximum amount '.$send_max_amount.'';
                    }
                    else if (($total_balance_kes < $amount) && ($amount > $total_balance_kes) && ($total_balance_kes < $total_amount_to_debit) && ($total_amount_to_debit > $total_balance_kes)) {
                        $response['status'] = 'error';
                        $response['message'] = 'Insufficient funds to gift';
    
                    }
                    else
                    {
                        $receiverWalletID = $phone;

                        $gift_user = $this->gifting_b2c($transaction_number,$wallet_id,$receiverWalletID,$amount_to_debit,$charge_percent,$charge_amount,$total_amount_to_debit,$phone,$amount,$transfer_method);
                        // $response = $gift_user;
                        if ( $gift_user === TRUE) {
                                //notify user and partner
                            $message = ''.$transaction_number.', Successfully KES '.$amount.' gift sent to '.$phone.'';

                            $sendsms =  $this->Operations->sendSMS($sender_phone,$message);
                            $send_user_sms =  $this->Operations->sendSMS($phone,$description);
                            # code...
                            $response['status'] = 'success';
                            $response['message'] = $message;
                        }
                        else{
                            $response['status'] = 'error';
                            $response['message'] = 'Unable to gift now,try later';
                        }
                        
                    }
                       
                    
                }
                elseif ($details['response'] == 0) {
                    # code...
				$response = $details;

                }
                else
                {
				$response = $details;

                }
               

			}

		}

		echo json_encode($response);
    }


    public function gifting_b2c($transaction_number,$wallet_id,$receiverWalletID,$amount_to_debit,$charge_percent,$charge_amount,$total_amount_to_debit,$phone,$amount,$transfer_method)
	{
	    
        //chnage this parameters
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $mpesa_consumer_key = 'PGlrTUnTDcDIfPJRBGVv3LFLWUQPMmiI'; //put the live key from Daraja portal
        $mpesa_consumer_secret = 'S1C8ykQq3UQIGi3q'; //put the live cunsumer secret from Daraja Portal
        $InitiatorName = 'WEBSTEPAK'; //put the user created at the mpesa org portal. The user must be of API type
        $password = 'Stepakash@2024'; //put the password of the above user
        // $ResultURL = base_url().'index.php/b2c_result'; //put the result URL
        // $QueueTimeOutURL = base_url().'index.php/b2c_result'; //put the QueueTimeOutURL URL
        $ResultURL = 'https://stk.stepakash.com/gifting_b2c.php';
        $QueueTimeOutURL = 'https://stk.stepakash.com/gifting_b2c.php';
        //$Amount = 10; //input amount you want to send
        $PartyA = '4125347'; //input the pay bill bulk payments
        //$PartyB = '254793601418'; //input the phone number that will receive money eg. 254712345678
        $PartyB = $phone;
        
            
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $credentials = base64_encode($mpesa_consumer_key.':'.$mpesa_consumer_secret);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$credentials)); //setting a custom header
        curl_setopt($curl, CURLOPT_HEADER, false); //set false to allow json decode
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); //very important
        $curl_response = curl_exec($curl);
        $cred_password_raw = json_decode($curl_response, true); 
        $cred_password = $cred_password_raw['access_token']; 
        //setting security credentials
        $publicKey_path = 'cert.cer';
        $fp=fopen($publicKey_path,"r");
        $publicKey=fread($fp,8192);
        fclose($fp);
        $plaintext = $password; 
        
        openssl_public_encrypt($plaintext, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);
        
        $security_credential = base64_encode($encrypted); 
        
        
        $url = 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$cred_password)); //setting custom header
        
        $curl_post_data = array(
            //Fill in the request parameters with valid values
            'InitiatorName' => $InitiatorName,
            'SecurityCredential' => $security_credential,
            'CommandID' => 'SalaryPayment',
            'Amount' => $amount,
            'PartyA' => $PartyA,
            'PartyB' => $PartyB,
            'ResultURL' => $ResultURL,
            'QueueTimeOutURL' => $QueueTimeOutURL,
            'Remarks' => 'none applicable',
            'Occasion' => 'none applicable'
        );
        
        $data_string = json_encode($curl_post_data);
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        
        $curl_response = curl_exec($curl);
    
        $responseArray = json_decode($curl_response, true);
        $conversationID = $responseArray['ConversationID'];
        $originatorConversationID = $responseArray['OriginatorConversationID'];
        $responseCode = $responseArray['ResponseCode'];
        

        $results = $this->user_transfer_mpesa($transaction_number,$wallet_id,$receiverWalletID,$amount_to_debit,$charge_percent,$charge_amount,$total_amount_to_debit,$phone,$amount,$conversationID,$originatorConversationID,$responseCode,$transfer_method);
        return $results;  
        // return $responseArray;
        

    
	}
	

	public function user_transfer_mpesa($transaction_number,$wallet_id,$receiverWalletID,$amount_to_debit,$charge_percent,$charge_amount,$total_amount_to_debit,$phone,$amount,$conversationID,$originatorConversationID,$responseCode,$transfer_method) 
    {

    
  
        $response = array();
        $table = 'gifting';
        $transaction_id = $this->Operations->Generator(9);

        $data = array(
            'transaction_id' => $transaction_id,
            'transaction_number' => $transaction_number,
            'wallet_id' => $wallet_id,
            'phone' => $phone,
            'amount' => $amount,
            'conversationID' => $conversationID,
            'OriginatorConversationID' => $originatorConversationID,
            'ResponseCode' => $responseCode,
            'sent' => 0,
            'status' => 0,
            'transfer_method' => $transfer_method,
            'transfer_date' => $this->date,
        );

        $save = $this->Operations->Create($table, $data);

        if($save === TRUE)
        {
            $debit_account = $this->debit_send_gift($transaction_number,$wallet_id,$receiverWalletID,$amount_to_debit,$charge_percent,$charge_amount,$total_amount_to_debit);

        }

        // print_r($save);
        
        if($save === TRUE && $debit_account === TRUE)
        {

            
            $response = TRUE;
        }else
        {
            $response = FALSE;
        }
        
        return $response;


    }


    public function debit_send_gift($transaction_number,$senderWalletID,$receiverWalletID,$amountToDebit,$chargePercent,$chargeAmt,$TotalamountToDebit)
   {
       $senderTable = 'customer_ledger';
       $dr_cr = 'dr';
       $trans_id =  $this->Operations->Generator(8);
       $transaction_id =  $this->Operations->Generator(9);
       $system_trans_id =  $this->Operations->Generator(8);
       $paymethod = 'STEPAKASH';
       $created_at =  $this->date;
       $currency = 'KES';
       $conversionRate = 0;
       $description = 'Gifting';
       $partner_id = 1;
       $partner_earning = 0;
       
       
       $senderTransactionData = array(
                'transaction_id'	=>	$transaction_id,
                'transaction_number' => $transaction_number,
                'receipt_no'		=>	NULL,
                'description'		=>	$description,
                'pay_method' => $paymethod,
                'wallet_id' => $senderWalletID,
                'receiver_wallet_id'=>$receiverWalletID,
                'trans_id' => $trans_id,
                'paid_amount' => $TotalamountToDebit,
                'cr_dr'=>$dr_cr,
                'trans_date' => $created_at,
                'currency' => $currency,
                'amount' => ($amountToDebit),
                'rate' => $conversionRate,
                'deriv' => 14,
                'chargePercent' =>$chargePercent,
                'charge' =>$chargeAmt,
                'total_amount' =>$TotalamountToDebit,
                'status' => 1,
                'partner_id'=>$partner_id,
                'partner_earning'=>$partner_earning,
                'created_at' => $created_at,
            );
            $saveSenderTransaction = $this->Operations->Create($senderTable, $senderTransactionData);
            
            
            
            $system_ledger_data = array
                (
                    'transaction_id'	=>	$transaction_id,
                    'transaction_number' => $transaction_number,
                    'receipt_no'		=>	NULL,
                    'description'		=>	$description,
                    'pay_method' => $paymethod,
                    'wallet_id' => $senderWalletID,
                    'trans_id' => $system_trans_id,
                    'paid_amount' => $TotalamountToDebit,
                    'cr_dr'=>$dr_cr,
                    'trans_date' => $created_at, 
                    'currency' => $currency,
                    'deriv' => 14,
                    'amount' => ($amountToDebit),
                    'rate' => $conversionRate,
                    'chargePercent' =>$chargePercent,
                    'charge' =>$chargeAmt,
                    'total_amount' =>$TotalamountToDebit,
                    'status' => 1,
                    'created_at' => $created_at,
                );
        
                $save_system_ledger = $this->Operations->Create('system_ledger',$system_ledger_data);
                
                if($save_system_ledger === TRUE && $saveSenderTransaction === TRUE)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            
            
   }


    //PAY NOW BUTTON
    public function pay_now()
    {
        $response = array();
    
        // Check if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
			echo json_encode($response);
           
            exit();
        }
		else 
		{
			$session_id = $this->input->post('session_id');
			$partner_id = $this->input->post('partner_id');
			$amount = $this->input->post('amount');

			// Validate form data
			$this->form_validation->set_rules('session_id', 'session_id', 'required');
			$this->form_validation->set_rules('partner_id', 'partner_id', 'required');
			$this->form_validation->set_rules('amount', 'amount', 'required');

			if ($this->form_validation->run() == FALSE) {
				// Handle validation errors
				$response['status'] = 'fail';
				$response['message'] = 'all fields required';
			}
			else{
				$details = $this->Operations->auth_session($session_id,$this->date,$this->timeframe);

                if($details['response'] == 1)
                {
                    //do other validations
                    $validate_partner = $this->Operations->PartnerAccount($partner_id);
                    if($validate_partner['response'] === 1)
                    {
                        //success partner
                        if($amount < 1)
                        {
                            $response['status'] = 'error';
                            $response['message'] = 'transaction failed, minimum amount 1';
                        }
                        else
                        {
                            $transaction_number = $this->transaction_number;


                            $description = 'Instant Payment';

                            $senderWalletID = $details['data']['wallet_id'];
                            $sender_phone = $details['data']['phone'];
                            $total_balance_kes = $details['data']['total_balance'];

                            $amountToDebit = $amount;

                            $chargePercent = 0;

                            $chargeAmt = ($amount * $chargePercent);

                            $TotalamountToDebit = ($chargeAmt + $amount);

                            $partner_id = $validate_partner['data']['partner_id'];

                            $partner_earning = 0;

                            $to_account = 1;
                            $client_debit = '';
                            $partner_credit = '';


                            if (($total_balance_kes < $amount) && ($amount > $total_balance_kes)) {
                                $response['status'] = 'error';
                                $response['message'] = 'Insufficient funds in user wallet';
                                $response['data'] = null;
            
                            }
                            else
                            {
                                //debit from account
                                $client_debit = $this->debit_pay_now($transaction_number,$description,$senderWalletID,$amountToDebit,$chargePercent,$chargeAmt,$TotalamountToDebit,$partner_id,$partner_earning);
                                if($client_debit === TRUE)
                                {
                                    $partner_credit = $this->partner_credit_to_account($partner_id,$amount,$description,$to_account);

                                    if($partner_credit === TRUE)
                                    {
                                        if ($client_debit === TRUE && $partner_credit === TRUE) {
                                            //notify user and partner
                                            $message = ''.$transaction_number.', Successfully amount '.$amount.' debited from your account to account '.$partner_id.'';
            
                                            $sendsms =  $this->Operations->sendSMS($sender_phone,$message);
            
                                            # code...
                                            $response['status'] = 'success';
                                            $response['message'] = 'transaction successful';
                                        }
                                        else{
                                            $response['status'] = 'error';
                                            $response['message'] = 'Something went wrong';
                                        }

                                    }
                                    else
                                    {
                                        $response['status'] = 'error';
                                        $response['message'] = 'cannot credit to business account';
                                    }

                                }
                                else{
                                    $response['status'] = 'error';
                                    $response['message'] = 'cannot debit from client account';
                                }
                            }

                           
                           
                           

                            // $response['status'] = 'success';
                            // $response['partner_transaction_number'] = $this->partner_transaction_number;
                            // $response['transaction_number'] = $this->transaction_number;


                        }
                       
                    }
                    else
                    {
				        $response = $validate_partner;

                    }
                    
                }
                elseif ($details['response'] == 0) {
                    # code...
				$response = $details;

                }
                else
                {
				$response = $details;

                }
               

			}

		}

		echo json_encode($response);
    }


    public function partner_credit_to_account($partner_id,$amount,$description,$to_account)
    {

        $cr_dr = 'cr';

        $pay_method = 'STEPAKASH';
            
        $transaction_id =  $this->Operations->OTP(9);

        $partner_transaction_number = $this->partner_transaction_number;

        // $next_transaction = $this->getNextReceipt($partner_transaction_number);

        $partner_ledger_data = array(
            'transaction_id'	=>	$transaction_id,
            'transaction_number' => $partner_transaction_number,
            'receipt_no'		=>	$this->Operations->Generator(15),
            'description'		=>	$description,
            'pay_method' => $pay_method,
            'partner_id' => $partner_id,
            'trans_id' => $partner_transaction_number,
            'trans_amount' => $amount,
            'cr_dr'=>$cr_dr,
            'charge' =>0,
            'charge_percent' =>0,
            'currency' => 'KES',
            'amount' => $amount,
            'total_amount' =>$amount,
            'ledger_account'=>$to_account,
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
            'pay_method' => $pay_method,
            'partner_id' => $partner_id,
            'trans_id' => $partner_transaction_number,
            'trans_amount' => $amount,
            'cr_dr'=>$cr_dr,
            'charge' =>0,
            'charge_percent' =>0,
            'currency' => 'KES',
            'amount' => $amount,
            'total_amount' =>$amount,
            'ledger_account'=>$to_account,
            'status' => 1,
            'trans_date' => $this->date,
        );

        $save_partner_system_ledger = $this->Operations->Create('partner_system_ledger',$partner_system_ledger_data);

        if($save_partner_ledger === TRUE && $save_partner_system_ledger === TRUE)
        {
            return TRUE;
        }
        else{
            return FALSE;
        }

    }


    public function GeneratePartnerNextTransaction()
    {
        $last_id = $this->Operations->getLastPartnerTransactionId();
        // echo $last_id; 

        $nextReceipt = $this->getNextReceipt($last_id);
        return $nextReceipt;
    }

}
