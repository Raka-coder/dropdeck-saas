<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccessEvent;
use App\Enums\DropLinkStatus;
use App\Enums\DropLinkTarget;
use App\Models\Attachment;
use App\Models\DropLink;
use App\Support\FileGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDropLinkController extends Controller
{
    public function show(Request $request, string $token): HttpResponse
    {
        $link = $this->findLink($token);

        $blocked = $this->blockedReason($link);

        if ($blocked !== null) {
            $this->log($request, $link, AccessEvent::Blocked, $blocked);

            return $this->page($link, $blocked);
        }

        if ($link->requiresPassword() && ! $this->unlocked($request, $link)) {
            return $this->page($link, 'password');
        }

        $this->log($request, $link, AccessEvent::View);

        return $this->page($link, 'ok');
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $link = $this->findLink($token);

        $blocked = $this->blockedReason($link);

        if ($blocked !== null) {
            $this->log($request, $link, AccessEvent::Blocked, $blocked);

            return redirect()->route('droplink.show', $link->token);
        }

        $request->validate([
            'password' => ['required', 'string', 'max:64'],
        ]);

        if (! Hash::check((string) $request->input('password'), $link->password_hash ?? '')) {
            $this->log($request, $link, AccessEvent::PasswordFailed);

            throw ValidationException::withMessages([
                'password' => __('droplinks.errors.wrong_password'),
            ]);
        }

        $request->session()->put($this->sessionKey($link), true);

        $this->log($request, $link, AccessEvent::PasswordOk);

        return redirect()->route('droplink.show', $link->token);
    }

    public function download(Request $request, string $token, ?string $attachment = null): StreamedResponse|RedirectResponse
    {
        $link = $this->findLink($token);

        $blocked = $this->blockedReason($link);

        if ($blocked !== null) {
            $this->log($request, $link, AccessEvent::Blocked, $blocked);

            return redirect()->route('droplink.show', $link->token);
        }

        if ($link->requiresPassword() && ! $this->unlocked($request, $link)) {
            return redirect()->route('droplink.show', $link->token);
        }

        $file = $this->resolveDownload($link, $attachment);

        if (! $link->consumeDownload()) {
            $this->log($request, $link, AccessEvent::Blocked, 'exhausted');

            return redirect()->route('droplink.show', $link->token);
        }

        $this->log($request, $link, AccessEvent::Download, null, $file->id);

        $stream = Storage::disk($file->disk)->readStream($file->path);

        abort_if($stream === null, 404);

        return response()->stream(
            function () use ($stream): void {
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => FileGuard::safeDownloadMime($file->mime_type),
                'Content-Length' => (string) $file->size_bytes,
                'Content-Disposition' => FileGuard::contentDisposition($file->original_name),
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function findLink(string $token): DropLink
    {
        return DropLink::query()->where('token', $token)->firstOrFail();
    }

    private function blockedReason(DropLink $link): ?string
    {
        return match ($link->status()) {
            DropLinkStatus::Revoked => 'revoked',
            DropLinkStatus::Expired => 'expired',
            DropLinkStatus::Exhausted => 'exhausted',
            DropLinkStatus::Active => null,
        };
    }

    private function resolveDownload(DropLink $link, ?string $attachmentId): Attachment
    {
        if ($link->target_type === DropLinkTarget::File) {
            $attachment = $link->attachment()->firstOrFail();

            abort_if($attachmentId !== null && $attachmentId !== (string) $attachment->id, 404);

            return $attachment;
        }

        abort_if($attachmentId === null, 404);

        return $link->card()
            ->firstOrFail()
            ->attachments()
            ->whereKey($attachmentId)
            ->firstOrFail();
    }

    private function unlocked(Request $request, DropLink $link): bool
    {
        return $request->session()->get($this->sessionKey($link)) === true;
    }

    private function sessionKey(DropLink $link): string
    {
        return 'droplink_unlocked.'.$link->getKey();
    }

    private function log(
        Request $request,
        DropLink $link,
        AccessEvent $event,
        ?string $reason = null,
        ?int $attachmentId = null,
    ): void {
        $link->accesses()->create([
            'attachment_id' => $attachmentId,
            'event' => $event,
            'blocked_reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }

    private function page(DropLink $link, string $state): HttpResponse
    {
        $link->loadMissing(['attachment', 'card']);

        $files = $link->target_type === DropLinkTarget::Card
            ? $link->card?->attachments()->orderBy('id')->get() ?? collect()
            : collect();

        return response()->view('pages.public.droplink', [
            'link' => $link,
            'state' => $state,
            'file' => $link->target_type === DropLinkTarget::File ? $link->attachment : null,
            'files' => $files,
            'remaining' => $link->remainingDownloads(),
        ])->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
