<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Session lifetime rules (S-0903, security baseline section 1, V7):
 * - a session belongs to one `session_version` of its user; moving the
 *   version on ends every older session, whatever the session driver;
 * - a session ends at the latest after `session.absolute_lifetime` minutes
 *   since sign-in, however active it is (idle timeout: `session.lifetime`).
 */
final class Sessions
{
    public const string VERSION = 'auth.session_version';

    public const string STARTED = 'auth.started_at';

    /** Bind a session to the user's current version at sign-in. */
    public function start(Session $session, User $user): void
    {
        $session->put(self::VERSION, $user->session_version);
        $session->put(self::STARTED, now()->getTimestamp());
    }

    /** Whether the session may go on; a session without the keys is adopted. */
    public function isValid(Session $session, User $user): bool
    {
        $version = $session->get(self::VERSION);
        $started = $session->get(self::STARTED);

        if (! is_int($version) || ! is_int($started)) {
            $this->start($session, $user);

            return true;
        }

        $absolute = config()->integer('session.absolute_lifetime', 720) * 60;

        return $version === $user->session_version && now()->getTimestamp() - $started <= $absolute;
    }

    /**
     * End every session of the user except the given one, which moves to the
     * new version and gets a new ID. Returns the number of other sessions
     * deleted from the session table (database driver only, else null).
     */
    public function endOthers(User $user, Session $current): ?int
    {
        $this->moveOn($user);
        $this->keepRememberMe($user);
        $current->put(self::VERSION, $user->session_version);
        $current->migrate(true);

        if (config('session.driver') !== 'database') {
            return null;
        }

        return $this->table()
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $current->getId())
            ->delete();
    }

    /** End every session of the user, also "remember me" on other devices. */
    public function endAll(User $user): void
    {
        $this->moveOn($user);
    }

    /** Other sessions in the session table (database driver only, else null). */
    public function countOthers(User $user, Session $current): ?int
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        return $this->table()
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $current->getId())
            ->count();
    }

    private function table(): Builder
    {
        $connection = config('session.connection');

        return DB::connection(is_string($connection) ? $connection : null)
            ->table(config()->string('session.table', 'sessions'));
    }

    /**
     * Move the version on inside a locked row, so two revocations at the same
     * time cannot both keep their session: the later one wins.
     */
    private function moveOn(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $current = User::query()->whereKey($user->getKey())->lockForUpdate()->value('session_version');
            $next = (is_numeric($current) ? (int) $current : 0) + 1;

            User::query()->whereKey($user->getKey())->update([
                'session_version' => $next,
                'remember_token' => Str::random(60),
            ]);
        });

        $user->refresh();
    }

    /** The current device keeps "remember me" when the user had it on. */
    private function keepRememberMe(User $user): void
    {
        $guard = Auth::guard('web');

        if (request()->cookies->has($guard->getRecallerName()) && $guard->id() === $user->getKey()) {
            $guard->login($user, true);
        }
    }
}
