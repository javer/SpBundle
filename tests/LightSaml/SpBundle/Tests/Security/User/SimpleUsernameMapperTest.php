<?php

namespace LightSaml\SpBundle\Tests\Security\User;

use LightSaml\ClaimTypes;
use LightSaml\Model\Assertion\Assertion;
use LightSaml\Model\Assertion\Attribute;
use LightSaml\Model\Assertion\AttributeStatement;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Assertion\Subject;
use LightSaml\Model\Protocol\Response;
use LightSaml\SamlConstants;
use LightSaml\SpBundle\Security\User\SimpleUsernameMapper;
use PHPUnit\Framework\TestCase;

class SimpleUsernameMapperTest extends TestCase
{
    public function resolves_username_from_attributes_provider(): array
    {
        return [
            [
                $this->buildResponse($this->buildAssertion(
                    [ClaimTypes::GIVEN_NAME => 'John', ClaimTypes::EMAIL_ADDRESS => 'user@domain.com', ClaimTypes::COMMON_NAME => 'user'],
                    null,
                    null
                )),
                [ClaimTypes::EMAIL_ADDRESS, ClaimTypes::COMMON_NAME],
                'user@domain.com',
            ],
            [
                $this->buildResponse($this->buildAssertion(
                    [ClaimTypes::GIVEN_NAME => 'John', ClaimTypes::EMAIL_ADDRESS => 'user@domain.com', ClaimTypes::COMMON_NAME => 'user'],
                    '123123123',
                    SamlConstants::NAME_ID_FORMAT_TRANSIENT
                )),
                [SimpleUsernameMapper::NAME_ID, ClaimTypes::EMAIL_ADDRESS, ClaimTypes::COMMON_NAME],
                'user@domain.com',
            ],
            [
                $this->buildResponse($this->buildAssertion(
                    [ClaimTypes::GIVEN_NAME => 'John', ClaimTypes::EMAIL_ADDRESS => 'user@domain.com', ClaimTypes::COMMON_NAME => 'user'],
                    'user_name_id',
                    SamlConstants::NAME_ID_FORMAT_PERSISTENT
                )),
                [SimpleUsernameMapper::NAME_ID, ClaimTypes::EMAIL_ADDRESS, ClaimTypes::COMMON_NAME],
                'user_name_id',
            ],
            [
                $this->buildResponse($this->buildAssertion(
                    [ClaimTypes::GIVEN_NAME => 'John', ClaimTypes::EMAIL_ADDRESS => 'user@domain.com', ClaimTypes::COMMON_NAME => 'user'],
                    'user_name_id',
                    SamlConstants::NAME_ID_FORMAT_TRANSIENT
                )),
                [SimpleUsernameMapper::NAME_ID, ClaimTypes::ADFS_1_EMAIL, ClaimTypes::PPID],
                null,
            ],
        ];
    }

    /**
     * @dataProvider resolves_username_from_attributes_provider
     */
    public function test_resolves_username_from_attributes(
        Response $response,
        array $attributeList,
        ?string $expectedUsername,
    ): void
    {
        $simpleUsernameMapper = new SimpleUsernameMapper($attributeList);
        $actualUsername = $simpleUsernameMapper->getUsername($response);
        $this->assertEquals($expectedUsername, $actualUsername);
    }

    private function buildResponse(Assertion $assertion, ?Response $response = null): Response
    {
        if (null == $response) {
            $response = new Response();
        }
        $response->addAssertion($assertion);

        return $response;
    }

    private function buildAssertion(
        array $assertionAttributes,
        ?string $nameId,
        ?string $nameIdFormat,
        ?Assertion $assertion = null,
    ): Assertion
    {
        if (null == $assertion) {
            $assertion = new Assertion();
        }
        $assertion->addItem($attributeStatement = new AttributeStatement());
        foreach ($assertionAttributes as $attributeName => $attributeValue) {
            $attributeStatement->addAttribute(new Attribute($attributeName, $attributeValue));
        }
        if ($nameId) {
            $assertion->setSubject(new Subject());
            $assertion->getSubject()->setNameID(new NameID($nameId, $nameIdFormat));
        }

        return $assertion;
    }
}
