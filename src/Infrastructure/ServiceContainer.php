<?php

namespace JobQueue\Infrastructure;

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * This class may be used to make extra services available to jobs from workers.
 *
 * The following services have to be set in /config/services_{env}.yml :
 *
 * @property \Symfony\Component\EventDispatcher\EventDispatcher $dispatcher
 * @property \Psr\Log\LoggerInterface $logger
 * @property \JobQueue\Domain\Task\Queue $queue
 */
class ServiceContainer
{
    /**
     *
     * @var static
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
     * @return static
     */
    public static function getInstance()
    {
        if (self::$instance) {
            return self::$instance;
        }

        $services = self::getServices();

        return self::$instance = new static($services);
    }

    /**
     *
     * @return ContainerInterface
     */
    protected static function getServices(): ContainerInterface
    {
        $cache = self::getConfigurationCachePath();

        if (Environment::isProd() and is_readable($cache)) {
            // Retrieve services from the cache, if exists...
            require_once $cache;
            $services = new \ProjectServiceContainer;

        } else {
            // ... otherwise compile & cache the configuration
            $services = new ContainerBuilder;

            $loader = new YamlFileLoader($services, new FileLocator);
            $loader->load(self::getConfigurationFilePath());

            // Compile and cache production config
            if (Environment::isProd()) {
                $services->compile(true);

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
     * @return string
     */
    protected static function getConfigurationFilePath(): string
    {
        // Get path from environment variables
        if ($path = (string) getenv('JOBQUEUE_CONFIG_PATH')) {
            return $path;
        }

        // Get dir path depending on how component is used
        if (class_exists('\Composer\Autoload\ClassLoader')) {
            // Find config dir using composer ClassLoader class
            $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
            $root = dirname(dirname(dirname($reflection->getFileName())));

        } else {
            // If composer has not been user, try to guess the root path
            $root = dirname(dirname(__DIR__));
        }

        return sprintf('%s/config/services_%s.yml', $root, Environment::getName());
    }

    /**
     *
     * @return string
     */
    protected static function getConfigurationCachePath(): string
    {
        // Get dir path from environment variables
        if (!$dir = (string) getenv('JOBQUEUE_CACHE_PATH')) {
            $dir = sys_get_temp_dir();
        }

        return sprintf('%s/jobqueue_services.php', $dir);
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
