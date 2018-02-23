<?php

namespace JobQueue\Infrastructure;

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 *
 * @property \JobQueue\Domain\Task\Queue $queue
 * @property \Psr\Log\LoggerInterface $logger
 */
final class ServiceContainer
{
    /**
     *
     * @var self
     */
    protected static $instance;

    /**
     *
     * @var ContainerInterface
     */
    protected $services;

    /**
     *
     * @param ContainerInterface $services
     */
    public function __construct(ContainerInterface $services)
    {
        $this->services = $services;
    }

    /**
     *
     * @return ServiceContainer
     */
    public static function getInstance(): self
    {
        if (self::$instance) {
            return self::$instance;
        }

        $root = realpath(dirname(dirname(__DIR__)));
        $cache = sprintf('%s/cache/services.php', $root);
        if (is_readable($cache)) {
            // Retrieve services from the cache, if exists...
            require_once $cache;
            $services = new \ProjectServiceContainer;

        } else {
            // ... otherwise compile & cache the configuration
            $services = new ContainerBuilder;

            $loader = new YamlFileLoader($services, new FileLocator);
            $loader->load(sprintf('%s/config/services.yml', $root));

            $services->compile(true);

            if (!is_dir($cacheDir = dirname($cache))) {
                mkdir($cacheDir, 0777);
            }

            file_put_contents($cache, (new PhpDumper($services))->dump());
        }

        return self::$instance = new self($services);
    }

    /**
     *
     * @param $service
     * @return bool
     */
    public function __isset($service)
    {
        return $this->services->has($service);
    }

    /**
     *
     * @param $service
     * @return mixed|object
     */
    public function __get($service)
    {
        return $this->services->get($service);
    }
}
