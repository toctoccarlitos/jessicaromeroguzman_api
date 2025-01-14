<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Core\Request;
use App\Entity\ActivityLog;

class SecurityService
{
    private EntityManagerInterface $em;
    private CacheService $cache;
    private array $config;

    // Rutas públicas que requieren validación completa
    private array $publicEndpoints = [
        'newsletter/subscribe',
        'contact',
        'login'
    ];

    public function __construct(EntityManagerInterface $em, CacheService $cache)
    {
        $this->em = $em;
        $this->cache = $cache;
        $this->config = $this->loadConfig();
    }

    private function loadConfig(): array
    {
        return [
            'rate_limit' => [
                'window' => 60,
                'max_requests' => 30
            ],
            'honeypot' => [
                'fields' => ['website', 'url', 'email_confirm']
            ],
            'recaptcha' => [
                'site_key' => $_ENV['RECAPTCHA_SITE_KEY'] ?? null,
                'secret_key' => $_ENV['RECAPTCHA_SECRET_KEY'] ?? null,
                'score_threshold' => 0.5
            ],
            'csrf' => [
                'token_length' => 32,
                'expiry' => 3600
            ]
        ];
    }

    public function validateRequest(Request $request): array
    {
        $path = $request->getUrl();
        $data = $request->getBody();
        $ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';
        $formId = $data['form_id'] ?? 'unknown';

        // Determinar si es un endpoint público
        $isPublicEndpoint = $this->isPublicEndpoint($path);

        // Validaciones para endpoints públicos
        if ($isPublicEndpoint) {
            // 1. Validar honeypot primero (es la validación más ligera)
            if (!$this->validateHoneypot($request)) {
                $this->logSuspiciousActivity($ip, 'honeypot_triggered', $formId);
                return [
                    'valid' => false,
                    'message' => 'Invalid form submission'
                ];
            }

            // 2. Verificar CSRF
            if (!isset($data['csrf_token'])) {
                $this->logSuspiciousActivity($ip, 'csrf_missing', $formId);
                return [
                    'valid' => false,
                    'message' => 'Missing security token'
                ];
            }

            if (!$this->validateCSRFToken($data['csrf_token'])) {
                $this->logSuspiciousActivity($ip, 'csrf_invalid', $formId);
                return [
                    'valid' => false,
                    'message' => 'Invalid security token'
                ];
            }

            // 3. Rate limiting
            if (!$this->checkRateLimit($ip)) {
                $this->logSuspiciousActivity($ip, 'rate_limit_exceeded', $formId);
                return [
                    'valid' => false,
                    'message' => 'Too many requests. Please try again later.'
                ];
            }

            // 4. Validación de reCAPTCHA solo para endpoints que lo requieren
            if ($this->requiresValidation($path, 'recaptcha')) {
                if (!isset($data['recaptcha_token'])) {
                    $this->logSuspiciousActivity($ip, 'recaptcha_missing', $formId);
                    return [
                        'valid' => false,
                        'message' => 'reCAPTCHA verification required'
                    ];
                }

                $recaptchaResponse = $this->verifyRecaptcha($data['recaptcha_token']);
                if (!$recaptchaResponse['success']) {
                    $this->logSuspiciousActivity($ip, 'recaptcha_failed', $formId);
                    return [
                        'valid' => false,
                        'message' => 'Invalid verification. Please try again.'
                    ];
                }
            }

            // 5. Validación de timestamp
            if (isset($data['timestamp']) && !$this->validateTimestamp($data['timestamp'])) {
                $this->logSuspiciousActivity($ip, 'invalid_timestamp', $formId);
                return [
                    'valid' => false,
                    'message' => 'Invalid request timing'
                ];
            }
        }

        return ['valid' => true];
    }

    // Agregar el método validateTimestamp que faltaba
    private function validateTimestamp(?string $timestamp): bool
    {
        if (!$timestamp) return false;
        
        $requestTime = (int) $timestamp;
        $currentTime = time() * 1000; // Convertir a milisegundos
        $timeDiff = abs($currentTime - $requestTime);
        
        // Permitir una diferencia de hasta 5 minutos
        return $timeDiff <= (5 * 60 * 1000);
    }

    private function isPublicEndpoint(string $path): bool
    {
        $path = trim($path, '/');
        foreach ($this->publicEndpoints as $endpoint) {
            if (strpos($path, $endpoint) !== false) {
                return true;
            }
        }
        return false;
    }

