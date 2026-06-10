<?php

declare(strict_types=1);

namespace A2A\Tests\Models;

use A2A\Models\DataPart;
use A2A\Models\FilePart;
use A2A\Models\FileWithBytes;
use A2A\Models\FileWithUri;
use A2A\Models\Part;
use A2A\Models\TextPart;
use PHPUnit\Framework\TestCase;

class PartTest extends TestCase
{
    public function testTextPart(): void
    {
        $part = new TextPart('Hello World', ['lang' => 'en']);

        $this->assertEquals('text', $part->getKind());
        $this->assertEquals('Hello World', $part->getText());

        $expectedArray = [
            'kind' => 'text',
            'text' => 'Hello World',
            'metadata' => ['lang' => 'en'],
        ];
        $this->assertEquals($expectedArray, $part->toArray());
    }

    public function testFilePartWithBytes(): void
    {
        $fileWithBytes = new FileWithBytes('base64data', 'test.png', 'image/png');
        $part = new FilePart($fileWithBytes, ['source' => 'upload']);

        $this->assertEquals('file', $part->getKind());
        $this->assertInstanceOf(FileWithBytes::class, $part->getFile());
        $this->assertEquals('base64data', $part->getFile()->getBytes());

        $expectedArray = [
            'kind' => 'file',
            'file' => [
                'bytes' => 'base64data',
                'name' => 'test.png',
                'mimeType' => 'image/png',
            ],
            'metadata' => ['source' => 'upload'],
        ];
        $this->assertEquals($expectedArray, $part->toArray());
    }

    public function testFilePartWithUri(): void
    {
        $fileWithUri = new FileWithUri('https://example.com/file.zip', 'archive.zip', 'application/zip');
        $part = new FilePart($fileWithUri);

        $this->assertEquals('file', $part->getKind());
        $this->assertInstanceOf(FileWithUri::class, $part->getFile());
        $this->assertEquals('https://example.com/file.zip', $part->getFile()->getUri());

        $expectedArray = [
            'kind' => 'file',
            'file' => [
                'uri' => 'https://example.com/file.zip',
                'name' => 'archive.zip',
                'mimeType' => 'application/zip',
            ],
        ];
        $this->assertEquals($expectedArray, $part->toArray());
    }

    public function testDataPart(): void
    {
        $data = ['user_id' => 123, 'settings' => ['theme' => 'dark']];
        $part = new DataPart($data, ['schema' => 'v1.0']);

        $this->assertEquals('data', $part->getKind());
        $this->assertEquals($data, $part->getData());

        $expectedArray = [
            'kind' => 'data',
            'data' => $data,
            'metadata' => ['schema' => 'v1.0'],
        ];
        $this->assertEquals($expectedArray, $part->toArray());
    }

    public function testPartFactory(): void
    {
        $textData = ['kind' => 'text', 'text' => 'From factory'];
        $fileData = ['kind' => 'file', 'file' => ['uri' => 'https://example.com/image.jpg']];
        $dataData = ['kind' => 'data', 'data' => ['status' => 'ok']];
        $legacyTextData = ['type' => 'text', 'content' => 'Legacy']; // For backward compatibility test

        $textPart = Part::fromArray($textData);
        $filePart = Part::fromArray($fileData);
        $dataPart = Part::fromArray($dataData);
        $legacyPart = Part::fromArray($legacyTextData);

        $this->assertInstanceOf(TextPart::class, $textPart);
        $this->assertEquals('From factory', $textPart->getText());

        $this->assertInstanceOf(FilePart::class, $filePart);
        $this->assertInstanceOf(FileWithUri::class, $filePart->getFile());
        $this->assertEquals('https://example.com/image.jpg', $filePart->getFile()->getUri());

        $this->assertInstanceOf(DataPart::class, $dataPart);
        $this->assertEquals(['status' => 'ok'], $dataPart->getData());

        $this->assertInstanceOf(TextPart::class, $legacyPart);
        $this->assertEquals('Legacy', $legacyPart->getText());
    }

    public function testPartFactoryThrowsExceptionForUnknownKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown part kind: unknown');
        Part::fromArray(['kind' => 'unknown']);
    }

    public function testPartFactoryThrowsExceptionForInvalidFilePart(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FilePart must have either "bytes" or "uri".');
        Part::fromArray(['kind' => 'file', 'file' => ['name' => 'invalid.txt']]);
    }
}
