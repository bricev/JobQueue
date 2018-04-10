<?php

namespace JobQueue\Infrastructure;

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 *
 * @property \Symfony\Component\EventDispatcher\EventDispatcher $dispatcher
 * @property \Psr\Log\LoggerInterface $logger
 * @property \JobQueue\Domain\Task\Queue $queue
 */
final class ServiceContainer
{
    /**
     *
     * @var static
     */
    private static $instance;

    /**
     *
     * @var ContainerInterface
     */
    private $services;

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
     * @return static
     */
    public static function getInstance()
    {
        if (self::$instance) {
            return self::$instance;
        }

        $services = self::getServices();

        return self::$instance = new self($services);
    }

    /**
     *
     * @return ContainerInterface
     */
    private static function getServices(): ContainerInterface
    {
        $cache = sprintf('%s/jobqueue_services.php', sys_get_temp_dir());

        if ('prod' === getenv('JOBQUEUE_ENV') and is_readable($cache)) {
            // Retrieve services from the cache, if exists...
            require_once $cache;
            $services = new \ProjectServiceContainer;

        } else {
            // ... otherwise compile & cache the configuration
            $services = new ContainerBuilder;

            $loader = new YamlFileLoader($services, new FileLocator);
            $loader->load(sprintf('%s/config/services.yml', dirname(dirname(__DIR__))));

            $services->compile(true);

            // Compile and cache production config
            if ('prod' === getenv('JOBQUEUE_ENV')) {
                if (!is_dir($cacheDir = dirname($cache))) {
                    mkdir($cacheDir, 0777, true);
                }

                file_put_contents($cache, (new PhpDumper($services))->dump());
            }
        }

        return $services;
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
