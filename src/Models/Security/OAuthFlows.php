<?php

declare(strict_types=1);

namespace A2A\Models\Security;

/**
 * OAuthFlows model.
 *
 * @see https://swagger.io/specification/#oauth-flows-object
 */
class OAuthFlows
{
    private ?OAuthFlow $implicit;
    private ?OAuthFlow $password;
    private ?OAuthFlow $clientCredentials;
    private ?OAuthFlow $authorizationCode;

    public function __construct(
        ?OAuthFlow $implicit = null,
        ?OAuthFlow $password = null,
        ?OAuthFlow $clientCredentials = null,
        ?OAuthFlow $authorizationCode = null
    ) {
        $this->implicit = $implicit;
        $this->password = $password;
        $this->clientCredentials = $clientCredentials;
        $this->authorizationCode = $authorizationCode;
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->implicit !== null) {
            $data['implicit'] = $this->implicit->toArray();
        }
        if ($this->password !== null) {
            $data['password'] = $this->password->toArray();
        }
        if ($this->clientCredentials !== null) {
            $data['clientCredentials'] = $this->clientCredentials->toArray();
        }
        if ($this->authorizationCode !== null) {
            $data['authorizationCode'] = $this->authorizationCode->toArray();
        }
        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['implicit']) ? OAuthFlow::fromArray($data['implicit']) : null,
            isset($data['password']) ? OAuthFlow::fromArray($data['password']) : null,
            isset($data['clientCredentials']) ? OAuthFlow::fromArray($data['clientCredentials']) : null,
            isset($data['authorizationCode']) ? OAuthFlow::fromArray($data['authorizationCode']) : null
        );
    }
}
