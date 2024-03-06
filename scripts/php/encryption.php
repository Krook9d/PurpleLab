<?php
// encryption.php

function encryptData($data, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encryptedData = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encryptedData);
}

function decryptData($dataWithIv, $key) {
    $dataWithIv = base64_decode($dataWithIv);
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($dataWithIv, 0, $ivLength);
    $data = substr($dataWithIv, $ivLength);
    return openssl_decrypt($data, 'aes-256-cbc', $key, 0, $iv);
}

?>
