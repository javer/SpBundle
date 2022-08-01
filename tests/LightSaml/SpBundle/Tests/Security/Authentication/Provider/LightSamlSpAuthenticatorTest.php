<?php

namespace LightSaml\SpBundle\Tests\Security\Authentication\Provider;

use LightSaml\Action\ActionInterface;
use LightSaml\Builder\Profile\ProfileBuilderInterface;
use LightSaml\Context\Profile\ProfileContext;
use LightSaml\Model\Protocol\Response;
use LightSaml\SpBundle\Security\Authentication\Token\SamlSpToken;
use LightSaml\SpBundle\Security\Authentication\Token\SamlSpTokenFactoryInterface;
use LightSaml\SpBundle\Security\Authenticator\LightSamlSpAuthenticator;
use LightSaml\SpBundle\Security\User\AttributeMapperInterface;
use LightSaml\SpBundle\Security\User\UserCreatorInterface;
use LightSaml\SpBundle\Security\User\UsernameMapperInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\HttpUtils;

class LightSamlSpAuthenticatorTest extends TestCase
{
    public function test_constructs_with_provider_key(): void
    {
        $this->expectNotToPerformAssertions();

        new LightSamlSpAuthenticator('main', $this->getHttpUtilsMock(), $this->getProfileBuilderMock());
    }

    public function test_constructs_with_all_arguments(): void
    {
        $this->expectNotToPerformAssertions();

        new LightSamlSpAuthenticator(
            'main',
            $this->getHttpUtilsMock(),
            $this->getProfileBuilderMock(),
            $this->getUserProviderMock(),
            $this->getUsernameMapperMock(),
            $this->getUserCreatorMock(),
            $this->getAttributeMapperMock(),
            $this->getTokenFactoryMock(),
            $this->getAuthenticationSuccessHandlerMock(),
            $this->getAuthenticationFailureHandlerMock(),
            [],
        );
    }

    public function test_supports_check_path(): void
    {
        $authenticator = new LightSamlSpAuthenticator(
            'main',
            $httpUtilsMock = $this->getHttpUtilsMock(),
            $this->getProfileBuilderMock(),
            options: ['check_path' => '/login/saml'],
        );

        $request = Request::create('/login/saml');

        $httpUtilsMock->expects($this->once())
            ->method('checkRequestPath')
            ->with($request)
            ->willReturn(true);

        $this->assertTrue($authenticator->supports($request));
    }

    public function test_creates_passport_with_user_and_his_roles(): void
    {
        $authenticator = new LightSamlSpAuthenticator(
            'main',
            $this->getHttpUtilsMock(),
            $profileBuilderMock = $this->getProfileBuilderMock(),
            $userProviderMock = $this->getUserProviderMock(),
            $usernameMapperMock = $this->getUsernameMapperMock(),
        );

        $actionMock = $this->getActionMock();
        $contextMock = $this->getContextMock();

        $profileBuilderMock->expects($this->any())
            ->method('buildContext')
            ->willReturn($contextMock);
        $profileBuilderMock->expects($this->any())
            ->method('buildAction')
            ->willReturn($actionMock);

        $samlResponse = new Response();

        $contextMock->expects($this->any())
            ->method('getInboundMessage')
            ->willReturn($samlResponse);

        $actionMock->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (ProfileContext $context) use ($samlResponse, $contextMock) {
                $this->assertSame($contextMock, $context);
            });

        $expectedUsername = 'some.username';
        $user = $this->getUserMock();
        $user
            ->method(method_exists(UserInterface::class, 'getUserIdentifier') ? 'getUserIdentifier' : 'getUsername')
            ->willReturn($expectedUsername);

        $usernameMapperMock->expects($this->once())
            ->method('getUsername')
            ->willReturn($expectedUsername);

        $userProviderMock->expects($this->once())
            ->method(
                method_exists(UserProviderInterface::class, 'loadUserByIdentifier')
                    ? 'loadUserByIdentifier'
                    : 'loadUserByUsername'
            )
            ->with($expectedUsername)
            ->willReturn($user);

