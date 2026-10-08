<?php

namespace Omnireview\Bridge\Symfony;

use Omnireview\Bridge\Twig\OmnireviewExtension;
use Omnireview\Google\GoogleGatewayFactory;
use Omnireview\Tripadvisor\TripadvisorGatewayFactory;
use Omnireview\Trustpilot\TrustpilotGatewayFactory;
use Omnireview\GatewayFactoryInterface;
use Omnireview\GatewayInterface;
use Omnireview\InviteInterface;
use Omnireview\RatingInterface;
use Omnireview\ReplyInterface;
use Omnireview\ReviewsInterface;
use Omnireview\WriteUrlInterface;
use Omnireview\Registry;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Extension\AbstractExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnireview in a Symfony application: the gateway packages installed
 * (omnireview/google, omnireview/tripadvisor, omnireview/trustpilot)
 * registered, the gateways built from configuration and injectable by
 * their name, and Twig's functions with the application's cache:
 *
 *     omnireview:
 *         gateways:
 *             google: { factory: google, options: { api_key: '%env(GOOGLE_PLACES_KEY)%' } }
 *             trustpilot: { factory: trustpilot, options: { api_key: '%env(TRUSTPILOT_KEY)%' } }
 *         cache:
 *             pool: cache.app      # kept no longer than each platform allows
 *             ttl: 3600
 *
 *     public function __construct(GatewayInterface $google) {}
 *     {{ omnireview_widget('google', place_id) }}
 *
 * Nothing is built, nor checked, when the container compiles. A factory is
 * autowired, the application's http_client given to it when it has one.
 */
final class OmnireviewBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnireview';

    /** The gateway packages this bundle knows, registered when installed. */
    private const FACTORIES = [GoogleGatewayFactory::class, TripadvisorGatewayFactory::class, TrustpilotGatewayFactory::class];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('gateways')
                    ->info('The gateways, by name: a factory (google, tripadvisor, trustpilot...) and its options.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('factory')->isRequired()->cannotBeEmpty()->end()
                            ->variableNode('options')->defaultValue([])->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('pool')->defaultValue('cache.app')->info('A Symfony cache (CacheInterface) the Twig functions keep what they show in - never longer than the platform allows.')->end()
                        ->integerNode('ttl')->defaultValue(3600)->min(0)->end()
                    ->end()
                ->end()
            ->end();
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', ['paths' => [\dirname(__DIR__).'/Twig/templates' => 'Omnireview']]);
        }
    }

    /** @param array{gateways: array<string, array{factory: string, options: array<string, mixed>}>, cache: array{pool: ?string, ttl: int}} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(GatewayFactoryInterface::class)->addTag('omnireview.gateway_factory');

        $services = $container->services();
        foreach (self::FACTORIES as $factory) {
            if (class_exists($factory) && is_subclass_of($factory, GatewayFactoryInterface::class)) {
                $definition = $services->set($factory)->autowire()->tag('omnireview.gateway_factory');
                foreach ((new \ReflectionClass($factory))->getConstructor()?->getParameters() ?? [] as $parameter) {
                    if ('http' === $parameter->getName()) {
                        $definition->arg('$http', service('http_client')->nullOnInvalid());
                    }
                }
            }
        }

        $services->set(Registry::class)
            ->args([tagged_iterator('omnireview.gateway_factory'), $config['gateways']])
            ->public();

        foreach (array_keys($config['gateways']) as $name) {
            $id = 'omnireview.gateway.'.$name;
            $services->set($id, GatewayInterface::class)->factory([service(Registry::class), 'get'])->args([$name]);
            foreach ([GatewayInterface::class, RatingInterface::class, ReviewsInterface::class, ReplyInterface::class, InviteInterface::class, WriteUrlInterface::class] as $type) {
                $builder->registerAliasForArgument($id, $type, $name);
            }
        }

        if (class_exists(AbstractExtension::class)) {
            $services->set(OmnireviewExtension::class)
                ->args([service(Registry::class), null !== $config['cache']['pool'] ? service($config['cache']['pool'])->nullOnInvalid() : null, $config['cache']['ttl']])
                ->tag('twig.extension');
        }
    }
}
