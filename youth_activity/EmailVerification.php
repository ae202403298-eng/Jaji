<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

class EmailVerification {

    public function sendVerificationEmail($to, $name, &$pin_code) {

        $mail = new PHPMailer(true);

        try {

            // GENERATE 6 DIGIT PIN
            $pin_code = rand(100000, 999999);

            // SMTP SETTINGS
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;

            // YOUR GMAIL
            $mail->Username   = 'jaji.zhanjianahtabilin@gmail.com';

            // YOUR APP PASSWORD
            $mail->Password   = 'tcep ejvu lfve qvqk';

            // SECURITY
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // FROM
            $mail->setFrom(
                'jaji.zhanjianahtabilin@gmail.com',
                'Youth System'
            );

            // TO
            $mail->addAddress($to, $name);

            // EMAIL CONTENT
            $mail->isHTML(true);

            $mail->Subject = 'Email Verification PIN';

            $mail->Body = "
                <div style='font-family: Arial;'>

                    <h2>Email Verification</h2>

                    <p>Hello <b>$name</b>,</p>

                    <p>Your verification PIN is:</p>

                    <h1 style='letter-spacing:5px;'>
                        $pin_code
                    </h1>

                    <p>
                        Enter this PIN in the verification page.
                    </p>

                </div>
            ";

            // SEND EMAIL
            $mail->send();

            return true;

        } catch (Exception $e) {

            return false;
        }
    }
}
?>