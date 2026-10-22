<?php

declare(strict_types=1);

namespace App\Livewire\Vault;

use App\Actions\Vault\StoreAttachment;
use App\Exceptions\QuotaExceededException;
use App\Models\Card;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class UploadZone extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $cardId;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public function mount(int $cardId): void
    {
        $this->cardId = $this->resolveCard($cardId)->id;
    }

    public function updatedFiles(): void
    {
        $this->resetErrorBag();

        $this->validate($this->rules(), $this->messages(), $this->attributes());
    }

    public function remove(int $index): void
    {
        unset($this->files[$index]);

        $this->files = array_values($this->files);
    }

    public function upload(StoreAttachment $store): void
    {
        $card = $this->resolveCard($this->cardId);
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        if ($this->isRateLimited($user)) {
            return;
        }

        $this->validate($this->rules(), $this->messages(), $this->attributes());

        $uploaded = 0;
        $messages = [];

        foreach ($this->files as $file) {
            try {
                $store->handle($user, $card, $file);
                $uploaded++;
            } catch (ValidationException $exception) {
                array_push($messages, ...Arr::flatten($exception->errors()));
            } catch (QuotaExceededException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        $this->files = [];
        $this->resetErrorBag();

        if ($messages !== []) {
            $this->addError('files', implode(' ', array_unique($messages)));
        }

        if ($uploaded > 0) {
            $this->dispatch('attachment-uploaded', cardId: $card->id);

            Flux::toast(variant: 'success', text: __('attachments.uploaded', ['count' => $uploaded]));
        }
    }

    public function render(): View
    {
        return view('livewire.vault.upload-zone', [
            'maxFileMb' => (int) config('dropdeck.limits.max_file_mb'),
            'remaining' => $this->remainingSlots(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'files' => ['array', 'max:'.$this->remainingSlots()],
            'files.*' => ['file', 'max:'.((int) config('dropdeck.limits.max_file_mb') * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'files.max' => __('attachments.errors.too_many', ['max' => (int) config('dropdeck.limits.max_files_per_card')]),
            'files.*.max' => __('attachments.errors.too_large', ['max' => (int) config('dropdeck.limits.max_file_mb').' MB']),
            'files.*.file' => __('attachments.errors.unreadable'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'files' => __('attachments.title'),
            'files.*' => __('attachments.file'),
        ];
    }

    private function isRateLimited(User $user): bool
    {
        $key = 'upload:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('dropdeck.limits.upload_per_minute'))) {
            $this->addError('files', __('attachments.errors.rate_limited'));

            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    private function remainingSlots(): int
    {
        $used = Card::query()->whereKey($this->cardId)->firstOrFail()->attachments()->count();

        return max(0, (int) config('dropdeck.limits.max_files_per_card') - $used);
    }

    private function resolveCard(int $cardId): Card
    {
        return Card::query()
            ->where('user_id', auth()->id())
            ->whereKey($cardId)
            ->firstOrFail();
    }
}
