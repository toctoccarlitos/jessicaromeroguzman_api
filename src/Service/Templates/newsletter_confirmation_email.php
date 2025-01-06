<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Bienvenido al Newsletter!</title>
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
            font-size: 16px;
            line-height: 1.7;
        }

        .perks {
            background: #F3F4F6;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .perks ul {
            margin: 0;
            padding-left: 20px;
        }

        .perks li {
            margin: 10px 0;
            color: #4B5563;
        }

        .signature {
            margin-top: 30px;
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: #9333EA;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .unsubscribe {
            text-align: center;
            font-size: 14px;
            color: #6B7280;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
        }

        .unsubscribe a {
            color: #9333EA;
            text-decoration: none;
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
                <div class="header-content">
                    <h1>¡Bienvenido al Newsletter! ✨</h1>
                    <div class="subtitle">by Jessica Romero Guzmán</div>
                </div>
            </div>

            <div class="content">
                <p>¡Hola! 👋</p>

                <p>¡Gracias por unirte a nuestra comunidad! Me emociona tenerte aquí y poder compartir contigo contenido exclusivo y valioso.</p>

                <div class="perks">
                    <p><strong>¿Qué puedes esperar?</strong></p>
                    <ul>
                        <li>Actualizaciones y novedades antes que nadie</li>
                        <li>Consejos y mejores prácticas exclusivas</li>
                        <li>Recursos útiles para tu desarrollo profesional</li>
                        <li>Contenido inspirador y motivador</li>
                    </ul>
                </div>

                <p>Mantente atento a tu bandeja de entrada, ¡pronto recibirás contenido increíble! 💫</p>

                <p>¡Un abrazo! 💜</p>

                <div class="signature">
                    Jessica Romero Guzmán
                    <div style="font-size: 14px; font-weight: 400; color: #6B7280;">UpTeam</div>
                </div>

                <div class="unsubscribe">
                    <p>Si en algún momento deseas darte de baja, puedes hacerlo <a href="<?php echo htmlspecialchars($unsubscribeUrl); ?>">haciendo clic aquí</a>.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>