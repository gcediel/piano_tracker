<?php
require_once 'config/auth.php';

cerrarSesion();
header('Location: login.php');
exit;
