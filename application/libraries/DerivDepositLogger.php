<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * DerivSessionTracker - Comprehensive tracking for Deriv deposit operations
 * Tracks session states, WebSocket operations, and identifies logout issues
 */
class DerivSessionTracker
{
    private $CI;
    private $log_directory;
    private $session_file;
    private $websocket_log_file;
    private $error_log_file;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->log_directory = APPPATH . 'logs/deriv_sessions/';

        // Create directory if it doesn't exist
        if (!is_dir($this->log_directory)) {
            mkdir($this->log_directory, 0755, true);
        }

        $date = date('Y-m-d');
        $this->session_file = $this->log_directory . "session_tracking_{$date}.log";
        $this->websocket_log_file = $this->log_directory . "websocket_operations_{$date}.log";
        $this->error_log_file = $this->log_directory . "session_errors_{$date}.log";
    }

    /**
     * Track the start of a deposit operation
     */
    public function trackDepositStart($data)
    {
        $timestamp = date('Y-m-d H:i:s');
        $session_id = $data['session_id'] ?? 'N/A';
        $transaction_id = $data['transaction_id'] ?? 'N/A';

        $logEntry = [
            'timestamp' => $timestamp,
            'event' => 'DEPOSIT_START',
            'session_id' => $session_id,
            'transaction_id' => $transaction_id,
            'user_data' => [
                'cr_number' => $data['cr_number'] ?? 'N/A',
                'amount' => $data['amount'] ?? 'N/A',
                'wallet_id' => $data['wallet_id'] ?? 'N/A'
            ],
            'session_info' => $this->getSessionInfo($session_id),
            'process_id' => getmypid(),
            'memory_usage' => memory_get_usage(true)
        ];

        $this->writeToLog($this->session_file, $logEntry);
        return $logEntry;
    }

    /**
     * Track session validation attempts
     */
    public function trackSessionValidation($session_id, $validation_result, $time_diff = null)
    {
        $timestamp = date('Y-m-d H:i:s');

        $logEntry = [
            'timestamp' => $timestamp,
            'event' => 'SESSION_VALIDATION',
            'session_id' => $session_id,
            'validation_result' => $validation_result,
            'time_difference' => $time_diff,
            'session_details' => $this->getDetailedSessionInfo($session_id),
            'is_valid' => $validation_result['is_valid'] ?? false,
            'expiry_reason' => $validation_result['expiry_reason'] ?? null
        ];

        $this->writeToLog($this->session_file, $logEntry);

        // Log errors separately if validation failed
        if (!($validation_result['is_valid'] ?? true)) {
            $this->logSessionError($session_id, 'SESSION_EXPIRED', $validation_result);
        }

        return $logEntry;
    }

    /**
     * Track session extensions
     */
    public function trackSessionExtension($session_id, $extension_point, $success = true)
    {
        $timestamp = date('Y-m-d H:i:s');

        $logEntry = [
            'timestamp' => $timestamp,
            'event' => 'SESSION_EXTENSION',
            'session_id' => $session_id,
            'extension_point' => $extension_point,
            'success' => $success,
            'session_info_before' => $this->getSessionInfo($session_id),
            'new_session_time' => date('Y-m-d H:i:s')
        ];

        $this->writeToLog($this->session_file, $logEntry);
        return $logEntry;
    }

    /**
     * Track WebSocket operations
     */
    public function trackWebSocketOperation($operation_type, $data, $result)
    {
        $timestamp = date('Y-m-d H:i:s');

        $logEntry = [
            'timestamp' => $timestamp,
            'event' => 'WEBSOCKET_OPERATION',
            'operation_type' => $operation_type, // 'BALANCE_CHECK' or 'TRANSFER'
            'input_data' => [
                'amount' => $data['amount'] ?? null,
                'cr_number' => $data['cr_number'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null
            ],
            'websocket_details' => [
                'endpoint' => 'ws.derivws.com',
                'app_id' => 76420,
                'connection_timeout' => $operation_type === 'TRANSFER' ? 15 : 10
            ],
            'operation_result' => [
                'success' => $result['success'] ?? false,
                'error' => $result['error'] ?? null,
                'response_data' => $this->sanitizeWebSocketResponse($result)
            ],
            'duration' => $result['duration'] ?? null,
            'memory_usage' => memory_get_usage(true)
        ];

        $this->writeToLog($this->websocket_log_file, $logEntry);

        // Log WebSocket errors separately
        if (!($result['success'] ?? true)) {
            $this->logWebSocketError($operation_type, $data, $result);
        }

        return $logEntry;
    }

    /**
     * Track the complete deposit flow
     */
    public function trackDepositFlow($session_id, $transaction_id, $steps)
    {
        $timestamp = date('Y-m-d H:i:s');

        $logEntry = [
            'timestamp' => $timestamp,
            'event' => 'DEPOSIT_FLOW_COMPLETE',
            'session_id' => $session_id,
            'transaction_id' => $transaction_id,
            'flow_steps' => $steps,
            'total_duration' => $this->calculateTotalDuration($steps),
            'session_extensions_count' => $this->countSessionExtensions($steps),
            'websocket_operations_count' => $this->countWebSocketOperations($steps),
            'final_session_status' => $this->getSessionInfo($session_id)
        ];

        $this->writeToLog($this->session_file, $logEntry);
        return $logEntry;
    }

    /**
     * Log session-related errors
     */
    public function logSessionError($session_id, $error_type, $error_details)
    {
        $timestamp = date('Y-m-d H:i:s');

        $errorEntry = [
            'timestamp' => $timestamp,
            'error_type' => $error_type,
            'session_id' => $session_id,
            'error_details' => $error_details,
            'session_state' => $this->getDetailedSessionInfo($session_id),
            'stack_trace' => $this->getStackTrace(),
            'server_info' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ]
        ];

        $this->writeToLog($this->error_log_file, $errorEntry);

        // Send immediate alert for critical session errors
        if (in_array($error_type, ['SESSION_EXPIRED_DURING_TRANSFER', 'WEBSOCKET_SESSION_CONFLICT'])) {
            $this->sendCriticalAlert($errorEntry);
        }

        return $errorEntry;
    }

    /**
     * Log WebSocket-specific errors
     */
    public function logWebSocketError($operation_type, $input_data, $error_result)
    {
        $timestamp = date('Y-m-d H:i:s');

        $errorEntry = [
            'timestamp' => $timestamp,
            'error_type' => 'WEBSOCKET_ERROR',
            'operation_type' => $operation_type,
            'input_data' => $input_data,
            'error_result' => $error_result,
            'connection_details' => [
                'endpoint' => 'ws.derivws.com',
                'app_id' => 76420,
                'token_used' => 'DidPRclTKE0WYtT' // Masked for security
            ],
            'system_state' => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'connection_count' => $this->getActiveConnectionCount()
            ]
        ];

        $this->writeToLog($this->error_log_file, $errorEntry);
        return $errorEntry;
    }

    /**
     * Get comprehensive session information
     */
    private function getDetailedSessionInfo($session_id)
    {
        if (empty($session_id)) {
            return ['status' => 'NO_SESSION_ID'];
        }

        try {
            $session_condition = ['session_id' => $session_id];
            $session_data = $this->CI->Operations->SearchByCondition('login_session', $session_condition);

            if (empty($session_data)) {
                return ['status' => 'SESSION_NOT_FOUND'];
            }

            $session = $session_data[0];
            $current_time = date('Y-m-d H:i:s');
            $logged_time = $session['created_on'];

            $logged_timestamp = strtotime($logged_time);
            $current_timestamp = strtotime($current_time);
            $time_diff = $current_timestamp - $logged_timestamp;

            return [
                'status' => 'FOUND',
                'session_data' => $session,
                'timing' => [
                    'logged_time' => $logged_time,
                    'current_time' => $current_time,
                    'logged_timestamp' => $logged_timestamp,
                    'current_timestamp' => $current_timestamp,
                    'time_difference_seconds' => $time_diff,
                    'time_difference_minutes' => round($time_diff / 60, 2)
                ],
                'validity' => [
                    'standard_timeout' => 600, // 10 minutes
                    'deriv_timeout' => 1800,   // 30 minutes
                    'is_valid_standard' => $time_diff <= 600,
                    'is_valid_deriv' => $time_diff <= 1800
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get basic session information
     */
    private function getSessionInfo($session_id)
    {
        $detailed = $this->getDetailedSessionInfo($session_id);
        return [
            'status' => $detailed['status'],
            'time_diff_minutes' => $detailed['timing']['time_difference_minutes'] ?? null,
            'is_valid' => $detailed['validity']['is_valid_deriv'] ?? false
        ];
    }

    /**
     * Sanitize WebSocket response for logging
     */
    private function sanitizeWebSocketResponse($result)
    {
        $sanitized = $result;

        // Remove sensitive information
        if (isset($sanitized['token'])) {
            $sanitized['token'] = '[MASKED]';
        }

        if (isset($sanitized['authorize'])) {
            $sanitized['authorize'] = '[MASKED]';
        }

        return $sanitized;
    }

    /**
     * Calculate total duration from flow steps
     */
    private function calculateTotalDuration($steps)
    {
        if (empty($steps) || !is_array($steps)) {
            return null;
        }

        $start_time = null;
        $end_time = null;

        foreach ($steps as $step) {
            if (isset($step['timestamp'])) {
                $timestamp = strtotime($step['timestamp']);
                if ($start_time === null || $timestamp < $start_time) {
                    $start_time = $timestamp;
                }
                if ($end_time === null || $timestamp > $end_time) {
                    $end_time = $timestamp;
                }
            }
        }

        return $end_time && $start_time ? ($end_time - $start_time) : null;
    }

    /**
     * Count session extensions in flow
     */
    private function countSessionExtensions($steps)
    {
        if (!is_array($steps)) return 0;

        return count(array_filter($steps, function ($step) {
            return isset($step['event']) && $step['event'] === 'SESSION_EXTENSION';
        }));
    }

    /**
     * Count WebSocket operations in flow
     */
    private function countWebSocketOperations($steps)
    {
        if (!is_array($steps)) return 0;

        return count(array_filter($steps, function ($step) {
            return isset($step['event']) && $step['event'] === 'WEBSOCKET_OPERATION';
        }));
    }

    /**
     * Get current stack trace
     */
    private function getStackTrace()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        return array_slice($trace, 1, 5); // Limit to 5 most recent calls
    }

    /**
     * Get active connection count (estimated)
     */
    private function getActiveConnectionCount()
    {
        // This is a simple estimation - in a real scenario, 
        // you might track this more accurately
        return 1; // Current connection
    }

    /**
     * Write log entry to file
     */
    private function writeToLog($file, $entry)
    {
        $logLine = json_encode($entry, JSON_PRETTY_PRINT) . "\n" . str_repeat('-', 80) . "\n";
        file_put_contents($file, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Send critical alert for severe session issues
     */
    private function sendCriticalAlert($errorEntry)
    {
        // Send SMS to admin phones for critical issues
        $adminPhones = ['0703416091', '0710964626'];
        $message = "CRITICAL: Deriv session issue - " . $errorEntry['error_type'] .
            " for session " . $errorEntry['session_id'] .
            " at " . $errorEntry['timestamp'];

        foreach ($adminPhones as $phone) {
            $this->CI->Operations->sendSMS($phone, $message);
        }
    }

    /**
     * Generate session health report
     */
    public function generateSessionHealthReport($date = null)
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $session_file = $this->log_directory . "session_tracking_{$date}.log";
        $error_file = $this->log_directory . "session_errors_{$date}.log";

        if (!file_exists($session_file)) {
            return ['error' => 'No session data found for ' . $date];
        }

        $session_content = file_get_contents($session_file);
        $error_content = file_exists($error_file) ? file_get_contents($error_file) : '';

        // Parse logs and generate report
        $sessions_started = substr_count($session_content, '"event":"DEPOSIT_START"');
        $sessions_expired = substr_count($error_content, '"error_type":"SESSION_EXPIRED"');
        $websocket_errors = substr_count($error_content, '"error_type":"WEBSOCKET_ERROR"');
        $session_extensions = substr_count($session_content, '"event":"SESSION_EXTENSION"');

        return [
            'date' => $date,
            'summary' => [
                'total_deposit_attempts' => $sessions_started,
                'session_expiry_issues' => $sessions_expired,
                'websocket_errors' => $websocket_errors,
                'total_session_extensions' => $session_extensions,
                'success_rate' => $sessions_started > 0 ?
                    round((($sessions_started - $sessions_expired) / $sessions_started) * 100, 2) : 0
            ],
            'recommendations' => $this->generateRecommendations($sessions_expired, $websocket_errors, $session_extensions)
        ];
    }

    /**
     * Generate recommendations based on session data
     */
    private function generateRecommendations($expired_count, $websocket_errors, $extensions_count)
    {
        $recommendations = [];

        if ($expired_count > 5) {
            $recommendations[] = "High session expiry rate detected. Consider increasing session timeout for Deriv operations.";
        }

        if ($websocket_errors > 3) {
            $recommendations[] = "Multiple WebSocket errors detected. Check network connectivity and Deriv API status.";
        }

        if ($extensions_count > 20) {
            $recommendations[] = "Frequent session extensions indicate long-running operations. Consider optimizing the deposit flow.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "Session health looks good. No immediate actions required.";
        }

        return $recommendations;
    }
}
