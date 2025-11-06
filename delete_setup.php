<?php
// Delete setup files
unlink(__DIR__ . '/setup_database.php');
unlink(__FILE__);

header('Location: modules/timbangan/timbangan1.php');
exit;
?>