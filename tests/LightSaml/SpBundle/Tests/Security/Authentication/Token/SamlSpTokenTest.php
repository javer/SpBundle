<?php

namespace LightSaml\SpBundle\Tests\Security\Authentication\Token;

use LightSaml\SpBundle\Security\Authentication\Token\SamlSpToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

class SamlSpTokenTest extends TestCase
{
    public function test_constructs_with_roles_array_provider_key_string_attributes_array_and_user(): void
    {
        $token = new SamlSpToken(
            new InMemoryUser('username', ''),
            'main',
            $expectedRoleNames = ['ROLE_USER'],
            $expectedAttributes = ['a', 'b'],
        );

        $this->assertEquals($expectedRoleNames, $token->getRoleNames());
        $this->assertEquals($expectedAttributes, $token->getAttributes());
    }

    public function test_returns_empty_credentials(): void
    {
        $token = new SamlSpToken(new InMemoryUser('username', '123'), 'main', [], []);
        $this->assertEquals([], $token->getCredentials());
    }

    public function test_returns_provider_key_given_in_constructor(): void
    {
        $token = new SamlSpToken(new InMemoryUser('username', ''), $expectedFirewallName = 'main', [], []);
        $this->assertEquals($expectedFirewallName, $token->getFirewallName());
    }
}
