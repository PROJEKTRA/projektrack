<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Tenancy;

use LogicException;

/**
 * A model was about to be written for a tenant other than the current one, or
 * its tenant_id was about to change. Both are programming errors, never valid.
 */
final class CrossTenantWrite extends LogicException {}
