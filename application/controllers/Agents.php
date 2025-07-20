<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Agents extends CI_Controller {

	private $transaction_id;

    private $transaction_number;
    
    private $currentDateTime;
    
    private $date;
    
    private $timeframe;
    
    public function __construct()
    {
        
        parent::__construct();
        $this->load->model('Operations');
            
        $this->currentDateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        
         $this->date  = $this->currentDateTime->format('Y-m-d H:i:s');
         
         $this->timeframe = 600;

		 $transaction_number =  $this->GenerateNextTransaction();
		 $this->transaction_number = $transaction_number;
            
       header('Content-Type: application/json');
       header("Access-Control-Allow-Origin: * ");
       header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
       header('Access-Control-Allow-Headers: Content-Type');
       header('Access-Control-Max-Age: 86400');
    }

	

	public function agents_auth()
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
				$response = $details;

			}

		}

		echo json_encode($response);


		
	}





   public function set_agent_commission()
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

			$service_id = $this->input->post('service_id');
			$service_type = $this->input->post('service_type');
			$min_amount = $this->input->post('min_amount');
			$max_amount = $this->input->post('max_amount');
			$charge_percent = $this->input->post('charge_percent');
			$agent_commission = $this->input->post('agent_commission');


		
			// Validate form data
			$this->form_validation->set_rules('service_id', 'service_id', 'required');
			$this->form_validation->set_rules('min_amount', 'min_amount', 'required');
			$this->form_validation->set_rules('max_amount', 'max_amount', 'required');
			$this->form_validation->set_rules('charge_percent', 'charge_percent', 'required');
			$this->form_validation->set_rules('agent_commission', 'agent_commission', 'required');


		
			if ($this->form_validation->run() == FALSE) {
				// Handle validation errors
				$response['status'] = 'fail';
				$response['message'] = 'all fields required';
				$response['data'] = null;
			} 
			else
			{
				$table = 'service_commission';

				$condition = array(
				'service_id'=>$service_id,
				'service_type' => $service_type,

				);
				$search = $this->Operations->SearchByCondition($table,$condition);
				if ($search) {
					$service_condition = array('commission_id' => $search[0]['commission_id']);
					$updatedata = array(
						'service_type' => $service_type,
						'min_amount' => $min_amount,
						'max_amount' => $max_amount,
						'charge_percent' => $charge_percent,
						'agent_commission'=>$agent_commission,
						'created_on'=>$this->date,

					);
					$update = $this->Operations->UpdateData($table, $service_condition, $updatedata);

					if ($update) {
						$response['status'] = 'success';
						$response['message'] = 'saved successfully';
						$response['data'] = null;
					} else {
						$response['status'] = 'error';
						$response['message'] = 'uable to save now,try later';
						$response['data'] = null;
					}
				}
				else
				{
					$data = array(
						'service_id' => $service_id,
						'service_type' => $service_type,
						'min_amount' => $min_amount,
						'max_amount' => $max_amount,
						'charge_percent' => $charge_percent,
						'agent_commission'=>$agent_commission,
						'created_on'=>$this->date,
					);
		
					$save = $this->Operations->Create($table, $data);
	
					if ($save) {
						$response['status'] = 'success';
						$response['message'] = 'saved successfully';
						$response['data'] = null;
					} else {
						$response['status'] = 'error';
						$response['message'] = 'uable to save now,try later';
						$response['data'] = null;
					}
				}

				

			}

		}

		echo json_encode($response);

	}


	private function getServiceType($service_id, $transaction_type)
	{
		$service_type_string = ""; // Default value

		// Map service_type based on the service_id
		switch ($service_id) {
			case 1:
				$service_type_string = "Mpesa";
				break;
			case 2:
				$service_type_string = "Agency";
				break;

			case 3:
				$service_type_string = "Gifting";
				break;

			// Add more cases for other service types as needed
		}

		// Determine exchange type based on transaction_type
		// $exchange_type_info = ($transaction_type == 1) ? "Deposit" : "Withdraw";
		if ($transaction_type == 1) {
			$exchange_type_info = "Deposit";
		} elseif ($transaction_type == 2) {
			$exchange_type_info = "Withdraw";
		} elseif ($transaction_type == 3) {
			$exchange_type_info = "Sending";
		} else {
			$exchange_type_info = "Others"; // Default case if none of the above
		}

		// Append exchange type info to the service type string
		$service_type_string .= " $exchange_type_info";

		return $service_type_string;
	}

	public function view_service_commission()
	{
		$response = array();

		$segments = $this->uri->rsegment_array();
		// Get the last segment
		$lastSegment = end($segments);

		$search = $this->Operations->Search('service_commission');

		$data['title'] = $lastSegment;

		if (!empty($search)) {
			$response['status'] = 'success';
			$response['message'] = 'success';
			$response['data'] = array();

			foreach ($search as $rate) {
				// Get service_type from the new function
				$service_type = $this->getServiceType($rate['service_id'], $rate['service_type']);

				// Add service_type to the rate data
				$rate['service_type'] = $service_type;

				$response['data'][] = $rate;
			}
		} else {
			$response['status'] = 'error';
			$response['message'] = 'data not found';
			$response['data'] = null;
		}

		// Send JSON response
		echo json_encode($response);
	}


	 //MAKING WITHDRAWAL TO AN AGENT
	 public function withdraw_to_agent()
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
			$agent_Wallet = $this->input->post('agent_wallet');
			// $sender_wallet = $this->input->post('sender_wallet');
			$amount = $this->input->post('amount');



			// Validate form data
			$this->form_validation->set_rules('session_id', 'session_id', 'required');
			$this->form_validation->set_rules('agent_wallet', 'agent_wallet', 'required');
			// $this->form_validation->set_rules('sender_wallet', 'sender_wallet', 'required');
			$this->form_validation->set_rules('amount', 'amount', 'required');


			if ($this->form_validation->run() == FALSE) {
				// Handle validation errors
				$response['status'] = 'fail';
				$response['message'] = 'all fields required';
				$response['data'] = null;
			}
			else{
				$details = $this->Operations->auth_session($session_id,$this->date,$this->timeframe);

                if($details['response'] != 1)
                {
					$response = $details;
                }
                else {

					$service = 2;
					$type = 2;

					$agents = $this->getServiceLimits($service,$type);
					$service_id = $agents['service_id'];
					$service_type = $agents['service_type'];
					$min_amount = $agents['min_amount'];
					$max_amount = $agents['max_amount'];
					$charge_percent = $agents['charge_percent'];
					$agent_commission = $agents['agent_commission'];



					$agent_details= $this->Operations->AgentsAccount($agent_Wallet);
					$receiver_credit =$agent_details['total_credit'];
					$receiver_debit =$agent_details['total_debit'];
					$receiver_balance =$agent_details['total_balance'];
					$receiver_phone = $agent_details['phone'];
					$receiver_wallet = $agent_details['wallet_id'];
					$recipientWalletID = $receiver_wallet ;


					$origin_wallet = $details['data']['wallet_id'];

					$sender_details = $this->Operations->UserAccount($origin_wallet);
					$sender_credit =$sender_details['total_credit'];
					$sender_debit =$sender_details['total_debit'];
					$sender_balance =$sender_details['total_balance'];
					$sender_first_balance =$sender_details['total_balance'];


					$sender_phone = $sender_details['phone'];
					$sender_wallet = $sender_details['wallet_id'];


					$sender_charges = ($amount * $charge_percent) / 100;
					$sender_charges = round($sender_charges,2);
					$total_sender_amount = $sender_charges + $amount;

					$agent_earnings = ($sender_charges * $agent_commission) / 100;
					$agent_earnings = round($agent_earnings,2);

	
					// Calculate sender and agent balances after transaction
					$sender_balance = $sender_details['total_balance'] - ($amount + $sender_charges);
					$sender_balance = round($sender_balance,2);

					$receiver_balance = $agent_details['total_balance'] + ($agent_earnings);
					$receiver_balance = round($receiver_balance,2);
					

					if (!is_numeric($amount)) {
						$response['status'] = 'error';
						$response['message'] = 'amount should be numeric';
						$response['data'] = null;
					}
					else if($agent_details['agent'] !=1)
					{ 
						$response = $agent_details;
					}
					else if($sender_details['user'] != 1)
					{
						$response = $sender_details;
					}
					else if($agent_details['wallet_id'] == $sender_details['wallet_id'])
					{
						$response['status'] = 'error';
						$response['message'] = 'cannot complete transaction to same Wallet ID';
						$response['data'] = null;
						
					}
			
					else if ($amount < $min_amount) {
            
						$response['status'] = 'error';
						$response['message'] = 'Minimum withdraw amount is '.$min_amount.'';
						$response['data'] = null;
			  
					}
					else if ($amount > $max_amount) {
            
						$response['status'] = 'error';
						$response['message'] = 'Max withdraw amount is '.$max_amount.'';
						$response['data'] = null;
			  
					}
					 else if($sender_first_balance < $total_sender_amount) {
						$response['status'] = 'error';
						$response['message'] = 'Insufficient funds in your wallet to withdraw';
						$response['data'] = null;
			
					} 
					else if ($sender_details['user'] == 1 && $agent_details['agent'] == 1) {
						# code...
						 // Calculate charges for the sender and commission for the agent
						 


						 $transaction_number = $this->transaction_number;

						$next_transaction_number = $this->getNextReceipt($transaction_number);


						 $messo = ''.$transaction_number.' Succesfully KES '.$amount.' withdrawn to Agent ID '.$recipientWalletID . ' on '.$this->date.'.';
      
                    
						$senderMessage = ''.$transaction_number.' successfully ' . $amount . ' KES withdrawn to Agent ID ' . $recipientWalletID . ' on '.$this->date.'';
						$recipientMessage = ''.$next_transaction_number.' received ' . $amount . ' KES from wallet ID ' . $sender_wallet . ' on '.$this->date.'';

			
						
						$cr_dr = 'cr';
						$dr_cr = 'dr';
						$paymethod = 'STEPAKASH';
						$description = 'Withdraw to agent: '.$recipientWalletID.'';
						$transaction_id = $this->Operations->OTP(9);

						$receipt_no = $this->Operations->Generator(15); 
						$trans_id = $this->Operations->Generator(8);
						$trans_date = $this->date;
						$currency = 'KES';
						$conversionRate = 0;
						$amountToCredit = $agent_earnings;
						$TotalamountToCredit = $agent_earnings;
						
						$TotalamountToDebit = $total_sender_amount;
						$amountToDebit = $total_sender_amount;

						
						
						
						$saveSenderTransaction = $this->DebitP2PToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$sender_wallet,$trans_date,$currency,$amountToDebit,
						$conversionRate,$charge_percent,$sender_charges,$TotalamountToDebit);
						
						$saveRecipientTransaction = $this->CreditP2PToAccount($transaction_id,$next_transaction_number,$receipt_no,$description,$paymethod,$recipientWalletID,
						$trans_date,$currency,$amountToCredit,$TotalamountToCredit);


						
						if ($saveRecipientTransaction === TRUE && $saveSenderTransaction === TRUE ) {
                  
                    
                   
							//SEND USER APP NOTIFICATION 
							 $sendersms = $this->Operations->sendSMS($sender_phone, $senderMessage);
							 $recieversms = $this->Operations->sendSMS($receiver_phone, $recipientMessage);
							 
							 $response['status'] = 'success'; 
							 $response['message'] = $messo;
							 $response['sender_balance'] = $sender_balance;
							 $response['receiver_balance'] = $receiver_balance;
							 $response['sender_charges'] = $sender_charges;
							 $response['agent_earnings'] = $agent_earnings;

						} else {
							$response['status'] = 'error';
							 $response['message'] = 'Unable to process your  request now, please try again.';
							 $response['data'] = null;
						}


		 
						 // Response data
						
					}
					else
					{
						$response['status'] = 'error';
						$response['message'] = 'something went wrong';
					}

	
				
                }
               

			}

		}

		echo json_encode($response);
		// print_r($sender_details);

 
	 }


	 public function DebitP2PToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$senderWalletID,$trans_date,$currency,$amountToDebit,
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
	 
	 public function CreditP2PToAccount($transaction_id,$transaction_number,$receipt_no,$description,$paymethod,$receiverWalletID,
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


	 public function getServiceLimits($service_id,$service_type)
	 {
		$table = 'service_commission';
		$condition = array(
			'service_id'=>$service_id,
			'service_type'=>$service_type,
		);
		$details = $this->Operations->SearchByCondition($table,$condition);
		return $details[0];
	 }


	 public function GenerateNextTransaction()
	 {
		 $last_id = $this->Operations->getLastTransactionId();
		 // echo $last_id; 
 
		 $nextReceipt = $this->getNextReceipt($last_id);
		 return $nextReceipt;
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





	


}
