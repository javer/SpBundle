<?php

namespace LightSaml\SpBundle\Tests\Controller;

use LightSaml\Action\ActionInterface;
use LightSaml\Builder\Profile\ProfileBuilderInterface;
use LightSaml\Context\Profile\HttpResponseContext;
use LightSaml\Context\Profile\ProfileContext;
use LightSaml\SpBundle\Controller\DefaultController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class DefaultControllerTest extends TestCase
{
    public function test_metadata_action_returns_response_from_profile()
    {
        $controller = new DefaultController();
        $controller->setContainer($containerMock = $this->getContainerMock());

        $containerMock->expects($this->any())
            ->method('get')
            ->with('ligthsaml.profile.metadata')
            ->willReturn($profileBuilderMock = $this->getProfileBuilderMock());

        $actionMock = $this->getActionMock();
        $contextMock = $this->getContextMock();

        $profileBuilderMock->expects($this->any())
            ->method('buildContext')
            ->willReturn($contextMock);
        $profileBuilderMock->expects($this->any())
            ->method('buildAction')
            ->willReturn($actionMock);

        $contextMock->expects($this->once())
            ->method('getHttpResponseContext')
            ->willReturn($httpResponseContext = $this->getHttpResponseContextMock());

        $httpResponseContext->expects($this->once())
            ->method('getResponse')
            ->willReturn($expectedResponse = new Response(''));

        $actualResponse = $controller->metadataAction();

        $this->assertSame($expectedResponse, $actualResponse);
    }

    private function getContainerMock(): MockObject|ContainerInterface
    {
        return $this->createMock(ContainerInterface::class);
    }

    private function getProfileBuilderMock(): MockObject|ProfileBuilderInterface
    {
        return $this->createMock(ProfileBuilderInterface::class);
    }

    private function getContextMock(): MockObject|ProfileContext
    {
        return $this->createMock(ProfileContext::class);
    }

    private function getActionMock(): MockObject|ActionInterface
    {
        return $this->createMock(ActionInterface::class);
    }

    private function getHttpResponseContextMock(): MockObject|HttpResponseContext
    {
        return $this->createMock(HttpResponseContext::class);
    }
}
