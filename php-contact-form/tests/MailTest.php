<?php

declare(strict_types=1);

use ContactForm\Mail\Email;
use ContactForm\Mail\MailerFactory;
use ContactForm\Mail\MailException;
use ContactForm\Mail\NullMailer;
use ContactForm\Mail\SmtpConfig;
use ContactForm\Mail\SmtpMailer;

/**
 * Runs SmtpMailer against a scripted fake server over a local socket pair.
 * Returns everything the client wrote.
 *
 * @param list<string> $replies
 */
function smtpConversation(array $replies, SmtpConfig $config, Email $email, ?Throwable &$error = null): string
{
    [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    fwrite($server, implode('', array_map(static fn (string $r): string => $r . "\r\n", $replies)));
    try {
        (new SmtpMailer($config, static fn () => $client))->send($email);
    } catch (Throwable $e) {
        $error = $e;
    }
    $written = stream_get_contents($server);
    fclose($server);

    return (string) $written;
}

function demoEmail(string $body = "Hello.\n.hidden line starts with a dot\n"): Email
{
    return new Email('no-reply@atlas.example', 'contato@atlas.example', 'Novo contato: orçamento', $body, 'cliente@example.com');
}

test('Mail: disabled when SMTP_HOST is not set', function (): void {
    assertTrue(MailerFactory::fromEnv([]) instanceof NullMailer);
    assertTrue(MailerFactory::fromEnv(['SMTP_HOST' => 'smtp.example.com']) instanceof SmtpMailer);
});

test('Mail: SMTP conversation with AUTH LOGIN, dot-stuffing and UTF-8 subject', function (): void {
    $written = smtpConversation(
        ['220 fake ESMTP', '250-fake', '250 AUTH LOGIN', '334 VXNlcm5hbWU6', '334 UGFzc3dvcmQ6', '235 ok', '250 ok', '250 ok', '354 go', '250 queued', '221 bye'],
        new SmtpConfig('fake', 25, 'none', 'user@atlas.example', 's3cret'),
        demoEmail(),
    );
    assertContains("EHLO localhost\r\n", $written);
    assertContains("AUTH LOGIN\r\n" . base64_encode('user@atlas.example') . "\r\n" . base64_encode('s3cret') . "\r\n", $written);
    assertContains("MAIL FROM:<no-reply@atlas.example>\r\nRCPT TO:<contato@atlas.example>\r\nDATA\r\n", $written);
    assertContains("Reply-To: cliente@example.com\r\n", $written);
    assertContains('Subject: Novo contato: =?UTF-8?B?b3LDp2FtZW50bw==?=', $written);
    assertContains("\r\n.\r\nQUIT\r\n", $written);

    // The body is base64-encoded, so a line starting with "." can never end the DATA section early.
    preg_match("/\r\n\r\n(.*)\r\n\.\r\n/s", $written, $m);
    assertSame(demoEmail()->textBody, base64_decode(str_replace("\r\n", '', $m[1])));
});

test('Mail: server rejection raises MailException', function (): void {
    smtpConversation(
        ['220 fake', '250 fake', '250 ok', '550 mailbox unavailable'],
        new SmtpConfig('fake', 25, 'none'),
        demoEmail(),
        $error,
    );
    assertTrue($error instanceof MailException, 'MailException');
    assertContains('550 mailbox unavailable', $error->getMessage());
    assertContains('RCPT TO', $error->getMessage());
});

test('Mail: Email refuses header injection', function (): void {
    assertThrows(InvalidArgumentException::class, fn () => new Email('a@b.c', "x@y.z\r\nBcc: e@f.g", 'Hi', 'Body'));
    assertThrows(InvalidArgumentException::class, fn () => new Email('a@b.c', 'x@y.z', "Hi\r\nBcc: e@f.g", 'Body'));
});
