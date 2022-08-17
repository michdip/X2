<?php

require_once( 'libphp-phpmailer/autoload.php' );
require_once( ROOT_DIR . '/conf.d/mail.conf' );

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class myPhpMailer
{
    public static function sendMail( $message, $subject, $recipients, &$logger )
    {
        $mailer = new PHPMailer( true );

        try
        {
            /* Debugging
             * SMTP::DEBUG_OFF = off (for production use)
             * SMTP::DEBUG_CLIENT = client messages
             * SMTP::DEBUG_SERVER = client and server messages
             */
            $mailer->SMTPDebug = SMTP::DEBUG_OFF;

            // Konfiguration des SMTP-Server
            $mailer->isSMTP( );

            $mailer->Host = SMTP_HOST;
            $mailer->Port = SMTP_PORT;

            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USER;
            $mailer->Password = SMTP_PWD;

            /* Options: '' 'ssl' 'tls'
             * default ''
             */
            if( SMTP_SECURE != 'none' )
                $mailer->SMTPSecure = SMTP_SECURE;

            $mailer->setFrom( SMTP_FROM, 'X2' );

            // die Empfänger-Adressen setzen
            foreach( $recipients as $recipient )
                $mailer->addAddress( $recipient );

            // den Betreff setzen
            $mailer->Subject = $subject;

            // den Inhalt der Mail setzen
            $mailer->Body = $message;

            // versenden der Mail
            $mailer->send();
        }
        catch( Exception $e )
        {
            $logger->writeLog( 'myPhpMailer::sendMail ERROR Message could not be sent. { ' . $mailer->ErrorInfo . '}' );
        }

    }
}

?>
