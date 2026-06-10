<?php

declare(strict_types=1);

namespace A2A\Models;

use InvalidArgumentException;

/**
 * Factory for creating File instances from array data
 */
class FileFactory
{
    public static function fromArray(array $data): FileInterface
    {
        if (isset($data['bytes'])) {
            return FileWithBytes::fromArray($data);
        } elseif (isset($data['uri'])) {
            return FileWithUri::fromArray($data);
        } elseif (isset($data['url'])) {
            // Handle legacy 'url' field by converting to 'uri' for compatibility
            $data['uri'] = $data['url'];
            unset($data['url']);
            return FileWithUri::fromArray($data);
        } else {
            throw new InvalidArgumentException('File data must contain either "bytes", "uri", or "url" field');
        }
    }
}
