<?php

namespace JobQueue\Infrastructure;

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * The following services have to be set in /config/services_{env}.yml :
 *
 * @property \Symfony\Component\EventDispatcher\EventDispatcher $dispatcher
 * @property \Psr\Log\LoggerInterface $logger
 * @property \JobQueue\Domain\Task\Queue $queue
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

        if (class_exists('\Composer\Autoload\ClassLoader')) {
            // Find config dir using composer ClassLoader class
            $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
            $root = dirname(dirname(dirname($reflection->getFileName())));

        } else {
            // If composer has net been user, try to guess the root path
            $root = dirname(dirname(__DIR__));
        }

        $cache = sprintf('%s/cache/services_%s.php', $root, Environment::getName());
        if (Environment::isProd() and is_readable($cache)) {
            // Retrieve services from the cache, if exists...
            require_once $cache;
            $services = new \ProjectServiceContainer;

        } else {
            // ... otherwise compile & cache the configuration
            $services = new ContainerBuilder;

            $loader = new YamlFileLoader($services, new FileLocator);
            $loader->load(sprintf('%s/config/services_%s.yml', $root, Environment::getName()));

            // Compile and cache production config
            if (Environment::isProd()) {
                $services->compile(true);

                if (!is_dir($cacheDir = dirname($cache))) {
                    mkdir($cacheDir, 0777);
                }

                file_put_contents($cache, (new PhpDumper($services))->dump());
            }
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
