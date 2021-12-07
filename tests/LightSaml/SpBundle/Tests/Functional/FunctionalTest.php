<?php

namespace LightSaml\SpBundle\Tests\Functional;

use LightSaml\State\Sso\SsoSessionState;
use LightSaml\State\Sso\SsoState;
use LightSaml\Store\Sso\SsoStateStoreInterface;
use SimpleXMLElement;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FunctionalTest extends WebTestCase
{
    const OWN_ENTITY_ID = 'https://localhost/lightSAML/SPBundle';

    protected function setUp(): void
    {
        require_once __DIR__.'/app/AppKernel.php';
    }

    protected static function getKernelClass(): string
    {
        return \AppKernel::class;
    }

    public function test_metadata(): void
    {
        $client = static::createClient();

        $client->request('GET', '/saml/metadata.xml');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $xml = $client->getResponse()->getContent();

        $root = new SimpleXMLElement($xml);
        $this->assertEquals('EntityDescriptor', $root->getName());
        $this->assertEquals(self::OWN_ENTITY_ID, $root['entityID']);
        $this->assertEquals(1, $root->SPSSODescriptor->count());
        $this->assertEquals(2, $root->SPSSODescriptor->KeyDescriptor->count());
        $this->assertEquals(1, $root->SPSSODescriptor->AssertionConsumerService->count());
    }

    public function test_discovery(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/saml/discovery');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $idpCrawler = $crawler->filter('a[data-idp]');
        $this->assertEquals(3, $idpCrawler->count());
        $arr = [];
        foreach ($idpCrawler as $idp) {
            $arr[$idp->getAttribute('data-idp')] = 1;
        }
        $this->assertArrayHasKey('https://openidp.feide.no', $arr);
        $this->assertArrayHasKey('https://localhost/lightSAML/lightSAML-IDP', $arr);
        $this->assertArrayHasKey('https://idp.testshib.org/idp/shibboleth', $arr);
    }

    public function test_login(): void
    {
        $client = static::createClient();
        $client->getContainer()->set('session', $this->createMock(SessionInterface::class));

        $crawler = $client->request('GET', '/saml/login?idp=https://localhost/lightSAML/lightSAML-IDP');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $crawlerForm = $crawler->filter('form');
        $this->assertEquals(1, $crawlerForm->count());
        $this->assertEquals('https://localhost/lightsaml/lightSAML-IDP/web/idp/login.php', $crawlerForm->first()->attr('action'));

        $crawlerSamlRequest = $crawler->filter('input[name="SAMLRequest"]');
        $this->assertEquals(1, $crawlerSamlRequest->count());
        $code = $crawlerSamlRequest->first()->attr('value');
        $xml = base64_decode($code);

        $root = new SimpleXMLElement($xml);
        $this->assertEquals('AuthnRequest', $root->getName());
        $this->assertEquals('https://localhost/lightsaml/lightSAML-IDP/web/idp/login.php', $root['Destination']);
        $this->assertEquals(1, $root->children('saml', true)->Issuer->count());
        $this->assertEquals(self::OWN_ENTITY_ID, (string)$root->children('saml', true)->Issuer);
    }

    public function test_sessions(): void
    {
        $ssoState = new SsoState();
        $ssoState->addSsoSession((new SsoSessionState())->setIdpEntityId('idp1')->setSpEntityId('sp1'));
        $ssoState->addSsoSession((new SsoSessionState())->setIdpEntityId('idp2')->setSpEntityId('sp2'));

        $ssoStateStoreMock = $this->createMock(SsoStateStoreInterface::class);
        $ssoStateStoreMock->method('get')
            ->willReturn($ssoState);

        $client = static::createClient();
        $client->getContainer()->set('lightsaml.store.sso_state', $ssoStateStoreMock);

        $crawler = $client->request('GET', '/saml/sessions');

        $crawlerSessions = $crawler->filter('ul[data-session]');
        $this->assertEquals(2, $crawlerSessions->count());

        $this->assertEquals('idp1', $crawlerSessions->first()->filter('li[data-idp]')->attr('data-idp'));
        $this->assertEquals('sp1', $crawlerSessions->first()->filter('li[data-sp]')->attr('data-sp'));
        $this->assertEquals('idp2', $crawlerSessions->last()->filter('li[data-idp]')->attr('data-idp'));
        $this->assertEquals('sp2', $crawlerSessions->last()->filter('li[data-sp]')->attr('data-sp'));
    }
}
