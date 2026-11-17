<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use DomainException;

/**
 * A role or grant change that would break a rule: a portal role for an internal
 * user (or the reverse), a permission the user type cannot hold, or removing
 * the last administrator of a tenant.
 */
final class InvalidRoleChange extends DomainException {}
