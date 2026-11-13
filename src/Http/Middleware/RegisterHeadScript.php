<?php

declare(strict_types=1);

namespace bensomething\cast\Http\Middleware;

use bensomething\cast\Plugin;
use bensomething\cast\Settings;
use Closure;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Cms\View\HtmlStack;
use Illuminate\Http\Request;

use function CraftCms\Cms\currentUserElement;

/**
 * Puts Cast's head script on every control panel page.
 *
 * 1.x hooked page-template rendering, which on Craft 6 fires for bridged Twig screens
 * but not native Inertia pages. This sits in the `craft.cp` middleware group instead,
 * which every CP route passes through after the session has started, so the user is
 * known. The Inertia shell prints `HtmlStack`'s head ahead of Craft's stylesheets, so
 * the script runs before first paint.
 *
 * Client-side Inertia visits don't re-render the head, so the attribute stamped on the
 * first full load carries across them. A theme change reloads.
 */
final readonly class RegisterHeadScript
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->isMethod('GET')) {
            app(HtmlStack::class)->js(
                sprintf(
                    'document.documentElement.dataset.castTheme = %s;',
                    json_encode($this->themeHandle(), JSON_THROW_ON_ERROR | JSON_HEX_TAG),
                ),
                Position::Head,
                'cast-head',
            );
        }

        return $next($request);
    }

    /** The user's own choice, or the site default if they haven't made one. */
    private function themeHandle(): string
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();
        $chosen = currentUserElement()?->getPreference('castTheme');

        if (! is_string($chosen) || $chosen === Settings::THEME_INHERIT || ! $settings->allowUserOverride) {
            return $settings->defaultTheme;
        }

        return $chosen;
    }
}
