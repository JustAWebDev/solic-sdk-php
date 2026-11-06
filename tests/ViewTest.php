<?php

namespace Solic\Tests;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Solic\View;

final class ViewTest extends TestCase
{
    public function testRendersAJsonApiResponse(): void
    {
        $html = (new View())->render(json_encode($this->response(), JSON_THROW_ON_ERROR), '/submit');

        self::assertSame('contact-form', $this->xpath($html)->evaluate('string(//form/@id)'));
        self::assertSame('/submit', $this->xpath($html)->evaluate('string(//form/@action)'));
    }

    public function testJsonAndObjectInputsRenderIdentically(): void
    {
        $data = $this->response();
        $view = new View();

        self::assertSame(
            $view->render($data, '/submit'),
            $view->render(json_encode($data, JSON_THROW_ON_ERROR), '/submit')
        );
    }

    public function testRendersFormMethodCsrfTokenFieldsAndSubmitButton(): void
    {
        $xpath = $this->xpath((new View())->render($this->response(), '/submit'));

        self::assertSame('post', $xpath->evaluate('string(//form/@method)'));
        self::assertSame('fixture-csrf', $xpath->evaluate('string(//input[@name="csrf_token"]/@value)'));
        self::assertSame('hidden', $xpath->evaluate('string(//input[@name="csrf_token"]/@type)'));
        self::assertSame('text', $xpath->evaluate('string(//input[@name="field_name"]/@type)'));
        self::assertSame('email', $xpath->evaluate('string(//input[@name="field_email"]/@type)'));
        self::assertSame('field_name', $xpath->evaluate('string(//label[text()="Your name"]/@for)'));
        self::assertSame('field_name', $xpath->evaluate('string(//input[@name="field_name"]/@id)'));
        self::assertSame(1, $xpath->query('//textarea[@name="field_message" and @id="field_message"]')->length);
        self::assertSame('Send', $xpath->evaluate('string(//input[@type="submit"]/@value)'));
    }

    public function testHoneypotRendersAsATextFieldWithHiddenWrapperCss(): void
    {
        $html = (new View())->render($this->response(), '/submit');
        $xpath = $this->xpath($html);

        self::assertSame('text', $xpath->evaluate('string(//input[@name="field_trap"]/@type)'));
        self::assertSame(1, $xpath->query('//div[@class="form-group field_trap"]/input[@name="field_trap"]')->length);
        self::assertMatchesRegularExpression('/\.form-group\.field_trap\s*\{\s*display:\s*none;\s*\}/', $html);
    }

    public function testEscapesFormAttributesAndFieldLabels(): void
    {
        $data = $this->response();
        $payload = '\"><script>alert("fixture")</script>&';
        $data->data[0]->name = $payload;
        $data->data[0]->csrf_token = $payload;
        $data->data[0]->fields[0]->name = $payload;

        $html = (new View())->render($data, $payload);
        $xpath = $this->xpath($html);

        self::assertSame(0, $xpath->query('//script')->length);
        self::assertStringNotContainsString('<script>', $html);
        self::assertSame($payload, $xpath->evaluate('string(//form/@id)'));
        self::assertSame($payload, $xpath->evaluate('string(//form/@action)'));
        self::assertSame($payload, $xpath->evaluate('string(//input[@name="csrf_token"]/@value)'));
        self::assertSame($payload, $xpath->evaluate('string(//label[@for="field_name"])'));
    }

    public function testAdditionalContentIsEscapedByDefault(): void
    {
        $content = '<strong>Example & "quoted"</strong>';

        $html = (new View())->render($this->response(), '/submit', $content);

        self::assertStringContainsString(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'), $html);
        self::assertSame(0, $this->xpath($html)->query('//strong')->length);
    }

    public function testExplicitEscapeFlagEscapesAdditionalContent(): void
    {
        $content = '<script>alert("fixture")</script>';

        $html = (new View())->render($this->response(), '/submit', $content, true);

        self::assertStringContainsString(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'), $html);
        self::assertSame(0, $this->xpath($html)->query('//script')->length);
    }

    public function testTrustedContentCanBeRenderedRawByExplicitOptIn(): void
    {
        $content = '<strong data-fixture="trusted">Trusted content</strong>';

        $html = (new View())->render($this->response(), '/submit', $content, false);

        self::assertStringContainsString($content, $html);
        self::assertSame('Trusted content', $this->xpath($html)->evaluate('string(//strong[@data-fixture="trusted"])'));
    }

    public function testOmittedAdditionalContentIsTheSameAsAnEmptyString(): void
    {
        $view = new View();
        $data = $this->response();

        self::assertSame($view->render($data, '/submit'), $view->render($data, '/submit', ''));
    }

    public function testRendersTheFirstFormFromTheApiResponse(): void
    {
        $data = $this->response();
        $second = clone $data->data[0];
        $second->name = 'second-form';
        $data->data[] = $second;

        $xpath = $this->xpath((new View())->render($data, '/submit'));

        self::assertSame(1, $xpath->query('//form')->length);
        self::assertSame('contact-form', $xpath->evaluate('string(//form/@id)'));
    }

    public function testFormWithNoFieldsStillIncludesCsrfAndSubmitInputs(): void
    {
        $data = $this->response();
        $data->data[0]->fields = [];

        $xpath = $this->xpath((new View())->render($data, '/submit'));

        self::assertSame(1, $xpath->query('//form')->length);
        self::assertSame(2, $xpath->query('//input')->length);
        self::assertSame(0, $xpath->query('//textarea | //label')->length);
    }

    public function testRenderingDoesNotMutateTheSuppliedObject(): void
    {
        $data = $this->response();
        $before = json_encode($data, JSON_THROW_ON_ERROR);

        (new View())->render($data, '/submit');

        self::assertSame($before, json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function response(): object
    {
        // Representative data shape used by View, not a live Solic response.
        return (object) ['data' => [(object) [
            'name' => 'contact-form',
            'csrf_token' => 'fixture-csrf',
            'fields' => [
                (object) ['name' => 'Your name', 'field_token' => 'field_name', 'type' => 'text'],
                (object) ['name' => 'Email address', 'field_token' => 'field_email', 'type' => 'email'],
                (object) ['name' => 'Message', 'field_token' => 'field_message', 'type' => 'textarea'],
                (object) ['name' => 'Leave blank', 'field_token' => 'field_trap', 'type' => 'honeypot'],
            ],
        ]]];
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            $document->loadHTML('<?xml encoding="UTF-8">' . $html);

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
