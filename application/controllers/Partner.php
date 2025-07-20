<?php



class Partner extends CI_Controller

{

    private $currentDateTime;

    private $date;

    private $timeframe;

    private $partner_transaction_number;

    private $transaction_number;


	public function __construct()

    {

        

        parent::__construct();

        $this->load->model('Operations');

         header('Content-Type: application/json');


        $this->currentDateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));

    
         $this->date  = $this->currentDateTime->format('Y-m-d H:i:s');


         $this->timeframe = 6000;

         $partner_transaction_number =  $this->GeneratePartnerNextTransaction();

         $this->partner_transaction_number = $partner_transaction_number;


         $transaction_number =  $this->GenerateNextTransaction();
         $this->transaction_number = $transaction_number;

    }

    public function partner_auth()
    {

        $response = array();

    

        $this->form_validation->set_rules('email', 'email', 'required');

        $this->form_validation->set_rules('password', 'password', 'required');

        $this->form_validation->set_rules('ip_address', 'ip_address', 'required');

        $this->form_validation->set_rules('mac_address', 'mac_address', 'required');


        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if ($this->form_validation->run() == FALSE) {

                // Handle validation errors

                $response['status'] = 'fail';

                $response['message'] = 'all fields required to login';

            } else {

                // Process the validated data

                $table = "users";

                $email = $this->input->post('email');
                $email = $this->clean_email($email);

                $password = $this->input->post('password');
                $password = str_replace(' ', '', $password); // Remove spaces from

                $ip_address = $this->input->post('ip_address');

                $mac_address = $this->input->post('mac_address');


                if (empty($email)) {

                    // Handle specific error cases

                    $response['status'] = 'fail';
                    $response['message'] = 'email required'; 

                } 
                elseif (empty($password)) {
                    # code...
                    $response['status'] = 'fail';
                    $response['message'] = 'password required'; 

                }
                // elseif (empty($partner_id)) {
                //     # code...
                //     $response['status'] = 'fail';
                //     $response['message'] = 'partner_id required'; 

                // }
                else {

                    if ($this->Operations->resolve_admin_login($email, $password, $table)) {

                        $user_id = $this->Operations->get_user_id_from_email($email, $table);

                        $user = $this->Operations->get_user($user_id, $table);

                        $id = $user->id;

                        $names = $user->names;

                        $email = $user->$email;

                        $phone = $user->phone;

                        $partner_id = $user->partner_id;

                        $user_type = $user->user_type;

                        $role_type = $user->role_type;

                        $created_on = $user->created_on;

                        $audit_time = $this->date;

                        $session_id = $this->Operations->SaveAdminLoginSession($partner_id,$phone,$id,$audit_time);

                        $action = 'logged in the system';
                        $save_audit = $this->Operations->SaveSystemAudit($partner_id,$id,$ip_address,$mac_address,$action,$audit_time);

                        

                        $partner_data = 'partners';

                        $partner_condition = array('partner_id'=>$partner_id);

                        $partner_details = $this->Operations->SearchByCondition($partner_data,$partner_condition);

                        $partner_name = $partner_details[0]['partner_name'];
                        $partner_phone = $partner_details[0]['partner_phone'];
                        $partner_email = $partner_details[0]['partner_email'];
                        $new_partner_id = $partner_details[0]['partner_id'];



                        if(!empty($new_partner_id))
                        {
                           http_response_code(200); // Bad Request
                           $data = array(
                                'user_id' => $id,
                                'partner_id' => $partner_id,
                                'partner_name' => $partner_name,
                                'partner_phone' => $partner_phone,
                                'partner_email' => $partner_email,

                                'user_name' => $names,
                                'user_phone' => $phone,
                                'user_email' => $email,
                                'user_type' => $user_type,
                                'role_type' => $role_type,


                                'token'=>$session_id,
                                'created_on'=>$this->date,
                            );

                            $response['status'] = 'success';

                            $response['message'] = 'successful login';

                            $response['data'] = $data;
                        }
                        else
                        {
                            http_response_code(401); // Bad Request
                            $response['status'] = 'fail';
                            $response['message'] = 'authentication fail, unauthorized user '.$new_partner_id.''; 
                        }

                        
                        

                    } else {
                        http_response_code(401); // Bad Request

                        $response['status'] = 'fail';

                        $response['message'] = 'authentication fail';

                    }

                }

            }

        } else {

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only POST request allowed';

        }

    

        echo json_encode($response);

    }


    public function generate_token()
    {

        $response = array();

    

        $this->form_validation->set_rules('email', 'email', 'required');

        $this->form_validation->set_rules('password', 'password', 'required');

        // $this->form_validation->set_rules('partner_id', 'partner_id', 'required');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if ($this->form_validation->run() == FALSE) {

                // Handle validation errors

                $response['status'] = 'fail';

                $response['message'] = 'email or password required';

            } else {

                // Process the validated data

                $table = "users";

                $email = $this->input->post('email');
                $email = $this->clean_email($email);

                $password = $this->input->post('password');
                $password = str_replace(' ', '', $password); // Remove spaces from

                // $partner_id = $this->input->post('partner_id');

                if (empty($email)) {

                    // Handle specific error cases

                    $response['status'] = 'fail';
                    $response['message'] = 'email required'; 

                } 
                elseif (empty($password)) {
                    # code...
                    $response['status'] = 'fail';
                    $response['message'] = 'password required'; 

                }
                // elseif (empty($partner_id)) {
                //     # code...
                //     $response['status'] = 'fail';
                //     $response['message'] = 'partner_id required'; 

                // }
                else {

                    if ($this->Operations->resolve_admin_login($email, $password, $table)) {

                        $user_id = $this->Operations->get_user_id_from_email($email, $table);

                        $user = $this->Operations->get_user($user_id, $table);

                        $id = $user->id;

                        $names = $user->names;

                        $email = $user->$email;

                        $phone = $user->phone;

                        $partner_id = $user->partner_id;

                        $user_type = $user->user_type;

                        $role_type = $user->role_type;

                        $created_on = $user->created_on;

                        $session_id = $this->Operations->SaveAdminLoginSession($partner_id,$phone,$id,$this->date);

                        $partner_data = 'partners';

                        $partner_condition = array('partner_id'=>$partner_id);

                        $partner_details = $this->Operations->SearchByCondition($partner_data,$partner_condition);

                        $partner_name = $partner_details[0]['partner_name'];
                        $partner_phone = $partner_details[0]['partner_phone'];
                        $partner_email = $partner_details[0]['partner_email'];
                        $new_partner_id = $partner_details[0]['partner_id'];



                        if(!empty($new_partner_id) || $user_type == 2 || $user_type == 3 || $user_type == 4 || $user_type == 5 )
                        {
                           http_response_code(200); // Bad Request
                           $data = array(
                                'user_id' => $id,
                                'partner_id' => $partner_id,
                                'partner_name' => $partner_name,
                                'partner_phone' => $partner_phone,
                                'partner_email' => $partner_email,

                                'user_name' => $names,
                                'user_phone' => $phone,
                                'user_email' => $email,
                                'user_type' => $user_type,
                                'role_type' => $role_type,

                                'token'=>$session_id,
                                'created_on'=>$this->date,
                            );

                            $response['status'] = 'success';

                            $response['message'] = 'successful login';

                            $response['data'] = $data;
                        }
                        else
                        {
                            http_response_code(401); // Bad Request
                            $response['status'] = 'fail';
                            $response['message'] = 'authentication fail, unauthorized user'; 
                        }

                        
                        

                    } else {
                        http_response_code(401); // Bad Request

                        $response['status'] = 'fail';

                        $response['message'] = 'authentication fail';

                    }

                }

            }

        } else {

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only POST request allowed';

        }

    

        echo json_encode($response);

    }


    public function auth()
    {
        $response = array(); // Initialize response variable

        // Check if Authorization header is present
        $authorizationHeader = $this->input->get_request_header('Authorization', TRUE);

        if ($authorizationHeader) {
            // Extract token from Authorization header
            $token = str_replace('Bearer ', '', $authorizationHeader);
            $validate = $this->validate_token($token);
            $status = $validate['status'];
       
            // Validate token (you may want to implement this logic)
            if ($validate['status'] == 'success') {
                // Token is valid, continue with the request
                // Your logic to fetch users goes here
                http_response_code(200);
                $response['status'] = 'success';
                $response['message'] = 'token is valid';
                $response['data'] = $validate['data'];

            } else {
                // Token is invalid, return 401 Unauthorized
                http_response_code(401);
                $response = $validate;
        
            }
        } else {
            // Authorization header is missing, return 401 Unauthorized
            http_response_code(401);
            
            $response['status'] = 'fail';
            $response['message'] = 'Authorization header required';
        }

        // Output response
               return json_encode($response);

    }


    public function user_auth()
    {
        $response = array();

        $auth_response = $this->auth();
    
        // Decode the JSON response into an associative array
        $auth_data = json_decode($auth_response, true);
        // echo $auth_data['status'];
        echo $auth_response;
    }


    public function validate_token($token)
    {
        $currentTime = strtotime($this->date);
        $timeframe = $this->timeframe;
        $response = $this->Operations->partner_auth($token,$currentTime,$timeframe);
        return $response;

    }

    private function clean_email($email) {
        // Remove leading and trailing whitespace
        $clean_email = trim($email);
    
        // Validate email address format
        if (filter_var($clean_email, FILTER_VALIDATE_EMAIL)) {
            // Optionally, remove special characters
            $clean_email = filter_var($clean_email, FILTER_SANITIZE_EMAIL);
        } else {
            // Invalid email address format
            $clean_email = null;
        }
    
        return $clean_email;
    }


    public function dashboard_data()
    {
        $response = array();

        $auth_response = $this->auth();



        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $info = $this->PartnerAccount($partner_id);                          
            
                        $total_cr = $this->Operations->total_partner_cr($partner_id);
                        $total_cr = number_format($total_cr, 2, '.', ',');

                        $total_dr = $this->Operations->total_partner_dr($partner_id);
                        $total_dr = number_format($total_dr, 2, '.', ',');

                        $total_earning = $this->Operations->total_partner_earning($partner_id);
                        $total_earning = number_format($total_earning, 2, '.', ',');

                        $partner_earning_chart = $this->Operations->get_partner_earning_chart($partner_id);
                        $partner_customers_chart = $this->Operations->get_partner_customers_chart($partner_id);
                        $partner_financial_chart = $this->Operations->get_partner_financial_chart($partner_id);
                        $partner_total_customer = $this->Operations->get_partners_customers($partner_id);

                        $data = array(
                            'partner_id' => $info['partner_id'],
                            'partner_phone' => $info['partner_phone'],
                            'created_on' => $info['created_on'],
                            'total_credit_reserved' => $info['total_credit_reserved'],
                            'total_debit_reserved' => $info['total_debit_reserved'],
                            'total_balance_reserved' => $info['total_balance_reserved'],
                            'total_credit_active' => $info['total_credit_active'],
                            'total_debit_active' => $info['total_debit_active'],
                            'total_balance_active' => $info['total_balance_active'],
                            'total_credit_uncleared' => $info['total_credit_uncleared'],
                            'total_debit_uncleared' => $info['total_debit_uncleared'],
                            'total_balance_uncleared' => $info['total_balance_uncleared'],
                            'total_cr'=>$total_cr,
                            'total_dr'=>$total_dr,
                            'total_earning'=>$total_earning,
                            'partner_earning_chart'=>json_encode($partner_earning_chart),
                            'partner_customers_chart'=>json_encode($partner_customers_chart),
                            'partner_financial_chart'=>json_encode($partner_financial_chart),
                            'partner_total_customer'=>$partner_total_customer,

                        );

              

                        $response['status'] = 'success';
                        $response['message'] = 'account information';
                        $response['data'] = $data;
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }



        echo json_encode($response);
    }

    
    public function create_user()
    {
       
        $response = array();

        $auth_response = $this->auth();
        $auth_data = json_decode($auth_response, true);
        
    
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = '';
        } else {
            if($auth_data['status'] == 'success')
            {
                $table = 'users';
                $names = $this->input->post('names');
                $email = $this->input->post('email');
                $phone = $this->input->post('phone');
                $partner_id = $this->input->post('partner_id');
                $user_type = $this->input->post('user_type');
                $password = $this->input->post('password');
                $confirmpassword = $this->input->post('confirm_password');
                $mobile = preg_replace('/^(?:\+?254|0)?/', '+254', $phone);
                
                // Additional checks for existing phone and email
                $p_id = $this->Operations->get_user_id_from_phone($mobile, $table);
                $ph = $this->Operations->get_user($p_id, $table);

                $email_id = $this->Operations->get_user_id_from_email($email, $table);
                $em = $this->Operations->get_user($email_id, $table);

                //validate partner _id
                $partner_condition = array('partner_id'=>$partner_id);
                $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                
               
                // Form validation for phone
                if (empty($phone)) {
                    $response['status'] = 'error';
                    $response['message'] = 'phone required';
                    
                }
                elseif (empty($names)) {
                    $response['status'] = 'error';
                    $response['message'] = 'name required';
                    
                }
                
                elseif (empty($email)) {
                    $response['status'] = 'error';
                    $response['message'] = 'email required';
                    
                }
                elseif (empty($partner_id)) {
                    $response['status'] = 'error';
                    $response['message'] = 'partner_id required';
                    
                }
                elseif(empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] == NULL || $validate_partner[0]['partner_id'] == '')
                {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner id not valid';
                }
                
                elseif (empty($user_type)) {
                    $response['status'] = 'error';
                    $response['message'] = 'user_type required';
                
                }
                elseif (empty($password)) {
                    $response['status'] = 'error';
                    $response['message'] = 'Password required';
                    
                } elseif ($ph) {
                    $response['status'] = 'error';
                    $response['message'] = 'Phone number already exists';
                    
                } 
                elseif ($em) {
                    $response['status'] = 'error';
                    $response['message'] = 'email already exists';
                    
                } 
                elseif (empty($confirmpassword)) {
                    $response['status'] = 'error';
                    $response['message'] = 'Confirm password required';
                    
                }
                elseif ($password != $confirmpassword) {
                    $response['status'] = 'error';
                    $response['message'] = 'Passwords must match';
                    
                }
                
                else {
                    
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
                            $response['message'] = 'Successful user account created';
                        } else {
                            $response['status'] = 'error';
                            $response['message'] = 'Unable to add now, try again';
                        }
                 
                }
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }
            
        }

    
        // Send JSON response
        echo json_encode($response);
    
    }

    public function view_users()
    {
        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');
        $response = array();


        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id required';
     
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $table = 'users';
                        $users_condition = array('partner_id'=>$partner_id);
                        $get_users = $this->Operations->SearchByCondition($table,$users_condition);
                        $users = [];
                        $role_type = '';

                        foreach ($get_users as $key) {
                            # code...
                            $get['user_id'] = $key['id'];
                            $get['user_name'] = $key['names'];
                            $get['user_email'] = $key['email'];
                            $get['user_phone'] = $key['phone'];
                            $get['user_type'] = $key['user_type'];
                            $get['role_type'] = $key['role_type'];
                            if($key['user_type'] == 2)
                            {
                            $role_type = 'Administrator';

                            }
                            elseif($key['user_type'] == 3)
                            {
                            $role_type = 'Business Manager';

                            }
                            elseif($key['user_type'] == 4)
                            {
                            $role_type = 'Business Auditor';

                            }
                            elseif($key['user_type'] == 5)
                            {
                            $role_type = 'API user';

                            }
                            $get['role_name'] = $role_type;
                            $get['partner_id'] = $key['partner_id'];
                            $get['created_on'] = $key['created_on'];
                            $users[] = $get;
                        }
                        $response['status'] = 'success';
                        $response['message'] = 'users';
                        $response['data'] = $users;
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }

    public function top_up_account()
    {
       
        $response = array();

        $auth_response = $this->auth();
        $auth_data = json_decode($auth_response, true);
        
    
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = '';
        } else {
            if($auth_data['status'] == 'success')
            {
                $partner_id = $this->input->post('partner_id');
                $top_up_method = $this->input->post('top_up_method');
                $amount = $this->input->post('amount');
                $account_number = $this->input->post('account_number');
                $description = $this->input->post('description');
            


                //validate partner _id
                $partner_condition = array('partner_id'=>$partner_id);
                $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                
               
                // Form validation for phone
                if (empty($partner_id)) {
                    $response['status'] = 'error';
                    $response['message'] = 'partner_id required';
                    
                }
                elseif(empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] == NULL || $validate_partner[0]['partner_id'] == '')
                {
                    $response['status'] = 'error';
                    $response['message'] = 'partner id not valid';
                }
                
                elseif (empty($top_up_method)) {
                    $response['status'] = 'error';
                    $response['message'] = 'top_up_method required';
                
                }
                elseif (empty($amount)) {
                    $response['status'] = 'error';
                    $response['message'] = 'amount required';
                    
                }
                elseif (empty($account_number)) {
                    $response['status'] = 'error';
                    $response['message'] = 'account_number required';
                    
                }
                elseif (empty($description)) {
                    $response['status'] = 'error';
                    $response['message'] = 'description required';
                    
                }
                else {
                    if($top_up_method == 5)
                    {
                        $mpesa_deposit = $this->mpesa_deposit($amount,$account_number,$partner_id);
                    }

                    
                    $response = $mpesa_deposit;
                 
                }
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }
            
        }

    
        // Send JSON response
        echo json_encode($response);
    
    }


    public function mpesa_deposit($amount,$mobile,$partner_id)
    {
        $phone = preg_replace('/^(?:\+?254|0)?/','254', $mobile);

        $mpesa_consumer_key = "WGEE5jgXQowJ56mld9g3GKG15AtqUMPj";
        $mpesa_consumer_secret = "0gtwpYJd57dFdh9b";
        $mpesapass = "386ab37a45b861a7813ce7d412c4db1ce2c552dd8872505cfedfd64a94036a55"; // mpesa key
        $shortcode = "4124755"; // shortcode to pay to e.g., paybill/store number/head office no.
        $systemUrl = 'https://stk.stepakash.com/partners_response.php';
        $identifierType = "CustomerPayBillOnline";
        $invoice_number = "STEPAKASH PARTNERS ACCOUNT";

        $feedback = $this->check_transaction_api(
            $partner_id,
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

        // Handle the feedback as needed
        return $feedback;
    }


    function check_transaction_api($partner_id,$mpesa_consumer_key,$mpesa_consumer_secret,
            $mpesapass,$amount,$phone_no,$shortcode,$systemUrl,$identifierType,$invoice_number)
    {
        //set system time to Nairobi
        //date_default_timezone_set("Africa/Nairobi");
        $timestamp = date("Ymdhis");
        //set pass
        $password = base64_encode($shortcode.$mpesapass.$timestamp);
        
        $curl = curl_init();
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        curl_setopt($curl, CURLOPT_URL, $url);
        $credentials = base64_encode($mpesa_consumer_key.':'.$mpesa_consumer_secret);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$credentials)); //setting a custom header
        curl_setopt($curl, CURLOPT_HEADER, false); //set false to allow json decode
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); //very important
        $curl_response = curl_exec($curl);
        $cred_password_raw = json_decode($curl_response, true); 
        $cred_password = $cred_password_raw['access_token']; 
        
        
        $url = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$cred_password)); //setting custom header
        
        $curl_post_data = array(
          //Fill in the request parameters with valid values
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
        //echo json_decode($curl_response); /*avoid json_decoding twice */
        $resultStatus_raw = json_decode($curl_response, true); ;
        $resultStatus = $resultStatus_raw['ResponseCode'];
        $MerchantRequestID = $resultStatus_raw['MerchantRequestID'];
        $CheckoutRequestID = $resultStatus_raw['CheckoutRequestID'];


        
        if ($resultStatus === "0"){

            
            $mobile = preg_replace('/^(?:\+?254|0)?/','+254', $phone_no);

            $saving = $this->save_mpesa_deposit($partner_id,$mobile,$amount,$MerchantRequestID,$CheckoutRequestID);
            // return $saving;
            $response = $saving;


            
        }
        else{
            $error_on = "error#";
            $error = $error_on.$resultStatus_raw['errorMessage'];
            // return $error;
            $response['status'] = 'error';
            $response['message'] = $error;
        }

        return $response;
    }
    
    

    public function save_mpesa_deposit($partner_id,$phone_no,$amount,$MerchantRequestID,$CheckoutRequestID)
    {
        
 
        $date = $this->date;
        $data = array
        (
            'partner_id'=>$partner_id,
            'phone'=>$phone_no,
            'amount'=>$amount,
            'MerchantRequestID'=>$MerchantRequestID,
            'CheckoutRequestID'=>$CheckoutRequestID,
            'status'=>0, 
            'created_on'=>$date,
        );
            
    
        $save = $this->Operations->Create('partner_mpesa_deposit',$data);
        
        $message = '';
        $response = array();
        
        if($save === TRUE)
        {
            $message = 'Success transaction initiated';
            $response['status'] = 'success';
            $response['message'] = $message;


        }
        else
        {
             $message = 'Something went wrong,contact support';
             $response['status'] = 'error';
             $response['message'] = $message;
        }
         return $response;



    }


    public function move_funds()
    {
       
        $response = array();

        $auth_response = $this->auth();
        $auth_data = json_decode($auth_response, true);
        
    
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = '';
        } else {
            if($auth_data['status'] == 'success')
            {
                $partner_id = $this->input->post('partner_id');
                $from_account = $this->input->post('from_account');
                $to_account = $this->input->post('to_account');
                $amount = $this->input->post('amount');
                $description = $this->input->post('description');
            


                //validate partner _id
                $partner_condition = array('partner_id'=>$partner_id);
                $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);


                $partner_details = $this->PartnerAccount($partner_id);
                $total_credit_reserved = $partner_details['total_credit_reserved'];
                $total_debit_reserved = $partner_details['total_debit_reserved'];
                $total_balance_reserved = $partner_details['total_balance_reserved'];


                $total_credit_active = $partner_details['total_credit_active'];
                $total_debit_active = $partner_details['total_debit_active'];
                $total_balance_active = $partner_details['total_balance_active'];
                
               
                // Form validation for phone
                if (empty($partner_id)) {
                    $response['status'] = 'error';
                    $response['message'] = 'partner_id required';
                    
                }
                elseif(empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] == NULL || $validate_partner[0]['partner_id'] == '')
                {
                    $response['status'] = 'fail';
                    $response['message'] = 'partner id not valid';
                }
                
                elseif (empty($from_account)) {
                    $response['status'] = 'error';
                    $response['message'] = 'debiting account required';
                
                }
                elseif ($from_account !=1) {
                    $response['status'] = 'error';
                    $response['message'] = 'debiting account must be from reserved account';
                
                }
                elseif (empty($to_account)) {
                    $response['status'] = 'error';
                    $response['message'] = 'crediting account required';
                
                }
                elseif (empty($amount)) {
                    $response['status'] = 'error';
                    $response['message'] = 'amount required';
                    
                }
                elseif ($amount < 10) {
                    $response['status'] = 'error';
                    $response['message'] = 'min amount KES 10';
                    
                }

                elseif ($amount > $total_balance_reserved) {
                    $response['status'] = 'error';
                    $response['message'] = 'insufficient reserved funds to transact';
                    
                }        
                elseif (empty($description)) {
                    $response['status'] = 'error';
                    $response['message'] = 'description required';
                    
                }
                else {

                    $debit_account = $this->partner_debit_from_account($partner_id,$amount,$description,$from_account);
                    
                    $credit_account = $this->partner_credit_to_account($partner_id,$amount,$description,$to_account);
                   


                    if($debit_account === TRUE && $credit_account === TRUE)
                    {
                        $response['status'] = 'success';
                        $response['message'] = 'success funds moved to float account, approval awaiting';
                        
                    }
                    else
                    {
                        $response['status'] = 'error';
                        $response['message'] = 'unable to move funds now, try later';


                    }

                }
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }
            
        }

    
        // Send JSON response
        echo json_encode($response);
    
    }


    public function transfer_funds()
    {
       
        $response = array();

        $auth_response = $this->auth();
        $auth_data = json_decode($auth_response, true);
        
    
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = '';
        } else {
            if($auth_data['status'] == 'success')
            {
    
                $partner_id = $this->input->post('partner_id');
                $from_account = $this->input->post('from_account');
                $to_account = $this->input->post('to_account');
                $account_number = $this->input->post('account_number');

                $amount = $this->input->post('amount');
                $description = $this->input->post('description');
            


                //validate partner _id
                $partner_condition = array('partner_id'=>$partner_id);
                $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);


                $partner_details = $this->PartnerAccount($partner_id);
                $total_credit_reserved = $partner_details['total_credit_reserved'];
                $total_debit_reserved = $partner_details['total_debit_reserved'];
                $total_balance_reserved = $partner_details['total_balance_reserved'];


                $total_credit_active = $partner_details['total_credit_active'];
                $total_debit_active = $partner_details['total_debit_active'];
                $total_balance_active = $partner_details['total_balance_active'];

                $transaction_id = $this->Operations->Generator(8); 

                
               
                // Form validation for phone
                if (empty($partner_id)) {
                    $response['status'] = 'error';
                    $response['message'] = 'partner_id required';
                    
                }
                elseif(empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] == NULL || $validate_partner[0]['partner_id'] == '')
                {
                    $response['status'] = 'fail';
                    $response['message'] = 'partner id not valid';
                }
                
                elseif (empty($from_account)) {
                    $response['status'] = 'error';
                    $response['message'] = 'debiting account required';
                
                }
                elseif ($from_account !=2) {
                    $response['status'] = 'error';
                    $response['message'] = 'debiting account must be from float account';
                
                }
                elseif (empty($to_account)) {
                    $response['status'] = 'error';
                    $response['message'] = 'crediting account required';
                
                }
                elseif (empty($account_number)) {
                    $response['status'] = 'error';
                    $response['message'] = 'account number required';
                
                }
                elseif (empty($amount)) {
                    $response['status'] = 'error';
                    $response['message'] = 'amount required';
                    
                }
                elseif ($amount < 10) {
                    $response['status'] = 'error';
                    $response['message'] = 'minimum amount is KES 10';
                    
                }

                elseif ($amount > $total_balance_active) {
                    $response['status'] = 'error';
                    $response['message'] = 'insufficient float balance to transact';
                    
                }        
                // elseif (empty($description)) {
                //     $response['status'] = 'error';
                //     $response['message'] = 'description required';
                    
                // }
                else {

                    $transaction_number = $this->transaction_number;
                    $description = 'Funds Transfer';
                    $receiver_wallet = $account_number;
                    $amount_to_transfer = $amount;

                    $charge_percent = 0;

                    $charge_amount = ($amount * $charge_percent);

                    $total_to_transfer = ($charge_amount + $amount);

                    $partner_earning = 0;

                    if($to_account == 5)
                    {
                        $phone = str_replace(' ', '', $account_number);
                        $phone = preg_replace('/^(?:\+?254|0)?/','254', $phone);
                        $debit_account = $this->b2c($transaction_id,$partner_id,$phone,$amount,$to_account,$from_account);

                        if($debit_account === TRUE)
                        {
                            $response['status'] = 'success';
                            $response['message'] = 'success funds transfered to '.$account_number.' account';
                            
                        }
                        else
                        {
                            $response['status'] = 'error';
                            $response['message'] = 'unable to move funds now, try later';
    
    
                        }

                    }
                    elseif ($to_account == 7 || $to_account == 8) {
                        //validate if account to receive is valid
                        $receiver_details = $this->Operations->AgentsAccount($account_number);
                        if($receiver_details['response'] == 0)
                        {
                            $response['status'] = 'error';
                            $response['message'] = 'receiver account not found';
                        }
                        elseif ($receiver_details['response'] == 1) {
                            # code...
                            $debit_from_partner = $this->partner_debit_from_account($partner_id,$amount,$description,$from_account);
                            if($debit_from_partner === TRUE)
                            {
                                $credit_debit_account = $this->partner_credit_to_wallet($transaction_number,$description,$receiver_wallet,$amount_to_transfer,$charge_percent,$charge_amount,$total_to_transfer,$partner_id,$partner_earning);
                    
                                if($credit_debit_account === TRUE && $debit_from_partner === TRUE )
                                {
                                    $response['status'] = 'success';
                                    $response['message'] = 'success funds transfered to '.$account_number.' account';
                                }
                                else
                                {
                                    $response['status'] = 'error';
                                $response['message'] = 'unable to transact now, try later';
                                }
                            }
                            else
                            {
                                $response['status'] = 'error';
                                $response['message'] = 'unable to transfer funds now, try later';
                            }
                        }
                        else
                        {
                            $response = $receiver_details;
                        }
                        # code...
                        
                        
                    }

               


                 

                }
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }
            
        }

    
        // Send JSON response
        echo json_encode($response);
    
    }


    public function b2c($transaction_id,$partner_id,$account_number,$Amount,$transfer_method,$from_account)
	{
	    
        //chnage this parameters
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $mpesa_consumer_key = 'PGlrTUnTDcDIfPJRBGVv3LFLWUQPMmiI'; //put the live key from Daraja portal
        $mpesa_consumer_secret = 'S1C8ykQq3UQIGi3q'; //put the live cunsumer secret from Daraja Portal
        $InitiatorName = 'WEBSTEPAK'; //put the user created at the mpesa org portal. The user must be of API type
        $password = 'Stepakash@2023'; //put the password of the above user
        // $ResultURL = base_url().'index.php/b2c_result'; //put the result URL
        // $QueueTimeOutURL = base_url().'index.php/b2c_result'; //put the QueueTimeOutURL URL
        $ResultURL = 'https://stk.stepakash.com/partner_b2c.php';
        $QueueTimeOutURL = 'https://stk.stepakash.com/partner_b2c.php';
        //$Amount = 10; //input amount you want to send
        $PartyA = '4125347'; //input the pay bill bulk payments
        //$PartyB = '254793601418'; //input the phone number that will receive money eg. 254712345678
        $PartyB = $account_number;
        
            
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
            'Amount' => $Amount,
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
        

        $results = $this->partner_transfer_mpesa($transaction_id,$partner_id,$account_number,$Amount,$conversationID,$originatorConversationID,$responseCode,$transfer_method,$from_account);
        return $results;  
        


    
	}
	

	public function partner_transfer_mpesa($transaction_id,$partner_id,$account_number,$amount,$conversationID,$originatorConversationID,$ResponseCode,$transfer_method,$from_account) 
    {

        $partner_transaction_number = $this->partner_transaction_number;
  
        $response = array();
        $table = 'partner_transfer_funds';

        $data = array(
            'transaction_id' => $transaction_id,
            'transaction_number' => $partner_transaction_number,
            'partner_id' => $partner_id,
            'account_number' => $account_number,
            'amount' => $amount,
            'conversationID' => $conversationID,
            'OriginatorConversationID' => $originatorConversationID,
            'ResponseCode' => $ResponseCode,
            'withdraw' => 0,
            'status' => 0,
            'transfer_method' => $transfer_method,
            'transfer_date' => $this->date,
        );

        $save = $this->Operations->Create($table, $data);

        $description = 'ITP';
    
        $debit_account = $this->partner_debit_from_account($partner_id,$amount,$description,$from_account);


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


    public function partner_debit_from_account($partner_id,$amount,$description,$from_account)
    {

        $cr_dr = 'dr';

        $pay_method = 'STEPAKASH';
            
        $transaction_id =  $this->Operations->OTP(9);

        $partner_transaction_number = $this->partner_transaction_number;


        // $description = 'funding float account';

        $partner_ledger_data = array(
            'transaction_id'	=>	$transaction_id,
            'transaction_number' => $partner_transaction_number,
            'receipt_no'		=>	NULL,
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
            'ledger_account'=>$from_account,
            'status' => 1,
            'trans_date' => $this->date,
        );
        $save_partner_ledger = $this->Operations->Create('partner_ledger',$partner_ledger_data);

        $partner_system_ledger_data = array
        (
            'transaction_id'	=>	$transaction_id,
            'transaction_number' => $partner_transaction_number,
            'receipt_no'		=>	NULL,
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
            'ledger_account'=>$from_account,
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


    public function partner_credit_to_account($partner_id,$amount,$description,$to_account)
    {

        $cr_dr = 'cr';

        $pay_method = 'STEPAKASH';
            
        $transaction_id =  $this->Operations->OTP(9);

        $partner_transaction_number = $this->partner_transaction_number;

        $next_transaction = $this->getNextReceipt($partner_transaction_number);


        // $description = 'funding float account';

        $partner_ledger_data = array(
            'transaction_id'	=>	$transaction_id,
            'transaction_number' => $next_transaction,
            'receipt_no'		=>	$this->Operations->Generator(15),
            'description'		=>	$description,
            'pay_method' => $pay_method,
            'partner_id' => $partner_id,
            'trans_id' => $next_transaction,
            'trans_amount' => $amount,
            'cr_dr'=>$cr_dr,
            'charge' =>0,
            'charge_percent' =>0,
            'currency' => 'KES',
            'amount' => $amount,
            'total_amount' =>$amount,
            'ledger_account'=>$to_account,
            'status' => 0,
            'trans_date' => $this->date,
        );
        $save_partner_ledger = $this->Operations->Create('partner_ledger',$partner_ledger_data);

        $partner_system_ledger_data = array
        (
            'transaction_id'	=>	$transaction_id,
            'transaction_number' => $next_transaction,
            'receipt_no'		=>	$this->Operations->Generator(15),
            'description'		=>	$description,
            'pay_method' => $pay_method,
            'partner_id' => $partner_id,
            'trans_id' => $next_transaction,
            'trans_amount' => $amount,
            'cr_dr'=>$cr_dr,
            'charge' =>0,
            'charge_percent' =>0,
            'currency' => 'KES',
            'amount' => $amount,
            'total_amount' =>$amount,
            'ledger_account'=>$to_account,
            'status' => 0,
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


    public function partner_credit_to_wallet($transaction_number,$description,$senderWalletID,$amountToDebit,$chargePercent,$chargeAmt,$TotalamountToDebit,$partner_id,$partner_earning)
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

    public function test()
    {
        $partner_id ='SKP0001';
        $info = $this->PartnerAccount($partner_id);
        print_r($info);
    }

 
    public function get_partner_account()
    {

        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $info = $this->PartnerAccount($partner_id);

                        $response['status'] = 'success';
                        $response['message'] = 'account information';
                        $response['data'] = $info;
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }

 
    public function float_report()
    {

        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $info = $this->PartnerAccount($partner_id);              
                        $data = array(
                            'partner_id' => $info['partner_id'],
                            'partner_phone' => $info['partner_phone'],
                            'created_on' => $info['created_on'],
                            'total_credit_reserved' => $info['total_credit_reserved'],
                            'total_debit_reserved' => $info['total_debit_reserved'],
                            'total_balance_reserved' => $info['total_balance_reserved'],
                            'total_credit_active' => $info['total_credit_active'],
                            'total_debit_active' => $info['total_debit_active'],
                            'total_balance_active' => $info['total_balance_active'],
                            'total_credit_uncleared' => $info['total_credit_uncleared'],
                            'total_debit_uncleared' => $info['total_debit_uncleared'],
                            'total_balance_uncleared' => $info['total_balance_uncleared'],
                            'transactions' => array()
                        );

                        foreach ($info['transactions'] as $key) {
                            $transaction = array(
                                'partner_ledger_id' => $key['partner_ledger_id'],
                                'transaction_id' => $key['transaction_id'],
                                'transaction_number' => $key['transaction_number'],
                                'receipt_no' => $key['receipt_no'],
                                'description' => $key['description'],
                                'pay_method' => $key['pay_method'],
                                'partner_id' => $key['partner_id'],
                                'trans_id' => $key['trans_id'],
                                'trans_amount' => $key['trans_amount'],
                                'cr_dr' => $key['cr_dr'],
                                'ledger_type' => ($key['cr_dr'] == 'cr') ? 'Credit' : 'Debit',
                                'charge' => $key['charge'],
                                'charge_percent' => $key['charge_percent'],
                                'currency' => $key['currency'],
                                'amount' => $key['amount'],
                                'total_amount' => $key['total_amount'],
                                'ledger_account' => $key['ledger_account'],
                                'account' => ($key['ledger_account'] == 1) ? 'Reserved Account' : 'Float Account',
                                'status' => $key['status'],
                                'txn_status' => ($key['status'] == 1) ? 'Complete' : (($key['status'] == 2) ? 'Declined' : 'Pending'),
                                'trans_date' => $key['trans_date']
                            );

                            $data['transactions'][] = $transaction;
                        }

                        $response['status'] = 'success';
                        $response['message'] = 'account information';
                        $response['data'] = $data;
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }

    public function audit_report()
    {

        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {

                        $get = $this->Operations->SystemAudit($partner_id);
                        $response['status'] = 'success';
                        $response['message'] = 'audit report';
                        $response['data'] = $get;
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }


    public function pending_transfers()
    {

        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $partner_condition1 = array('partner_id'=>$partner_id,'status'=>0);

                        $info = $this->Operations->SearchByCondition('partner_ledger',$partner_condition1);           
                        $data = [];
                        foreach ($info as $key) {
                            $transaction = array(
                                'partner_ledger_id' => $key['partner_ledger_id'],
                                'transaction_id' => $key['transaction_id'],
                                'transaction_number' => $key['transaction_number'],
                                'receipt_no' => $key['receipt_no'],
                                'description' => $key['description'],
                                'pay_method' => $key['pay_method'],
                                'partner_id' => $key['partner_id'],
                                'trans_id' => $key['trans_id'],
                                'trans_amount' => $key['trans_amount'],
                                'cr_dr' => $key['cr_dr'],
                                'ledger_type' => ($key['cr_dr'] == 'cr') ? 'Credit' : 'Debit',
                                'charge' => $key['charge'],
                                'charge_percent' => $key['charge_percent'],
                                'currency' => $key['currency'],
                                'amount' => $key['amount'],
                                'total_amount' => $key['total_amount'],
                                'ledger_account' => $key['ledger_account'],
                                'account' => ($key['ledger_account'] == 1) ? 'Reserved Account' : 'Float Account',
                                'status' => $key['status'],
                                'txn_status' => ($key['status'] == 1) ? 'Complete' : (($key['status'] == 2) ? 'Declined' : 'Pending'),
                                'trans_date' => $key['trans_date']
                            );

                            $data[] = $transaction;
                        }

                        $response['status'] = 'success';
                        $response['message'] = 'pending transfers';
                        $response['data'] = $data;
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }


    public function declined_transaction()
    {

        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $partner_condition1 = array('partner_id'=>$partner_id,'status'=>2);

                        $info = $this->Operations->SearchByCondition('partner_ledger',$partner_condition1);           
                        $data = [];
                        foreach ($info as $key) {
                            $transaction = array(
                                'partner_ledger_id' => $key['partner_ledger_id'],
                                'transaction_id' => $key['transaction_id'],
                                'transaction_number' => $key['transaction_number'],
                                'receipt_no' => $key['receipt_no'],
                                'description' => $key['description'],
                                'pay_method' => $key['pay_method'],
                                'partner_id' => $key['partner_id'],
                                'trans_id' => $key['trans_id'],
                                'trans_amount' => $key['trans_amount'],
                                'cr_dr' => $key['cr_dr'],
                                'ledger_type' => ($key['cr_dr'] == 'cr') ? 'Credit' : 'Debit',
                                'charge' => $key['charge'],
                                'charge_percent' => $key['charge_percent'],
                                'currency' => $key['currency'],
                                'amount' => $key['amount'],
                                'total_amount' => $key['total_amount'],
                                'ledger_account' => $key['ledger_account'],
                                'account' => ($key['ledger_account'] == 1) ? 'Reserved Account' : 'Float Account',
                                'status' => $key['status'],
                                'txn_status' => ($key['status'] == 1) ? 'Complete' : (($key['status'] == 2) ? 'Declined' : 'Pending'),
                                'trans_date' => $key['trans_date']
                            );

                            $data[] = $transaction;
                        }

                        $response['status'] = 'success';
                        $response['message'] = 'declined transactions';
                        $response['data'] = $data;
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }

    public function approve_transfer()
    {

        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');
        $this->form_validation->set_rules('partner_ledger_id', 'partner_ledger_id', 'required');


        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id or ledger_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_ledger_id = $this->input->post('partner_ledger_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $partner_condition1 = array(
                        'partner_id'=>$partner_id,
                        'partner_ledger_id'=>$partner_ledger_id,
                        );
                        $search = $this->Operations->SearchByCondition('partner_ledger',$partner_condition1);  
                        if($search)         
                        {
                            $transaction_number = $search[0]['transaction_number'];
                            $update_data = array('status'=>1);
                            $partner_condition2 = array(
                                'partner_id'=>$partner_id,
                                'transaction_number'=>$transaction_number,
                                );

                            $update = $this->Operations->UpdateData('partner_ledger',$partner_condition1,$update_data); 
                            $update1 = $this->Operations->UpdateData('partner_system_ledger',$partner_condition2,$update_data);  


                            if($update === TRUE && $update1 === TRUE)
                            {
                                $response['status'] = 'success';
                                $response['message'] = 'successfully transaction Complete';
                            }
                            else
                            {
                                $response['status'] = 'error';
                                $response['message'] = 'unable to approve transaction now, try later';  
                            }

                        }
                        else
                        {
                            $response['status'] = 'error';
                            $response['message'] = 'transaction not found';  

                        }
                        
                       
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }


    public function decline_transfer()
    {

        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');
        $this->form_validation->set_rules('partner_ledger_id', 'partner_ledger_id', 'required');


        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id or ledger_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_ledger_id = $this->input->post('partner_ledger_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $partner_condition1 = array(
                        'partner_id'=>$partner_id,
                        'partner_ledger_id'=>$partner_ledger_id,
                        'status'=>0);
                        $search = $this->Operations->SearchByCondition('partner_ledger',$partner_condition1);  

                        
                        if($search)         
                        {
     
                            
                            $transaction_number = $search[0]['transaction_number'];
                            $update_data = array('status'=>2);
                            $partner_condition2 = array(
                                'partner_id'=>$partner_id,
                                'transaction_number'=>$transaction_number,
                                'status'=>0);

                            $update = $this->Operations->UpdateData('partner_ledger',$partner_condition1,$update_data); 
                            $update1 = $this->Operations->UpdateData('partner_system_ledger',$partner_condition2,$update_data); 

                            if($update === TRUE && $update1 === TRUE)
                            {
                                $response['status'] = 'success';
                                $response['message'] = 'successfully transaction declined';
                            }
                            else
                            {
                                $response['status'] = 'error';
                                $response['message'] = 'unable to decline transaction now, try later';  
                            }

                        }
                        else
                        {
                            $response['status'] = 'error';
                            $response['message'] = 'transaction not found or already Complete';  

                        }
                        
                       
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }


    public function transaction_report()
    {

        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'fail';
                    $response['message'] = 'partner_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $partner_condition1 = array('partner_id'=>$partner_id);

                        $info = $this->Operations->SearchByCondition('customer_ledger',$partner_condition1);           
                        $data = [];
                        foreach ($info as $key) {
                            $transaction = array(
                                'partner_ledger_id' => $key['customer_ledger_id'],
                                'transaction_id' => $key['transaction_id'],
                                'transaction_number' => $key['transaction_number'],
                                'receipt_no' => $key['receipt_no'],
                                'description' => $key['description'],
                                'pay_method' => $key['pay_method'],
                                'wallet_id' => $key['wallet_id'],
                                'partner_id' => $key['partner_id'],
                                'trans_id' => $key['trans_id'],
                                'trans_amount' => $key['paid_amount'],
                                'cr_dr' => $key['cr_dr'],
                                'ledger_type' => ($key['cr_dr'] == 'cr') ? 'Credit' : 'Debit',
                                'charge' => $key['charge'],
                                'charge_percent' => $key['chargePercent'],
                                'currency' => $key['currency'],
                                'amount' => $key['amount'],
                                'total_amount' => $key['total_amount'],
                                'ledger_account' => $key['ledger_account'],
                                'status' => $key['status'],
                                'txn_status' => ($key['status'] == 1) ? 'Complete' : (($key['status'] == 2) ? 'Declined' : 'Pending'),
                                'trans_date' => $key['created_at']
                            );

                            $data[] = $transaction;
                        }

                        $response['status'] = 'success';
                        $response['message'] = 'transactions report';
                        $response['data'] = $data;
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }

 

    public function initiate_transaction()
    {
       
        $response = array();

        $auth_response = $this->auth();
        $auth_data = json_decode($auth_response, true);
        
    
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400); // Bad Request
            $response['status'] = 'fail';
            $response['message'] = 'Only POST request allowed';
            $response['data'] = '';
        } else {
            if($auth_data['status'] == 'success')
            {
    
                $partner_id = $this->input->post('partner_id');
                $from_account = $this->input->post('from_account');
                $to_account = $this->input->post('to_account');
                $account_number = $this->input->post('account_number');
                $amount = $this->input->post('amount');
                $description = $this->input->post('description');
            
                //validate partner _id
                $partner_condition = array('partner_id'=>$partner_id);
                $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);


                $partner_details = $this->PartnerAccount($partner_id);
                $total_credit_reserved = $partner_details['total_credit_reserved'];
                $total_debit_reserved = $partner_details['total_debit_reserved'];
                $total_balance_reserved = $partner_details['total_balance_reserved'];


                $total_credit_active = $partner_details['total_credit_active'];
                $total_debit_active = $partner_details['total_debit_active'];
                $total_balance_active = $partner_details['total_balance_active'];

                $transaction_id = $this->Operations->Generator(8); 

                
               
                // Form validation for phone
                if (empty($partner_id)) {
                    $response['status'] = 'error';
                    $response['message'] = 'partner_id required';
                    
                }
                elseif(empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] == NULL || $validate_partner[0]['partner_id'] == '')
                {
                    $response['status'] = 'fail';
                    $response['message'] = 'partner id not valid';
                }
                
                elseif (empty($from_account)) {
                    $response['status'] = 'error';
                    $response['message'] = 'debiting account required';
                
                }
                elseif ($from_account !=2) {
                    $response['status'] = 'error';
                    $response['message'] = 'debiting account must be from float account';
                
                }
                elseif (empty($to_account)) {
                    $response['status'] = 'error';
                    $response['message'] = 'crediting account required';
                
                }
                elseif (empty($account_number)) {
                    $response['status'] = 'error';
                    $response['message'] = 'account number required';
                
                }
                elseif (empty($amount)) {
                    $response['status'] = 'error';
                    $response['message'] = 'amount required';
                    
                }
                elseif ($amount < 10) {
                    $response['status'] = 'error';
                    $response['message'] = 'minimum amount is KES 10';
                    
                }

                elseif ($amount > $total_balance_active) {
                    $response['status'] = 'error';
                    $response['message'] = 'insufficient float balance to transact';
                    
                }        
                // elseif (empty($description)) {
                //     $response['status'] = 'error';
                //     $response['message'] = 'description required';
                    
                // }
                else {

                    if($to_account == 5)
                    {
                        $phone = str_replace(' ', '', $account_number);
                        $phone = preg_replace('/^(?:\+?254|0)?/','254', $phone);
                        $debit_account = $this->b2c($transaction_id,$partner_id,$phone,$amount,$to_account,$from_account);

                    }

               


                    if($debit_account === TRUE)
                    {
                        $response['status'] = 'success';
                        $response['message'] = 'success funds transfered to '.$account_number.' account';
                        
                    }
                    else
                    {
                        $response['status'] = 'error';
                        $response['message'] = 'unable to move funds now, try later';


                    }

                }
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }
            
        }

    
        // Send JSON response
        echo json_encode($response);
    
    }



    public function verify_transaction()
    {

        $this->form_validation->set_rules('partner_id', 'partner_id', 'required');
        $this->form_validation->set_rules('transaction_id', 'transaction_id', 'required');


        if ($_SERVER['REQUEST_METHOD'] == 'POST') 
        {
            $response = array();
            $auth_response = $this->auth();
            $auth_data = json_decode($auth_response, true);
            if($auth_data['status'] == 'success')
            {
                if ($this->form_validation->run() == FALSE) {
                    http_response_code(401); // Bad Request
                    $response['status'] = 'error';
                    $response['message'] = 'partner_id or transaction_id required';
    
                } else {
                    //validate partner _id
                    $partner_id = $this->input->post('partner_id');
                    $transaction_id = $this->input->post('transaction_id');

                    //validate partner
                    $partner_condition = array('partner_id'=>$partner_id);
                    $validate_partner = $this->Operations->SearchByCondition('partners',$partner_condition);
                    if(!empty($validate_partner[0]['partner_id']) || $validate_partner[0]['partner_id'] != NULL || $validate_partner[0]['partner_id'] != '')
                    {
                        $info = $this->transaction_details($partner_id, $transaction_id);

                        $response = $info;
                    }
                    else
                    {
                        http_response_code(401); // Bad Request
                        $response['status'] = 'fail';
                        $response['message'] = 'partner id not valid';
                    }
                    
                }
                
            }
            elseif ($auth_data['status'] == 'fail') {
                # code...
                $response = $auth_data;
            }
            else
            {
                $response = $auth_data;
            }

            
        }else{

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only Post request Allowed';
        }
      

        echo json_encode($response);
    }


    public function transaction_details($partner_id, $transaction_id)
    {
        $condition1 = array('partner_id' => $partner_id, 'transaction_number' => $transaction_id);
    
        $transaction = $this->Operations->SearchByCondition('customer_ledger', $condition1);
    
        if ($transaction) {
            $transaction_number = $transaction[0]['transaction_number'];
            $receipt_no = $transaction[0]['receipt_no'];
            $description = $transaction[0]['description'];
            $pay_method = $transaction[0]['pay_method'];
            $wallet_id = $transaction[0]['wallet_id'];
            $partner_id = $transaction[0]['partner_id'];
            $trans_id = $transaction[0]['trans_id'];
            $trans_amount = $transaction[0]['amount'];
            $cr_dr = $transaction[0]['cr_dr'];
            $charge = $transaction[0]['charge'];
            $charge_percent = $transaction[0]['chargePercent'];
            $currency = $transaction[0]['currency'];
            $amount = $transaction[0]['amount'];
            $total_amount = $transaction[0]['total_amount'];
            $status = $transaction[0]['status'];
            $trans_date = $transaction[0]['trans_date'];
            $trans_status = ($status == 1) ? 'Complete' : (($status == 2) ? 'Declined' : 'Pending');
            $ledger_type = ($cr_dr == 'cr') ? 'Credit' : 'Debit';
    
            $response = array(
                'status' => 'success',
                'message' => 'Transaction details retrieved successfully',
                'data' => array(
                    'transaction_number' => $transaction_number,
                    'receipt_no' => $receipt_no,
                    'description' => $description,
                    'pay_method' => $pay_method,
                    'wallet_id' => $wallet_id,
                    'partner_id' => $partner_id,
                    'trans_id' => $trans_id,
                    'trans_amount' => $trans_amount,
                    'cr_dr' => $cr_dr,
                    'charge' => $charge,
                    'charge_percent' => $charge_percent,
                    'currency' => $currency,
                    'amount' => $amount,
                    'total_amount' => $total_amount,
                    'ledger_type'=>$ledger_type,
                    'transaction_status' => $trans_status,
                    'trans_date' => $trans_date,
                )
            );
    
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Transaction details not found',
                'data' => null
            );
        }
    
        return $response;
    }
    

    public function PartnerAccount($partner_id)
   {
       
       $senderSummary = $this->Operations->partner_transection_summary($partner_id);
        
       $senderTotalCredit_reserved = (float) str_replace(',', '', number_format($senderSummary[0][0]['total_credit_reserved'], 2));
       $senderTotalDebit_reserved = (float) str_replace(',', '', number_format($senderSummary[3][0]['total_debit_reserved'], 2));
       $senderTotalBalanceKes_reserved = $senderTotalCredit_reserved - $senderTotalDebit_reserved;
       $senderTotalBalanceKes_reserved = str_replace(',', '', number_format($senderTotalBalanceKes_reserved, 2));


       $senderTotalCredit_active = (float) str_replace(',', '', number_format($senderSummary[1][0]['total_credit_active'], 2));
       $senderTotalDebit_active = (float) str_replace(',', '', number_format($senderSummary[4][0]['total_debit_active'], 2));
       $senderTotalBalanceKes_active = $senderTotalCredit_active - $senderTotalDebit_active;
       $senderTotalBalanceKes_active = str_replace(',', '', number_format($senderTotalBalanceKes_active, 2));

       $senderTotalCredit_uncleared = (float) str_replace(',', '', number_format($senderSummary[2][0]['total_credit_uncleared'], 2));
       $senderTotalDebit_uncleared = (float) str_replace(',', '', number_format($senderSummary[5][0]['total_debit_uncleared'], 2));
       $senderTotalBalanceKes_uncleared = $senderTotalCredit_uncleared - $senderTotalDebit_uncleared;
       $senderTotalBalanceKes_uncleared = str_replace(',', '', number_format($senderTotalBalanceKes_uncleared, 2));

       
       $condition1 = array('partner_id'=>$partner_id);
       
       $sender_details = $this->Operations->SearchByCondition('partners',$condition1);

       $user_transactions = $this->Operations->SearchByCondition('partner_ledger',$condition1);

    
       $partner_phone =  $sender_details[0]['partner_phone'];
       $sender_name =  $sender_details[0]['partner_name'];
       $sender_partner_id =  $sender_details[0]['partner_id'];
       $partner_created_on =  $sender_details[0]['partner_created_on'];
       
       return array(
           'partner_id'=>$sender_partner_id,
           'partner_phone'=>$partner_phone,
           'created_on'=>$partner_created_on,
           'total_credit_reserved'=>$senderTotalCredit_reserved,
           'total_debit_reserved'=>$senderTotalDebit_reserved,
           'total_balance_reserved'=>$senderTotalBalanceKes_reserved,
           'total_credit_active'=>$senderTotalCredit_active,
           'total_debit_active'=>$senderTotalDebit_active,
           'total_balance_active'=>$senderTotalBalanceKes_active,
           'total_credit_uncleared'=>$senderTotalCredit_uncleared,
           'total_debit_uncleared'=>$senderTotalDebit_uncleared,
           'total_balance_uncleared'=>$senderTotalBalanceKes_uncleared,
           'transactions'=>$user_transactions,
        );

        // return $senderSummary;

   }


    public function sendotp()
    {

        $response = array();

    

        $phoned = $this->input->post('phone');

        $phoned = str_replace(' ', '', $phoned); // Remove spaces from

        $mobile = preg_replace('/^(?:\+?254|0)?/', '+254', $phoned);

    

        $this->form_validation->set_rules('phone', 'phone', 'required');

    

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if ($this->form_validation->run() == FALSE) {

                // Handle validation errors

                $response['status'] = 'fail';

                $response['message'] = 'Phone is required';

                $response['data'] = '';

            } else {

                if (empty($mobile)) {

                    $response['status'] = 'fail';

                    $response['message'] = 'Phone is required';

                    $response['data'] = '';

                } else {

                    $table = 'customers';

                    $condition = array('phone' => $mobile);

                    $search = $this->Operations->SearchByCondition($table, $condition);

    

                    if ($search) {

                        $wallet_id = $search[0]['wallet_id'];

                        $mobile = $search[0]['phone'];

                        $otp = $this->Operations->OTP(6);

                        

                        $sessdata = array

                        (

                            'wallet_id'=>$wallet_id,

                            'phone'=>$mobile,

                            'otp'=>$otp,

                            'created_on'=>$this->date,

                        );

                        

                        $save = $this->Operations->Create('forgot_password',$sessdata);

                        if($save === TRUE)

                        {

                            $message = 'Password reset OTP verification code '.$otp.' input it on time';

                            $sms = $this->Operations->sendSMS($mobile,$message);

                            $response['status'] = 'success';

                            $response['message'] = 'OTP code has been sent to '.$mobile.' please input on time';

                            $response['data'] = $sessdata; 

                        }

                        else

                        {

                            $response['status'] = 'error';

                            $response['message'] = 'Something went wrong resetting password';

                        }

    

                    } else {

                        $response['status'] = 'fail';

                        $response['message'] = 'Phone number not registered';

                        $response['data'] = '';

                    }

                }

            }

        } else {

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only POST request allowed';

        }

    

        echo json_encode($response);

    }

     public function verifyOtp()
    {

        $response = array();

    

        $otp = $this->input->post('otp');

        $otp = str_replace(' ', '', $otp);

    

        $this->form_validation->set_rules('otp', 'otp', 'required');

    

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if ($this->form_validation->run() == FALSE) {

                // Handle validation errors

                $response['status'] = 'fail';

                $response['message'] = 'otp is required';

                $response['data'] = '';

            } else {

                if (empty($otp)) {

                    $response['status'] = 'fail';

                    $response['message'] = 'otp is required';

                    $response['data'] = '';

                } else {

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

                            $wallet_id = $search[0]['wallet_id'];

                            $mobile = $search[0]['phone'];

    

                            $sessdata = array(

                                'wallet_id' => $wallet_id,

                                'phone' => $mobile,

                            );

    

                            $response['status'] = 'success';

                            $response['message'] = 'phone verified, reset your password now';

                            $response['data'] = $sessdata;

                        } else {

                            $response['status'] = 'fail';

                            $response['message'] = 'Verification code has expired';

                            $response['data'] = '';

                        }

                    } else {

                        $response['status'] = 'fail';

                        $response['message'] = 'Invalid Verification code ';

                        $response['data'] = '';

                    }

                }

            }

        } else {

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only POST request allowed';

        }

    

        echo json_encode($response);

    }


    public function updatepassword()
    {

        $response = array();

    

        $table = 'customers';

        $pass1 = $this->input->post('password');

        $pass2 = $this->input->post('confirmpassword');

        $mobile = $this->input->post('phone');

        $wallet_id = $this->input->post('wallet_id');

    

        $this->form_validation->set_rules('password', 'password', 'required');

        $this->form_validation->set_rules('confirmpassword', 'confirmpassword', 'required');

        $this->form_validation->set_rules('phone', 'phone', 'required');

        $this->form_validation->set_rules('wallet_id', 'wallet_id', 'required');

    

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if ($this->form_validation->run() == FALSE) {

                // Handle validation errors

                $response['status'] = 'fail';

                $response['message'] = 'Phone, password, confirmpassword, and wallet_id are required';

                $response['data'] = '';

            } else {

                if ($pass1 != $pass2) {

                    $response['status'] = 'fail';

                    $response['message'] = 'Passwords must match';

                    $response['data'] = '';

                } else {

                    $data = array(

                        'password' => $this->Operations->hash_password($pass2),

                    );

    

                    $condition = array(

                        'wallet_id' => $wallet_id,

                    );

    

                    if ($this->Operations->UpdateData($table, $condition, $data)) {

                        $action = 'Updated account details';

                        $this->Operations->RecordAction($action);

                        $message = "Password updated. New Password: " . $pass2;

                        $sms = $this->Operations->sendSMS($mobile, $message);

    

                        $response['status'] = 'success';

                        $response['message'] = 'Password updated successfully';

                        $response['data'] = '';

                    } else {

                        $response['status'] = 'fail';

                        $response['message'] = 'Unable to update password, try again';

                        $response['data'] = '';

                    }

                }

            }

        } else {

            http_response_code(400); // Bad Request

            $response['status'] = 'fail';

            $response['message'] = 'Only POST request allowed';

            $response['data'] = '';

        }

    

        echo json_encode($response);

    }


    public function logout()
    {
        
    }


    public function GeneratePartnerNextTransaction()
    {
        $last_id = $this->Operations->getLastPartnerTransactionId();
        // echo $last_id; 

        $nextReceipt = $this->getNextReceipt($last_id);
        return $nextReceipt;
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
    
        // If the digits part rolls over to 100, adjust letters and reset digits to 1
        if ($nextDigits == 100) {
            // Increment the last letter
            $lettersArray = str_split($letters);
            $lastIndex = count($lettersArray) - 1;
            $lettersArray[$lastIndex] = chr(ord($lettersArray[$lastIndex]) + 1);
    
            // Convert the array back to a string
            $nextLetters = implode('', $lettersArray);
    
            // Reset digits to 1
            $nextDigits = 1;
        } else {
            $nextLetters = $letters;
        }
    
        // Ensure that the digits part is formatted with leading zeros if necessary
        $nextDigitsStr = str_pad($nextDigits, 2, '0', STR_PAD_LEFT);
    
        // Construct the next receipt number
        $nextReceipt = $nextLetters . $nextDigitsStr . $nextExtraLetter;
    
        return $nextReceipt;
    }




}

