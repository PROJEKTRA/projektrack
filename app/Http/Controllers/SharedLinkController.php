<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers;

use App\Models\SharedLink;
use App\SharedLinks\ResolvedLink;
use App\SharedLinks\SharedLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public side of shared links (S-0987): /s/{token} shows the one object
 * of the link, /s/{token}/{action} runs another granted action (reading
 * actions by GET, changing ones by POST with a CSRF token). Unknown, expired
 * and revoked links, objects that are gone and actions the link does not
 * grant all get the same 404. Responses are never cached; link previews of
 * chat and mail scanners get a page without content.
 */
class SharedLinkController extends Controller
{
    private const string UNLOCKED = 'shared_links.unlocked.';

    /** An unlocked session asks for the password again after this. */
    private const int UNLOCK_HOURS = 12;

    public function __construct(private readonly SharedLinks $links) {}

    public function show(Request $request, string $token): Response
    {
        return $this->answer($request, $token, 'view');
    }

    public function action(Request $request, string $token, string $action): Response
    {
        return $this->answer($request, $token, $action);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $resolved = $this->usable($request, $token, 'unlock');
        $link = $resolved->link;
        $password = $request->input('password');

        if ($link->isLocked()) {
            $this->log($request, $link, 'unlock', 'locked');
            abort(429);
        }

        if (! is_string($password) || ! $this->links->checkPassword($link, $password)) {
            $this->log($request, $link, 'unlock', 'password_wrong');

            // Explicitly to the link: no Referer, and no previous URL in the session.
            return redirect()->to($request->getPathInfo())->withErrors(['password' => __('core:shared_links.unlock.wrong')]);
        }

        $request->session()->regenerate();
        $request->session()->put(self::UNLOCKED.$link->id, ['grant' => $this->links->grant($link->fresh() ?? $link), 'at' => now()->getTimestamp()]);
        $this->log($request, $link, 'unlock', 'ok');

        return redirect()->to($request->getPathInfo());
    }

    private function answer(Request $request, string $token, string $action): Response
    {
        $resolved = $this->usable($request, $token, $action);
        $link = $resolved->link;

        if (! $resolved->grants($action)) {
            $this->log($request, $link, $action, 'not_granted');
            abort(404);
        }

        // Reading actions by GET, changing ones by POST only.
        if ($resolved->mutates($action) !== $request->isMethod('POST')) {
            abort(404);
        }

        if (! $this->isUnlocked($request, $link)) {
            if ($link->isLocked()) {
                $this->log($request, $link, $action, 'locked');
                abort(429);
            }
            // Other actions need the password first, entered on the link itself.
            abort_unless($action === 'view', 404);

            return $this->private(Inertia::render('shared/Unlock', ['action' => SharedLinks::here($request)])->toResponse($request));
        }

        if ($request->isMethod('HEAD')) {
            return $this->private(response('', 200));
        }

        // Link previews and mail scanners: no content, nothing is run.
        if (SharedLinks::browserFamily($request->userAgent()) === 'bot') {
            $this->log($request, $link, $action, 'bot');

            return $this->private(Inertia::render('shared/Hidden')->toResponse($request));
        }

        $this->log($request, $link, $action, 'ok');

        return $this->private($resolved->target->respond($link, $resolved->object, $action, $request));
    }

    /** The link, or the generic 404; expired and revoked uses are logged. */
    private function usable(Request $request, string $token, string $action): ResolvedLink
    {
        $resolved = $this->links->resolve($token);

        if ($resolved === null) {
            abort(404);
        }

        if (! $resolved->link->isActive()) {
            $this->log($request, $resolved->link, $action, $resolved->link->revoked_at !== null ? 'revoked' : 'expired');
            abort(404);
        }

        return $resolved;
    }

    /** No password, or this session entered the current one in the last hours. */
    private function isUnlocked(Request $request, SharedLink $link): bool
    {
        if ($link->password === null) {
            return true;
        }

        $unlocked = $request->session()->get(self::UNLOCKED.$link->id);

        return is_array($unlocked)
            && ($unlocked['grant'] ?? null) === $this->links->grant($link)
            && is_int($unlocked['at'] ?? null)
            && $unlocked['at'] > now()->subHours(self::UNLOCK_HOURS)->getTimestamp();
    }

    private function log(Request $request, SharedLink $link, string $action, string $outcome): void
    {
        $this->links->logAccess($link, mb_substr($action, 0, 40), $outcome, $request->ip(), $request->userAgent());
    }

    private function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
