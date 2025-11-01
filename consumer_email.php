<?php

declare(strict_types=1);

$config = require_once __DIR__ . '/bootstrap.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Configuración de la conexión
$connection = new AMQPStreamConnection(
    host: $config['rabbitmq']['host'],
    port: $config['rabbitmq']['port'],
    user: $config['rabbitmq']['user'],
    password: $config['rabbitmq']['password']
);

$channel = $connection->channel();

// Configurar QoS (prefetch_size = 0, prefetch_count = 1)
$channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

// Callback para procesar los mensajes
$callback = function (AMQPMessage $message) use ($channel): void {
    $data = json_decode($message->body, associative: true);

    echo sprintf(
        "[%s] Procesando mensaje para usuario ID: %d (Intento %d de 3)\n",
        date('H:i:s'),
        $data['user_id'],
        $data['retries'] + 1
    );

    // Simular fallo aleatorio (50% de probabilidad)
    $falla = ($data['user_id'] % 2 == 0);

    if ($falla) {
        echo "Error al procesar el mensaje...\n";

        // Verificar si aún hay reintentos disponibles (permitir hasta 3 intentos)
        if ($data['retries'] < 2) {  // 0->1->2 (tres intentos en total)
            $data['retries']++;

            // Crear nuevo mensaje con el contador de reintentos actualizado
            $retryMessage = new AMQPMessage(
                body: json_encode($data),
                properties: ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );
            echo sprintf(
                format: "Reintentando más tarde (intento %d de 3)...\n",
                values: $data['retries']
            );

            // Publicar el mensaje actualizado en la cola de reintento
            $channel->basic_publish(
                msg: $retryMessage,
                exchange: 'dlx_exchange',
                routing_key: 'retry.email'
            );

            // Confirmar el mensaje original
            $channel->basic_ack((string)$message->getDeliveryTag());
        } else {
            echo "Error irrecuperable. Moviendo a cola de errores.\n";

            // Crear mensaje para la cola muerta final
            $deadMessage = new AMQPMessage(
                body: json_encode([
                    'original_message' => $data,
                    'error' => 'Máximo número de reintentos alcanzado',
                    'last_retry' => date('Y-m-d H:i:s'),
                ]),
                properties: ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            // Publicar en la cola muerta final
            $channel->basic_publish(
                msg: $deadMessage,
                exchange: 'final_dlx_exchange',
                routing_key: 'dead.email'
            );

            // Confirmar el mensaje original
            $channel->basic_ack(delivery_tag: (string)$message->getDeliveryTag());
        }
    } else {
        // Simulación de envío exitoso de email
        echo sprintf(
            format: "Email enviado exitosamente a %s\n",
            values: $data['email']
        );

        // Confirmar el mensaje
        $channel->basic_ack(delivery_tag: (string)$message->getDeliveryTag());
    }
};

// Consumir mensajes de la cola
$channel->basic_consume(
    queue: 'email_queue',
    consumer_tag: '',
    no_local: false,
    no_ack: false,
    exclusive: false,
    nowait: false,
    callback: $callback
);

echo "Esperando mensajes. Para salir presiona CTRL+C\n";

// Mantener el consumidor ejecutándose
while (count($channel->callbacks)) {
    $channel->wait();
}

// Cerrar la conexión
$channel->close();
$connection->close();
