<?php

namespace App\Http\Requests;

use App\Support\Spam\AltchaGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // anyone can send an enquiry
    }

    public function rules(): array
    {
        return [
            'listing_ref' => ['required', 'string', 'size:8'],
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['required', 'email', 'max:190'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:40'],
            'children' => ['nullable', 'integer', 'min:0', 'max:40'],
            'message' => ['required', 'string', 'max:4000'],
            'consent' => ['accepted'],
            'altcha' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'guest_name' => __('your name'),
            'guest_email' => __('email'),
            'guest_phone' => __('phone'),
            'check_in' => __('arrival date'),
            'check_out' => __('leaving date'),
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => __('Please tick the box to agree to the privacy notice.'),
            'check_out.after' => __('The leaving date must be after the arrival date.'),
        ];
    }

    /** The ALTCHA check runs only once the form itself is valid, so a solved challenge is not wasted. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->isHoneypotFilled()) {
                    return;
                }
                if (! app(AltchaGuard::class)->verify($this->input('altcha'))) {
                    $validator->errors()->add('altcha', __('Please complete the "I am not a robot" check.'));
                }
            },
        ];
    }

    /** Back to the form, not the top of the listing page. */
    protected function getRedirectUrl(): string
    {
        return strtok(parent::getRedirectUrl(), '#').'#enquiry';
    }

    public function isHoneypotFilled(): bool
    {
        return filled($this->input('website'));
    }
}
