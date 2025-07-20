<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Business extends CI_Controller {

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


	public function create_merchant()
    {
        $response = array();
    
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = '';
        } else {
            $table = 'partners';
            $partner_id = $this->input->post('partner_id');
            $partner_name = $this->input->post('partner_name');
            $partner_email = $this->input->post('partner_email');
            $partner_phone = $this->input->post('partner_phone'); 
            $mobile = preg_replace('/^(?:\+?254|0)?/', '+254', $partner_phone);
            
             // Additional checks for existing phone and email
            $p_id = $this->Operations->get_partner_id_from_phone($mobile, $table);
            $ph = $this->Operations->get_user($p_id, $table);

            $email_id = $this->Operations->get_partner_id_from_email($partner_email, $table);
            $em = $this->Operations->get_user($email_id, $table);
    
            // Form validation for phone
			if (empty($partner_id)) {
                $response['status'] = 'error';
                $response['message'] = 'partner id is required';
                $response['data'] = '';
            } 
            elseif (empty($partner_phone)) {
                $response['status'] = 'error';
                $response['message'] = 'partner phone number is required';
                $response['data'] = '';
            }
             elseif (empty($partner_name)) {
                $response['status'] = 'error';
                $response['message'] = 'partner name is required';
                $response['data'] = '';
            }
            
             elseif (empty($partner_email)) {
                $response['status'] = 'error';
                $response['message'] = 'partner email is required';
                $response['data'] = '';
            }
            else {
                
                if ($ph) {
                $response['status'] = 'error';
                $response['message'] = 'Phone number already exists';
                $response['data'] = '';
                }
                elseif ($em) {
                    $response['status'] = 'error';
                    $response['message'] = 'email already exists';
                    $response['data'] = '';
                }else
                {
                    $data = array(
						'partner_id'=>$partner_id,
                        'partner_name' => $partner_name,
                        'partner_email' => $partner_email,
                        'partner_phone' => $partner_phone,
                        'partner_created_on' => $this->date, 
                    );
    
                    if ($this->Operations->Create($table, $data)) {
                        $subject = 'Account created';
                        $message = 'Success! Your account has been created. Use the following to login:  Phone: ' . $phone . ' and Password: ' . $confirmpassword . '';
    
                        // $sms = $this->Operations->sendSMS($mobile, $message);
    
                        $response['status'] = 'success';
                        $response['message'] = 'Successful account created. Login to start';
                        $response['data'] = '';
                    } else {
                        $response['status'] = 'error';
                        $response['message'] = 'Unable to add now, try again';
                        $response['data'] = '';
                    }
                 }
           
                
            }
        }
    
        // Send JSON response
        echo json_encode($response);
    }


	public function view_merchant()
    {
        $response = array();

        $search = $this->Operations->Search('partners');

    
        if (!empty($search)) {
            $response['status'] = 'success';
            $response['message'] = 'System users retrieved successfully';
            $response['data'] = $search;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'partners not found';
            $response['data'] = null;
        }
    
        echo json_encode($response);
    }

	public function partner_auth()
	{
		
	}





	


}
