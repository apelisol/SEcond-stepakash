<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/

//USER APP
$route['default_controller'] = 'welcome';
$route['signup'] = 'Auth/CreateAccount';
$route['login'] = 'Auth/Login';

// deriv authentication
$route['DerivOAuth'] = 'Auth/DerivOAuth';
$route['DerivCallback'] = 'Auth/DerivCallback';
$route['GetDerivSessionData'] = 'Auth/GetDerivSessionData';

// deriv transactions
$route['deriv_withdraw'] = 'Main/WithdrawFromDeriv';
$route['deriv_deposit'] = 'Main/DepositToDeriv';

// deriv callbacks
$route['deposit_callback'] = 'Deriv/deposit_callback';
$route['transfer_error'] = 'Deriv/transfer_error';
$route['balance_update'] = 'Deriv/balance_update';

// authentication
$route['sendotp'] = 'Auth/sendotp';
$route['verifyotp'] = 'Auth/verifyOtp';
$route['updatepassword'] = 'Auth/updatepassword';
$route['passwordupdate'] = 'Main/passwordupdate';
$route['updatephone'] = 'Main/updatephone';

// mpesa transactions
$route['deposit_mpesa'] = 'Main/DepositFromMpesa';
$route['mpesa_withdraw'] = 'Main/WithdrawToMpesa';
$route['mpesa_deposit'] = 'Main/DepositToDeriv';

// mpesa callbacks
$route['stkresults'] = 'Money/stkresults';
$route['b2c_result'] = 'Money/b2c_result';
$route['mpesa_c2b_results'] = 'Money/mpesa_c2b_results';
$route['validation_url'] = 'Money/validation_url';

// app functionality
$route['home_data'] = 'Main/home';
$route['user_transactions'] = 'Main/transactions';
$route['balance'] = 'Main/balance';
$route['outbox'] = 'Main/outbox';
$route['send_p2p'] = 'Main/StepakashP2P';
$route['mpesa_b2c_test'] = 'Main/Mpesa_b2c_test';
$route['register_url'] = 'Money/register_url';
$route['next_receipt'] = 'Money/next_receipt';
$route['query_receipt'] = 'Main/query_receipt';
$route['pay_now'] = 'Main/pay_now';
$route['send_gift'] = 'Main/send_gift';

//ADMIN 
$route['adminLogin'] = 'Auth/adminLogin';
$route['adminhome'] = 'Main/adminhome';
$route['depositsrequest'] = 'Main/depositsrequest';
$route['withdrawalrequest'] = 'Main/withdrawalrequest';
$route['adminappusers'] = 'Main/adminappusers';
$route['get_user_account'] = 'Main/get_user_account';
$route['adminsystemusers'] = 'Main/adminsystemusers';
$route['viewrate'] = 'Main/viewrate';
$route['setexchange'] = 'Main/setexchange';
$route['get_rates'] = 'Main/get_rates';
$route['create_admin'] = 'Main/AdminCreateAccount';
$route['mpesa_deposits'] = 'Main/mpesa_deposits';
$route['mpesa_withdrawals'] = 'Main/mpesa_withdrawals';
$route['mpesa_withdrawals_transactions'] = 'Main/mpesa_withdrawals_transactions';
$route['b2c_result'] = 'Money/b2c_result';
$route['process_deposit'] = 'Main/process_deporequest';
$route['process_withdrawal'] = 'Main/process_withdrawalrequest';
$route['reject_withdrawal'] = 'Main/reject_withdrawal_request';
$route['deduct_from_wallet'] = 'Main/deduct_from_wallet';
$route['add_user_wallet'] = 'Main/add_user_wallet';
$route['stepakash_debit_report'] = 'Main/stepakash_debit_report';
$route['stepakash_credit_report'] = 'Main/stepakash_credit_report';
$route['crypto_deposit_request'] = 'Main/crypto_deposit_request';
$route['crypto_withdrawal_request'] = 'Main/crypto_withdrawal_request';
$route['app_audit'] = 'Main/app_audit';
$route['update_user_account'] = 'Main/update_user_account';
$route['active_users'] = 'Main/active_users';
$route['cypto_request'] = 'Main/cypto_request';
$route['process_cypto_deposit'] = 'Main/process_cypto_deposit';
$route['process_cypto_withdraw'] = 'Main/process_cypto_withdraw';
$route['reject_cypto_withdraw'] = 'Main/reject_cypto_withdraw';
$route['gift_request'] = 'Main/gift_request';


//AGENTS
$route['set_agent_commission'] = 'Agents/set_agent_commission';
$route['agents_auth'] = 'Agents/agents_auth';
$route['view_service_commission'] = 'Agents/view_service_commission';
$route['withdraw_to_agent'] = 'Agents/withdraw_to_agent';


//BUSINESS MODULE
$route['create_merchant'] = 'Business/create_merchant';
$route['view_merchant'] = 'Business/view_merchant';
$route['generate_token'] = 'Partner/generate_token';
$route['partner_auth'] = 'Partner/partner_auth';
$route['auth'] = 'Partner/user_auth';
$route['dashboard_data'] = 'Partner/dashboard_data';
$route['custom_reports'] = 'Partner/custom_reports';
$route['account_balance'] = 'Partner/account_balance';
$route['create_user'] = 'Partner/create_user';
$route['view_users'] = 'Partner/view_users';
//Accounts
$route['top_up_account'] = 'Partner/top_up_account';
$route['move_funds'] = 'Partner/move_funds';
$route['transfer_funds'] = 'Partner/transfer_funds';
$route['get_partner_account'] = 'Partner/get_partner_account';
$route['float_report'] = 'Partner/float_report';
$route['pending_transfers'] = 'Partner/pending_transfers';
$route['declined_transaction'] = 'Partner/declined_transaction';
$route['review_transaction'] = 'Main/review_transaction';
$route['approve_transfer'] = 'Partner/approve_transfer';
$route['decline_transfer'] = 'Partner/decline_transfer';
$route['test'] = 'Partner/test';
$route['initiate_transaction'] = 'Partner/initiate_transaction';
$route['reverse_transaction'] = 'Partner/reverse_transaction';
$route['verify_transaction'] = 'Partner/verify_transaction';
$route['transaction_report'] = 'Partner/transaction_report';

$route['audit_report'] = 'Partner/audit_report';
$route['logout'] = 'Partner/logout';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
