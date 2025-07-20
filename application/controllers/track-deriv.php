<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Deriv_deposit_logger
{
    private $logFilePath;
    private $transactionId;
    private $sessionId;
    private $walletId;
    private $crNumber;
    private $amount;

    public function __construct($transactionId, $sessionId, $walletId = null, $crNumber = null, $amount = null)
    {
        $this->transactionId = $transactionId;
        $this->sessionId = $sessionId;
        $this->walletId = $walletId;
        $this->crNumber = $crNumber;
        $this->amount = $amount;

        $logDir = APPPATH . 'logs/deriv_deposits/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $this->logFilePath = $logDir . 'deposit_' . date('Y-m-d') . '.log';
    }

    public function log($message, $data = [], $level = 'INFO')
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'transaction_id' => $this->transactionId,
            'session_id' => $this->sessionId,
            'wallet_id' => $this->walletId,
            'cr_number' => $this->crNumber,
            'amount' => $this->amount,
            'message' => $message,
            'data' => $data,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];

        $logEntry = json_encode($logData, JSON_PRETTY_PRINT) . "\n";
        file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function logWebsocketEvent($event, $requestData = null, $responseData = null)
    {
        $this->log("WebSocket " . strtoupper($event), [
            'websocket_event' => $event,
            'request' => $requestData,
            'response' => $responseData,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ], 'WEBSOCKET');
    }

    public function logSessionStatus()
    {
        $sessionData = [
            'session_id' => session_id(),
            'session_data' => $_SESSION,
            'session_status' => session_status(),
            'session_cookie_params' => session_get_cookie_params()
        ];

        $this->log("Session status check", $sessionData, 'DEBUG');
    }

    public function logError($errorMessage, $exception = null)
    {
        $errorData = [
            'error_message' => $errorMessage,
            'exception' => $exception ? [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ] : null,
            'server' => $_SERVER,
            'request' => $_REQUEST
        ];

        $this->log($errorMessage, $errorData, 'ERROR');
    }

    public function logDepositFlow($step, $data = [])
    {
        $this->log("Deposit flow - " . $step, array_merge([
            'current_step' => $step,
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ], $data), 'FLOW');
    }

    public function logBalanceCheck($balanceData)
    {
        $this->log("Agent balance check", [
            'balance' => $balanceData['balance'] ?? null,
            'currency' => $balanceData['currency'] ?? null,
            'success' => $balanceData['success'] ?? false,
            'error' => $balanceData['error'] ?? null
        ], 'BALANCE');
    }

    public function logTransferAttempt($transferData)
    {
        $this->log("Transfer attempt", [
            'transfer_success' => $transferData['success'] ?? false,
            'transaction_id' => $transferData['transaction_id'] ?? null,
            'client_details' => $transferData['client_to_full_name'] ?? null,
            'error' => $transferData['error'] ?? null
        ], 'TRANSFER');
    }

    public function logDatabaseOperation($operation, $table, $condition, $data, $result)
    {
        $this->log("Database operation", [
            'operation' => $operation,
            'table' => $table,
            'condition' => $condition,
            'data' => $data,
            'result' => $result,
            'affected_rows' => $this->db->affected_rows() ?? null,
            'last_query' => $this->db->last_query() ?? null
        ], 'DATABASE');
    }

    public function logCompleteOperation($status, $message)
    {
        $this->log("Operation completed", [
            'final_status' => $status,
            'message' => $message,
            'total_execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ], 'COMPLETE');
    }
}
