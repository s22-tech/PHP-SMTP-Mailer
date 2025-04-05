<?php

declare(strict_types=1);
/*************************************************************
 Description : PHP Class for sending SMTP Mail
 Orig Author : halojoy  https://github.com/halojoy/PHP-SMTP-Mailer
 Updated by  : s22-tech  https://github.com/s22-tech/PHP-SMTP-Mailer
 *************************************************************/

class SMTPMailer {

	public $smtp_host;
	public $att_path;
	public $att_type;
	public $attachment;
	public $att_encoding;
	public $port     = 465;
	public $to       = [];
	public $from     = [];
	public $cc       = [];
	public $bcc      = [];
	public $reply_to = [];
	public $username = '';
	public $password = '';
	public $show_log = false;
	public $smtp_secure = 'SSL';
	public $charset = 'UTF-8';
	public $transfer_encoding = '7bit';
	public string $subject = 'No subject';
	public string $body_html = '';
	public string $body_plain = '';
	
	private $header;
	private $sock;
	private $local;
	private $hostname;
	private $mail_headers;
	private $log = [];


	public function __construct() {
		$this->local = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? @$_SERVER['SERVER_ADDR'];
	}


	public function set_property($type, $value) {
		$this->$type = $value;
	}


	public function set_from($address, $name = '') {
		$this->from = [$address, $name];
	}

	/**
	 * Set the recipient address information.
	 *
	 * @param string $type     The address type: to, cc, bcc, reply_to.
	 * @param string $address  The email address to send to.
	 * @param string $name     The recipient's name (optional).
	 */
	public function add_address($type, $address, $name = '') {
		$this->$type[] = [$address, $name];
	}


  // Remove all email addresses.
	public function clear_addresses() {
		$this->to  = [];
		$this->cc  = [];
		$this->bcc = [];
		$this->reply_to = [];
	}


  // Add attachment file.
	public function add_attachment(array $att_path, $att_encoding, $att_type) {
		if (!is_array($att_path)) {
			throw new Exception('$att_path must be an array in '.__FUNCTION__.'()');
		}
		$this->attachment   = $att_path;
		$this->att_encoding = $att_encoding;
		$this->att_type     = $att_type;
	}


	/**
	 * Set the charset for the email.
	 *
	 * @param string $charset The charset to use. Default is 'UTF-8'.
	 */
	public function set_charset($charset) {
		$this->charset = $charset;
	}


	public function print_log() {
		if ($this->show_log === true) {
			echo 'SMTP Mail Transaction Log' . PHP_EOL;
			print_r($this->log);
		}
	}


	public function debug() {
		echo PHP_EOL;
		echo 'smtp_host: ' . $this->smtp_host   . PHP_EOL;
		echo 'secure: '    . $this->smtp_secure . PHP_EOL;
		echo 'hostname: '  . $this->hostname    . PHP_EOL;
		echo 'port: '      . $this->port        . PHP_EOL;
	}


	public function send() {
	  // Prepare data for sending.
		$this->mail_headers = $this->do_headers();
		$user64 = base64_encode($this->username);
		$pass64 = base64_encode($this->password);
		$mailfrom = '<'.$this->from[0].'>';
// 		foreach (array_merge($this->to, $this->cc, $this->bcc) as $address) {
// 			$mailto[] = '<'.$address[0].'>';
// 		}
		$mailto = array_map(fn($address) => '<'.$address[0].'>', array_merge($this->to, $this->cc, $this->bcc));

		$this->hostname = $this->smtp_secure === 'tls' ? 'tcp://'.$this->smtp_host : 'ssl://'.$this->smtp_host;

	  // Open server connection and run transfers.
		$this->sock = fsockopen($this->hostname, $this->port, $enum, $estr, 30);
		if (!$this->sock) exit('Socket connection error: '.$this->hostname);
		$this->log[] = 'CONNECTION: fsockopen('.$this->hostname.')';
		$this->response('220');
		$this->log_request('EHLO '.$this->local, '250');

		if ($this->smtp_secure == 'tls') {
			$this->log_request('STARTTLS', '220');
			stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
			$this->log_request('EHLO '.$this->local, '250');
		}

		$this->log_request('AUTH LOGIN', '334');
		$this->log_request($user64, '334');
		$this->log_request($pass64, '235');

		$this->log_request('MAIL FROM: '.$mailfrom, '250');

		foreach ($mailto as $address) {
			$this->log_request('RCPT TO: '.$address, '250');
		}
		foreach ($this->bcc as $address) {
			$this->log_request('RCPT TO: <'.$address[0].'>', '250');
		}

		$this->log_request('DATA', '354');
		$this->log[] = htmlspecialchars($this->do_headers(false));
		$this->request($this->mail_headers, '250');

		$this->log_request('QUIT', '221');
		fclose($this->sock);

		$this->print_log();

		return true;
	}


  // Log command and do request.
	private function log_request($cmd, $code) {
		$this->log[] = htmlspecialchars($cmd);
		$this->request($cmd, $code);
	}


  // Send one command and test response.
	private function request($cmd, $code) {
		fwrite($this->sock, $cmd."\r\n");
		$this->response($code);
	}


