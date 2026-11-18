<?php

namespace Tests\Unit;

use App\Rules\NotFreeEmailProvider;
use PHPUnit\Framework\TestCase;

class NotFreeEmailProviderTest extends TestCase
{
    private function fails(?string $value): bool
    {
        $failed = false;

        (new NotFreeEmailProvider)->validate('email', $value, function () use (&$failed): void {
            $failed = true;
        });

        return $failed;
    }

    public function test_free_providers_are_rejected(): void
    {
        foreach (['juan@gmail.com', 'ventas@HOTMAIL.com', 'x@outlook.es', 'y@yahoo.com.mx', 'z@icloud.com'] as $email) {
            $this->assertTrue($this->fails($email), "{$email} debería rechazarse");
        }
    }

    public function test_own_domain_is_allowed(): void
    {
        foreach (['info@tuempresa.com', 'ventas@proyectopublicitario.com.mx', 'hola@mant-elek.mx'] as $email) {
            $this->assertFalse($this->fails($email), "{$email} debería permitirse");
        }
    }

    public function test_empty_value_is_allowed(): void
    {
        $this->assertFalse($this->fails(null));
        $this->assertFalse($this->fails(''));
    }
}
