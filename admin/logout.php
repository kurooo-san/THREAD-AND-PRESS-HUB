<?php
// The storefront logout also clears the remember-me cookie and its DB token;
// destroying only the session here let that cookie sign the admin straight back in.
header("Location: ../logout.php");
exit();
?>
