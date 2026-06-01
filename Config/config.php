<?php

declare(strict_types=1);

return [
    'name'        => 'MJML API Bundle',
    'description' => 'Enables MJML email creation via API with GrapesJS builder support',
    'version'     => '1.0.0',
    'author'      => 'Mark Poljanšek',

    'routes' => [
        'api' => [
            'mautic_api_mjml_emails_new' => [
                'path'       => '/emails/mjml/new',
                'controller' => 'MauticPlugin\MauticMjmlApiBundle\Controller\MjmlApiController::createEmailAction',
                'method'     => 'POST',
            ],
            'mautic_api_mjml_emails_edit' => [
                'path'       => '/emails/mjml/{id}/edit',
                'controller' => 'MauticPlugin\MauticMjmlApiBundle\Controller\MjmlApiController::editEmailAction',
                'method'     => 'PATCH',
            ],
        ],
    ],

    'services' => [
        'other' => [
            'mautic.mjmlapi.helper.mjml_compiler' => [
                'class'     => \MauticPlugin\MauticMjmlApiBundle\Helper\MjmlCompiler::class,
                'arguments' => [],
            ],
        ],
    ],
];