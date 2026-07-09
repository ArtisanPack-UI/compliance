<?php

/**
 * Shared bootstrap for compliance AI agent tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Contracts\CredentialResolver;
use ArtisanPackUI\Ai\Credentials\ChainedCredentialResolver;
use ArtisanPackUI\Ai\Credentials\Credentials;
use ArtisanPackUI\Compliance\ComplianceServiceProvider;
use Tests\Support\FakeAgentPrompter;

/**
 * Registers a fake prompter, stub credentials, and enables the three
 * feature toggles the compliance AI surface cares about.
 */
final class AiAgentTestSetup
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    public static function bootstrap( $app ): FakeAgentPrompter
    {
        /** @var ChainedCredentialResolver $resolver */
        $resolver = $app->make( CredentialResolver::class );
        $resolver->setOverride(
            new Credentials( provider: 'anthropic', apiKey: 'sk-test', defaultModel: 'claude-haiku-4-5' ),
        );
        $resolver->useStore( fn () => null );

        $prompter = new FakeAgentPrompter();
        $app->instance( AgentPrompter::class, $prompter );

        foreach ( ComplianceServiceProvider::AI_FEATURE_KEYS as $key ) {
            $app['config']->set( "artisanpack.ai.features.{$key}.enabled", true );
        }

        return $prompter;
    }
}
