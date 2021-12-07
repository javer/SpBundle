<?php

namespace LightSaml\SpBundle\Tests\DependencyInjection\Security\Factory;

use LightSaml\SpBundle\DependencyInjection\Security\Factory\LightSamlSpFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ScalarNode;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

class LightSamlSpFactoryTest extends TestCase
{
    public function test_constructs_without_arguments(): void
    {
        $this->expectNotToPerformAssertions();

        new LightSamlSpFactory();
    }

    public function test_key(): void
    {
        $factory = new LightSamlSpFactory();
        $this->assertEquals('light_saml_sp', $factory->getKey());
    }

    public function test_position(): void
    {
        $factory = new LightSamlSpFactory();
        $this->assertEquals('form', $factory->getPosition());
    }

    public function configuration_provider(): array
    {
        return [
            ['username_mapper', ScalarNode::class, 'lightsaml_sp.username_mapper.simple'],
            ['user_creator', ScalarNode::class, null],
            ['attribute_mapper', ScalarNode::class, 'lightsaml_sp.attribute_mapper.simple'],
            ['token_factory', ScalarNode::class, 'lightsaml_sp.token_factory'],
        ];
    }

    /**
     * @dataProvider configuration_provider
     */
    public function test_configuration(string $configurationName, string $type, mixed $defaultValue): void
    {
        $factory = new LightSamlSpFactory();
        $treeBuilder = new TreeBuilder('light_saml_sp');
        $factory->addConfiguration($treeBuilder->getRootNode());
        $children = $treeBuilder->buildTree()->getChildren();
        $this->assertArrayHasKey($configurationName, $children);
        $this->assertInstanceOf($type, $children[$configurationName]);

        $this->assertEquals($defaultValue, $children[$configurationName]->getDefaultValue());
    }

    public function test_create_returns_authenticator(): void
    {
        $containerBuilder = new ContainerBuilder(new ParameterBag());
        $config = $this->getDefaultConfig();
        $factory = new LightSamlSpFactory();
        $authenticatorId = $factory->createAuthenticator($containerBuilder, 'main', $config, 'user.provider.id');
        $this->assertIsString($authenticatorId);
    }

    public function test_returns_lightsaml_authenticator_with_suffix(): void
    {
        $containerBuilder = new ContainerBuilder(new ParameterBag());
        $config = $this->getDefaultConfig();
        $factory = new LightSamlSpFactory();
        $authenticatorId = $factory->createAuthenticator($containerBuilder, 'main', $config, 'user.provider.id');
        $this->assertStringStartsWith('security.authenticator.lightsaml_sp', $authenticatorId);
        $this->assertStringEndsWith('.main', $authenticatorId);
    }

    public function test_creates_authenticator_service(): void
    {
        $containerBuilder = new ContainerBuilder(new ParameterBag());
        $config = $this->getDefaultConfig();
        $factory = new LightSamlSpFactory();
        $authenticatorId = $factory->createAuthenticator($containerBuilder, 'main', $config, 'user.provider.id');
        $this->assertTrue($containerBuilder->hasDefinition($authenticatorId));
    }

    public function test_injects_user_provider_to_authenticator(): void
    {
        $containerBuilder = new ContainerBuilder(new ParameterBag());
        $config = $this->getDefaultConfig();
        $factory = new LightSamlSpFactory();
        $authenticatorId = $factory->createAuthenticator($containerBuilder, 'main', $config, $userProvider = 'user.provider.id');
        $definition = $containerBuilder->getDefinition($authenticatorId);
        /** @var Reference $reference */
        $reference = $definition->getArgument(3);
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertEquals($userProvider, (string) $reference);
    }

    public function test_injects_username_mapper_to_authenticator(): void
    {
        $containerBuilder = new ContainerBuilder(new ParameterBag());
        $config = $this->getDefaultConfig();
        $factory = new LightSamlSpFactory();
        $authenticatorId = $factory->createAuthenticator($containerBuilder, 'main', $config, 'user.provider.id');
        $definition = $containerBuilder->getDefinition($authenticatorId);
        /** @var Reference $reference */
        $reference = $definition->getArgument(4);
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertEquals($config['username_mapper'], (string) $reference);
    }

    public function test_injects_user_creator_to_authenticator(): void
    {
        $containerBuilder = new ContainerBuilder(new ParameterBag());
        $config = $this->getDefaultConfig();
        $factory = new LightSamlSpFactory();
        $authenticatorId = $factory->createAuthenticator($containerBuilder, 'main', $config, 'user.provider.id');
        $definition = $containerBuilder->getDefinition($authenticatorId);
        /** @var Reference $reference */
        $reference = $definition->getArgument(5);
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertEquals($config['user_creator'], (string) $reference);
    }

    public function test_injects_attribute_mapper_to_authenticator(): void
    {
        $containerBuilder = new ContainerBuilder(new ParameterBag());
        $config = $this->getDefaultConfig();
        $factory = new LightSamlSpFactory();
        $authenticatorId = $factory->createAuthenticator($containerBuilder, 'main', $config, 'user.provider.id');
        $definition = $containerBuilder->getDefinition($authenticatorId);
        /** @var Reference $reference */
        $reference = $definition->getArgument(6);
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertEquals($config['attribute_mapper'], (string) $reference);
    }

    public function test_injects_token_factory_to_auth_provider(): void
    {
        $containerBuilder = new ContainerBuilder(new ParameterBag());
        $config = $this->getDefaultConfig();
        $factory = new LightSamlSpFactory();
        $authenticatorId = $factory->createAuthenticator($containerBuilder, 'main', $config, 'user.provider.id');
        $definition = $containerBuilder->getDefinition($authenticatorId);
        /** @var Reference $reference */
        $reference = $definition->getArgument(7);
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertEquals($config['token_factory'], (string) $reference);
    }

    private function getDefaultConfig(): array
    {
        return [
            'username_mapper' => 'lightsaml_sp.username_mapper.simple',
            'token_factory' => 'lightsaml_sp.token_factory',
            'user_creator' => 'some.user.creator',
            'attribute_mapper' => 'some.attribute.mapper',
            'remember_me' => true,
            'provider' => 'some.provider',
            'success_handler' => 'success_handler',
            'failure_handler' => 'failure_handler',
            'check_path' => '/login_check',
            'use_forward' => false,
            'require_previous_session' => true,
            'always_use_default_target_path' => false,
            'default_target_path' => '/',
            'login_path' => '/login',
            'target_path_parameter' => '_target_path',
            'use_referer' => false,
            'failure_path' => null,
            'failure_forward' => false,
            'failure_path_parameter' => '_failure_path',
        ];
    }
}
