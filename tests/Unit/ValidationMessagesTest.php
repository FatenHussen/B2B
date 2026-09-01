<?php

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Presentation\Http\Requests\RegisterRepRequest;
use Modules\Identity\Presentation\Http\Requests\RequestPublicOtpRequest;

function assertRequestFails(FormRequest $request): ValidationException
{
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    try {
        $request->validateResolved();
    } catch (ValidationException $e) {
        return $e;
    }

    throw new RuntimeException('Expected the form request to fail validation.');
}

it('translates syrian phone errors to arabic from the form request', function () {
    app()->setLocale('ar');

    $e = assertRequestFails(RequestPublicOtpRequest::create('/', 'POST', [
        'phone' => '12345',
        'purpose' => 'login',
    ]));

    expect($e->errors())->toHaveKey('phone')
        ->and($e->errors()['phone'][0])->toContain('سوري');
});

it('translates syrian phone errors to english when locale is en', function () {
    app()->setLocale('en');

    $e = assertRequestFails(RequestPublicOtpRequest::create('/', 'POST', [
        'phone' => '12345',
        'purpose' => 'login',
    ]));

    expect($e->errors()['phone'][0])->toContain('Syrian');
});

it('uses arabic attribute names for register-rep required fields', function () {
    app()->setLocale('ar');

    $e = assertRequestFails(RegisterRepRequest::create('/', 'POST', []));

    expect($e->errors())->toHaveKey('name')
        ->and($e->errors()['name'][0])->toContain('الاسم')
        ->and($e->errors())->toHaveKey('supply_channel_id')
        ->and($e->errors()['supply_channel_id'][0])->toContain('قناة التوريد');
});
