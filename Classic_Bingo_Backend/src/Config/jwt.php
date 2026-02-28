<?php

return [
    'secret' => $_ENV['JWT_SECRET'] ?? 'default-secret-key',
    'expiration' => (int)($_ENV['JWT_EXPIRATION'] ?? 3600), // 1 hour default
];