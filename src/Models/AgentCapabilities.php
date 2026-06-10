<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Defines optional capabilities supported by an agent
 */
class AgentCapabilities
{
    private bool $streaming = false;
    private bool $pushNotifications = false;
    private bool $stateTransitionHistory = false;
    private array $extensions = [];

    public function __construct(
        bool $streaming = false,
        bool $pushNotifications = false,
        bool $stateTransitionHistory = false,
        array $extensions = []
    ) {
        $this->streaming = $streaming;
        $this->pushNotifications = $pushNotifications;
        $this->stateTransitionHistory = $stateTransitionHistory;
        $this->extensions = $extensions;
    }

    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    public function setStreaming(bool $streaming): void
    {
        $this->streaming = $streaming;
    }

    public function isPushNotifications(): bool
    {
        return $this->pushNotifications;
    }

    public function setPushNotifications(bool $pushNotifications): void
    {
        $this->pushNotifications = $pushNotifications;
    }

    public function isStateTransitionHistory(): bool
    {
        return $this->stateTransitionHistory;
    }

    public function setStateTransitionHistory(bool $stateTransitionHistory): void
    {
        $this->stateTransitionHistory = $stateTransitionHistory;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function addExtension(AgentExtension $extension): void
    {
        $this->extensions[] = $extension;
    }

    public function toArray(): array
    {
        $result = [
            'streaming' => $this->streaming,
            'pushNotifications' => $this->pushNotifications,
            'stateTransitionHistory' => $this->stateTransitionHistory
        ];

        if (!empty($this->extensions)) {
            $result['extensions'] = array_map(
                fn(AgentExtension $ext) => $ext->toArray(),
                $this->extensions
            );
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        $extensions = [];
        if (isset($data['extensions'])) {
            foreach ($data['extensions'] as $extData) {
                $extensions[] = AgentExtension::fromArray($extData);
            }
        }

        return new self(
            $data['streaming'] ?? false,
            $data['pushNotifications'] ?? false,
            $data['stateTransitionHistory'] ?? false,
            $extensions
        );
    }
}