  // Read and verify response code.
	private function response($code) {
		stream_set_timeout($this->sock, 8);
		$result = fread($this->sock, 768);
		$meta = stream_get_meta_data($this->sock);
		if ($meta['timed_out'] === true) {
			fclose($this->sock);
			$this->log[] = 'There was a timeout in the Server response.';
			$this->print_log();
			print_r($meta);
			exit();
		}
		$this->log[] = $result;
		if (substr($result, 0, 3) == $code) return;
		fclose($this->sock);
		$this->log[] = 'SMTP Server response Error';
		$this->print_log();
		exit();
	}


  // Do create headers after precheck.
	private function do_headers($filedata = true) {
	  // Precheck. Test if we have the necessary data.
		if (empty($this->username) || empty($this->password)) {
			exit('We need the username and password for: '. $this->smtp_host . PHP_EOL);
		}
		if (empty($this->from)) $this->from = [$this->username, ''];
		if (empty($this->to) || !filter_var($this->to[0][0], FILTER_VALIDATE_EMAIL)) {
			exit('We need a valid email address to send to.' . PHP_EOL);
		}
		if (strlen(trim($this->body_html)) < 3 && strlen(trim($this->body_plain)) < 3) {
			exit('There was no message to send.' . PHP_EOL);
		}

	  // Create Headers.
		$header_string = '';
		$this->create_headers($filedata);
		foreach ($this->header as $val) {
			$header_string .= $val."\r\n";
		}
		return rtrim($header_string);
	}


	private function create_headers($filedata) {
	  // Add space between body and attachments.
		if ($this->body_html)  $this->body_html  .= '<br>'.PHP_EOL.'<br>'.PHP_EOL;
		if ($this->body_plain) $this->body_plain .= PHP_EOL . PHP_EOL;

		$this->header = [
			'Date: '.date('r'),
			'To: '.$this->format_address_list($this->to),
			'From: '.$this->format_address($this->from),
			'Subject: '.'=?UTF-8?B?'.base64_encode($this->subject).'?=',
			'Message-ID: '.$this->generate_message_id(),
			/* 'X-Mailer: '.'PHP/'.phpversion(), */
			'MIME-Version: '.'1.0'
		];

		if (!empty($this->cc)) $this->header[] = 'Cc: '.$this->format_address_list($this->cc);
		if (!empty($this->reply_to)) $this->header[] = 'Reply-To: '.$this->format_address_list($this->reply_to);

		$boundary = md5(uniqid());
		if (empty($this->attachment) || !file_exists($this->attachment[0])) {
		  // No attachment.
			if ($this->body_plain && $this->body_html) {
				$this->header[] = 'Content-Type: multipart/alternative; boundary="'.$boundary.'"';
				$this->header[] = '';
				$this->header[] = 'This is a multi-part message in MIME format.';
				$this->header[] = '--'.$boundary;
				$this->define_content('plain', 'body_plain');
				$this->header[] = '--'.$boundary;
				$this->define_content('html', 'body_html');
				$this->header[] = '--'.$boundary.'--';
			}
			elseif ($this->body_plain) {
				$this->define_content('plain', 'body_plain');
			}
			else {
				$this->define_content('html', 'body_html');
			}
		}
		else {
		  // Contains attachment(s).
			$this->header[] = 'Content-Type: multipart/mixed; boundary="' .$boundary.'"';
			$this->header[] = '';
			$this->header[] = 'This is a multi-part message in MIME format.';
			$this->header[] = 'Content-Type: multipart/alternative; boundary="'.'--'.$boundary.'"';
			if ($this->body_plain) {
				$this->define_content('plain', 'body_plain');
				$this->header[] = '--'.$boundary;
			}
			if ($this->body_html) {
				$this->define_content('html', 'body_html');
				$this->header[] = '--'.$boundary;
			}
			foreach ($this->attachment as $path) {
			  // Loop thru attachments...
				if (file_exists($path)) {
					$this->header[] = 'Content-Type: application/'.$this->att_type.'; name="'.basename($path).'"';
					$this->header[] = 'Content-Transfer-Encoding: '.$this->att_encoding;
					$this->header[] = 'Content-Disposition: attachment; filename="'.basename($path).'"';
					$this->header[] = '';
					if ($filedata) {
					  // Encode file contents.
						$contents = chunk_split(base64_encode(file_get_contents($path)));
						$this->header[] = $contents;
					}
					$this->header[] = '--'.$boundary;
				}
			}
		  // Add final "--".
			$this->header[count($this->header) - 1] .= '--';
		}
	  // End headers with a period.
		$this->header[] = '.';
	}


	private function define_content($type, $msg) {
		$this->header[] = 'Content-Type: text/'.$type.'; charset="'.$this->charset.'"';
		$this->header[] = 'Content-Transfer-Encoding: '.$this->transfer_encoding;
		$this->header[] = '';
		if ($this->transfer_encoding == 'quoted-printable') {
			$this->header[] = quoted_printable_encode($this->$msg);
		}
		else {
			$this->header[] = $this->$msg;
		}
	}


	private function format_address($address) {
		return ($address[1] == '') ? $address[0] : '"'.$address[1].'" <'.$address[0].'>';
	}


	private function format_address_list($addresses) {
		return implode(', '. "\r\n\t", array_map([$this, 'format_address'], $addresses));
	}


	private function generate_message_id() {
		return sprintf(
			"<%s.%s@%s>",
			base_convert((string)time(), 10, 36),
			base_convert(bin2hex(openssl_random_pseudo_bytes(8)), 16, 36),
			$this->local
		);
	}

}
