<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // SMTP GMAIL
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'clinicflow.test@gmail.com';
    $mail->Password   = 'mqjkyrttbgjclpgu';      // app password 16 digit
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;
    $mail->SMTPAutoTLS = true;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    // PENGIRIM & PENERIMA
    $mail->setFrom('clinicflow.test@gmail.com', 'ClinicFlow Test');
    $mail->addAddress('EMAIL_TUJUAN@GMAIL.COM');   // ganti dengan email yang kamu cek

    // ISI EMAIL
    $mail->isHTML(true);
    $mail->Subject = 'Tes email dari localhost';
    $mail->Body    = 'Jika email ini masuk, berarti SMTP Gmail sudah berhasil.';

    $mail->send();
    echo 'OK';
} catch (Exception $e) {
    echo 'KESALAHAN: ' . $mail->ErrorInfo;
}