<?php

// La API del televisor se usa desde apps empaquetadas (origen file:// o null) y se
// autentica con token de dispositivo, nunca con cookies: se permite cualquier origen.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'DELETE'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Adult-Token', 'X-Pairing-Secret'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
