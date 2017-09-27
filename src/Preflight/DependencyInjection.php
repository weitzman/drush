<?php
namespace Drush\Preflight;

use Drush\Drush;
use Drush\Cache\CommandCache;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application;
use Consolidation\Config\ConfigInterface;
use Composer\Autoload\ClassLoader;
use League\Container\ContainerInterface;
use Drush\SiteAlias\SiteAliasManager;

/**
 * Prepare our Dependency Injection Container
 */
class DependencyInjection
{
    /**
     * Set up our dependency injection container.
     */
    public static function initContainer(
        Application $application,
        ConfigInterface $config,
        InputInterface $input,
        OutputInterface $output,
        ClassLoader $loader,
        DrupalFinder $drupalFinder,
        SiteAliasManager $aliasManager
    ) {

        // Create default input and output objects if they were not provided
        if (!$input) {
            $input = new \Symfony\Component\Console\Input\StringInput('');
        }
        if (!$output) {
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        }
        // Set up our dependency injection container.
        $container = new \League\Container\Container();

        \Robo\Robo::configureContainer($container, $application, $config, $input, $output);
        $container->add('container', $container);

        static::addDrushServices($container, $loader, $drupalFinder, $aliasManager);

        // Store the container in the \Drush object
        Drush::setContainer($container);
        \Robo\Robo::setContainer($container);

        // Change service definitions as needed for our application.
        static::alterServicesForDrush($container, $application);

        // Inject needed services into our application object.
        static::injectApplicationServices($container, $application);

        return $container;
    }

    protected static function addDrushServices(ContainerInterface $container, ClassLoader $loader, DrupalFinder $drupalFinder, SiteAliasManager $aliasManager)
    {
        // Override Robo's logger with our own
        $container->share('logger', 'Drush\Log\Logger')
          ->withArgument('output')
          ->withMethodCall('setLogOutputStyler', ['logStyler']);

        $container->share('loader', $loader);
        $container->share('site.alias.manager', $aliasManager);

        // Override Robo's formatter manager with our own
        // @todo not sure that we'll use this. Maybe remove it.
        $container->share('formatterManager', \Drush\Formatters\DrushFormatterManager::class)
            ->withMethodCall('addDefaultFormatters', [])
            ->withMethodCall('addDefaultSimplifiers', []);

        // Add some of our own objects to the container
        $container->share('bootstrap.default', 'Drush\Boot\EmptyBoot');
        $container->share('bootstrap.drupal6', 'Drush\Boot\DrupalBoot6');
        $container->share('bootstrap.drupal7', 'Drush\Boot\DrupalBoot7');
        $container->share('bootstrap.drupal8', 'Drush\Boot\DrupalBoot8');
        $container->share('bootstrap.manager', 'Drush\Boot\BootstrapManager')
            ->withArgument('bootstrap.default')
            ->withMethodCall('setDrupalFinder', [$drupalFinder]);
        // TODO: Can we somehow add these via discovery (e.g. backdrop extension?)
        $container->extend('bootstrap.manager')
            ->withMethodCall('add', ['bootstrap.drupal6'])
            ->withMethodCall('add', ['bootstrap.drupal7'])
            ->withMethodCall('add', ['bootstrap.drupal8']);
        $container->share('bootstrap.hook', 'Drush\Boot\BootstrapHook')
          ->withArgument('bootstrap.manager');
        $container->share('redispatch.hook', 'Drush\Preflight\RedispatchHook');

        // Robo does not manage the command discovery object in the container,
        // but we will register and configure one for our use.
        // TODO: Some old adapter code uses this, but the Symfony dispatcher does not.
        // See Application::commandDiscovery().
        $container->share('commandDiscovery', 'Consolidation\AnnotatedCommand\CommandFileDiscovery')
            ->withMethodCall('addSearchLocation', ['CommandFiles'])
            ->withMethodCall('setSearchPattern', ['#.*(Commands|CommandFile).php$#']);

        // Add inflectors
        $container->inflector(\Drush\Boot\AutoloaderAwareInterface::class)
            ->invokeMethod('setAutoloader', ['loader']);
        $container->inflector(\Drush\SiteAlias\SiteAliasManagerAwareInterface::class)
            ->invokeMethod('setSiteAliasManager', ['site.alias.manager']);
    }

    protected static function alterServicesForDrush(ContainerInterface $container, Application $application)
    {
        // Add our own callback to the hook manager
        $hookManager = $container->get('hookManager');
        $hookManager->addInitializeHook($container->get('redispatch.hook'));
        $hookManager->addInitializeHook($container->get('bootstrap.hook'));
        $hookManager->addOutputExtractor(new \Drush\Backend\BackendResultSetter());
        // @todo: do we need both backend result setters? The one below should be removed at some point.
        $hookManager->add('annotatedcomand_adapter_backend_result', \Consolidation\AnnotatedCommand\Hooks\HookManager::EXTRACT_OUTPUT);

        // Install our command cache into the command factory
        // TODO: Create class-based implementation of our cache management functions.
        $cacheBackend = _drush_cache_get_object('factory');
        $commandCacheDataStore = new CommandCache($cacheBackend);

        $factory = $container->get('commandFactory');
        $factory->setIncludeAllPublicMethods(false);
        $factory->setDataStore($commandCacheDataStore);

        // It is necessary to set the dispatcher when using configureContainer
        $eventDispatcher = $container->get('eventDispatcher');
        $eventDispatcher->addSubscriber(new \Drush\Command\GlobalOptionsEventListener());
        $application->setDispatcher($eventDispatcher);
    }

    protected static function injectApplicationServices(ContainerInterface $container, Application $application)
    {
        $application->setLogger($container->get('logger'));
        $application->setBootstrapManager($container->get('bootstrap.manager'));
        $application->setAliasManager($container->get('site.alias.manager'));
    }
}