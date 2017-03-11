<?php

namespace App\Http\Requests;

use Dingo\Api\Http\FormRequest;

class AccountsApiRequest extends FormRequest
{

    public function rules()
    {
        return [
            'validation_rules' => [
                'fDAmount' => 'required',
                'cAccountName' => 'required',
                'cAccountIdentity' => 'required'
            ]
        ];
    }

    public function authorize()
    {
        return true;
    }
}
