<?php

declare(strict_types=1);

namespace ContactForm\Mail;

final readonly class Email
{
    public function __construct(
        public string $from,
        public string $to,
        public string $subject,
        public string $textBody,
        public ?string $replyTo = null,
    ) {
        foreach ([$from, $to, $replyTo ?? $from] as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('Invalid e-mail address.');
            }
        }
        if (preg_match('/[\r\n]/', $subject) === 1) {
            throw new \InvalidArgumentException('Line breaks are not allowed in the subject.');
        }
    }

    /** RFC 5322 message with a base64 UTF-8 text body (no header injection, no long lines). */
    public function toMime(\DateTimeInterface $date, string $messageIdDomain): string
    {
        $headers = [
            'Date' => $date->format(\DateTimeInterface::RFC2822),
            'From' => $this->from,
            'To' => $this->to,
            'Subject' => mb_encode_mimeheader($this->subject, 'UTF-8', 'B', "\r\n"),
            'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . $messageIdDomain . '>',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'base64',
        ];
        if ($this->replyTo !== null) {
            $headers['Reply-To'] = $this->replyTo;
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($this->textBody), 76, "\r\n"));
    }
}
