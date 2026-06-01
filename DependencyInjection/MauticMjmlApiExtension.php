<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMjmlApiBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Loads the bundle's Config/services.php into the container.
 *
 * Mautic/Symfony only auto-loads a plugin's services.php when the bundle has a
 * DependencyInjection Extension named <BundleName-without-Bundle>Extension. The
 * empty PluginBundleBase does not load services.php on its own.
 */
class MauticMjmlApiExtension extends Extension
{
    /**
     * @param mixed[] $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Config'));
        $loader->load('services.php');
    }
}
