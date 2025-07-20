<?php
declare(strict_types=1);

/**
 * Deriv Agent Balance Checker with HTML Output
 */

require_once 'vendor/autoload.php';
use WebSocket\Client;
use WebSocket\TimeoutException;
use JsonException;

class DerivBalanceChecker
{
    private const APP_ID = 76420;
    private const ENDPOINT = 'ws.derivws.com';
    private const TIMEOUT = 10;
    private const LOW_BALANCE_THRESHOLD = 100;

    private string $token;
    private Client $client;
    private array $diagnostics = [];
    private array $apiLog = [];
    private array $connectionInfo = [];
    private array $balanceInfo = [];

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->initialize();
    }

    private function initialize(): void
    {
        date_default_timezone_set('Africa/Nairobi');
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }

    public function run(): string
    {
        try {
            $this->runDiagnostics();
            $this->checkBalance();
            return $this->generateHtmlReport();
            
        } catch (Exception $e) {
            return $this->generateErrorHtml($e);
        }
    }

    private function connect(): void
    {
        $url = sprintf('wss://%s/websockets/v3?app_id=%d', self::ENDPOINT, self::APP_ID);
        
        $this->connectionInfo = [
            'url' => $url,
            'status' => 'pending'
        ];

        $this->client = new Client($url, [
            'timeout' => self::TIMEOUT
        ]);
        
        $this->connectionInfo['status'] = 'connected';
    }

    private function authorize(): array
    {
        $request = [
            "authorize" => $this->token,
            "req_id" => 1
        ];
        
        $this->logApiRequest('Authorization', $request);
        $this->sendRequest($request);
        $response = $this->receiveResponse();
        
        if (!isset($response['authorize'])) {
            throw new RuntimeException("Authorization failed: " . ($response['error']['message'] ?? 'Unknown error'));
        }
        
        $this->balanceInfo = [
            'loginid' => $response['authorize']['loginid'],
            'currency' => $response['authorize']['currency'],
            'balance' => $response['authorize']['balance']
        ];
        
        return $response;
    }

    private function getBalance(): array
    {
        $request = [
            "balance" => 1,
            "req_id" => 2
        ];
        
        $this->logApiRequest('Balance Check', $request);
        $this->sendRequest($request);
        $response = $this->receiveResponse();
        
        if (!isset($response['balance'])) {
            throw new RuntimeException("Balance check failed: " . ($response['error']['message'] ?? 'Unknown error'));
        }
        
        $this->balanceInfo = array_merge($this->balanceInfo, $response['balance']);
        return $response['balance'];
    }

    private function checkBalance(): void
    {
        $this->connect();
        $this->authorize();
        $this->getBalance();
        $this->client->close();
    }

    private function runDiagnostics(): void
    {
        $this->diagnostics = [
            'WebSocket Client' => [
                'status' => class_exists(Client::class),
                'details' => 'textalk/websocket-client'
            ],
            'cURL' => [
                'status' => function_exists('curl_init'),
                'details' => function_exists('curl_version') ? curl_version()['version'] : 'Not available'
            ],
            'JSON Support' => [
                'status' => function_exists('json_encode') && function_exists('json_decode'),
                'details' => 'PHP ' . PHP_VERSION
            ],
            'Internet Connection' => [
                'status' => $this->checkInternetConnection(),
                'details' => $this->checkInternetConnection() ? 'Active connection' : 'No internet access'
            ]
        ];
    }

    private function checkInternetConnection(): bool
    {
        $connected = @fsockopen("www.google.com", 80, $errno, $errstr, 5);
        if ($connected) {
            fclose($connected);
            return true;
        }
        return false;
    }

    private function sendRequest(array $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $this->client->send($json);
    }

    private function receiveResponse(): array
    {
        $response = $this->client->receive();
        
        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid JSON response: " . $e->getMessage());
        }
    }

    private function logApiRequest(string $step, array $request): void
    {
        $this->apiLog[] = [
            'step' => $step,
            'request' => $request,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function generateHtmlReport(): string
    {
        $balanceStatus = $this->balanceInfo['balance'] >= self::LOW_BALANCE_THRESHOLD ? 'success' : 'warning';
        $balanceStatusText = $balanceStatus === 'success' ? 
            'Sufficient for transactions' : 'Low balance warning';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deriv Agent Balance Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            color: #333;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        h1, h2 {
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 3px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #e6f7ff;
        }
        .success {
            color: #27ae60;
            font-weight: bold;
        }
        .warning {
            color: #f39c12;
            font-weight: bold;
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-success {
            background-color: #27ae60;
        }
        .status-warning {
            background-color: #f39c12;
        }
        .status-error {
            background-color: #e74c3c;
        }
        .summary-card {
            background: white;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        code {
            background: #f4f4f4;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Deriv Agent Balance Report</h1>
        <p>Generated on: <?= date('Y-m-d H:i:s') ?></p>
        
        <div class="summary-card">
            <h2>Connection Summary</h2>
            <table>
                <tr>
                    <th>Parameter</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>WebSocket Connection</td>
                    <td><?= htmlspecialchars($this->connectionInfo['url']) ?></td>
                    <td>
                        <span class="status-indicator status-success"></span>
                        <span class="success">Connected</span>
                    </td>
                </tr>
                <tr>
                    <td>Authorization</td>
                    <td>Token-based</td>
                    <td>
                        <span class="status-indicator status-success"></span>
                        <span class="success">Authenticated</span>
                    </td>
                </tr>
                <tr>
                    <td>App ID</td>
                    <td><?= self::APP_ID ?></td>
                    <td>
                        <span class="status-indicator status-success"></span>
                        <span class="success">Valid</span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="summary-card">
            <h2>Balance Information</h2>
            <table>
                <tr>
                    <th>Account Detail</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Account ID</td>
                    <td><?= htmlspecialchars($this->balanceInfo['loginid']) ?></td>
                </tr>
                <tr>
                    <td>Current Balance</td>
                    <td><?= number_format($this->balanceInfo['balance'], 2) ?> <?= $this->balanceInfo['currency'] ?></td>
                </tr>
                <tr>
                    <td>Balance Status</td>
                    <td>
                        <span class="status-indicator status-<?= $balanceStatus ?>"></span>
                        <span class="<?= $balanceStatus ?>"><?= $balanceStatusText ?></span>
                    </td>
                </tr>
                <tr>
                    <td>Last Updated</td>
                    <td><?= date('Y-m-d H:i:s') ?></td>
                </tr>
            </table>
        </div>

        <div class="summary-card">
            <h2>System Diagnostics</h2>
            <table>
                <tr>
                    <th>Check</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
                <?php foreach ($this->diagnostics as $name => $check): ?>
                <tr>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td>
                        <span class="status-indicator status-<?= $check['status'] ? 'success' : 'error' ?>"></span>
                        <span class="<?= $check['status'] ? 'success' : 'error' ?>">
                            <?= $check['status'] ? 'Available' : 'Not Available' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($check['details']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="summary-card">
            <h2>API Request Log</h2>
            <table>
                <tr>
                    <th>Step</th>
                    <th>Request</th>
                    <th>Response</th>
                </tr>
                <?php foreach ($this->apiLog as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['step']) ?></td>
                    <td><code><?= htmlspecialchars(json_encode($log['request'])) ?></code></td>
                    <td>
                        <?php if ($log['step'] === 'Authorization'): ?>
                        Success - Account <?= htmlspecialchars($this->balanceInfo['loginid']) ?>
                        <?php else: ?>
                        Balance: <?= number_format($this->balanceInfo['balance'], 2) ?> <?= $this->balanceInfo['currency'] ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    private function generateErrorHtml(Exception $e): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Deriv Agent Balance Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 40px;
            color: #333;
        }
        .error-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #e74c3c;
            border-radius: 5px;
            background-color: #fdecea;
        }
        h1 {
            color: #e74c3c;
        }
        .error-details {
            background: white;
            padding: 15px;
            border-radius: 3px;
            margin-top: 20px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Deriv Balance Check Failed</h1>
        <p>The system encountered an error while trying to check your Deriv account balance.</p>
        
        <div class="error-details">
            <strong>Error Message:</strong><br>
            <?= htmlspecialchars($e->getMessage()) ?>
            
            <br><br>
            
            <strong>Timestamp:</strong><br>
            <?= date('Y-m-d H:i:s') ?>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}

// Execution
try {
    $checker = new DerivBalanceChecker('DidPRclTKE0WYtT');
    echo $checker->run();
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage();
    exit(1);
}