<?php

namespace BMND\Router;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Error 
{
    public function __construct(
		public int|string $code = 404,
    ) {}
}
