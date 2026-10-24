<?php
require_once __DIR__ . '/lib/bootstrap.php';
if (user()) siem('LOGOUT', 'Cierre de sesión en el portal', 'SUCCESS');
$_SESSION = [];
session_destroy();
header('Location: ' . url('login.php'));
