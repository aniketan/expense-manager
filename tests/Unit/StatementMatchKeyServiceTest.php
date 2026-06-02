<?php

namespace Tests\Unit;

use App\Services\StatementMatchKeyService;
use PHPUnit\Framework\TestCase;

class StatementMatchKeyServiceTest extends TestCase
{
    private StatementMatchKeyService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new StatementMatchKeyService;
    }

    public function test_normalize_description_prefers_upi_segment(): void
    {
        $d = 'UPI/600208969804/DR/INDI/UTIB/foo';
        $this->assertSame('upi/600208969804', $this->svc->normalizeDescription($d));
    }

    public function test_normalize_description_alphanumeric_prefix_without_upi(): void
    {
        $d = 'NEFT SOME BANK PAYMENT REF ABC';
        $norm = $this->svc->normalizeDescription($d);
        $this->assertLessThanOrEqual(30, strlen($norm));
        $this->assertSame('neftsomebankpaymentrefabc', $norm);
    }

    public function test_match_key_is_deterministic(): void
    {
        $desc = 'DEMO CREDIT CARD PAYMENT/XXXXXXXXXXXX0000';
        $expected = '2026-03-22_'.$this->svc->formatAmountForKey(624.29).'_'.$this->svc->normalizeDescription($desc);
        $this->assertSame($expected, $this->svc->matchKey('2026-03-22', 624.29, $desc));
    }

    public function test_amount_norm_description_secondary_key_ignores_calendar_date(): void
    {
        $desc = 'UPI/111111111111/TEST SECONDARY KEY';
        $expected = $this->svc->formatAmountForKey(100).'_'.$this->svc->normalizeDescription($desc);
        $this->assertSame($expected, $this->svc->matchKeyAmountNormDesc(100, $desc));
    }

    public function test_is_description_thin_detects_placeholder_and_short(): void
    {
        $this->assertTrue($this->svc->isDescriptionThin(''));
        $this->assertTrue($this->svc->isDescriptionThin('ab'));
        $this->assertTrue($this->svc->isDescriptionThin('Synced from external DB'));
        $this->assertFalse($this->svc->isDescriptionThin('Real narration here'));
    }
}
