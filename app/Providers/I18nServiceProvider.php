<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

use App\Audit\AuditLog;
use App\Branding\Brand;
use App\I18n\Locales;
use App\I18n\StringCatalog;
use App\I18n\Strings;
use App\I18n\StringTranslator;
use App\I18n\TenantLanguages;
use App\Modules\ModuleRegistry;
use App\Support\Lists;
use App\Tenancy\TenantContext;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;
use Psr\Log\LoggerInterface;

/**
 * String system (S-0916, ADR-0005).
 */
class I18nServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Locales::class, function (Application $app): Locales {
            $config = $app->make(Repository::class);

            return new Locales(
                Lists::strings($config->array('i18n.locales')),
                $config->string('i18n.default'),
                $config->string('i18n.source'),
                Lists::strings($config->array('i18n.rtl')),
                Lists::strings($config->array('i18n.refused')),
                $config->boolean('i18n.pseudo'),
            );
        });

        $this->app->singleton(StringCatalog::class, fn (Application $app): StringCatalog => new StringCatalog(
            $app->langPath(),
            $app->make(ModuleRegistry::class),
            $app->make(Repository::class)->string('i18n.cache_path'),
            $app->make(LoggerInterface::class),
        ));

        // Per request: the languages of the current tenant (S-0921).
        $this->app->scoped(TenantLanguages::class, fn (Application $app): TenantLanguages => new TenantLanguages(
            $app->make(Locales::class),
            $app->make(StringCatalog::class),
            $app->make(TenantContext::class),
            $app->make(AuditLog::class),
            $app->make('cache.store'),
        ));

        $this->app->scoped(Strings::class, fn (Application $app): Strings => new Strings(
            $app->make(TenantLanguages::class),
            $app->make(StringCatalog::class),
            $app->make(TenantContext::class),
            $app,
            $app->make('cache.store'),
            $app->make(LoggerInterface::class),
            // {brand}: the tenant's brand, else the instance brand (S-0911).
            fn (): string => $app->make(Brand::class)->name(),
            fn (): string => $app->make(Brand::class)->publicName(),
        ));

        // Laravel's translator (validation, Fortify, mail) reads from the same layer.
        $this->app->extend('translator', function (Translator $translator, Application $app): StringTranslator {
            $bridge = new StringTranslator($translator->getLoader(), $translator->getLocale());
            $bridge->setFallback($translator->getFallback());
            $bridge->setStrings(fn (): Strings => $app->make(Strings::class));

            return $bridge;
        });
    }

    public function boot(): void
    {
        // `php artisan optimize` compiles the strings; `optimize:clear` removes them.
        $this->optimizes(
            optimize: 'projektrack:strings:cache',
            clear: 'projektrack:strings:clear',
            key: 'strings',
        );
    }
}
