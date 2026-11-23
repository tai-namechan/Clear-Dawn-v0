<?php

namespace Tests\Unit\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AuthorizesMoneyUserRulesTest extends TestCase
{
    public function test_non_negative_minor_rules_accept_15_digits_and_reject_16_digits(): void
    {
        $rules = $this->ruleProbe()->nonNegativeMinorRules();

        $this->assertFalse(Validator::make(['amount' => '999999999999999'], ['amount' => $rules])->fails());
        $this->assertTrue(Validator::make(['amount' => '9999999999999999'], ['amount' => $rules])->fails());
    }

    public function test_signed_minor_rules_accept_15_digits_and_reject_signed_16_digits(): void
    {
        $rules = $this->ruleProbe()->signedMinorRules();

        $this->assertFalse(Validator::make(['amount' => '-999999999999999'], ['amount' => $rules])->fails());
        $this->assertTrue(Validator::make(['amount' => '-9999999999999999'], ['amount' => $rules])->fails());
    }

    private function ruleProbe(): object
    {
        return new class
        {
            use AuthorizesMoneyUser {
                nonNegativeMinorRules as public;
                signedMinorRules as public;
            }
        };
    }
}
