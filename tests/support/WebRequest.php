<?php

namespace bensomething\cast\tests\support;

use yii\web\Request;

/**
 * A web request the controller can be handed in place of a real one.
 *
 * `PreferencesController` reads its request through `$this->request`, guards on
 * {@see getIsCpRequest()}, {@see getIsPost()} and {@see getAcceptsJson()}, and pulls the
 * chosen theme out of the posted body. Those are the methods filled in here; each is a
 * public property so a test can pose as a request that fails one guard at a time.
 */
class WebRequest extends Request
{
    public bool $cpRequest = true;

    public bool $post = true;

    public bool $acceptsJson = true;

    /** @var array<string, mixed> */
    public array $bodyParams = [];

    public function getIsCpRequest(): bool
    {
        return $this->cpRequest;
    }

    public function getIsPost(): bool
    {
        return $this->post;
    }

    public function getAcceptsJson(): bool
    {
        return $this->acceptsJson;
    }

    public function getIsOptions(): bool
    {
        return false;
    }

    public function getBodyParam($name, $defaultValue = null): mixed
    {
        return $this->bodyParams[$name] ?? $defaultValue;
    }

    /**
     * Craft's own, rather than Yii's: the controller calls it, and the base web request
     * doesn't have it. A missing param is the framework's error to raise, not something
     * these tests provoke, so this just serves what was posted.
     *
     * @param string $name
     */
    public function getRequiredBodyParam($name): mixed
    {
        return $this->getBodyParam($name);
    }

    /**
     * A stand-in token, so the success path can set its `X-CSRF-Token` header without a
     * session to mint a real one.
     */
    public function getCsrfToken($regenerate = false): string
    {
        return 'test-csrf-token';
    }
}