        $passport = $authenticator->authenticate(Request::create('/'));

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertSame($samlResponse, $passport->getAttribute(LightSamlSpAuthenticator::PASSPORT_SAML_RESPONSE));
        $this->assertSame($user, $passport->getUser());
    }

    public function test_creates_authenticated_token_with_user_and_his_roles_and_attributes(): void
    {
        $authenticator = new LightSamlSpAuthenticator(
            $firewallName = 'main',
            $this->getHttpUtilsMock(),
            $this->getProfileBuilderMock(),
        );

        $samlResponse = new Response();
        $attributes = ['a' => 'b'];

        $user = $this->getUserMock();
        $user->expects($this->any())
            ->method('getRoles')
            ->willReturn($expectedRoles = ['foo', 'bar']);
        $user
            ->method(method_exists(UserInterface::class, 'getUserIdentifier') ? 'getUserIdentifier' : 'getUsername')
            ->willReturn('some.username');

        $passport = new SelfValidatingPassport(new UserBadge(
            method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername(),
            static fn() => $user,
        ));
        $passport->setAttribute(LightSamlSpAuthenticator::PASSPORT_ATTRIBUTES, $attributes);
        $passport->setAttribute(LightSamlSpAuthenticator::PASSPORT_SAML_RESPONSE, $samlResponse);

        $authenticatedToken = $authenticator->createToken($passport, $firewallName);

        $this->assertInstanceOf(SamlSpToken::class, $authenticatedToken);
        $this->assertSame($user, $authenticatedToken->getUser());
        $this->assertEquals($firewallName, $authenticatedToken->getFirewallName());
        $this->assertEquals($expectedRoles, $authenticatedToken->getRoleNames());
        $this->assertEquals($attributes, $authenticatedToken->getAttributes());
    }

    public function test_calls_user_creator_if_user_does_not_exist(): void
    {
        $authenticator = new LightSamlSpAuthenticator(
            'main',
            $this->getHttpUtilsMock(),
            $profileBuilderMock = $this->getProfileBuilderMock(),
            userCreator: $userCreatorMock = $this->getUserCreatorMock(),
        );

        $actionMock = $this->getActionMock();
        $contextMock = $this->getContextMock();

        $profileBuilderMock->expects($this->any())
            ->method('buildContext')
            ->willReturn($contextMock);
        $profileBuilderMock->expects($this->any())
            ->method('buildAction')
            ->willReturn($actionMock);

        $samlResponse = new Response();

        $contextMock->expects($this->any())
            ->method('getInboundMessage')
            ->willReturn($samlResponse);

        $user = $this->getUserMock();
        $user->expects($this->any())
            ->method('getRoles')
            ->willReturn(['foo', 'bar']);
        $user
            ->method(method_exists(UserInterface::class, 'getUserIdentifier') ? 'getUserIdentifier' : 'getUsername')
            ->willReturn('some.username');

        $userCreatorMock->expects($this->once())
            ->method('createUser')
            ->with($samlResponse)
            ->willReturn($user);

        $passport = $authenticator->authenticate(Request::create('/'));

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertSame($samlResponse, $passport->getAttribute(LightSamlSpAuthenticator::PASSPORT_SAML_RESPONSE));
        $this->assertSame($user, $passport->getUser());
    }

    public function test_throws_authentication_exception_if_user_does_not_exists_and_its_not_created(): void
    {
        $authenticator = new LightSamlSpAuthenticator(
            'main',
            $this->getHttpUtilsMock(),
            $profileBuilderMock = $this->getProfileBuilderMock(),
            userProvider: $userProviderMock = $this->getUserProviderMock(),
            usernameMapper: $usernameMapperMock = $this->getUsernameMapperMock(),
            userCreator: $userCreatorMock = $this->getUserCreatorMock(),
        );

        $actionMock = $this->getActionMock();
        $contextMock = $this->getContextMock();

        $profileBuilderMock->expects($this->any())
            ->method('buildContext')
            ->willReturn($contextMock);
        $profileBuilderMock->expects($this->any())
            ->method('buildAction')
            ->willReturn($actionMock);

        $samlResponse = new Response();

        $contextMock->expects($this->any())
            ->method('getInboundMessage')
            ->willReturn($samlResponse);

        $expectedUsername = 'some.username';
        $user = $this->getUserMock();
        $user->expects($this->any())
            ->method('getRoles')
            ->willReturn(['foo', 'bar']);
        $user
            ->method(method_exists(UserInterface::class, 'getUserIdentifier') ? 'getUserIdentifier' : 'getUsername')
            ->willReturn($expectedUsername);

        $usernameMapperMock->expects($this->once())
            ->method('getUsername')
            ->willReturn($expectedUsername);

        $userProviderMock->expects($this->once())
            ->method(
                method_exists(UserProviderInterface::class, 'loadUserByIdentifier')
                    ? 'loadUserByIdentifier'
                    : 'loadUserByUsername'
            )
            ->with($expectedUsername)
            ->willThrowException(new UserNotFoundException());

        $userCreatorMock->expects($this->once())
            ->method('createUser')
            ->with($samlResponse)
            ->willReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unable to resolve user');

        $authenticator->authenticate(Request::create('/'));
    }

    public function test_throws_authentication_exception_if_there_is_no_username_in_response(): void
    {
        $authenticator = new LightSamlSpAuthenticator(
            'main',
            $this->getHttpUtilsMock(),
            $profileBuilderMock = $this->getProfileBuilderMock(),
            userProvider: $userProviderMock = $this->getUserProviderMock(),
            usernameMapper: $usernameMapperMock = $this->getUsernameMapperMock(),
            userCreator: $userCreatorMock = $this->getUserCreatorMock(),
        );

        $actionMock = $this->getActionMock();
        $contextMock = $this->getContextMock();

        $profileBuilderMock
            ->method('buildContext')
            ->willReturn($contextMock);
        $profileBuilderMock
            ->method('buildAction')
            ->willReturn($actionMock);

        $samlResponse = new Response();

        $contextMock
            ->method('getInboundMessage')
            ->willReturn($samlResponse);

        $usernameMapperMock
            ->method('getUsername')
            ->willReturn(null);

        $userProviderMock->expects($this->never())
            ->method(
                method_exists(UserProviderInterface::class, 'loadUserByIdentifier')
                    ? 'loadUserByIdentifier'
                    : 'loadUserByUsername'
            );

        $userCreatorMock->expects($this->once())
            ->method('createUser')
            ->with($samlResponse)
            ->willReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unable to resolve user');

        $authenticator->authenticate(Request::create('/'));
    }

    public function test_creates_authenticated_token_with_attributes_from_attribute_mapper(): void
    {
        $authenticator = new LightSamlSpAuthenticator(
            'main',
            $this->getHttpUtilsMock(),
            $profileBuilderMock = $this->getProfileBuilderMock(),
            userProvider: $userProviderMock = $this->getUserProviderMock(),
            usernameMapper: $usernameMapperMock = $this->getUsernameMapperMock(),
            attributeMapper: $attributeMapperMock = $this->getAttributeMapperMock(),
        );

        $actionMock = $this->getActionMock();
        $contextMock = $this->getContextMock();

        $profileBuilderMock->expects($this->any())
            ->method('buildContext')
            ->willReturn($contextMock);
        $profileBuilderMock->expects($this->any())
            ->method('buildAction')
            ->willReturn($actionMock);

        $samlResponse = new Response();

        $contextMock->expects($this->any())
            ->method('getInboundMessage')
            ->willReturn($samlResponse);

        $expectedUsername = 'some.username';
        $user = $this->getUserMock();
        $user->expects($this->any())
            ->method('getRoles')
            ->willReturn(['foo', 'bar']);
        $user
            ->method(method_exists(UserInterface::class, 'getUserIdentifier') ? 'getUserIdentifier' : 'getUsername')
            ->willReturn($expectedUsername);

        $usernameMapperMock->expects($this->once())
            ->method('getUsername')
            ->willReturn($expectedUsername);

        $userProviderMock->expects($this->once())
            ->method(
                method_exists(UserProviderInterface::class, 'loadUserByIdentifier')
                    ? 'loadUserByIdentifier'
                    : 'loadUserByUsername'
            )
            ->with($expectedUsername)
            ->willReturn($user);

        $attributeMapperMock->expects($this->once())
            ->method('getAttributes')
            ->with($samlResponse)
            ->willReturn($expectedAttributes = ['a', 'b', 'c']);

        $passport = $authenticator->authenticate(Request::create('/'));

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertEquals($expectedAttributes, $passport->getAttribute(LightSamlSpAuthenticator::PASSPORT_ATTRIBUTES));
    }

    public function test_calls_token_factory_if_provided(): void
    {
        $authenticator = new LightSamlSpAuthenticator(
            $firewallName = 'main',
            $this->getHttpUtilsMock(),
            $this->getProfileBuilderMock(),
            tokenFactory: $tokenFactoryMock = $this->getTokenFactoryMock(),
        );

        $samlResponse = new Response();
        $attributes = ['a' => 'b'];

        $user = $this->getUserMock();
        $user->expects($this->any())
            ->method('getRoles')
            ->willReturn(['foo', 'bar']);
        $user
            ->method(method_exists(UserInterface::class, 'getUserIdentifier') ? 'getUserIdentifier' : 'getUsername')
            ->willReturn('some.username');

        $token = new SamlSpToken($user, $firewallName, [], $attributes);

        $tokenFactoryMock->expects($this->once())
            ->method('create')
            ->with($user, $firewallName, $attributes, $samlResponse)
            ->willReturn($token);

        $passport = new SelfValidatingPassport(new UserBadge(
            method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername(),
            static fn() => $user,
        ));
        $passport->setAttribute(LightSamlSpAuthenticator::PASSPORT_ATTRIBUTES, $attributes);
        $passport->setAttribute(LightSamlSpAuthenticator::PASSPORT_SAML_RESPONSE, $samlResponse);

        $authenticator->createToken($passport, $firewallName);
    }

    private function getHttpUtilsMock(): MockObject|HttpUtils
    {
        return $this->createMock(HttpUtils::class);
    }

    private function getContextMock(): MockObject|ProfileContext
    {
        return $this->createMock(ProfileContext::class);
    }

    private function getActionMock(): MockObject|ActionInterface
    {
        return $this->createMock(ActionInterface::class);
    }

    private function getProfileBuilderMock(): MockObject|ProfileBuilderInterface
    {
        return $this->createMock(ProfileBuilderInterface::class);
    }

    private function getUserMock(): MockObject|UserInterface
    {
        return $this->createMock(UserInterface::class);
    }

    private function getUserProviderMock(): MockObject|UserProviderInterface
    {
        return $this->createMock(UserProviderInterface::class);
    }

    private function getUsernameMapperMock(): MockObject|UsernameMapperInterface
    {
        return $this->createMock(UsernameMapperInterface::class);
    }

    private function getUserCreatorMock(): MockObject|UserCreatorInterface
    {
        return $this->createMock(UserCreatorInterface::class);
    }

    private function getAttributeMapperMock(): MockObject|AttributeMapperInterface
    {
        return $this->createMock(AttributeMapperInterface::class);
    }

    private function getTokenFactoryMock(): MockObject|SamlSpTokenFactoryInterface
    {
        return $this->createMock(SamlSpTokenFactoryInterface::class);
    }

    private function getAuthenticationSuccessHandlerMock(): MockObject|AuthenticationSuccessHandlerInterface
    {
        return $this->createMock(AuthenticationSuccessHandlerInterface::class);
    }

    private function getAuthenticationFailureHandlerMock(): MockObject|AuthenticationFailureHandlerInterface
    {
        return $this->createMock(AuthenticationFailureHandlerInterface::class);
    }
}
