<?php

namespace Lalalili\CommerceCore\Tests\Support;

class TraceTestCart
{
    private TraceTestCartContext $context;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(array $context = [])
    {
        $this->context = new TraceTestCartContext($context);
    }

    public function getContext(): TraceTestCartContext
    {
        return $this->context;
    }

    public function withContext(TraceTestCartContext $context): void
    {
        $this->context = $context;
    }
}
