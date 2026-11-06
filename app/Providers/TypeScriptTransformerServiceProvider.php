<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\ModuleWriter;
use Splicewire\Beam\Codegen\DeclaredParticleTransformedProvider;
use Splicewire\Beam\Codegen\DeclaredParticleTypes;

final class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->provider(new DeclaredParticleTransformedProvider(app(DeclaredParticleTypes::class)))
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(
                app_path(),
                base_path('vendor/splicewire/laravel-beam-accounts/src/Data/Pages'),
                // PlatformConnectionPageData carries the satellite's pairing snapshot, its identity
                // and capability projections, and the six-state enum. A referenced type that is not
                // in a transformed directory is reported by `typescript:transform` and then emitted
                // as `any` — a declared shape silently becoming untyped, which is exactly what
                // TypeScriptGenerationTest's "not found in the transformed types" assertion watches.
                base_path('vendor/splicewire/laravel-satellite/src/Pairing'),
            )
            ->writer(new ModuleWriter(path: null));
    }
}
