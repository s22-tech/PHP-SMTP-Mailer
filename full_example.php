<?php

require '/path/to/SMTPMailer.php';

$mail = new SMTPMailer;

$mail->show_log = false;  // true || false

$mail->port = 465;
$mail->smtp_host = 'mail.server.com';
$mail->username  = 'user@server.com';
$mail->password  = 'password';

$mail->set_from(address:'me@server.com', name:'tester');

$mail->add_address(type:'to', address:'someone@destination.com', name:'Someone');
$mail->add_address(type:'cc', address:'someone-else@destination.com', name:'Someone Else');
$mail->add_address(type:'bcc', address:'secret@destination.com', name:'Admirer');

$mail->subject = 'Greetings';

$mail->body_plain = <<<"PLAIN"
	Hello!  This is a test.
PLAIN;

$mail->body_html = <<<"HTML"
	This is a test from {$mail->smtp_host} on port {$mail->port}
	<br>
	<b>Greetings!</b>
HTML;

$mail->add_attachment(
	att_path: ['/Users/user_name/document.pdf'],
	att_encoding: 'base64',
	att_type: 'application/pdf',
);

if ($mail->send()) { echo 'Mail was sent successfully!'. PHP_EOL; }
else               { echo 'Mail failure!!!'. PHP_EOL; }
