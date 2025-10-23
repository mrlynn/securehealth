<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DocsControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();
        $client->request('GET', '/docs');

        $this->assertResponseRedirects('https://docs.securehealth.dev', 301);
    }

    public function testCategoryPage()
    {
        $client = static::createClient();
        $client->request('GET', '/docs/getting-started');

        $this->assertResponseRedirects('https://docs.securehealth.dev', 301);
    }

    public function testDocumentationPage()
    {
        $client = static::createClient();
        $client->request('GET', '/docs/getting-started/introduction');

        $this->assertResponseRedirects('https://docs.securehealth.dev', 301);
    }

    public function test404ForNonExistentCategory()
    {
        $client = static::createClient();
        $client->request('GET', '/docs/non-existent-category');

        $this->assertResponseRedirects('https://docs.securehealth.dev', 301);
    }

    public function test404ForNonExistentPage()
    {
        $client = static::createClient();
        $client->request('GET', '/docs/getting-started/non-existent-page');

        $this->assertResponseRedirects('https://docs.securehealth.dev', 301);
    }
}