<?php

namespace LightSaml\SpBundle\Tests\Security\User;

use LightSaml\Model\Assertion\Assertion;
use LightSaml\Model\Assertion\Attribute;
use LightSaml\Model\Assertion\AttributeStatement;
use LightSaml\Model\Protocol\Response;
use LightSaml\SpBundle\Security\User\SimpleAttributeMapper;
use PHPUnit\Framework\TestCase;

class SimpleAttributeMapperTest extends TestCase
{
    public function test_get_attributes_from_single_assertion_response(): void
    {
        $assertion = $this->buildAssertion([
            'organization' => 'test',
            'name' => 'John',
            'email_address' => 'john@domain.com',
            'test' => ['one', 'two'],
        ]);
        $response = $this->buildResponse($assertion);

        $expectedAttributes = [
            'organization' => 'test',
            'name' => 'John',
            'email_address' => 'john@domain.com',
            'test' => ['one', 'two'],
        ];

        $simpleAttributeMapper = new SimpleAttributeMapper();
        $actualAttributes = $simpleAttributeMapper->getAttributes($response);

        $this->assertEquals($expectedAttributes, $actualAttributes);
    }

    public function test_get_attributes_from_multi_assertions_response(): void
    {
        $assertion = $this->buildAssertion([
            'organization' => 'test',
            'name' => 'John',
            'email_address' => 'john@domain.com',
            'test' => ['one', 'two'],
        ]);

        $response = $this->buildResponse($assertion);

        $assertion = $this->buildAssertion([
            'name' => 'Doe',
            'email_address' => 'doe@domain.com',
            'test' => ['three', 'four'],
        ]);

        $response = $this->buildResponse($assertion, $response);

        $expectedAttributes = [
            'organization' => 'test',
            'name' => ['John', 'Doe'],
            'email_address' => ['john@domain.com', 'doe@domain.com'],
            'test' => ['one', 'two', 'three', 'four'],
        ];

        $simpleAttributeMapper = new SimpleAttributeMapper();
        $actualAttributes = $simpleAttributeMapper->getAttributes($response);

        $this->assertEquals($expectedAttributes, $actualAttributes);
    }

    public function test_get_attributes_from_multi_attribute_statements_response(): void
    {
        $assertion = $this->buildAssertion([
            'organization' => 'test',
            'name' => 'John',
            'email_address' => 'john@domain.com',
            'test' => ['one', 'two']
        ]);

        $assertion = $this->buildAssertion([
            'name' => 'Doe',
            'email_address' => 'doe@domain.com',
            'test' => ['three', 'four']
        ], $assertion);

        $response = $this->buildResponse($assertion);

        $expectedAttributes = [
            'organization' => 'test',
            'name' => ['John', 'Doe'],
            'email_address' => ['john@domain.com', 'doe@domain.com'],
            'test' => ['one', 'two', 'three', 'four'],
        ];

        $simpleAttributeMapper = new SimpleAttributeMapper();
        $actualAttributes = $simpleAttributeMapper->getAttributes($response);

        $this->assertEquals($expectedAttributes, $actualAttributes);
    }

    private function buildResponse(Assertion $assertion, ?Response $response = null): Response
    {
        if (null == $response) {
            $response = new Response();
        }

        $response->addAssertion($assertion);

        return $response;
    }

    private function buildAssertion(array $assertionAttributes, ?Assertion $assertion = null): Assertion
    {
        if (null == $assertion) {
            $assertion = new Assertion();
        }

        $assertion->addItem($attributeStatement = new AttributeStatement());

        foreach ($assertionAttributes as $attributeName => $attributeValue) {
            $attributeStatement->addAttribute(new Attribute($attributeName, $attributeValue));
        }

        return $assertion;
    }
}
