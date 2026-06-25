<?php

namespace Lalalili\CommerceCore\Tests\Support;

class TraceTestCartContext
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function with(string $key, mixed $value): self
    {
        $data = $this->data;
        data_set($data, $key, $value);

        return new self($data);
    }
}
