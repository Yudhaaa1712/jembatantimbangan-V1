<?php
// Delete reset files
unlink(__DIR__ . '/reset_database.php');
unlink(__FILE__);

header('Location: modules/timbangan/timbangan1.php');
exit;
?>