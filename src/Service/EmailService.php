<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\ActivationToken;
use App\Entity\PasswordResetToken;
use App\Entity\ContactMessage;
use App\Entity\Newsletter;
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

    public function sendContactConfirmation(ContactMessage $message): void
    {
        try {
            logger()->info('Sending contact confirmation email', [
                'to' => $message->getEmail()
            ]);

            $html = $this->renderTemplate('contact_confirmation_email', [
                'name' => $message->getName(),
                'message' => $message->getMessage(),
                'date' => $message->getCreatedAt()->format('d/m/Y H:i')
            ]);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($message->getEmail())
                ->subject('Hemos recibido tu mensaje')
                ->html($html);

            $this->mailer->send($email);

            logger()->info('Contact confirmation email sent successfully');

        } catch (\Exception $e) {
            logger()->error('Failed to send contact confirmation email', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function sendContactNotification(ContactMessage $message): void
    {
        try {
            $adminEmail = $_ENV['ADMIN_EMAIL'] ?? null;
            $dashboardUrl = $_ENV['DASHBOARD_URL'] ?? null;

            if (!$adminEmail) {
                logger()->warning('Admin email not configured, skipping notification email');
                return;
            }

            logger()->info('Sending contact notification email', [
                'to' => $adminEmail,
                'message_id' => $message->getId()
            ]);

            $html = $this->renderTemplate('contact_notification_email', [
                'name' => $message->getName(),
                'email' => $message->getEmail(),
                'phone' => $message->getPhone(),
                'message' => $message->getMessage(),
                'date' => $message->getCreatedAt()->format('d/m/Y H:i'),
                'messageId' => $message->getId()
            ]);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($adminEmail)
                ->subject('Nuevo mensaje de contacto')
                ->html($html);

            $this->mailer->send($email);

            logger()->info('Contact notification email sent successfully');

        } catch (\Exception $e) {
            logger()->error('Failed to send contact notification email', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendNewsletterConfirmation(Newsletter $newsletter): void
    {
        try {
            logger()->info('Sending newsletter confirmation email', [
                'email' => $newsletter->getEmail()
            ]);

            // Crear token para desuscribirse
            $token = $this->createUnsubscribeToken($newsletter->getEmail());
            $unsubscribeUrl = $_ENV['APP_URL'] . "/api/newsletter/unsubscribe?token=" . $token;

            $html = $this->renderTemplate('newsletter_confirmation_email', [
                'unsubscribeUrl' => $unsubscribeUrl
            ]);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($newsletter->getEmail())
                ->subject('¡Bienvenido a nuestro Newsletter!')
                ->html($html);

            $this->mailer->send($email);

            logger()->info('Newsletter confirmation email sent successfully');

        } catch (\Exception $e) {
            logger()->error('Failed to send newsletter confirmation email', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function sendNewsletterNotification(Newsletter $newsletter): void
    {
        try {
            $adminEmail = $_ENV['ADMIN_EMAIL'] ?? null;

            if (!$adminEmail) {
                logger()->warning('Admin email not configured, skipping newsletter notification');
                return;
            }

            logger()->info('Sending newsletter subscription notification', [
                'subscriber_email' => $newsletter->getEmail()
            ]);

            $html = $this->renderTemplate('newsletter_notification_email', [
                'email' => $newsletter->getEmail(),
                'date' => $newsletter->getSubscribedAt()->format('d/m/Y H:i'),
                'isResubscription' => $newsletter->getCreatedAt() != $newsletter->getSubscribedAt()
            ]);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($adminEmail)
                ->subject('Nueva suscripción al Newsletter')
                ->html($html);

            $this->mailer->send($email);

            logger()->info('Newsletter notification email sent successfully');

        } catch (\Exception $e) {
            logger()->error('Failed to send newsletter notification email', [
                'error' => $e->getMessage()
            ]);
            // No relanzamos la excepción ya que es una notificación interna
        }
    }

    private function createUnsubscribeToken(string $email): string
    {
        // Encriptar el email usando la clave secreta de la aplicación
        return base64_encode(openssl_encrypt(
            $email,
            'AES-256-CBC',
            $_ENV['APP_SECRET'],
            0,
            substr($_ENV['APP_SECRET'], 0, 16)
        ));
    }
}