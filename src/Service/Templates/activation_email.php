<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activa tu cuenta</title>
    <style>
        body {
            background-color: #FDF4FF;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #374151;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 8px 16px -4px rgba(147, 51, 234, 0.1);
            padding: 40px;
            transition: transform 0.3s ease;
        }

        .header {
            text-align: center;
            background: linear-gradient(135deg, #9333EA, #7928CA);
            margin: -40px -40px 30px -40px;
            padding: 40px 20px;
            border-radius: 24px 24px 0 0;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg,
                rgba(255,255,255,0.1) 0%,
                rgba(255,255,255,0.05) 100%);
            transform: skewY(-6deg);
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.02em;
            line-height: 1.2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
            font-weight: 500;
            margin-top: 8px;
            letter-spacing: 0.02em;
        }

        .content {
            margin: 30px 0;
        }

        .button {
            display: inline-block;
            background: linear-gradient(135deg, #9333EA, #7928CA);
            color: white !important;
            padding: 16px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(147, 51, 234, 0.2);
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(147, 51, 234, 0.3);
        }

        .footer {
            text-align: center;
            font-size: 14px;
            color: #6B7280;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
        }

        @media (max-width: 640px) {
            .container {
                padding: 16px;
            }

            .card {
                padding: 24px;
            }

            .header {
                margin: -24px -24px 24px -24px;
                padding: 32px 16px;
            }

            h1 {
                font-size: 24px;
            }

            .subtitle {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="header-content">
                    <h1>Bienvenido al UP-Team</h1>
                    <div class="subtitle">by Jessica Romero Guzmán</div>
                </div>
            </div>

            <div class="content">
                <p>Has sido registrado con el email:</p>
                <p style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($userEmail); ?></p>

                <p>Para activar tu cuenta y establecer tu contraseña, haz clic en el siguiente enlace:</p>

                <a href="<?php echo htmlspecialchars($activationUrl); ?>" class="button">
                    Activar mi cuenta
                </a>

                <p style="font-size: 14px; color: #6B7280;">
                    Este enlace expirará en <?php echo $expirationHours; ?> horas.
                </p>

                <p style="font-size: 14px; color: #6B7280;">
                    Si no has solicitado esta cuenta, puedes ignorar este email.
                </p>
            </div>

            <div class="footer">
                <p>Este es un email automático, por favor no respondas.</p>
            </div>
        </div>
    </div>
</body>
</html>