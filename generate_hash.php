<?php
$pw_list = [
    'admin123',
    'dokterarga123',
    'awan098',
    'fika0987',
    'dokterkayla123',
    'qanita123',
];

foreach ($pw_list as $pw) {
    echo $pw . ' => ' . password_hash($pw, PASSWORD_BCRYPT) . "<br>
";
}