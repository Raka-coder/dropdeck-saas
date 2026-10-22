<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;

class LabelForm extends Form
{
    public string $name = '';

    public string $color = '#6366f1';

    public ?int $boardId = null;

    public ?int $ignoreLabelId = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:40',
                Rule::unique('labels', 'name')
                    ->where('board_id', $this->boardId)
                    ->ignore($this->ignoreLabelId),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('cards.validation.label_required'),
            'name.max' => __('cards.validation.label_max'),
            'name.unique' => __('cards.validation.label_unique'),
            'color.required' => __('cards.validation.label_color_required'),
            'color.regex' => __('cards.validation.label_color_format'),
        ];
    }
}
