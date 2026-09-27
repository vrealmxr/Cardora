<?php

namespace Tests\Unit;

use App\Services\DiditSignatureService;
use PHPUnit\Framework\TestCase;

class DiditSignatureTest extends TestCase
{
    public function test_unicode_empty_objects_arrays_and_float_canonicalization(): void
    {
        $ts = (string) time();
        $raw = '{"timestamp":'.$ts.',"z":{"9":{},"10":[]},"name":"Μαρία / José","amount":1.0}';
        $canonical = '{"amount":1,"name":"Μαρία / José","timestamp":'.$ts.',"z":{"10":[],"9":{}}}';
        $signature = hash_hmac('sha256', $canonical, 'secret');
        $service = new DiditSignatureService();
        $this->assertTrue($service->verify($raw, $signature, $ts, 'secret'));
        $this->assertFalse($service->verify(str_replace('Μαρία', 'other', $raw), $signature, $ts, 'secret'));
        $this->assertFalse($service->verify($raw, $signature, (string) (time() - 301), 'secret'));
        $this->assertFalse($service->verify('{broken', $signature, $ts, 'secret'));
        $this->assertFalse($service->verify($raw, $signature, $ts, ''));
    }
}
