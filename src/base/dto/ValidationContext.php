<?php

namespace PSFS\base\dto;

use PSFS\base\Request;

class ValidationContext
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public array $payload = [],
        public array $headers = [],
        public bool $strictUnknownFields = true,
        public ?bool $enforceCsrf = null
    ) {
    }

    public static function fromRequest(?bool $enforceCsrf = null, ?bool $strictUnknownFields = null): self
    {
        $request = Request::getInstance();
        $payload = $request->getRawData();
        if (empty($payload)) {
            // Legacy fallback for form/url-encoded requests.
            $payload = $request->getData();
        }
        return new self(
            $payload,
            [],
            $strictUnknownFields ?? true,
            $enforceCsrf
        );
    }

    public function header(string $name): ?string
    {
        if (array_key_exists($name, $this->headers) && is_scalar($this->headers[$name])) {
            return (string)$this->headers[$name];
        }
        $needle = strtolower($name);
        foreach ($this->headers as $headerName => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            if (strtolower((string)$headerName) === $needle) {
                return (string)$value;
            }
            if (strtolower(str_replace('_', '-', (string)$headerName)) === $needle) {
                return (string)$value;
            }
        }
        return Request::header($name);
    }
}
