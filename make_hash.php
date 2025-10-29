<?php
$password = 'admin'; // Change this to your desired password
$hash = password_hash($password, PASSWORD_BCRYPT);
echo "Hash for '$password':\n$hash\n"; 