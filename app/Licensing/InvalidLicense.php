<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use RuntimeException;

/** Why a license token is refused, in one plain sentence. */
final class InvalidLicense extends RuntimeException {}
