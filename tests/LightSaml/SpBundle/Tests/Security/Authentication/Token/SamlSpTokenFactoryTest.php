<?php

namespace LightSaml\SpBundle\Tests\Security\Authentication\Token;

use LightSaml\Model\Protocol\Response;
use LightSaml\SpBundle\Security\Authentication\Token\SamlSpToken;
use LightSaml\SpBundle\Security\Authentication\Token\SamlSpTokenFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

class SamlSpTokenFactoryTest extends TestCase
{
    public function test_constructs_wout_arguments(): void
    {
        $this->expectNotToPerformAssertions();

        new SamlSpTokenFactory();
    }

    public function test_creates_token()
    {
        $factory = new SamlSpTokenFactory();

        $token = $factory->create(
            $user = new InMemoryUser('joe', '', ['ROLE_USER']),
            'main',
            $attributes = ['a'=>1],
            new Response(),
        );

        $this->assertInstanceOf(SamlSpToken::class, $token);
        $roleNames = $token->getRoleNames();
        $this->assertCount(1, $roleNames);
        $this->assertEquals('ROLE_USER', $roleNames[0]);
        $this->assertEquals($attributes, $token->getAttributes());
        $this->assertSame($user, $token->getUser());
    }
}
