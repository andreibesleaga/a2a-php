<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Factory for creating PartInterface objects from an array.
 */
class Part
{
    public static function fromArray(array $data): PartInterface
    {
        $kind = $data['kind'] ?? $data['type'] ?? null;

        if ($kind === null) {
            // For backward compatibility, if neither 'kind' nor 'type' is provided,
            // assume a text part and attempt to map common content keys.
            $content = $data['content'] ?? $data['text'] ?? '';
            if (!is_string($content)) {
                $content = is_scalar($content) ? (string) $content : '';
            }
            return new TextPart($content, $data['metadata'] ?? null);
        }

        switch ($kind) {
            case 'text':
                $text = $data['text'] ?? $data['content'] ?? '';
                if (!is_string($text)) {
                    $text = is_scalar($text) ? (string) $text : '';
                }
                return new TextPart($text, $data['metadata'] ?? null);
            case 'file':
                if (!isset($data['file']) || !is_array($data['file'])) {
                    throw new \InvalidArgumentException('FilePart must have a "file" object.');
                }
                $fileData = $data['file'];
                $uri = $fileData['uri'] ?? $fileData['url'] ?? $fileData['href'] ?? null;

                if (isset($fileData['bytes'])) {
                    $file = new FileWithBytes($fileData['bytes'], $fileData['name'] ?? null, $fileData['mimeType'] ?? null);
                } elseif ($uri !== null) {
                    $file = new FileWithUri($uri, $fileData['name'] ?? null, $fileData['mimeType'] ?? null);
                } else {
                    throw new \InvalidArgumentException('FilePart must have either "bytes" or "uri".');
                }
                return new FilePart($file, $data['metadata'] ?? null);
            case 'data':
                if (!array_key_exists('data', $data)) {
                    throw new \InvalidArgumentException('DataPart must have a "data" field.');
                }
                return new DataPart($data['data'], $data['metadata'] ?? null);
            default:
                throw new \InvalidArgumentException("Unknown part kind: {$kind}");
        }
    }
}