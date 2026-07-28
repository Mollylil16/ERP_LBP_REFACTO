<?php
$hashes = [
    'admin' => '$2y$10$EMb9UrWNLgsp4i/toUgF9O6SlqRCCAsoG7ksjuy.6Ih48SJXYDJsS',
    'brunell' => '$2y$10$ZPWlXZH.1PJLWIFpWoDvgeRq332nuxqHVFwZUn14MV8s5YEyKNJH.'
];

$passwords = [
    'admin', '123456', 'password', 'Test1234!', 'Succes2019', '@Succes2019', '@Succes2020', 'lbp_transit', 'admin123', 'admin@123', 'lbp2026', '@Succes2026'
];

foreach ($hashes as $name => $hash) {
    foreach ($passwords as $pw) {
        if (password_verify($pw, $hash)) {
            echo "Match found for $name: $pw\n";
            exit;
        }
    }
}
echo "No match found.\n";
