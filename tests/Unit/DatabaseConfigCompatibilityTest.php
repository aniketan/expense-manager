<?php

namespace Tests\Unit;

use ErrorException;
use Tests\TestCase;

class DatabaseConfigCompatibilityTest extends TestCase
{
    public function test_database_config_loads_without_php_deprecations(): void
    {
        set_error_handler(
            static function (int $severity, string $message): never {
                throw new ErrorException($message, 0, $severity);
            },
            E_DEPRECATED,
        );

        try {
            $config = require dirname(__DIR__, 2).'/config/database.php';
        } finally {
            restore_error_handler();
        }

        $this->assertSame('mysql', $config['connections']['mysql']['driver']);
        $this->assertSame('mariadb', $config['connections']['mariadb']['driver']);
    }
}
