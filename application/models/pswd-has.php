<?php
// User's plain text password
$password = "rental.com";

// Hash the password using the default algorithm (currently BCRYPT)
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Output the hashed password
echo "Hashed Password: " . $hashedPassword;
?>
