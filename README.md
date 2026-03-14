![Ready](https://img.shields.io/badge/status-ready%20to%20go-brightgreen.svg?style=flat-square)

### PHP-SMTP-Mailer
This is a lightweight SMTP PHPMailer.<br>
Updated to work with PHP 8.0+.<br>
This PHP Class supports TLS, SSL, and file attachments in mail.<br>
Simple, powerful, and easy to use.

##### Features:
* Sends mail using one SMTP Server, e.g. 'smtp.gmail.com'.
* Auth login with username and password.
* Uses security protocols TLS and SSL.
* Supports 'text/html' or 'text/plain' messages.
* Supports any number of file attachments.
* Default Charset is 'UTF-8' but can be changed.
* 8bit, 7bit, Binary, or Quoted-Printable transfer encoding.
* Logging of the transaction for debug.

##### Email Headers:
* From - one address
* Reply-To - multiple possible
* To  - multiple possible
* Cc  - multiple possible
* Bcc - multiple possible

### Usage
Set your config variables in your calling script.<br>
Here's a basic example:
```php
<?php

require '/path/to/SMTPMailer.php';

$mail = new SMTPMailer;

$mail->port = 465;
$mail->smtp_host = 'mail.server.com';
$mail->username  = 'user@server.com';
$mail->password  = 'password';

$mail->set_from(address:'me@server.com', name:'tester');

$mail->add_address(type:'to', address:'someone@destination.com', name:'Someone');
$mail->add_address(type:'cc', address:'someone-else@destination.com', name:'Someone Else');
$mail->add_address(type:'bcc', address:'secret@destination.com', name:'Admirer');

$mail->subject = 'Greetings';

$mail->body_text = <<<"PLAIN"
	Hello!  This is a test.
PLAIN;

$mail->body_html = <<<"HTML"
	This is a test from {$mail->smtp_host} on port {$mail->port}
	<br>
	<b>Greetings!</b>
HTML;

$mail->add_attachment(
	attachment_path: ['/Users/user_name/document.pdf'],
);

if ($mail->send()) { echo 'Mail was sent successfully!'. PHP_EOL; }
else               { echo 'Mail failure!!!'. PHP_EOL; }

?>
```
