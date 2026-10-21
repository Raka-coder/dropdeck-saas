<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

class CardForm extends Form
{
    public string $title = '';

    public string $description = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('cards.validation.title_required'),
            'title.min' => __('cards.validation.title_min'),
            'title.max' => __('cards.validation.title_max'),
            'description.max' => __('cards.validation.description_max'),
        ];
    }

    public function descriptionOrNull(): ?string
    {
        return $this->description === '' ? null : $this->description;
    }
}
