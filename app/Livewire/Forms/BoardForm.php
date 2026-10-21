<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

class BoardForm extends Form
{
    public string $title = '';

    public string $description = '';

    public string $color = '#6366f1';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('boards.validation.title_required'),
            'title.min' => __('boards.validation.title_min'),
            'title.max' => __('boards.validation.title_max'),
            'description.max' => __('boards.validation.description_max'),
            'color.required' => __('boards.validation.color_required'),
            'color.regex' => __('boards.validation.color_format'),
        ];
    }

    public function descriptionOrNull(): ?string
    {
        return $this->description === '' ? null : $this->description;
    }
}
