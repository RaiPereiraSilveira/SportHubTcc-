<?php
// landing.php — mantido apenas por compatibilidade com links antigos.
// A página de apresentação passou a ser index.php.
require_once __DIR__ . '/includes/config.php';
header('Location: ' . sh_url('index.php'), true, 301);
exit();
