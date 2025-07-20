<?php
// test_websocket.php
require __DIR__ . '/vendor/autoload.php';

function testWebSocketConnection() {
    $url = "wss://echo.websocket.org";
    
    try {
        $client = new WebSocket\Client($url);
        $client->send('Test message');
        $response = $client->receive();
        $client->close();
        
        echo "WebSocket test successful! Response: " . $response;
        return true;
    } catch (Exception $e) {
        echo "WebSocket test failed: " . $e->getMessage();
        return false;
    }
}

testWebSocketConnection();