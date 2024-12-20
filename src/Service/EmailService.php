<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\ActivationToken;
use App\Entity\PasswordResetToken;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use App\Service\Logger\AppLogger;

class EmailService
{
    private Mailer $mailer;
    private string $fromEmail;
    private string $fromName;
    private AppLogger $logger;

    public function __construct()
    {
        $this->logger = new AppLogger();

        try {
            // Todas las configuraciones desde variables de entorno
            $transport = new EsmtpTransport(
                $_ENV['MAIL_HOST'],
                (int)$_ENV['MAIL_PORT'],
                $_ENV['MAIL_ENCRYPTION'] === 'ssl'  // true si es ssl, false para otros
            );

            $transport->setUsername($_ENV['MAIL_USER']);
            $transport->setPassword($_ENV['MAIL_PASS']);

            $this->mailer = new Mailer($transport);
            $this->fromEmail = $_ENV['MAIL_FROM'];
            $this->fromName = $_ENV['MAIL_FROM_NAME'];

        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize EmailService', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], $e);
            throw $e;
        }
    }

    public function sendActivationEmail(User $user, ActivationToken $token): void
    {
        try {
            $activationUrl = $_ENV['APP_URL'] . "/activate?token=" . $token->getToken();

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($user->getEmail())
                ->subject('Activa tu cuenta')
                ->html($this->renderTemplate('activation_email', [
                    'activationUrl' => $activationUrl,
                    'expirationHours' => 48,
                    'userEmail' => $user->getEmail()
                ]));

            $this->mailer->send($email);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send activation email', [
                'user_email' => $user->getEmail(),
                'error' => $e->getMessage()
            ], $e);
            throw $e;
        }
    }

    private function renderTemplate(string $template, array $data): string
    {
        $templatePath = __DIR__ . "/Templates/$template.php";

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: $template");
        }

        ob_start();
        extract($data);
        include $templatePath;
        return ob_get_clean();
    }

    public function sendPasswordResetEmail(User $user, PasswordResetToken $token): void
    {
        try {
            $resetUrl = $_ENV['APP_URL'] . "/reset-password?token=" . $token->getToken();

            $html = $this->renderTemplate('password_reset_email', [
                'resetUrl' => $resetUrl
            ]);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($user->getEmail())
                ->subject('Restablecer tu contraseña')
                ->html($html);

            $this->mailer->send($email);

        } catch (\Exception $e) {
            $this->logger->error('Error enviando email de reset', [
                'user_id' => $user->getId()
            ], $e);
            throw $e;
        }
    }
}