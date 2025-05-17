<?php
$host = "localhost";    // or 127.0.0.1
$user = "root";           // default username in Workbench
$password = "";              // change if you have a password
$dbname = "librarydb";    // your database name

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully"; // Uncomment for testing
?>
