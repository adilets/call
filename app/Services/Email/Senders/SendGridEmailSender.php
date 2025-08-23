<?php

namespace App\Services\Email\Senders;

use App\Contracts\EmailSenderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class SendGridEmailSender implements EmailSenderInterface
{
    protected string $apiUrl = 'https://api.sendgrid.com/v3/mail/send';
    protected string $apiKey;
    protected ?string $defaultFromEmail;
    protected ?string $defaultFromName;

    public function __construct()
    {
        $this->apiKey           = (string) config('email.senders.sendgrid.api_key');
        $this->defaultFromEmail = config('email.senders.sendgrid.from_email');
        $this->defaultFromName  = config('email.senders.sendgrid.from_name');
    }

    /**
     * Базовый метод отправки.
     *
     * @param  string|array  $to  // 'user@ex.com' или ['email'=>'user@ex.com','name'=>'User']
     * @param  string  $subject
     * @param  string|null $html
     * @param  string|null $fromEmail
     * @param  string|null $fromName
     * @param  array $options  // reply_to, text, cc, bcc, headers, categories, template_id, dynamic_template_data, attachments
     */
    public function send(
        $to,
        string $subject,
        ?string $html,
        ?string $fromEmail = null,
        ?string $fromName = null,
        array $options = []
    ): bool {
        $fromEmail = $fromEmail
            ?: $this->defaultFromEmail
            ?: config('mail.from.address')
            ?: 'noreply@mydomain.com';
        $fromName  = $fromName
            ?: $this->defaultFromName
            ?: config('mail.from.name')
            ?: config('app.name', 'App');

        if (empty($this->apiKey)) {
            Log::warning('SendGrid: missing API key, aborting send');
            return false;
        }

        // Сбор получателей
        $toEntry = $this->formatAddress($to);
        $cc      = array_map([$this, 'formatAddress'], Arr::wrap($options['cc'] ?? []));
        $bcc     = array_map([$this, 'formatAddress'], Arr::wrap($options['bcc'] ?? []));

        // Reply-To
        $replyTo = null;
        if (!empty($options['reply_to'])) {
            $replyTo = $this->formatAddress($options['reply_to']);
        }

        // Контент (текст+html)
        $content = [];
        if (!empty($options['text'])) {
            $content[] = ['type' => 'text/plain', 'value' => (string) $options['text']];
        }

        if (!empty($html)) {
            $content[] = ['type' => 'text/html', 'value' => $html];
        }
        // Если используется динамический шаблон — html может быть пустым

        // Вложения: массив элементов с ключами path | content | filename | type | disposition | content_id
        $attachments = [];
        foreach (Arr::wrap($options['attachments'] ?? []) as $att) {
            // Можно передать либо готовый base64 в 'content', либо путь в 'path'
            $item = [];
            if (!empty($att['content'])) {
                $item['content'] = $att['content'];
            } elseif (!empty($att['path']) && is_readable($att['path'])) {
                $item['content'] = base64_encode(file_get_contents($att['path']));
            } else {
                continue;
            }
            $item['filename']   = $att['filename']   ?? basename($att['path'] ?? 'file.bin');
            $item['type']       = $att['type']       ?? null;
            $item['disposition']= $att['disposition']?? 'attachment';
            $item['content_id'] = $att['content_id'] ?? null;

            // Удаляем null-поля
            $attachments[] = array_filter($item, fn($v) => $v !== null);
        }

        // Заголовки
        $headers = $options['headers'] ?? [];

        // Категории (для SendGrid аналитики/фильтров)
        $categories = Arr::wrap($options['categories'] ?? []);

        // Динамические шаблоны SendGrid
        $templateId = $options['template_id'] ?? null;
        $dynamicData = $options['dynamic_template_data'] ?? null;

        $personalization = array_filter([
            'to'      => [$toEntry],
            'cc'      => !empty($cc) ? $cc : null,
            'bcc'     => !empty($bcc) ? $bcc : null,
            'subject' => $templateId ? null : $subject,
            'dynamic_template_data' => $dynamicData,
            'headers' => !empty($headers) ? $headers : null,
        ], fn($v) => $v !== null);

        $payload = array_filter([
            'from' => [
                'email' => $fromEmail,
                'name'  => $fromName,
            ],
            'reply_to' => $replyTo,
            'personalizations' => [$personalization],
            'content'     => $templateId ? null : $content,
            'attachments' => !empty($attachments) ? $attachments : null,
            'categories'  => !empty($categories) ? $categories : null,
            'template_id' => $templateId,
        ], fn($v) => $v !== null);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
                ->timeout(20)
                ->post($this->apiUrl, $payload);

            $status = $response->status();
            $reqId  = $response->header('X-Message-Id') ?: $response->header('X-Request-Id');

            if ($status === 202) { // SendGrid возвращает 202 Accepted при успехе
                Log::info('SendGrid: email accepted', [
                    'to'       => $toEntry,
                    'subject'  => $subject,
                    'requestId'=> $reqId,
                ]);
                return true;
            }

            // Парсим ошибки из тела
            $body = $response->json();
            $errors = $body['errors'] ?? [];
            Log::warning('SendGrid: send failed', [
                'status'   => $status,
                'errors'   => $errors,
                'body'     => $response->body(),
                'requestId'=> $reqId,
            ]);
            return false;

        } catch (\Throwable $e) {
            Log::error('SendGrid exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function formatAddress($value): array
    {
        if (is_string($value)) {
            return ['email' => $value];
        }
        if (is_array($value)) {
            return array_filter([
                'email' => $value['email'] ?? null,
                'name'  => $value['name']  ?? null,
            ], fn($v) => $v !== null);
        }
        throw new \InvalidArgumentException('Invalid email address format.');
    }
}

// Простой вызов:
//$ok = app(\App\Services\Email\Senders\SendGridEmailSender::class)
//    ->send('user@example.com', 'Welcome!', '<b>Hello</b> from mydomain!');


//С Reply-To, именем получателя и текстовой частью:
// $ok = app(\App\Services\Email\Senders\SendGridEmailSender::class)
//    ->send(
//        ['email' => 'user@example.com', 'name' => 'User'],
//        'Your invoice',
//        '<p>See attachment</p>',
//        null, // fromEmail (оставляем дефолт)
//        null, // fromName (оставляем дефолт)
//        [
//            'reply_to' => ['email' => 'support@mydomain.com', 'name' => 'Support'],
//            'text'     => "See attachment",
//            'attachments' => [
//                ['path' => storage_path('app/invoices/inv-123.pdf'), 'filename' => 'invoice.pdf', 'type' => 'application/pdf'],
//            ],
//            'headers'    => ['X-Campaign' => 'onboarding'],
//            'categories' => ['invoices', 'onboarding'],
//        ]
//    );


//С динамическим шаблоном SendGrid (Design Library → Dynamic Templates):
// $ok = app(\App\Services\Email\Senders\SendGridEmailSender::class)
//    ->send(
//        'user@example.com',
//        '', // subject можно оставить пустым — берётся из шаблона
//        null, // html не обязателен
//        null,
//        null,
//        [
//            'template_id' => 'd-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', // ID вашего шаблона
//            'dynamic_template_data' => [
//                'userName' => 'Adilet',
//                'actionUrl'=> 'https://mydomain.com/verify?token=abc',
//            ],
//        ]
//    );
