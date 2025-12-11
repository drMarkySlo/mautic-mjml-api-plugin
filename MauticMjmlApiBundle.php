<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMjmlApiBundle;

use Mautic\PluginBundle\Bundle\PluginBundleBase;

/**
 * Mautic MJML API Bundle
 * 
 * This bundle enables the creation and modification of Mautic emails 
 * via API using MJML content. It handles automatic compilation to HTML
 * and integrates with the GrapesJS builder to preserve the MJML source.
 */
class MauticMjmlApiBundle extends PluginBundleBase
{
}