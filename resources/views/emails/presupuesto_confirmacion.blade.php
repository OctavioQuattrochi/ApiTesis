
<h2>Presupuesto para tu pedido</h2>
<p>Hola {{ $quote->user->name }},</p>
<p>Tu presupuesto está listo para confirmar:</p>
<ul>
    <li>Producto: {{ $quote->color }}</li>
    <li>Medidas: {{ $quote->height_cm }} x {{ $quote->width_cm }} cm</li>
    <li>Cantidad: {{ $quote->quantity }}</li>
    <li>Precio estimado: ${{ number_format($quote->estimated_price, 2) }}</li>
    <li>Estado: {{ $quote->status }}</li>
</ul>
<p>Por favor, ingresa a tu cuenta para confirmar tu presupuesto y proceder con el pedido. Si tienes alguna pregunta, no dudes en contactarnos.</p>
<p>Gracias por elegirnos!</p>
<p>Saludos,<br>El equipo de ventas</p>