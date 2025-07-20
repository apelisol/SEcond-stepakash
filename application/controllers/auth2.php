<?php

/**
 * Auth Controller
 * Handles authentication operations including login, registration, password reset, and JWT operations
 */
class Auth extends CI_Controller
{
    private $currentDateTime;
    private $date;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Operations');
        header('Content-Type: application/json');
        
        $this->currentDateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $this->date = $this->currentDateTime->format('Y-m-d H:i:s');
    }

    // ==========================================
    // VIEW METHODS
    // ==========================================

    public function index()
    {
        $this->load->view('login');
    }

    // ==========================================
    // AUTHENTICATION METHODS
    // ==========================================

    /**
     * Handle user login
     */
    public function Login()
    {
        $response = array();

        // Validation rules
        $this->form_validation->set_rules('phone', 'phone', 'required');
        $this->form_validation->set_rules('password', 'password', 'required');
        $this->form_validation->set_rules('ip_address', 'ip_address', 'required');

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            http_response_code(400);
            $response = $this->createResponse('fail', 'Only POST request allowed');
            echo json_encode($response);
            return;
        }

        if ($this->form_validation->run() == FALSE) {
            $response = $this->createResponse('fail', 'Phone, IP address or password required');
            echo json_encode($response);
            return;
        }

        // Process validated data
        $table = "customers";
        $phone = $this->formatPhoneNumber($this->input->post('phone'));
        $password = trim(str_replace(' ', '', $this->input->post('password')));
        $ip_address = $this->input->post('ip_address');

        if (empty($phone) || empty($password) || empty($ip_address)) {
            $response = $this->createResponse('fail', 'Phone and password are required');
            echo json_encode($response);
            return;
        }

        if ($this->Operations->resolve_user_login($phone, $password, $table)) {
            $user_id = $this->Operations->get_user_id_from_phone($phone, $table);
            $user = $this->Operations->get_user($user_id, $table);
            
            $session_id = $this->Operations->SaveLoginSession(
                $user->wallet_id, 
                $user->phone, 
                $ip_address, 
                $this->date
            );

            $data = array(
                'id' => $user->id,
                'wallet_id' => $user->wallet_id,
                'account_number' => $user->account_number,
                'phone' => $user->phone,
                'agent' => $user->agent,
                'session_id' => $session_id,
                'created_on' => $this->date,
            );

            $response = $this->createResponse('success', 'Login successful', $data);
        } else {
            $response = $this->createResponse('fail', 'You have entered an invalid phone number or password');
        }

        echo json_encode($response);
    }

    /**
     * Handle admin login
     */
    public function adminLogin()
    {
        $response = array();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            $response = $this->createResponse('fail', 'Only POST request allowed');
            echo json_encode($response);
            return;
        }

        $table = "users";
        $email = $this->input->post('email');
        $password = $this->input->post('password');

        if (empty($email)) {
            $response = $this->createResponse('fail', 'Email required');
        } elseif (empty($password)) {
            $response = $this->createResponse('fail', 'Password required');
        } else {
            if ($this->Operations->resolve_super_admin_login($email, $password, $table)) {
                $user_id = $this->Operations->get_admin_id_from_username($email, $table);
                $user = $this->Operations->get_user($user_id, $table);

                $data = array(
                    'id' => $user->id,
                    'names' => $user->names,
                    'phone' => $user->phone,
                    'email' => $user->email
                );

                $action = $user->phone . ' logged in the system at ' . $this->date;
                $this->Operations->RecordAction($action);

                $response = $this->createResponse('success', 'Successfully logged in. Welcome', $data);
            } else {
                $response = $this->createResponse('fail', 'Unauthorised credentials!');
            }
        }

        echo json_encode($response);
    }

    /**
     * Handle user logout
     */
    public function logout()
    {
        if (isset($_SESSION['wallet_id']) && $_SESSION['phone'] === true) {
            foreach ($_SESSION as $key => $value) {
                unset($_SESSION[$key]);
            }
            redirect(base_url());
        } else {
            foreach ($_SESSION as $key => $value) {
                unset($_SESSION[$key]);
            }
            redirect(base_url());
        }
    }

    // ==========================================
    // ACCOUNT MANAGEMENT METHODS
    // ==========================================

    /**
     * Create new user account
     */
    public function CreateAccount()
    {
        // Validation rules
        $this->form_validation->set_rules('phone', 'phone', 'required');
        $this->form_validation->set_rules('password', 'password', 'required');
        $this->form_validation->set_rules('confirmpassword', 'confirmpassword', 'required');

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            http_response_code(400);
            $response = $this->createResponse('fail', 'Only POST request allowed');
            echo json_encode($response);
            return;
        }

        if ($this->form_validation->run() == FALSE) {
            $response = $this->createResponse('fail', validation_errors());
            echo json_encode($response);
            return;
        }

        $table = 'customers';
        $phone = $this->formatPhoneNumber($this->input->post('phone'));
        $password = trim(str_replace(' ', '', $this->input->post('password')));
        $confirmpassword = trim(str_replace(' ', '', $this->input->post('confirmpassword')));
        $account_number = strtoupper(trim(str_replace(' ', '', $this->input->post('account_number'))));

        // Validation checks
        $validation_result = $this->validateAccountCreation($phone, $password, $confirmpassword, $table);
        if ($validation_result !== true) {
            echo json_encode($validation_result);
            return;
        }

        // Create account
        $last_id = $this->Operations->getLastWalletId();
        $wallet_id = $this->getNextWallet($last_id);

        $data = array(
            'phone' => $phone,
            'password' => $this->Operations->hash_password($password),
            'wallet_id' => $wallet_id,
            'account_number' => $account_number,
            'created_on' => $this->date,
        );

        if ($this->Operations->Create($table, $data)) {
            $message = 'Success account created, wallet ID ' . $wallet_id . 
                      ', use the following to login: phone : ' . $phone . 
                      ' and same password you created account with';
            
            $this->Operations->sendSMS($phone, $message);
            $response = $this->createResponse('success', 'Successfully account created login to start');
        } else {
            $response = $this->createResponse('fail', 'Unable to add now, try again');
        }

        echo json_encode($response);
    }

    // ==========================================
    // PASSWORD RESET METHODS
    // ==========================================

    /**
     * Send OTP for password reset
     */
    public function sendotp()
    {
        $response = array();
        $phone = $this->formatPhoneNumber($this->input->post('phone'));

        $this->form_validation->set_rules('phone', 'phone', 'required');

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            http_response_code(400);
            $response = $this->createResponse('fail', 'Only POST request allowed');
            echo json_encode($response);
            return;
        }

        if ($this->form_validation->run() == FALSE || empty($phone)) {
            $response = $this->createResponse('fail', 'Phone is required');
            echo json_encode($response);
            return;
        }

        $table = 'customers';
        $condition = array('phone' => $phone);
        $search = $this->Operations->SearchByCondition($table, $condition);

        if ($search) {
            $wallet_id = $search[0]['wallet_id'];
            $mobile = $search[0]['phone'];
            $otp = $this->Operations->OTP(6);

            $sessdata = array(
                'wallet_id' => $wallet_id,
                'phone' => $mobile,
                'otp' => $otp,
                'created_on' => $this->date,
            );

            $save = $this->Operations->Create('forgot_password', $sessdata);
            
            if ($save === TRUE) {
                $message = 'Your password reset request was successful. Use this OTP: ' . $otp . 
                          ' to verify your account. Complete the process within 5 minutes.';
                $this->Operations->sendSMS($mobile, $message);
                
                $response = $this->createResponse(
                    'success', 
                    'An OTP has been sent to ' . $mobile . '. Please enter it within the next 5 minutes.',
                    $sessdata
                );
            } else {
                $response = $this->createResponse('error', 'Something went wrong resetting password');
            }
        } else {
            $response = $this->createResponse('fail', 'Phone number not registered');
        }

        echo json_encode($response);
    }

    /**
     * Verify OTP for password reset
     */
    public function verifyOtp()
    {
        $response = array();
        $otp = trim(str_replace(' ', '', $this->input->post('otp')));

        $this->form_validation->set_rules('otp', 'otp', 'required');

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            http_response_code(400);
            $response = $this->createResponse('fail', 'Only POST request allowed');
            echo json_encode($response);
            return;
        }

        if ($this->form_validation->run() == FALSE || empty($otp)) {
            $response = $this->createResponse('fail', 'OTP is required');
            echo json_encode($response);
            return;
        }

        $table = 'forgot_password';
        $condition = array('otp' => $otp);
        $search = $this->Operations->SearchByCondition($table, $condition);

        if (!empty($search) && $search[0]['otp'] == $otp) {
            // Check if OTP is still valid (within 5 minutes)
            $timestamp = strtotime($search[0]['created_on']);
            $currentTimestamp = strtotime($this->date);
            $timeDifference = $currentTimestamp - $timestamp;
            $expirationTime = 5 * 60; // 5 minutes in seconds

            if ($timeDifference <= $expirationTime) {
                $sessdata = array(
                    'wallet_id' => $search[0]['wallet_id'],
                    'phone' => $search[0]['phone'],
                );

                $response = $this->createResponse('success', 'Phone verified, reset your password now', $sessdata);
            } else {
                $response = $this->createResponse('fail', 'Verification code has expired');
            }
        } else {
            $response = $this->createResponse('fail', 'Invalid Verification code');
        }

        echo json_encode($response);
    }

    /**
     * Update user password
     */
    public function updatepassword()
    {
        $response = array();

        // Validation rules
        $this->form_validation->set_rules('password', 'password', 'required');
        $this->form_validation->set_rules('confirmpassword', 'confirmpassword', 'required');
        $this->form_validation->set_rules('phone', 'phone', 'required');
        $this->form_validation->set_rules('wallet_id', 'wallet_id', 'required');

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            http_response_code(400);
            $response = $this->createResponse('fail', 'Only POST request allowed');
            echo json_encode($response);
            return;
        }

        if ($this->form_validation->run() == FALSE) {
            $response = $this->createResponse('fail', 'Phone, password, confirmpassword, and wallet_id are required');
            echo json_encode($response);
            return;
        }

        $table = 'customers';
        $pass1 = $this->input->post('password');
        $pass2 = $this->input->post('confirmpassword');
        $mobile = $this->input->post('phone');
        $wallet_id = $this->input->post('wallet_id');

        if ($pass1 != $pass2) {
            $response = $this->createResponse('fail', 'Passwords must match');
            echo json_encode($response);
            return;
        }

        $data = array('password' => $this->Operations->hash_password($pass2));
        $condition = array('wallet_id' => $wallet_id);

        if ($this->Operations->UpdateData($table, $condition, $data)) {
            $action = 'Updated account details';
            $this->Operations->RecordAction($action);
            
            $message = "Password updated. New Password: " . $pass2;
            $this->Operations->sendSMS($mobile, $message);

            $response = $this->createResponse('success', 'Password updated successfully');
        } else {
            $response = $this->createResponse('fail', 'Unable to update password, try again');
        }

        echo json_encode($response);
    }

    // ==========================================
    // JWT METHODS
    // ==========================================

    /**
     * Generate JWT token
     */
    public function generate_jwt($payload, $secret = 'secret')
    {
        $headers = array('alg' => 'HS256', 'typ' => 'JWT');
        $headers_encoded = $this->base64url_encode(json_encode($headers));
        $payload_encoded = $this->base64url_encode(json_encode($payload));
        
        $signature = hash_hmac('SHA256', "$headers_encoded.$payload_encoded", $secret, true);
        $signature_encoded = $this->base64url_encode($signature);
        
        return "$headers_encoded.$payload_encoded.$signature_encoded";
    }

    /**
     * Validate JWT token
     */
    public function ValidateToken()
    {
        $header = getallheaders();

        if ($header == "") {
            echo json_encode(array('message' => 'Access Denied'));
            return;
        }

        $authcode = trim($header['Authorization']);
        if ($authcode == "") {
            echo json_encode(array('message' => 'Authorization Token not Set'));
            die();
        }

        if ($authcode != "") {
            $token = substr($authcode, 7);
            $response = $this->ValidateJwt($token);
            print_r($response);
        }
    }

    /**
     * Validate JWT token structure and expiration
     */
    public function ValidateJwt($jwt, $secret = 'secret')
    {
        // Split the JWT
        $tokenParts = explode('.', $jwt);
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signature_provided = $tokenParts[2];

        // Check expiration time
        $expiration = json_decode($payload)->exp;
        $is_token_expired = ($expiration - time()) < 0;

        // Build signature based on header and payload using secret
        $base64_url_header = $this->base64url_encode($header);
        $base64_url_payload = $this->base64url_encode($payload);
        $signature = hash_hmac('SHA256', $base64_url_header . "." . $base64_url_payload, $secret, true);
        $base64_url_signature = $this->base64url_encode($signature);

        // Verify signature matches
        $is_signature_valid = ($base64_url_signature === $signature_provided);

        if ($is_token_expired || !$is_signature_valid) {
            return json_encode(array('message' => "Timeout"));
        } else {
            $decoded = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $jwt)[1]))));
            $arr = ['message' => 'Access Allowed', 'status' => 200, 'data' => $decoded];
            return json_encode($arr);
        }
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Get user IP address
     */
    public function getUserIP()
    {
        $ip = null;

        // Check if IP is from shared internet connection
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check if IP is from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Use remote address if available
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Handle multiple IP addresses
        if (strpos($ip, ',') !== false) {
            $ipList = explode(',', $ip);
            $ip = trim($ipList[0]);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip = '::ffff:' . $ip;
        }

        return $ip;
    }

    /**
     * Generate next wallet ID
     */
    public function getNextWallet($currentReceipt)
    {
        // Separate letters, digits, and extra letter from receipt number
        preg_match('/([A-Z]+)(\d+)([A-Z]*)/', $currentReceipt, $matches);
        $letters = $matches[1];
        $digits = intval($matches[2]);
        $extraLetter = isset($matches[3]) ? $matches[3] : '';

        // Define maximum number of digits and letters
        $maxDigits = 4;
        $maxLetters = 2;

        // Increment extra letter if exists, otherwise increment digits
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

        // Handle digit rollover
        if ($nextDigits > str_repeat('9', $maxDigits)) {
            $lettersArray = str_split($letters);
            $lastIndex = count($lettersArray) - 1;
            $lettersArray[$lastIndex] = chr(ord($lettersArray[$lastIndex]) + 1);
            $nextLetters = implode('', $lettersArray);

            if (strlen($nextLetters) > $maxLetters) {
                $nextLetters = 'A';
                $nextDigits = 1;
            }
        } else {
            $nextLetters = $letters;
        }

        // Format digits with leading zeros
        $nextDigitsStr = str_pad($nextDigits, $maxDigits, '0', STR_PAD_LEFT);

        return $nextLetters . $nextDigitsStr . $nextExtraLetter;
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Format phone number to Kenyan standard
     */
    private function formatPhoneNumber($phone)
    {
        $phone = str_replace(' ', '', $phone);
        return preg_replace('/^(?:\+?254|0)?/', '+254', $phone);
    }

    /**
     * Create standardized response array
     */
    private function createResponse($status, $message, $data = '')
    {
        return array(
            'status' => $status,
            'message' => $message,
            'data' => $data
        );
    }

    /**
     * Validate account creation data
     */
    private function validateAccountCreation($phone, $password, $confirmpassword, $table)
    {
        if (empty($phone)) {
            return $this->createResponse('fail', 'Phone required');
        }

        if (strlen($phone) !== 13 || substr($phone, 0, 4) !== '+254') {
            return $this->createResponse('fail', 'Invalid phone number format. Please use the format +2547xxxx');
        }

        // Check if phone already exists
        $p_id = $this->Operations->get_user_id_from_phone($phone, $table);
        $ph = $this->Operations->get_user($p_id, $table);
        
        if ($ph) {
            return $this->createResponse('fail', 'Phone number already exists');
        }

        // Check wallet ID uniqueness
        $last_id = $this->Operations->getLastWalletId();
        $wallet_id = $this->getNextWallet($last_id);
        $wallet_condition = array('wallet_id' => $wallet_id);
        $get_wallet = $this->Operations->SearchByCondition('customers', $wallet_condition);

        if ($get_wallet) {
            return $this->createResponse('fail', 'Account already exists');
        }

        if (empty($password)) {
            return $this->createResponse('fail', 'Password required');
        }

        if (empty($confirmpassword)) {
            return $this->createResponse('fail', 'Password confirm required');
        }

        if ($password != $confirmpassword) {
            return $this->createResponse('fail', 'Passwords must match');
        }

        return true;
    }

    /**
     * Validate email format
     */
    private function validateemail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Clean input data
     */
    private function clean_input($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    /**
     * Hash password using bcrypt
     */
    private function hash_password($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify password hash
     */
    private function verify_password_hash($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Base64 URL encode
     */
    private function base64url_encode($str)
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }
}