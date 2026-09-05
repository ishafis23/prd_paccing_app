<?php

/*
|--------------------------------------------------------------------------
| Pest Test Case Bindings
|--------------------------------------------------------------------------
| Feature tests boot full app + refresh DB per test file.
| Unit tests (pure PHP logic, no DB) stay on PHPUnit base.
*/

uses(Tests\TestCase::class)->in('Feature');
uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');
