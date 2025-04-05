<?php

declare(strict_types=1);

/*************************************************************
 Description : PHP Class for sending SMTP Mail
 Orig Author : halojoy  https://github.com/halojoy/PHP-SMTP-Mailer
 Updated by  : s22-tech  https://github.com/s22-tech/PHP-SMTP-Mailer
 *************************************************************/

class SMTPMailer {

	const DEFAULT_PORT = 465;
	const DEFAULT_SMTP_SECURE = 'SSL';
	const DEFAULT_CHARSET = 'UTF-8';
	const DEFAULT_TRANSFER_ENCODING = '7bit';
	const DEFAULT_SUBJECT = 'No subject';

	public bool $show_log = false;
	public string $smtp_host;
	public int $port = self::DEFAULT_PORT;
	public string $username = '', $password = '';
	public string $subject = self::DEFAULT_SUBJECT;
	public string $body_html = '', $body_text = '';
	public string $smtp_secure = self::DEFAULT_SMTP_SECURE;
	public string $transfer_encoding = self::DEFAULT_TRANSFER_ENCODING;
	private array $attachments = [];
	private string $attachment_encoding;
	private array $from = ['',''], $to = [], $cc = [], $bcc = [], $reply_to = [];
	private string $charset = self::DEFAULT_CHARSET;
	private array $log = [];
	private $sock, $local, $hostname, $mail_headers, $header;

	public function __construct() {
		$this->local = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? @$_SERVER['SERVER_ADDR'];
	}

	public function set_property(string $type, $value): void {
		$this->$type = $value;
	}

	public function set_from(string $address, string $name = ''): void {
		$this->from = [$address, $name];
	}

	/**
	 * Set the recipient address information.
	 *
	 * @param string $type     The address type: to, cc, bcc, reply_to.
	 * @param string $address  The email address to send to.
	 * @param string $name     The recipient's name (optional).
	 */
	public function add_address(string $type, string $address, string $name = ''): void {
		$this->$type[] = [$address, $name];
	}

	public function clear_addresses(): void {
		$this->to = [];
		$this->cc = [];
		$this->bcc = [];
		$this->reply_to = [];
	}

	public function add_attachment(array $attachment_path, string $attachment_encoding = 'base64'): void {
		if (empty($attachment_path)) {
			throw new InvalidArgumentException('$attachment_path must be a non-empty array in ' . __FUNCTION__ . '()');
		}
		$this->attachments = $attachment_path;
		$this->attachment_encoding = $attachment_encoding;
	}

	public function set_charset(string $charset): void {
		$this->charset = $charset;
	}

	public function print_log(): void {
		if ($this->show_log) {
			echo 'SMTP Mail Transaction Log' . PHP_EOL;
			print_r($this->log);
		}
	}

	public function debug(): void {
		echo PHP_EOL;
		echo 'smtp_host: ' . $this->smtp_host   . PHP_EOL;
		echo 'secure: '    . $this->smtp_secure . PHP_EOL;
		echo 'hostname: '  . $this->hostname    . PHP_EOL;
		echo 'port: '      . $this->port        . PHP_EOL;
	}

	public function send(): bool {
		$this->prepare_data_for_sending();
		$this->open_server_connection();
		$this->authenticate();
		$this->send_mail();
		$this->close_connection();
		return true;
	}

	private function prepare_data_for_sending(): void {
		$this->mail_headers = $this->create_headers();
		$this->hostname = $this->smtp_secure === 'tls' ? 'tcp://' . $this->smtp_host : 'ssl://' . $this->smtp_host;
	}

	private function open_server_connection(): void {
		$this->sock = fsockopen($this->hostname, $this->port, $errno, $errstr, 30);
		if (!$this->sock) {
			throw new RuntimeException('Socket connection error: ' . $this->hostname);
		}
		$this->log[] = 'CONNECTION: fsockopen(' . $this->hostname . ')';
		$this->response('220');
		$this->log_request('EHLO ' . $this->local, '250');

		if ($this->smtp_secure === 'tls') {
			$this->log_request('STARTTLS', '220');
			stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
			$this->log_request('EHLO ' . $this->local, '250');
		}
	}

	private function authenticate(): void {
		$user64 = base64_encode($this->username);
		$pass64 = base64_encode($this->password);
		$this->log_request('AUTH LOGIN', '334');
		$this->log_request($user64, '334');
		$this->log_request($pass64, '235');
	}

	private function send_mail(): void {
		$mailfrom = '<' . $this->from[0] . '>';
		$mailto = array_map(fn($address) => '<' . $address[0] . '>', array_merge($this->to, $this->cc, $this->bcc));
		$this->log_request('MAIL FROM: ' . $mailfrom, '250');

		foreach ($mailto as $address) {
			$this->log_request('RCPT TO: ' . $address, '250');
		}
		foreach ($this->bcc as $address) {
			$this->log_request('RCPT TO: <' . $address[0] . '>', '250');
		}

		$this->log_request('DATA', '354');
		$this->log[] = htmlspecialchars($this->create_headers(false));
		$this->request($this->mail_headers, '250');

		$this->log_request('QUIT', '221');
		$this->print_log();
	}

