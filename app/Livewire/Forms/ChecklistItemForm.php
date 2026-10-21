<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

class ChecklistItemForm extends Form
{
    public string $content = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => __('cards.validation.checklist_required'),
            'content.max' => __('cards.validation.checklist_max'),
        ];
    }
}
