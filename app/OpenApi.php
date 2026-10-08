<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'BTS API',
    version: '1.0.0',
    description: 'REST API for BTS.id Recruitment Test — Product Management & Authentication'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum'
)]
class OpenApi
{
}
