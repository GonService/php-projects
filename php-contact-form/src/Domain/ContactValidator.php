<?php

declare(strict_types=1);

namespace ContactForm\Domain;

/** Server-side validation of the public form. Never trusts the browser's checks. */
final class ContactValidator
{
    public const LIMITS = ['name' => 100, 'email' => 254, 'phone' => 20, 'company' => 120, 'body' => 5000];

    /**
     * @param array<string, mixed> $input raw POST data
     * @return array{data: array<string, string>, errors: array<string, string>}
     */
    public function validate(array $input): array
    {
        $get = static function (string $key) use ($input): string {
            $value = $input[$key] ?? '';
            // Strip control characters (keeps \n and \t in the message body), normalize line endings.
            return is_string($value) ? trim(str_replace("\r\n", "\n", (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value))) : '';
        };

        $data = [
            'name' => $get('name'),
            'email' => mb_strtolower($get('email')),
            'phone' => $get('phone'),
            'company' => $get('company'),
            'topic' => $get('topic'),
            'body' => $get('message'),
        ];
        $errors = [];

        if (mb_strlen($data['name']) < 2) {
            $errors['name'] = 'Please tell us your name.';
        } elseif (preg_match('/[\r\n<>]/', $data['name']) === 1) {
            $errors['name'] = 'The name contains characters that are not allowed.';
        }
        if (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid e-mail address so we can reply.';
        }
        if ($data['phone'] !== '' && preg_match('/^\+?[0-9 ()\-]{8,20}$/', $data['phone']) !== 1) {
            $errors['phone'] = 'Use digits, spaces, parentheses or dashes.';
        }
        if (Topic::tryFrom($data['topic']) === null) {
            $errors['topic'] = 'Choose a subject.';
        }
        if (mb_strlen($data['body']) < 10) {
            $errors['message'] = 'The message must have at least 10 characters.';
        }
        foreach (self::LIMITS as $field => $max) {
            if (mb_strlen($data[$field]) > $max) {
                $errors[$field === 'body' ? 'message' : $field] = "At most {$max} characters.";
            }
        }
        if (($input['consent'] ?? '') !== 'yes') {
            $errors['consent'] = 'We need your consent to store the message and reply to you.';
        }

        return ['data' => $data, 'errors' => $errors];
    }
}
