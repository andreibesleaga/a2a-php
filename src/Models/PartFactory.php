<?php

declare(strict_types=1);

namespace A2A\Models;

use InvalidArgumentException;

/**
 * Factory for creating Part instances from array data
 */
class PartFactory
{
    public static function fromArray(array $data): PartInterface
    {
        if (!isset($data['kind'])) {
            throw new InvalidArgumentException('Part data must contain a "kind" field');
        }

        switch ($data['kind']) {
            case 'text':
                return TextPart::fromArray($data);
            case 'file':
                return FilePart::fromArray($data);
            case 'data':
                return DataPart::fromArray($data);
            default:
                throw new InvalidArgumentException('Unknown part kind: ' . $data['kind']);
        }
    }
}
