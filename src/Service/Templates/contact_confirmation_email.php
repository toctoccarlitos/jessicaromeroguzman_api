<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Gracias por tu mensaje!</title>
    <style>
        /* Mismos estilos que antes... */
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
            font-size: 16px;
            line-height: 1.7;
        }

        .message-preview {
            background: #F3F4F6;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .message-preview p {
            margin: 5px 0;
        }

        .emoji {
            font-size: 24px;
            margin-right: 8px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
        }

        .signature {
            margin-top: 30px;
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: #9333EA;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .signature a {
            all: unset;
            color: inherit;
            font: inherit; 
            letter-spacing: inherit;
            text-align: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        .brand {
            font-size: 14px;
            font-weight: 400;
            color: #6B7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>¡Hola <?php echo htmlspecialchars($name); ?>! 👋</h1>
            </div>

            <div class="content">
                <p><span class="emoji">✨</span>¡Tu mensaje ha llegado con éxito! Gracias por escribirnos.</p>

                <p style="margin-top: 20px;">Me encanta recibir mensajes y estaré encantada de ayudarte. Te responderé tan pronto como pueda con toda la info que necesitas.</p>

                <p>Por si te interesa recordar lo que me has contado, aquí tienes una copia de tu mensaje:</p>

                <div class="message-preview">
                    <p style="margin: 5px 0;"><strong>Fecha:</strong> <?php echo $date; ?></p>
                    <p style="margin: 15px 0;"><em><?php echo nl2br(htmlspecialchars($message)); ?></em></p>
                </div>

                <p style="margin-top: 25px;">¡Hablamos pronto! <span class="emoji">💜</span></p>
            </div>

            <div class="footer">
                <div class="signature">
                    <a href="https://jessicaromeroguzman.com" target="_blank">Jessica Romero Guzmán</a>
                    <div class="brand">UpTeam</div>
                </div>
                <p style="font-size: 14px; color: #6B7280; margin-top: 20px;">
                    Este es un email automático, pero no dudes en responder si necesitas algo más.
                </p>
            </div>
        </div>
    </div>
</body>
</html>