<?php

use WebSocket\Client;

/**
 * 
 */

class Operations extends CI_model

{



    public function CurlPost($url,$body)

   {

        $c = curl_init();

        curl_setopt($c, CURLOPT_URL,$url);

        curl_setopt($c, CURLOPT_POST, true);

        curl_setopt($c, CURLOPT_POSTFIELDS, $body);

        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

        $page = curl_exec($c);

        return $page;

        if(curl_errno($c)) {

          $error_msg = curl_error($c);

          echo $error_msg;

         }

          curl_close($c);

   }

   public function CurlPostHeaders($url, $body, $token)
    {
        $c = curl_init();

        // Set the URL
        curl_setopt($c, CURLOPT_URL, $url);

        // Set the request method to POST
        curl_setopt($c, CURLOPT_POST, true);

        // Set the request body
        curl_setopt($c, CURLOPT_POSTFIELDS, $body);

        // Set the authorization header
        curl_setopt($c, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $token
        ));

        // Return the response as a string
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

        // Execute the cURL request
        $page = curl_exec($c);

        // Check for cURL errors
        if(curl_errno($c)) {
            $error_msg = curl_error($c);
            echo $error_msg;
        }

        // Close the cURL handle
        curl_close($c);

        // Return the response
        return $page;
    }


   

   public function CurlFetch($url)

   {

         $c = curl_init(); 

         curl_setopt($c, CURLOPT_URL,$url);

         curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

         return $reponse = curl_exec($c);

         if (curl_errno($c)) {

         $error_msg = curl_error($c);

         }

         curl_close($c);

   }

   

      public function getMonthlyUserRegistrations()

    {

        $this->db->select("DATE_FORMAT(created_on, '%Y-%m') AS month", false);

        $this->db->select("COUNT(*) AS registrations");

        $this->db->from('customers');

        $this->db->group_by('month');

        $this->db->order_by('month', 'ASC');

        $query = $this->db->get();

    

        return $query->result();

    }

    

    public function GetEarnings()

	{

	    $this->db->select('ROUND(SUM(charge), 2) AS total_earning', FALSE); // Add FALSE to prevent CodeIgniter from escaping the query

        $this->db->from('customer_ledger');

        $query = $this->db->get();

        

        return $query->row()->total_earning;

	} 

    public function thisMonthlyEarnings()
    {
        $this->db->select("DATE_FORMAT(created_at, '%Y-%m') AS month", false);
        $this->db->select("ROUND(SUM(charge), 2) AS earnings", false);
        $this->db->from('customer_ledger');
        $this->db->where('YEAR(created_at)', date('Y'));
        $this->db->where('MONTH(created_at)', date('m'));
        $this->db->group_by('month');
        $this->db->order_by('month', 'ASC');
    
        $query = $this->db->get();
    
        return $query->result();
    }

	

    public function getMonthlyEarnings()
    {

        $this->db->select("DATE_FORMAT(created_at, '%Y-%m') AS month", false);

        $this->db->select("SUM(charge) AS earnings", false);

        $this->db->from('customer_ledger');

        $this->db->group_by('month');

        $this->db->order_by('month', 'ASC');

        $query = $this->db->get();

        return $query->result();

    }

    

    public function getDailyEarnings()
    {

        $this->db->select("DATE_FORMAT(created_at, '%Y-%m-%d') AS day", false);

        $this->db->select("SUM(charge) AS earnings", false);

        $this->db->from('customer_ledger');

        $this->db->group_by('day');

        $this->db->order_by('day', 'ASC');

        $query = $this->db->get();


        return $query->result();

    }

    

    public function getDailyDeposits()

    {

        $this->db->select("COALESCE(DATE_FORMAT(created_on, '%Y-%m-%d'), '0') AS day", false);

        $this->db->select("SUM(amount) AS deposits", false);

        $this->db->from('mpesa_deposit');

        $this->db->where('paid', 1);

        $this->db->group_by('day');

        $this->db->order_by('day', 'ASC');

        $query = $this->db->get();

    

        return $query->result();

    }

    

    public function getOverallDeposit()

    {

        $this->db->select('ROUND(SUM(amount), 2) AS total_deposit', FALSE); // Add FALSE to prevent CodeIgniter from escaping the query

        $this->db->from('mpesa_deposit');

        $this->db->where('paid', 1);

        $query = $this->db->get();

        return $query->row()->total_deposit;



    }

    

    public function getDailyWithdrawals()

    {

        $this->db->select("COALESCE(DATE_FORMAT(request_date, '%Y-%m-%d'), '0') AS date", false);

        $this->db->select("SUM(amount) AS withdrawals", false);

        $this->db->from('mpesa_withdrawals');

        $this->db->group_by('date');

        $this->db->order_by('date', 'ASC');

        $query = $this->db->get();

    

        return $query->result();

    }

    

    

    public function getOverallWithdrawal()
    {

         $this->db->select('ROUND(SUM(amount), 2) AS total_withdraw', FALSE); // Add FALSE to prevent CodeIgniter from escaping the query

        $this->db->from('mpesa_withdrawals');

        $query = $this->db->get();

        return $query->row()->total_withdraw;

    }

    







     public function getSumOfAmount() {

        // Use CodeIgniter's Active Record to calculate the sum

        $result = array();

    

        $this->db->select('SUM(paid_amount) AS total_credit');

        $this->db->from('customer_ledger');

        $this->db->where('receipt_no IS NOT NULL');

        

        $query = $this->db->get();

        

        if ($query->num_rows() > 0) {

            $result['total_credit'] = $query->row()->total_credit;

        } else {

            $result['total_credit'] = 0;

        }

    

        $this->db->reset_query();

    

        $this->db->select('SUM(paid_amount) AS total_debit');

        $this->db->from('customer_ledger');

        $this->db->where(array('receipt_no' => NULL, 'status' => 1));

        $query = $this->db->get();

    

        if ($query->num_rows() > 0) {

            $result['total_debit'] = $query->row()->total_debit;

        } else {

            $result['total_debit'] = 0;

        }

    

        // Calculate the difference between total_credit and total_debit

        $result['difference'] = $result['total_credit'] - $result['total_debit'];

    

        return $result['difference'];

    }


    public function StepakashTotalTransfers()
    {
        $this->db->select('ROUND(SUM(paid_amount),2) AS total_transfer');

        $this->db->from('customer_ledger');

        $this->db->where(array('deriv' => 10,'cr_dr'=>'cr'));

        $query = $this->db->get();

    

        if ($query->num_rows() > 0) {

            $result = $query->row()->total_transfer;

        } else {

            $result = 0;

        }

        return $result;

    }

    public function BinanceTotalTransfers()
    {
        $this->db->select('ROUND(SUM(paid_amount),2) AS total_transfer');

        $this->db->from('customer_ledger');

        $this->db->where(array('deriv' => 3));

        $query = $this->db->get();

    

        if ($query->num_rows() > 0) {

            $result = $query->row()->total_transfer;

        } else {

            $result = 0;

        }

        return $result;

    }

    public function ActiveUsers()
    {
        $query = $this->db->select('COUNT(DISTINCT customers.wallet_id) as num_customers')
        ->from('customers')
        ->join('customer_ledger', 'customers.wallet_id = customer_ledger.wallet_id', 'inner')
        ->get();

        // Check if there are results
        if ($query->num_rows() > 0) {
            return $query->row()->num_customers;
        } else {
            return 0;
        }
    }




    

   public function SearchJoin()
   {

       $this->db->select('a.*,a.id as request_id,b.wallet_id,b.phone,b.account_number');

    $this->db->from('deriv_deposit_request a'); 

    $this->db->join('customers b', 'b.wallet_id = a.wallet_id');  

    $this->db->order_by('a.id DESC');

    $query = $this->db->get(); 

    if($query->num_rows() != 0)

    {

        return $query->result_array();

    }

    else

    {

        return false;

    }

   }

   

   public function MpesaDeposits()

   {

        $condition = array('paid'=>1);

        $this->db->select('*');

	    $this->db->from('mpesa_deposit');

	    $this->db->where($condition);

	    $this->db->order_by('id  DESC');

		$this->db->order_by('created_on DESC');

        $query = $this->db->get(); 

        if($query->num_rows() != 0)

        {

            return $query->result_array();

        }

        else

        {

            return false;

        }

   }


   public function MpesaWithdrawals()

   {

        $condition = array('paid'=>1);

        $this->db->select('*');

        $this->db->from('mpesa_withdrawals');

        $this->db->where($condition);

        $this->db->order_by('id  DESC');
        $this->db->order_by('request_date DESC');
        $query = $this->db->get();
        if($query->num_rows() != 0)
        {
            return $query->result_array();
        }
        else
        {
            return false;
        }

   }

   public function SearchCustomers()
   {

        $this->db->select('id,wallet_id,phone,account_number,created_on');

	    $this->db->from('customers');

	    $this->db->order_by('id  DESC');

		$this->db->order_by('created_on DESC');

        $query = $this->db->get(); 

        if($query->num_rows() != 0)

        {

            return $query->result_array();

        }

        else

        {

            return false;

        }

   }


   public function SearchByCustomer($condition)
   {

        $this->db->select('id,wallet_id,phone,account_number,created_on');

	    $this->db->from('customers');

	    $this->db->where($condition);

	    $this->db->order_by('id  DESC');

		$this->db->order_by('created_on DESC');

        $query = $this->db->get(); 

        if($query->num_rows() != 0)

        {

            return $query->result_array();

        }

        else

        {

            return false;

        }

   }

   

   

    public function SearchRequest()

   {

       $this->db->select('a.*,b.wallet_id,b.phone,b.account_number,a.amount as amt_usd,a.id as request_id,a.status as processed');

        $this->db->from('deriv_deposit_request a'); 

        $this->db->join('customers b', 'b.wallet_id = a.wallet_id');   

        $this->db->order_by('status ASC'); // Orders all results by status in ascending order

        $this->db->order_by('request_date DESC'); // Then orders by created_at in descending order

        $query = $this->db->get(); 

        if($query->num_rows() != 0)

        {

            return $query->result_array();

        }

        else

        {

            return false;

        }

   }

   

   

   public function SearchWithdrawalRequest()

   {

       $this->db->select('a.*,b.wallet_id,b.phone,b.account_number,a.amount as amt_usd,a.id as request_id,a.status as processed');

    $this->db->from('deriv_withdraw_request a'); 

    $this->db->join('customers b', 'b.wallet_id = a.wallet_id');   

    $this->db->order_by('a.request_date DESC'); // Orders all results by status in ascending order

    $this->db->order_by('a.id DESC'); // Then orders by created_at in descending order

    $query = $this->db->get(); 

    if($query->num_rows() != 0)

    {

        return $query->result_array();

    }

    else

    {

        return false;

    }

   }





    

    public function customer_transection_summary($wallet_id)
	{

		$result=array();

		$this->db->select_sum('paid_amount','total_credit');

		$this->db->from('customer_ledger');

		$this->db->where(array('wallet_id'=>$wallet_id,'receipt_no !='=>NULL,'status'=>1));

		$query = $this->db->get();

		if ($query->num_rows() > 0) {

			$result[]=$query->result_array();	

		}

		

		$this->db->select_sum('paid_amount','total_debit');

		$this->db->from('customer_ledger');

		$this->db->where(array('wallet_id'=>$wallet_id,'status'=>1));

		$this->db->where('receipt_no =',NULL);

		$query = $this->db->get();

		

		if ($query->num_rows() > 0) {

			$result[]=$query->result_array();	

		}

		return $result;



	}

    public function partner_transection_summary($partner_id)
	{

		$result=array();

		$this->db->select_sum('trans_amount','total_credit_reserved');

		$this->db->from('partner_ledger');

		$this->db->where(array('partner_id'=>$partner_id,'receipt_no !='=>NULL,'status'=>1,'ledger_account'=>1));

		$query = $this->db->get();

		if ($query->num_rows() > 0) {

			$result[]=$query->result_array();	

		}


        $this->db->select_sum('trans_amount','total_credit_active');

		$this->db->from('partner_ledger');

		$this->db->where(array('partner_id'=>$partner_id,'receipt_no !='=>NULL,'status'=>1,'ledger_account'=>2));

		$query = $this->db->get();

		if ($query->num_rows() > 0) {

			$result[]=$query->result_array();	

		}

        $this->db->select_sum('trans_amount','total_credit_uncleared');

		$this->db->from('partner_ledger');

		$this->db->where(array('partner_id'=>$partner_id,'receipt_no !='=>NULL,'status'=>0,'ledger_account'=>2));

		$query = $this->db->get();

		if ($query->num_rows() > 0) {

			$result[]=$query->result_array();	

		}

		

		$this->db->select_sum('trans_amount','total_debit_reserved');

		$this->db->from('partner_ledger');

		$this->db->where(array('partner_id'=>$partner_id,'status'=>1,'ledger_account'=>1));

		$this->db->where('receipt_no =',NULL);

		$query = $this->db->get();

	
		if ($query->num_rows() > 0) {

			$result[]=$query->result_array();	

		}

        $this->db->select_sum('trans_amount','total_debit_active');

		$this->db->from('partner_ledger');

		$this->db->where(array('partner_id'=>$partner_id,'status'=>1,'ledger_account'=>2));

		$this->db->where('receipt_no =',NULL);

		$query = $this->db->get();
		if ($query->num_rows() > 0) {

			$result[]=$query->result_array();	

		}

        $this->db->select_sum('trans_amount','total_debit_uncleared');

		$this->db->from('partner_ledger');

		$this->db->where(array('partner_id'=>$partner_id,'status'=>0,'ledger_account'=>2));

		$this->db->where('receipt_no =',NULL);

		$query = $this->db->get();
		if ($query->num_rows() > 0) {

			$result[]=$query->result_array();	

		}


		return $result;

 

	}




	public function Create($table,$data)

	{

		return $this->db->insert($table, $data);

	}



	

	public function UpdateData($table,$condition,$data)

	{

	    $this->db->where($condition);

		$details = $this->db->update($table,$data);

		return $details;

	}



	public function Delete($table,$id)

	{

		$this->db->where('id',$id);

		$delete = $this->db->delete($table);

		return $delete;

	}





	public function Search($table)

	{

	  $this->db->select('*');

	  return $this->db->get($table)->result_array();

	}

	

	

	public function SearchSms($table)

	{

	  $this->db->select('*');

	  $this->db->order_by('id DESC');

	  return $this->db->get($table)->result_array();

	}



	//Count

	public function Count($table)

	{

		$this->db->select('*');

	  return $this->db->get($table)->num_rows();

	}



	//Count with condition

	public function CountWithCondition($table,$condition)

	{

		$this->db->select('*');

		$this->db->where($condition);

	  return $this->db->get($table)->num_rows();

	}

	







