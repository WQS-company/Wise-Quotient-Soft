<?php

/**
 * Uploads a file to Cloudinary.
 * 
 * @param string $filePath The absolute path or tmp_name to the file being uploaded
 * @param string $folder The folder name in Cloudinary (e.g. "avatars", "projects")
 * @param string $resourceType 'image', 'video', 'raw', or 'auto'
 * @return string|false The secure URL on success, false on failure
 */
function uploadToCloudinary($filePath, $folder = '', $resourceType = 'auto') {
    $cloudName = 'dbrngv7eg';
    $apiKey    = '989193635679214';
    $apiSecret = 'p0LTokA9aOAjAYiSIU8dathFSTk';
    
    $url = "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload";
    
    $timestamp = time();
    
    // Cloudinary requires the signature to be a SHA-1 hash of the sorted parameters (excluding file, api_key, resource_type, cloud_name) + the API secret.
    $signatureString = "";
    if ($folder !== '') {
        $signatureString .= "folder=" . $folder . "&";
    }
    $signatureString .= "timestamp=" . $timestamp . $apiSecret;
    
    $signature = sha1($signatureString);
    
    $cfile = new CURLFile($filePath);
    
    $data = array(
        'file' => $cfile,
        'api_key' => $apiKey,
        'timestamp' => $timestamp,
        'signature' => $signature
    );
    
    if ($folder !== '') {
        $data['folder'] = $folder;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Ignore SSL verification for local dev if necessary
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        return $result['secure_url'] ?? false;
    }
    
    return false;
}
