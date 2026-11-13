<?php

declare(strict_types=1);

namespace bensomething\cast\Http\Middleware;

use bensomething\cast\Themes;
use Closure;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Cms\View\HtmlStack;
use Illuminate\Http\Request;

use function CraftCms\Cms\currentUserElement;

/**
 * Stamps the user's light and dark appearances onto `<html>` on every control panel
 * page, as `data-cast-light` and `data-cast-dark`.
 *
 * Both are stamped whatever mode Craft is in. Theme stylesheets pair their attribute
 * with Craft's own `data-theme`, so when Craft flips between light and dark (a saved
 * preference, the command palette, or System following the OS live) the right
 * appearance applies with nothing from Cast having to react.
 *
 * 1.x hooked page-template rendering, which on Craft 6 fires for bridged Twig screens
 * but not native Inertia pages. This sits in the `craft.cp` middleware group instead,
 * which every CP route passes through after the session has started, so the user is
 * known. The Inertia shell prints `HtmlStack`'s head ahead of Craft's stylesheets, so
 * the script runs before first paint. Client-side visits don't re-render the head; the
 * attributes stamped on the first full load carry across them.
 */
final readonly class RegisterHeadScript
{
    public function __construct(private Themes $themes) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->isMethod('GET') && ($script = $this->script()) !== '') {
            app(HtmlStack::class)->js($script, Position::Head, 'cast-head');
        }

        return $next($request);
    }

    private function script(): string
    {
        $user = currentUserElement();
        $set = [];

        foreach ([Themes::SCHEME_LIGHT => 'castLight', Themes::SCHEME_DARK => 'castDark'] as $scheme => $key) {
            $handle = $this->themes->forUser($user, $scheme);

            // Craft's own appearance is the absence of the attribute.
            if ($handle !== '') {
                $set[] = sprintf('d.%s=%s;', $key, json_encode($handle, JSON_THROW_ON_ERROR | JSON_HEX_TAG));
            }
        }

        if ($this->themes->highContrastForUser($user)) {
            $set[] = 'd.castContrast="more";';
        }

        return $set === [] ? '' : 'var d=document.documentElement.dataset;'.implode('', $set);
    }
}
