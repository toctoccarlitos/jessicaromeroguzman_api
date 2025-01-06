<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Suscripción al Newsletter</title>
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

        h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .content {
            margin: 30px 0;
        }

        .details {
            background: #F3F4F6;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .details p {
            margin: 8px 0;
            line-height: 1.6;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-new {
            background-color: #34D399;
            color: white;
        }

        .status-returning {
            background-color: #60A5FA;
            color: white;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Nueva Suscripción al Newsletter ✨</h1>
            </div>

            <div class="content">
                <p>¡Hay una nueva suscripción al newsletter!</p>

                <div class="details">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                    <p><strong>Fecha:</strong> <?php echo $date; ?></p>
                    <p>
                        <strong>Tipo:</strong> 
                        <?php if ($isResubscription): ?>
                            <span class="status-badge status-returning">Usuario que regresa</span>
                        <?php else: ?>
                            <span class="status-badge status-new">Nueva suscripción</span>
                        <?php endif; ?>
                    </p>
                </div>

                <p>El suscriptor ya ha recibido el email de bienvenida automáticamente.</p>
            </div>

            <div class="footer">
                <p>Este es un email automático, por favor no respondas.</p>
            </div>
        </div>
    </div>
</body>
</html>