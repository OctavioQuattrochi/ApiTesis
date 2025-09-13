<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presupuesto listo para tu confirmación</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6fb; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: #f4f6fb;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: #fff; border-radius: 10px; box-shadow: 0 4px 16px #e0e0e0; margin: 40px 0;">
                    <tr>
                        <td style="padding: 32px 32px 16px 32px; text-align: center;">
                            <img src="https://i.imgur.com/4M7IWwP.png" alt="Logo" width="80" style="margin-bottom: 16px;">
                            <h2 style="color: #2a7ae4; margin-bottom: 8px;">¡Tu presupuesto está listo!</h2>
                            <p style="font-size: 17px; color: #333; margin-bottom: 24px;">Hola <strong>{{ $quote->user->name }}</strong>,<br>Te enviamos el detalle de tu presupuesto personalizado:</p>
                            <table width="100%" cellpadding="10" cellspacing="0" style="background: #f0f4f8; border-radius: 8px; margin-bottom: 24px;">
                                <tr>
                                    <td style="font-weight: bold; color: #555;">Producto:</td>
                                    <td>{{ $quote->color }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; color: #555;">Medidas:</td>
                                    <td>{{ $quote->height_cm }} x {{ $quote->width_cm }} cm</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; color: #555;">Cantidad:</td>
                                    <td>{{ $quote->quantity }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; color: #555;">Precio estimado:</td>
                                    <td style="color: #2a7ae4; font-size: 18px;"><strong>${{ number_format($quote->estimated_price, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; color: #555;">Estado:</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $quote->status)) }}</td>
                                </tr>
                            </table>
                            <a href="http://localhost:5173/login" style="display: inline-block; background: #2a7ae4; color: #fff; text-decoration: none; padding: 12px 32px; border-radius: 6px; font-size: 16px; margin-bottom: 24px;">Confirmar presupuesto</a>
                            <p style="font-size: 16px; color: #333; margin-bottom: 8px;">
                                <strong>Confirmar presupuesto desde "Mis Presupuestos" y enviar comprobante por wsp:</strong><br>
                                <span>CBU: <strong>0000003100000001234567</strong></span><br>
                                <span>WhatsApp: <strong>+54 9 351 2505516</strong></span>
                            </p>
                            <p style="font-size: 16px; color: #333; margin-bottom: 8px;">Si tienes alguna pregunta, no dudes en contactarnos.</p>
                            <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 24px 0;">
                            <p style="font-size: 15px; color: #888;">Gracias por elegirnos.<br>El equipo de ventas</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>