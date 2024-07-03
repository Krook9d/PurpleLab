<?php
session_start();

require 'encryption.php'; 


if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@local.com') {
    header('Location: connexion.html');
    exit();
}

$encryptionKey = getenv('ENCRYPTION_KEY');
if (!$encryptionKey) {
    die("Encryption key not found.");
}

$ldapServer = encryptData($_POST['ldapServer'], $encryptionKey);
$ldapDn = encryptData($_POST['ldapDn'], $encryptionKey);
$ldapUser = encryptData($_POST['ldapUser'], $encryptionKey);
$ldapPassword = encryptData($_POST['ldapPassword'], $encryptionKey);

file_put_contents('ldap_config.json', json_encode([
    'ldapServer' => $ldapServer,
    'ldapDn' => $ldapDn,
    'ldapUser' => $ldapUser,
    'ldapPassword' => $ldapPassword, 
]));


$_SESSION['ldap_config_saved'] = true;
header('Location: /admin.php');
exit();
