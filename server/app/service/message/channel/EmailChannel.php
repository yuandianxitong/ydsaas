<?php
declare(strict_types=1);

namespace app\service\message\channel;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use think\facade\Config;

class EmailChannel implements ChannelInterface
{
    public function send(string $receiver, string $templateId, array $data, array $extra = []): array
    {
        $host = (string) Config::get('mail.host', '');
        if (empty($host)) {
            return ['success' => false, 'error' => 'Mail config incomplete: host not set'];
        }

        try {
            $dsn = $this->buildDsn();
            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);

            $fromAddress = Config::get('mail.from.address', 'noreply@example.com');
            $fromName = Config::get('mail.from.name', 'YdAdmin SaaS');

            $email = (new Email())
                ->from("{$fromName} <{$fromAddress}>")
                ->to($receiver)
                ->subject($data['subject'] ?? $extra['subject'] ?? 'Notification')
                ->html($data['body'] ?? '');

            $mailer->send($email);

            return ['success' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildDsn(): string
    {
        $host       = Config::get('mail.host');
        $port       = Config::get('mail.port', 465);
        $username   = urlencode((string) Config::get('mail.username', ''));
        $password   = urlencode((string) Config::get('mail.password', ''));
        $encryption = Config::get('mail.encryption', 'ssl');

        $scheme = match ($encryption) {
            'tls'  => 'smtp',
            'ssl'  => 'smtps',
            default => 'smtp',
        };

        if (!empty($username)) {
            return "{$scheme}://{$username}:{$password}@{$host}:{$port}";
        }

        return "{$scheme}://{$host}:{$port}";
    }
}