//multiple conditions

    public function SearchByCondition($table,$condition)
	{

		$this->db->select('*');

	    $this->db->from($table);	

		$this->db->where($condition);

	   $get = $this->db->get();

		return $get->result_array();

	}


    public function SearchByConditionCrypto($table,$condition)
	{

		$this->db->select('a.*,b.phone,b.account_number');

	    $this->db->from($table.' a');	

        $this->db->join('customers b', 'b.wallet_id = a.wallet_id');

		$this->db->where($condition);

	   $get = $this->db->get();

		return $get->result_array();

	}

	

	public function SearchByConditionBuy($table, $condition)

    {

        $this->db->select('*');

        $this->db->from($table);

        $this->db->where($condition);

        $this->db->limit(1);

        $this->db->order_by('exchange_id', 'DESC'); // Change 'your_date_column' to the actual date column

        $get = $this->db->get();

        return $get->result_array(); // Use row_array() to get a single row

    }

    

    

    public function SearchByConditionSell($table, $condition)

    {

        $this->db->select('*');

        $this->db->from($table);

        $this->db->where($condition);

        $this->db->limit(1);

        $this->db->order_by('exchange_id', 'DESC'); // Change 'your_date_column' to the actual date column

        $get = $this->db->get();

        return $get->row_array(); // Use row_array() to get a single row

    }

	

	//multiple conditions

    public function SearchByConditionDeriv($table,$condition)

	{

		$this->db->select('*');

	    $this->db->from($table);	

		$this->db->where($condition);

		$this->db->order_by('customer_ledger_id DESC');

	    $get = $this->db->get();

		return $get->result_array();

	}







   public function JoinedTable($table,$table2)

   {

       $this->db->select('a.*,b.*');

    $this->db->from(''.$table.' a'); 

    $this->db->join(''.$table2.' b', 'b.customer_id =a.customer_id');

    // $this->db->where('b.customer_id = a.customer_id');        

    $query = $this->db->get(); 

    if($query->num_rows() != 0)

    {

        return $query->result_array();

    }

    else

    {

        return false;

    }

   }





	public function resolve_user_login($phone, $password, $table) {
		$this->db->select('password');
		$this->db->from($table);
		$this->db->where('phone', $phone);
		$hash = $this->db->get()->row('password');
		return $this->verify_password_hash($password, $hash);
	}





	public function resolve_admin_login($email,$password,$table)

	{
		$this->db->select('password');
		$this->db->from($table);
		$this->db->where('email', $email);
		// $this->db->where('partner_id', $partner_id);
		$hash = $this->db->get()->row('password');
		return $this->verify_password_hash($password, $hash);

	}

    public function resolve_super_admin_login($email,$password,$table)

	{
		$this->db->select('password');
		$this->db->from($table);
		$this->db->where('email', $email);
		$this->db->where('status', 1);
		$hash = $this->db->get()->row('password');
		return $this->verify_password_hash($password, $hash);

	}

	

	

	public function get_user_id_from_username($email,$table) {

		

		$this->db->select('id');

		$this->db->from($table);

		$this->db->where('email', $email);



		return $this->db->get()->row('id');

		

	}



	public function get_admin_id_from_username($email,$table) {

		

		$this->db->select('id');

		$this->db->from($table);

		$this->db->where('email', $email);



		return $this->db->get()->row('id');

		

	}

    public function get_partner_id_from_email($email,$table) {

	
		$this->db->select('id');

		$this->db->from($table);

		$this->db->where('partner_email', $email);

		return $this->db->get()->row('id');

	
	}



	public function get_user_id_from_email($email,$table) {

	
		$this->db->select('id');

		$this->db->from($table);

		$this->db->where('email', $email);

		return $this->db->get()->row('id');

	
	}



	public function get_user_id_from_phone($phone,$table) {

		

		$this->db->select('id');

		$this->db->from($table);

		$this->db->where('phone', $phone);



		return $this->db->get()->row('id');

		

	}

    public function get_partner_id_from_phone($phone,$table) {

		

		$this->db->select('id');

		$this->db->from($table);

		$this->db->where('partner_phone', $phone);

		return $this->db->get()->row('id');

		

	}



	public function get_user($user_id,$table) {

		

		$this->db->from($table);

		$this->db->where('id', $user_id);

		return $this->db->get()->row();

		

	}



	public function hash_password($password) {

		

		return password_hash($password, PASSWORD_BCRYPT);

		

	}

	



	public function verify_password_hash($password, $hash) {
		return password_verify($password, $hash);
	}

	public function SendEmail($email,$subject,$message)

	{

		$protocol = 'smtp';
        $smtp_host = 'ssl://mail.ruphids.co.ke';
        $smtp_port = '465';
        $smtp_user = 'contact@ruphids.co.ke';
        $smtp_pass = 'Sam200010';
        $mailtype = 'Html';
		$config = array(
			'protocol' => $protocol,
			'smtp_host' => $smtp_host,
			'smtp_port' => $smtp_port,
			'smtp_user' => $smtp_user,
			'smtp_pass' => $smtp_pass,
			'mailtype' => $mailtype,
			'charset' => 'utf-8',
			'newline'   => "\r\n"

		);



		$this->load->library('email');

		$this->email->initialize($config);



		$this->email->from($smtp_user);

		$this->email->to($email);

		$this->email->subject($subject);

		$this->email->message($message);



		if ($this->email->send()) {

			$data = array(

				'receiver' => $email,

				'message' => $message,

				'created_on' => date('Y-m-d H:i:s'),



			);

		$this->db->insert('outbox', $data);

		return true;

		} else {

		return false;

		}

	}

	

	//Send sms  
    public function sendSMS($mobile,$message)
    { 

        $currentDateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $date  = $currentDateTime->format('Y-m-d H:i:s');
        $phone = preg_replace('/^(?:\+?254|0)?/','254', $mobile);
        $userid = 'stevengewa';
        $password = 'Mindset123.';
        $senderid = 'STEPAK';


        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://smsportal.hostpinnacle.co.ke/SMSApi/send",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "userid=".$userid."&password=".$password."&mobile=".$phone."&msg=".$message."&senderid=".$senderid."&msgType=text&duplicatecheck=true&output=json&sendMethod=quick",
          CURLOPT_HTTPHEADER => array(
            "apikey: 74bab2db35bcedd343974d01b4f48b3cbf9631d3",
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
          ),

        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {

          echo "cURL Error #:" . $err;

        } else {

           $data = array(

			'receiver' => $mobile,

			'message' => $message,

			'created_on' => $date,

    		);

    	   $this->db->insert('outbox', $data);

        }

    }


  public function RecordAction($action)

  {
	  $wallet_id = '';
	  $phone = '';
	  $data = array(
		 'wallet_id' =>$wallet_id,
		 'phone' =>$phone,
		 'action' => $action,
		 'created_on'=> date('Y-m-d H:i:s')

	  );

	  $this->db->insert('audit', $data);

  }

  

  

  public function SaveLoginSession($wallet_id,$phone,$ip_address,$time)
  {

	  $session = $this->OTP(25);

	  $data = array(

		 'session_id' =>$session,

		 'wallet_id'=>$wallet_id,

		 'ip_address'=>$ip_address,

		 'phone'=>$phone,

		 'created_on'=> $time

	  );

	  $this->db->insert('login_session', $data);

	  return $session;

  }


  public function SaveAdminLoginSession($partner_id,$phone,$user_id,$time)
  {

	  $session = $this->Generator(14);

	  $data = array(

		 'session_id' =>$session,

		 'partner_id'=>$partner_id,

		 'user_id'=>$user_id,

		 'phone'=>$phone,

		 'created_on'=> $time

	  );

	  $this->db->insert('admin_login_session', $data);

	  return $session;

  }





    public function Password_Generator($length)
   {

    $string = "";

    $chars = "abcdefghijklmanopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

    $size = strlen($chars);

    for ($i = 0; $i < $length; $i++) {

        $string .= $chars[rand(0, $size - 1)];

    }

    return $string; 

   }





   public function Generator($length)

   {

   $string = "";

   $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

   $size = strlen($chars);

   for ($i = 0; $i < $length; $i++) {

       $string .= $chars[rand(0, $size - 1)];

   }

   return $string; 

   }



   public function OTP($length)

   {

   $string = "";

   $chars = "0123456789";

   $size = strlen($chars);

   for ($i = 0; $i < $length; $i++) {

       $string .= $chars[rand(0, $size - 1)];

   }

   return $string; 

   }

   

   public function DEPOTP($length)

   {

   $string = "";

   $chars = "123456789";

   $size = strlen($chars);

   for ($i = 0; $i < $length; $i++) {

       $string .= $chars[rand(0, $size - 1)];

   }

   return $string; 

   }


   public function getLastTransactionId() {
        $query = $this->db->select('transaction_number')
                        ->order_by('customer_ledger_id', 'DESC')
                        ->limit(1)
                        ->get('customer_ledger');
        
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->transaction_number;
        } else {
            return false;
        }
    }

    public function getLastPartnerTransactionId() {
        $query = $this->db->select('transaction_number')
                        ->order_by('partner_ledger_id', 'DESC')
                        ->limit(1)
                        ->get('partner_ledger');
        
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->transaction_number;
        } else {
            // return false;
            return 'SKP000001A';
        }
    }

    public function getLastWalletId() {
        $query = $this->db->select('wallet_id')
                            ->order_by('id', 'DESC')
                            ->limit(1)
                            ->get('customers');
            // Check if the query returned any rows            
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->wallet_id;
        } else {
            return false;
        }
    }


    public function auth_session($session_id,$currentTime,$timeframe)
   {
        if(empty($session_id))
        {
            $response['status'] = 'fail';
            $response['message'] = 'session_id required';
			$response['response'] = 0;
        }
        else if(empty($currentTime))
        {
            $response['status'] = 'fail';
            $response['message'] = 'currentTime required';
			$response['response'] = 0;

        }
        else if(empty($timeframe))
        {
            $response['status'] = 'fail';
            $response['message'] = 'timeframe required';
			$response['response'] = 0;

        }
        else
        {
            $session_table = 'login_session';
            $session_condition = array('session_id' => $session_id);
            $checksession = $this->SearchByCondition($session_table, $session_condition);
            $loggedtime = $checksession[0]['created_on'];
            $loggedTimestamp = strtotime($loggedtime);
            $currentTimestamp = strtotime($currentTime);
            $timediff = $currentTimestamp - $loggedTimestamp;
            if (($timediff) >  $timeframe) {
                $response['status'] = 'fail';
                $response['message'] = 'User logged out';
                $response['response'] = 0;

            }
            else if (!empty($checksession) && $checksession[0]['session_id'] == $session_id) {
                $logged_wallet = $checksession[0]['wallet_id'];
                $senderWalletID   = $logged_wallet;
                $senderSummary = $this->customer_transection_summary($senderWalletID);
                $senderTotalCredit = (float) str_replace(',', '', number_format($senderSummary[0][0]['total_credit'], 2));
                $senderTotalDebit = (float) str_replace(',', '', number_format($senderSummary[1][0]['total_debit'], 2));
                $senderTotalBalanceKes = $senderTotalCredit - $senderTotalDebit;
                $senderTotalBalanceKes = str_replace(',', '', number_format($senderTotalBalanceKes, 2));                
                $condition1 = array('wallet_id'=>$senderWalletID);
                $sender_details = $this->SearchByCondition('customers',$condition1);
                $user_transactions = $this->SearchByCondition('customer_ledger',$condition1);
                $sender_phone =  $sender_details[0]['phone'];
                $sender_wallet =  $sender_details[0]['wallet_id'];
                $created_on =  $sender_details[0]['created_on'];
                $account_number =  $sender_details[0]['account_number'];
                $user_response = array(
                    'wallet_id'=>$sender_wallet,
                    'phone'=>$sender_phone,
                    'created_on'=>$created_on,
                    'deriv_cr_number'=>$account_number,
                    'total_credit'=>$senderTotalCredit,
                    'total_debit'=>$senderTotalDebit,
                    'total_balance'=>$senderTotalBalanceKes,
                    'transactions'=>$user_transactions,
                );
                $response['status'] = 'success';
                $response['message'] = 'User logged details';
                $response['response'] = 1;
                $response['data'] = $user_response;
            }
            else if (!empty($checksession) && $checksession[0]['session_id'] != $session_id) {
                $response['status'] = 'error';
                $response['message'] = 'Invalid Authentication';
                $response['response'] = 0;
            }
        }
		return $response;
   }

    public function partner_auth($token,$currentTime,$timeframe)
   {
        if(empty($token))
        {
            $response['status'] = 'fail';
            $response['message'] = 'token required';
            $response['data'] = null;
        }
        else if(empty($currentTime))
        {
            $response['status'] = 'fail';
            $response['message'] = 'currentTime required';
            $response['data'] = null;
        }
        else if(empty($timeframe))
        {
            $response['status'] = 'fail';
            $response['message'] = 'timeframe required';
            $response['data'] = null;
        }
        else
        {
        $session_table = 'admin_login_session';
		$session_condition = array('session_id' => $token);
		$checksession = $this->SearchByCondition($session_table, $session_condition);
        $logged_session = $checksession[0]['session_id'];
		$loggedtime = $checksession[0]['created_on'];
		$loggedTimestamp = strtotime($loggedtime);
		$currentTimestamp = $currentTime;
		$timediff = $currentTimestamp - $loggedTimestamp;
		if (($timediff) >  $timeframe) {
			$response['status'] = 'fail';
			$response['message'] = 'token expired';
		}
		else if (!empty($checksession) &&  $logged_session == $token) {
			$partners_id = $checksession[0]['partner_id'];
			$user_id = $checksession[0]['user_id'];
			$user_response = array(
				'partner_id'=>$partners_id,
				'user_id'=>$user_id,
			 );
			 $response['status'] = 'success';
			 $response['message'] = 'valid token'; 
			 $response['data'] = $user_response;
		}
        else{
            $response['status'] = 'fail';
            $response['message'] = 'something went wrong'; 
        }
        }
		return $response;	
   }

    public function UserAccount($user_wallet)
    {
       $senderWalletID   = $user_wallet;
       $senderSummary = $this->customer_transection_summary($senderWalletID);
       $senderTotalCredit = (float) str_replace(',', '', number_format($senderSummary[0][0]['total_credit'], 2));
       $senderTotalDebit = (float) str_replace(',', '', number_format($senderSummary[1][0]['total_debit'], 2));
       $senderTotalBalanceKes = $senderTotalCredit - $senderTotalDebit;
       $senderTotalBalanceKes = str_replace(',', '', number_format($senderTotalBalanceKes, 2));
       $condition1 = array('wallet_id'=>$senderWalletID);
       $sender_details = $this->SearchByCondition('customers',$condition1);
       if(empty($sender_details))
       {
            $response['status'] = 'error';
            $response['message'] = 'User not found';
            $response['user'] = 0;
       }
       else
       {
        $user_transactions = $this->SearchByCondition('customer_ledger',$condition1);
        $sender_phone =  $sender_details[0]['phone'];
        $sender_wallet =  $sender_details[0]['wallet_id'];
        $created_on =  $sender_details[0]['created_on'];
        $account_number =  $sender_details[0]['account_number'];
        $response =  array(
            'wallet_id'=>$sender_wallet,
            'user'=>1,
            'phone'=>$sender_phone,
            'created_on'=>$created_on,
            'deriv_cr_number'=>$account_number,
            'total_credit'=>$senderTotalCredit,
            'total_debit'=>$senderTotalDebit,
            'total_balance'=>$senderTotalBalanceKes,
            'transactions'=>$user_transactions,
         );
       }
       return $response;
   }

   public function AgentsAccount($agent_wallet)
   {
       $senderWalletID   = $agent_wallet;
       $senderSummary = $this->customer_transection_summary($senderWalletID);
       $senderTotalCredit = (float) str_replace(',', '', number_format($senderSummary[0][0]['total_credit'], 2));
       $senderTotalDebit = (float) str_replace(',', '', number_format($senderSummary[1][0]['total_debit'], 2));
       $senderTotalBalanceKes = $senderTotalCredit - $senderTotalDebit;
       $senderTotalBalanceKes = str_replace(',', '', number_format($senderTotalBalanceKes, 2));
       $condition = array('wallet_id'=>$senderWalletID,'agent'=>1);
       $condition1 = array('wallet_id'=>$senderWalletID);
       $sender_details = $this->SearchByCondition('customers',$condition);
       if(empty($sender_details))
       {
            $response['status'] = 'error';
            $response['message'] = 'agent not found';
            $response['agent'] = 0;
            $response['response'] = 0;
       }
       else{
        $user_transactions = $this->SearchByCondition('customer_ledger',$condition1);
        $sender_phone =  $sender_details[0]['phone'];
        $agent =  $sender_details[0]['agent'];
        $sender_wallet =  $sender_details[0]['wallet_id'];
        $created_on =  $sender_details[0]['created_on'];
        $account_number =  $sender_details[0]['account_number'];
        $response =  array(
            'wallet_id'=>$sender_wallet,
            'agent'=>$agent,
            'phone'=>$sender_phone,
            'created_on'=>$created_on,
            'deriv_cr_number'=>$account_number,
            'total_credit'=>$senderTotalCredit,
            'total_debit'=>$senderTotalDebit,
            'total_balance'=>$senderTotalBalanceKes,
            'transactions'=>$user_transactions,
            'response'=>1,
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
        if(!empty($sender_details[0]['partner_id']) || $sender_details[0]['partner_id'] != NULL || $sender_details[0]['partner_id'] != '')
        {
            $partner_phone =  $sender_details[0]['partner_phone'];
            $sender_name =  $sender_details[0]['partner_name'];
            $sender_partner_id =  $sender_details[0]['partner_id'];
            $partner_created_on =  $sender_details[0]['partner_created_on'];
            $data = array(
                'partner_id'=>$sender_partner_id,
                'partner_name'=>$sender_name,
                'partner_phone'=>$partner_phone,
                'created_on'=>$partner_created_on,
                );
            $response['status'] = 'success';
            $response['message'] = 'partner data';
            $response['response'] = 1;
            $response['data'] = $data;
        }
        else
        {
            http_response_code(401); // Bad Request
            $response['status'] = 'error';
            $response['message'] = 'partner not found';
            $response['response'] = 0;
        }
        return $response;
    }

    public function SystemAudit($partner_id)
   {
        $this->db->select('a.*,b.names,b.phone,b.email');
        $this->db->from('system_audit a'); 
        $this->db->join('users b', 'b.id = a.user_id');  
        $this->db->where('a.partner_id',$partner_id);
        $query = $this->db->get(); 
        if($query->num_rows() != 0)
        {
            return $query->result_array();
        }
        else
        {
            return false;
        }
   }


    public function SaveSystemAudit($partner_id,$user_id,$ip_address,$mac_address,$action,$audit_time)
    {
	  $data = array(
        'partner_id' =>$partner_id,
        'user_id' =>$user_id,
        'ip_address' => $ip_address,
        'mac_address' => $mac_address,
        'action' => $action,
        'audit_time' => $audit_time,
	  );
	  $this->db->insert('system_audit', $data);

    }


    //PARTNER MODULE STARTS HERE 
    public function get_partners_customers($partner_id) 
    {
        // Select distinct wallet_id to count unique customers
        $this->db->distinct();
        $this->db->select('wallet_id');
        $this->db->from('customer_ledger');
        $this->db->where('partner_id', $partner_id);
        $query = $this->db->get();
        return $query->num_rows();
    }

    public function total_partner_cr($partner_id)
    {
        $this->db->select('COALESCE(SUM(trans_amount), 0) AS total_cr', false);
        $this->db->where('partner_id', $partner_id);
        $this->db->where('cr_dr', 'cr');
        $query = $this->db->get('partner_ledger');
        return $query->row()->total_cr;
    }
  
    public function total_partner_dr($partner_id)
    {
        $this->db->select('COALESCE(SUM(trans_amount), 0) AS total_dr', false);
        $this->db->where('partner_id', $partner_id);
        $this->db->where('cr_dr', 'dr');
        $query = $this->db->get('partner_ledger');
        return $query->row()->total_dr;
    }
  
    public function total_partner_earning($partner_id)
    {
        $this->db->select('COALESCE(SUM(charge), 0) AS total_earning', false);
        $this->db->where('partner_id', $partner_id);
        $query = $this->db->get('partner_ledger');
        return $query->row()->total_earning;
    }

    public function get_partner_earning_chart($partner_id)
    {
        $this->db->select("DATE_FORMAT(trans_date, '%Y-%m') AS month", false);
        $this->db->select("SUM(charge) AS earnings", false);
        $this->db->from('partner_ledger');
        $this->db->where('partner_id', $partner_id);
        $this->db->group_by('month');
        $this->db->order_by('month', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }


    public function get_partner_customers_chart($partner_id)
    {
        $this->db->select("DATE_FORMAT(created_at, '%Y-%m') AS month", false);
        $this->db->select("COUNT(DISTINCT wallet_id) AS customer_count", false);
        $this->db->from('customer_ledger');
        $this->db->where('partner_id', $partner_id);
        $this->db->group_by('month');
        $this->db->order_by('month', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }

    public function get_partner_financial_chart($partner_id)
    {
        $this->db->select("DATE_FORMAT(trans_date, '%Y-%m') AS month", false);
        $this->db->select("SUM(CASE WHEN cr_dr = 'cr' THEN trans_amount ELSE 0 END) AS credits", false);
        $this->db->select("SUM(CASE WHEN cr_dr = 'dr' THEN trans_amount ELSE 0 END) AS debits", false);
        $this->db->select("SUM(charge) AS earnings", false);
        $this->db->from('partner_ledger');
        $this->db->where('partner_id', $partner_id);
        $this->db->group_by('month');
        $this->db->order_by('month', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }

    public function total_gifting()
    {
        $this->db->select('ROUND(SUM(paid_amount),2) AS total_gifts');
        $this->db->from('customer_ledger');
        $this->db->where(array('deriv' => 14,'cr_dr'=>'dr'));
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $result = $query->row()->total_gifts;
        } else {
            $result = 0;
        }
        return $result;
    }



    public function Search_gift()
    {
        $this->db->select('*');
        $this->db->from('customer_ledger');
        $this->db->where(array('deriv' => 14,'cr_dr'=>'dr'));
        $this->db->order_by('customer_ledger_id DESC');
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

}



