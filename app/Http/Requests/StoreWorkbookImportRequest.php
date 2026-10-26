<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkbookImportRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'workbook' => ['required', 'file', 'extensions:xlsx', 'max:4096'],
            'confirm' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'workbook.extensions' => __('Upload the Dashboard Data workbook as an .xlsx file.'),
            'workbook.max' => __('The workbook must be under 4 MB.'),
            'confirm.accepted' => __('Tick the box to confirm the upload replaces the current data.'),
        ];
    }
}