	private function close_connection(): void {
		fclose($this->sock);
	}

	private function log_request(string $cmd, string $code): void {
		$this->log[] = htmlspecialchars($cmd);
		$this->request($cmd, $code);
	}

	private function request(string $cmd, string $code): void {
		fwrite($this->sock, $cmd . "\r\n");
		$this->response($code);
	}

	private function response(string $code): void {
		stream_set_timeout($this->sock, 8);
		$result = fread($this->sock, 768);
		$meta = stream_get_meta_data($this->sock);
		if ($meta['timed_out']) {
			fclose($this->sock);
			$this->log[] = 'There was a timeout in the Server response.';
			$this->print_log();
			throw new RuntimeException('Server response timeout', 1);
		}
		$this->log[] = $result;
		if (substr($result, 0, 3) !== $code) {
			fclose($this->sock);
			$this->log[] = 'SMTP Server response Error';
			$this->print_log();
			throw new RuntimeException('SMTP Server response error', 1);
		}
	}

	private function create_headers(bool $filedata = true): string {
	  // Pre-check. Test if we have the necessary data.
		if (empty($this->username) || empty($this->password)) {
			throw new InvalidArgumentException('We need the username and password for: ' . $this->smtp_host);
		}
		if (empty($this->from)) {
			$this->from = [$this->username, ''];
		}
		if (empty($this->to) || !filter_var($this->to[0][0], FILTER_VALIDATE_EMAIL)) {
			throw new InvalidArgumentException('We need a valid email address to send to.');
		}
		if (strlen(trim($this->body_html)) < 3 && strlen(trim($this->body_text)) < 3) {
			throw new InvalidArgumentException('There was no message to send.');
		}

	  // Create Headers.
		$header_string = '';
		$this->build_headers($filedata);
		foreach ($this->header as $val) {
			$header_string .= $val . "\r\n";
		}
		return rtrim($header_string);
	}

	private function build_headers(bool $filedata): void {
	  // Add space between body and attachments.
		if ($this->body_html) {
			$this->body_html .= '<br>' . PHP_EOL . '<br>' . PHP_EOL;
		}
		if ($this->body_text) {
			$this->body_text .= PHP_EOL . PHP_EOL;
		}

		$this->header = [
			'Date: ' . date('r'),
			'To: ' . $this->format_address_list($this->to),
			'From: ' . $this->format_address($this->from),
			'Subject: ' . '=?UTF-8?B?' . base64_encode($this->subject) . '?=',
			'Message-ID: ' . $this->generate_message_id(),
			'MIME-Version: 1.0'
		];

		if (!empty($this->cc)) {
			$this->header[] = 'Cc: ' . $this->format_address_list($this->cc);
		}
		if (!empty($this->reply_to)) {
			$this->header[] = 'Reply-To: ' . $this->format_address_list($this->reply_to);
		}

		$boundary = md5( uniqid() );

		if (empty($this->attachments)) {
			$this->build_alternative_headers($boundary);
		}
		else {
			$this->build_mixed_headers($boundary, $filedata);
		}

	  // End headers with a period.
		$this->header[] = '.';
	}

