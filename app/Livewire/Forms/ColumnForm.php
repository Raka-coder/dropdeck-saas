<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

class ColumnForm extends Form
{
    public string $title = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('columns.validation.title_required'),
            'title.max' => __('columns.validation.title_max'),
        ];
    }
}
