<?php

declare(strict_types=1);

namespace ContactForm\Mail;

/**
 * Minimal SMTP client (EHLO, STARTTLS, AUTH LOGIN, MAIL/RCPT/DATA) so the project needs no
 * Composer packages. For heavy use, swap in Symfony Mailer or PHPMailer behind the same interface.
 */
final class SmtpMailer implements Mailer
{
    /** @param (\Closure(SmtpConfig): resource)|null $connector replaces the network connection in tests */
    public function __construct(
        private readonly SmtpConfig $config,
        private readonly ?\Closure $connector = null,
    ) {
    }

    public function name(): string
    {
        return 'smtp';
    }

    public function send(Email $email): void
    {
        $stream = $this->connect();
        try {
            $this->expect($stream, [220]);
            $this->command($stream, 'EHLO ' . $this->config->heloHost, [250]);
            if ($this->config->encryption === 'tls') {
                $this->command($stream, 'STARTTLS', [220]);
                if (stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT) !== true) {
                    throw new MailException('STARTTLS negotiation failed.');
                }
                $this->command($stream, 'EHLO ' . $this->config->heloHost, [250]);
            }
            if ($this->config->username !== '') {
                $this->command($stream, 'AUTH LOGIN', [334]);
                $this->command($stream, base64_encode($this->config->username), [334]);
                $this->command($stream, base64_encode($this->config->password), [235], label: 'AUTH password');
            }
            $this->command($stream, 'MAIL FROM:<' . $email->from . '>', [250]);
            $this->command($stream, 'RCPT TO:<' . $email->to . '>', [250, 251]);
            $this->command($stream, 'DATA', [354]);
            $mime = $email->toMime(new \DateTimeImmutable(), $this->config->heloHost);
            // Dot-stuffing (RFC 5321 4.5.2), then the terminating "." line.
            $this->command($stream, preg_replace('/^\./m', '..', $mime) . "\r\n.", [250], label: 'message data');
            $this->command($stream, 'QUIT', [221]);
        } finally {
            fclose($stream);
        }
    }

    /** @return resource */
    private function connect()
    {
        if ($this->connector !== null) {
            return ($this->connector)($this->config);
        }
        $scheme = $this->config->encryption === 'ssl' ? 'ssl' : 'tcp';
        $stream = @stream_socket_client(
            "{$scheme}://{$this->config->host}:{$this->config->port}",
            $errno,
            $error,
            $this->config->timeout,
        );
        if ($stream === false) {
            throw new MailException("Could not connect to {$this->config->host}:{$this->config->port}: {$error}");
        }
        stream_set_timeout($stream, (int) ceil($this->config->timeout));

        return $stream;
    }

    /**
     * @param resource  $stream
     * @param list<int> $expected
     */
    private function command($stream, string $line, array $expected, ?string $label = null): string
    {
        if (fwrite($stream, $line . "\r\n") === false) {
            throw new MailException('Connection to the mail server was lost.');
        }

        return $this->expect($stream, $expected, $label ?? $line);
    }

    /**
     * Reads a (possibly multi-line) reply and checks its code.
     *
     * @param resource  $stream
     * @param list<int> $expected
     */
    private function expect($stream, array $expected, string $after = 'connect'): string
    {
        $reply = '';
        while (($line = fgets($stream, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new MailException("Unexpected SMTP reply after {$after}: " . trim($reply));
        }

        return $reply;
    }
}