	private function build_alternative_headers(string $boundary): void {
		if ($this->body_text && $this->body_html) {
			$this->header[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
			$this->header[] = '';
			$this->header[] = 'This is a multi-part message in MIME format.';
			$this->header[] = '--' . $boundary;
			$this->define_content('plain', 'body_text');
			$this->header[] = '--' . $boundary;
			$this->define_content('html', 'body_html');
			$this->header[] = '--' . $boundary . '--';
		}
		elseif ($this->body_text) {
			$this->define_content('plain', 'body_text');
		}
		else {
			$this->define_content('html', 'body_html');
		}
	}

	private function build_mixed_headers(string $boundary, bool $filedata): void {
		$this->header[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
		$this->header[] = '';
		$this->header[] = 'This is a multi-part message in MIME format.';
		$this->header[] = 'Content-Type: multipart/alternative; boundary="--' . $boundary . '"';
		if ($this->body_text) {
			$this->define_content('plain', 'body_text');
			$this->header[] = '--' . $boundary;
		}
		if ($this->body_html) {
			$this->define_content('html', 'body_html');
			$this->header[] = '--' . $boundary;
		}
		foreach ($this->attachments as $path) {
			if (file_exists($path)) {
				$att_type = $this->filename_to_type($path);
				$this->header[] = 'Content-Disposition: attachment; filename="' . basename($path) . '"';
				$this->header[] = 'Content-Transfer-Encoding: ' . $this->attachment_encoding;
				$this->header[] = 'Content-Type: ' . $att_type . '; name="' . basename($path) . '"';
				$this->header[] = '';
				if ($filedata) {
					$contents = chunk_split(base64_encode(file_get_contents($path)));
					$this->header[] = $contents;
				}
				$this->header[] = '--' . $boundary;
			}
		}
		$this->header[count($this->header) - 1] .= '--';
	}

	private function define_content(string $type, string $msg): void {
		$this->header[] = 'Content-Type: text/' . $type . '; charset="' . $this->charset . '"';
		$this->header[] = 'Content-Transfer-Encoding: ' . $this->transfer_encoding;
		$this->header[] = '';
		if ($this->transfer_encoding === 'quoted-printable') {
			$this->header[] = quoted_printable_encode($this->$msg);
		}
		else {
			$this->header[] = $this->$msg;
		}
	}

	private function format_address(array $address): string {
		return ($address[1] === '') ? $address[0] : '"' . $address[1] . '" <' . $address[0] . '>';
	}

	private function format_address_list(array $addresses): string {
		return implode(', ' . "\r\n\t", array_map([$this, 'format_address'], $addresses));
	}

	private function generate_message_id(): string {
		return sprintf(
			"<%s.%s@%s>",
			base_convert((string)time(), 10, 36),
			base_convert(bin2hex(openssl_random_pseudo_bytes(8)), 16, 36),
			$this->local
		);
	}

	public function mime_types(string $ext = ''): string {
		$mimes = [
			'xl'    => 'application/excel',
			'js'    => 'application/javascript',
			'hqx'   => 'application/mac-binhex40',
			'cpt'   => 'application/mac-compactpro',
			'bin'   => 'application/macbinary',
			'doc'   => 'application/msword',
			'word'  => 'application/msword',
			'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'xltx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
			'sldx'  => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
			'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'dotx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
			'pdf'   => 'application/pdf',
			'smi'   => 'application/smil',
			'smil'  => 'application/smil',
			'xls'   => 'application/vnd.ms-excel',
			'gtar'  => 'application/x-gtar',
			'php3'  => 'application/x-httpd-php',
			'php4'  => 'application/x-httpd-php',
			'php'   => 'application/x-httpd-php',
			'phtml' => 'application/x-httpd-php',
			'phps'  => 'application/x-httpd-php-source',
			'sit'   => 'application/x-stuffit',
			'tar'   => 'application/x-tar',
			'tgz'   => 'application/x-tar',
			'xht'   => 'application/xhtml+xml',
			'xhtml' => 'application/xhtml+xml',
			'zip'   => 'application/zip',
			'mid'   => 'audio/midi',
			'midi'  => 'audio/midi',
			'mp2'   => 'audio/mpeg',
			'mp3'   => 'audio/mpeg',
			'm4a'   => 'audio/mp4',
			'mpga'  => 'audio/mpeg',
			'aif'   => 'audio/x-aiff',
			'aifc'  => 'audio/x-aiff',
			'aiff'  => 'audio/x-aiff',
			'wav'   => 'audio/x-wav',
			'mka'   => 'audio/x-matroska',
			'bmp'   => 'image/bmp',
			'gif'   => 'image/gif',
			'jpeg'  => 'image/jpeg',
			'jpe'   => 'image/jpeg',
			'jpg'   => 'image/jpeg',
			'png'   => 'image/png',
			'tiff'  => 'image/tiff',
			'tif'   => 'image/tiff',
			'webp'  => 'image/webp',
			'avif'  => 'image/avif',
			'eml'   => 'message/rfc822',
			'css'   => 'text/css',
			'html'  => 'text/html',
			'htm'   => 'text/html',
			'shtml' => 'text/html',
			'log'   => 'text/plain',
			'text'  => 'text/plain',
			'txt'   => 'text/plain',
			'rtx'   => 'text/richtext',
			'rtf'   => 'text/rtf',
			'vcf'   => 'text/vcard',
			'vcard' => 'text/vcard',
			'ics'   => 'text/calendar',
			'xml'   => 'text/xml',
			'xsl'   => 'text/xml',
			'wmv'   => 'video/x-ms-wmv',
			'mpeg'  => 'video/mpeg',
			'mpe'   => 'video/mpeg',
			'mpg'   => 'video/mpeg',
			'mp4'   => 'video/mp4',
			'm4v'   => 'video/mp4',
			'mov'   => 'video/quicktime',
			'qt'    => 'video/quicktime',
			'avi'   => 'video/x-msvideo',
			'movie' => 'video/x-sgi-movie',
			'webm'  => 'video/webm',
			'mkv'   => 'video/x-matroska',
		];
		$ext = strtolower($ext);
		return $mimes[$ext] ?? 'application/octet-stream';
	}

	public function filename_to_type(string $filename): string {
		$q_pos = strpos($filename, '?');
		if (false !== $q_pos) {
			$filename = substr($filename, 0, $q_pos);
		}
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		return $this->mime_types($ext);
	}
}
