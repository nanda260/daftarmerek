<?php
// Ganti dengan key rahasia Anda (32 karakter)
define('ENCRYPTION_KEY', 'bidang-industri');

function encryptId($id) {
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($id, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptId($encrypted) {
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
}
?>