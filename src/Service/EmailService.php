<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\ActivationToken;
use App\Entity\PasswordResetToken;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class EmailService
{
    private Mailer $mailer;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        try {
            logger()->debug('Initializing EmailService');

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

            logger()->info('EmailService initialized successfully', [
                'host' => $_ENV['MAIL_HOST'],
                'port' => $_ENV['MAIL_PORT'],
                'from_email' => $this->fromEmail
            ]);

        } catch (\Exception $e) {
            logger()->error('Failed to initialize EmailService', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function sendActivationEmail(User $user, ActivationToken $token): void
    {
        try {
            logger()->info('Sending activation email', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'token_expires' => $token->getExpiresAt()->format('Y-m-d H:i:s')
            ]);

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

            logger()->info('Activation email sent successfully', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail()
            ]);

        } catch (\Exception $e) {
            logger()->error('Failed to send activation email', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function renderTemplate(string $template, array $data): string
    {
        $templatePath = __DIR__ . "/Templates/$template.php";

        if (!file_exists($templatePath)) {
            logger()->error('Email template not found', [
                'template' => $template,
                'path' => $templatePath
            ]);
            throw new \RuntimeException("Template not found: $template");
        }

        logger()->debug('Rendering email template', [
            'template' => $template,
            'data_keys' => array_keys($data)
        ]);

        ob_start();
        extract($data);
        include $templatePath;
        return ob_get_clean();
    }

    public function sendPasswordResetEmail(User $user, PasswordResetToken $token): void
    {
        try {
            logger()->info('Sending password reset email', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'token_expires' => $token->getExpiresAt()->format('Y-m-d H:i:s')
            ]);

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

            logger()->info('Password reset email sent successfully', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail()
            ]);

        } catch (\Exception $e) {
            logger()->error('Failed to send password reset email', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}