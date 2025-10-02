<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DocsControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();
        $client->request('GET', '/docs');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'SecureHealth Documentation');
    }

    public function testCategoryPage()
    {
        // Skip this test if the category doesn't exist yet
        if (!is_dir(__DIR__ . '/../../public/docs/getting-started')) {
            $this->markTestSkipped('Getting started category directory does not exist.');
        }

        $client = static::createClient();
        $client->request('GET', '/docs/getting-started');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'Getting-started');
    }

    public function testDocumentationPage()
    {
        // Skip this test if the page doesn't exist yet
        if (!file_exists(__DIR__ . '/../../public/docs/getting-started/introduction.md')) {
            $this->markTestSkipped('Introduction page does not exist.');
        }

        $client = static::createClient();
        $client->request('GET', '/docs/getting-started/introduction');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Introduction');
    }

    public function test404ForNonExistentCategory()
    {
        $client = static::createClient();
        $client->request('GET', '/docs/non-existent-category');

        $this->assertResponseStatusCodeSame(404);
    }

    public function test404ForNonExistentPage()
    {
        $client = static::createClient();
        $client->request('GET', '/docs/getting-started/non-existent-page');

        $this->assertResponseStatusCodeSame(404);
    }
}