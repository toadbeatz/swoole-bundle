<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Swoole Bundle for Symfony 7
 * 
 * High-performance bundle exploiting all Swoole 6.1 capabilities
 * 
 * @author toadbeatz
 */
class SwooleBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
