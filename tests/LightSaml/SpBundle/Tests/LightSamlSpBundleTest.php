<?php

namespace LightSaml\SpBundle\Tests;

use LightSaml\SpBundle\DependencyInjection\Security\Factory\LightSamlSpFactory;
use LightSaml\SpBundle\LightSamlSpBundle;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LightSamlSpBundleTest extends TestCase
{
    public function test_build_adds_security_extension(): void
    {
        $bundle = new LightSamlSpBundle();

        $containerBuilderMock = $this->getContainerBuilderMock();
        $containerBuilderMock->expects($this->once())
            ->method('getExtension')
            ->with('security')
            ->willReturn($extensionMock = $this->getExtensionMock());

        $extensionMock->expects($this->once())
            ->method('addAuthenticatorFactory')
            ->with($this->isInstanceOf(LightSamlSpFactory::class));

        $bundle->build($containerBuilderMock);
    }

    private function getContainerBuilderMock(): MockObject|ContainerBuilder
    {
        return $this->createMock(ContainerBuilder::class);
    }

    private function getExtensionMock(): MockObject|SecurityExtension
    {
        return $this->createMock(SecurityExtension::class);
    }
}