    private function validateCSRFToken(string $token): bool
    {
        try {
            $storedToken = $this->cache->get('csrf_' . session_id());
            if (!$storedToken) {
                return false;
            }
            return hash_equals($storedToken, $token);
        } catch (\Exception $e) {
            logger()->error('Error validating CSRF token', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sanitizeAndValidate(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            // Ignorar campos internos de seguridad
            if (in_array($key, ['csrf_token', 'timestamp', 'recaptcha_token', 'form_id'])) {
                continue;
            }

            // Sanitizar input
            $clean = $this->sanitizeInput($value);

            // Validar contenido spam
            if ($this->isSpam($clean)) {
                return [
                    'valid' => false,
                    'message' => 'Invalid content detected.'
                ];
            }

            $sanitized[$key] = $clean;
        }

        return [
            'valid' => true,
            'data' => $sanitized
        ];
    }

    private function checkRateLimit(string $ip): bool
    {
        $key = "rate_limit_{$ip}";
        $current = $this->cache->get($key, ['count' => 0, 'timestamp' => time()]);

        if ($current['timestamp'] < (time() - $this->config['rate_limit']['window'])) {
            $current = ['count' => 1, 'timestamp' => time()];
        } else {
            $current['count']++;
        }

        $this->cache->set($key, $current, $this->config['rate_limit']['window']);

        return $current['count'] <= $this->config['rate_limit']['max_requests'];
    }

    private function verifyRecaptcha(string $token): array
    {
        if (empty($token)) {
            return ['success' => false];
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $this->config['recaptcha']['secret_key'],
            'response' => $token
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response, true);

        return [
            'success' => $result['success'] ?? false,
            'score' => $result['score'] ?? 0,
            'action' => $result['action'] ?? ''
        ];
    }

    private function validateHoneypot(Request $request): bool
    {
        $data = $request->getBody();
        foreach ($this->config['honeypot']['fields'] as $field) {
            if (!empty($data[$field])) {
                return false;
            }
        }
        return true;
    }

    private function sanitizeInput($input): string
    {
        if (!is_string($input)) {
            return '';
        }

        // Eliminar caracteres invisibles
        $input = preg_replace('/[\x00-\x1F\x7F]/u', '', $input);

        // Convertir caracteres especiales a entidades HTML
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function isSpam(string $content): bool
    {
        // Lista de patrones spam comunes
        $patterns = [
            '/\b(?:viagra|casino|porn|xxx)\b/i',
            '/\[url=.*?\].*?\[\/url\]/i',
            '/<a\s+href/i',
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    private function logSuspiciousActivity(string $ip, string $type, string $formId): void
    {
        try {
            // Mapear el tipo de evento a las constantes de seguridad
            $securityType = match($type) {
                'rate_limit_exceeded' => ActivityLog::TYPE_SECURITY_RATE_LIMIT,
                'honeypot_triggered' => ActivityLog::TYPE_SECURITY_HONEYPOT,
                'csrf_invalid' => ActivityLog::TYPE_SECURITY_CSRF,
                'recaptcha_failed' => ActivityLog::TYPE_SECURITY_RECAPTCHA,
                'spam_detected' => ActivityLog::TYPE_SECURITY_SPAM,
                default => ActivityLog::TYPE_SECURITY_SUSPICIOUS
            };

            // Crear un nuevo log de actividad usando la estructura actual
            $log = new ActivityLog(
                $securityType,
                "Security event: {$type}",
                $ip
            );

            // Establecer el user agent y metadata
            $log->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown')
                ->setMetadata([
                    'form_id' => $formId,
                    'type_details' => $type
                ]);

            $this->em->persist($log);
            $this->em->flush();

        } catch (\Exception $e) {
            logger()->error('Error logging suspicious activity', [
                'error' => $e->getMessage(),
                'type' => $type,
                'ip' => $ip,
                'form_id' => $formId
            ]);
        }
    }

    public function generateCSRFToken(): string
    {
        try {
            // Generar token seguro
            $token = bin2hex(random_bytes(32));

            // Almacenar en cache con expiración
            $this->cache->set(
                'csrf_' . session_id(), 
                $token,
                3600 // 1 hora de validez
            );

            return $token;
        } catch (\Exception $e) {
            logger()->error('Error generating CSRF token', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function requiresValidation(string $path, string $validationType): bool
    {
        if (!$this->isPublicEndpoint($path)) {
            return false;
        }

        switch ($validationType) {
            case 'recaptcha':
                return in_array($path, ['newsletter/subscribe', 'contact', 'login']);
            case 'csrf':
                return true; // Todos los endpoints públicos requieren CSRF
            default:
                return false;
        }
    }
}