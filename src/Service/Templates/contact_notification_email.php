<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Mensaje de Contacto</title>
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

        .message-details {
            background: #F3F4F6;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .message-details p {
            margin: 5px 0;
        }

        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #9333EA, #7928CA);
            color: white !important;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
            transition: all 0.3s ease;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
            font-size: 14px;
            color: #6B7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Nuevo Mensaje de Contacto</h1>
            </div>

            <div class="content">
                <p>Se ha recibido un nuevo mensaje de contacto:</p>

                <div class="message-details">
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($name); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                    <?php if ($phone): ?>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($phone); ?></p>
                    <?php endif; ?>
                    <p><strong>Fecha:</strong> <?php echo $date; ?></p>
                    <p><strong>Mensaje:</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($message)); ?></p>
                </div>

                <p>Puedes gestionar este mensaje desde el panel de administración:</p>

                <center>
                    <a href="<?php echo $_ENV['DASHBOARD_URL']; ?>?messageId=<?php echo $messageId; ?>" class="action-button">
                        Ver en el Dashboard
                    </a>
                </center>
            </div>

            <div class="footer">
                <p>Este es un email automático, por favor no respondas.</p>
            </div>
        </div>
    </div>
</body>
</html>