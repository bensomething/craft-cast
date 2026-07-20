<?php

/**
 * Checks that every class and ID a Cast theme styles still exists somewhere in Craft's
 * own control-panel stylesheets.
 *
 * The themes are a long list of Craft's internal selectors, and nothing about a stale one
 * looks like a failure: the rule simply stops matching and that corner of the CP renders
 * light-on-light. Craft ships its compiled CSS inside the package we already depend on,
 * so a Craft release that renames a class can be caught here rather than in an issue.
 *
 * Names are compared as a set rather than by substring, so `.btn` can't vouch for
 * `.btn-group`. Anything Cast defines itself, or that belongs to a plugin rather than
 * Craft, is listed in IGNORE below.
 *
 * Usage: composer check-selectors
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/**
 * Names Cast defines itself rather than borrowing from Craft, so there's nothing to
 * check them against. A prefix rather than a list, because the list would need adding to
 * every time Cast grew a hook, and a stale entry is indistinguishable from a rotted
 * selector.
 *
 * Third-party plugins aren't exempted here. Cast patches CKEditor through its `--ck-*`
 * custom properties, which are declarations rather than selectors, so they never reach
 * this check. Should a class-based patch ever be added, it belongs in IGNORE with a
 * reason — it can't be verified against a package we don't depend on.
 */
const OWN_PREFIX = 'cast-';

/**
 * Selector names that legitimately have no counterpart in Craft.
 *
 * Each needs a reason. A name added here without one is indistinguishable from a
 * selector that has quietly rotted.
 *
 * @var array<string, string>
 */
const IGNORE = [];

/**
 * Pulls every class and ID name out of a stylesheet's selectors.
 *
 * Only selector text is scanned — never declarations — so hex colours can't be mistaken
 * for IDs. At-rule preludes (`@media`, `@supports`) are skipped but still descended into.
 *
 * @return array<string, true> Name => true, so callers can diff and look up cheaply.
 */
function selectorNames(string $css): array
{
    // Comments can contain anything, including whole commented-out rules.
    $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';

    $names = [];
    $buffer = '';

    foreach (str_split($css) as $char) {
        if ($char === '{') {
            $prelude = trim($buffer);
            $buffer = '';

            // An at-rule's prelude holds no selectors, but its body does.
            if ($prelude === '' || $prelude[0] === '@') {
                continue;
            }

            preg_match_all('/[.#](-?[A-Za-z_][\w-]*)/', $prelude, $matches);

            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }

            continue;
        }

        if ($char === '}') {
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    return $names;
}

/**
 * Pulls class and ID names out of a Twig template's `class` and `id` attributes.
 *
 * Craft's CSS isn't the whole picture: a hook can exist purely in markup. The Plugin
 * Store's `#app` is one — Vue mounts over it, so Craft has no rule for it, but Cast
 * still needs to reach it. Values are split on whitespace and anything that isn't a bare
 * identifier is dropped, which discards the Twig expressions interpolated among them.
 *
 * @return array<string, true>
 */
function markupNames(string $twig): array
{
    $names = [];

    preg_match_all('/\b(?:class|id)\s*=\s*"([^"]*)"/i', $twig, $matches);

    foreach ($matches[1] as $value) {
        foreach (preg_split('/\s+/', $value) ?: [] as $token) {
            if (preg_match('/^-?[A-Za-z_][\w-]*$/', $token)) {
                $names[$token] = true;
            }
        }
    }

    return $names;
}

/**
 * @param string[] $files
 * @return array<string, true>
 */
function namesIn(array $files): array
{
    $names = [];

    foreach ($files as $file) {
        $css = file_get_contents($file);

        if ($css === false) {
            fwrite(STDERR, "Couldn't read $file.\n");
            exit(1);
        }

        $names += str_ends_with($file, '.twig') ? markupNames($css) : selectorNames($css);
    }

    return $names;
}

/**
 * Every file under a directory matching one of the given extensions.
 *
 * @param string[] $extensions
 * @return string[]
 */
function filesUnder(string $directory, array $extensions): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

$themeFiles = glob("$root/src/resources/themes/*.css") ?: [];

// Every bundle Craft ships, not just the CP's own: the Plugin Store, Selectize, the
// installer and the widgets each carry their own stylesheet, and Cast styles all of them.
// Minified copies are skipped as duplicates of the readable ones. Craft's templates come
// too, for the hooks that only ever exist in markup.
$craftFiles = array_merge(
    array_values(array_filter(
        filesUnder("$root/vendor/craftcms/cms/src/web/assets", ['css']),
        static fn(string $file) => !str_ends_with($file, '.min.css'),
    )),
    filesUnder("$root/vendor/craftcms/cms/src/templates", ['twig']),
);

if ($themeFiles === []) {
    fwrite(STDERR, "No theme stylesheets found.\n");
    exit(1);
}

// A vendor directory that's absent or restructured would otherwise read as "every
// selector is stale", which is a confusing way to learn Composer hasn't run.
if ($craftFiles === []) {
    fwrite(STDERR, "No Craft stylesheets found under vendor/craftcms/cms. Run `composer install` first.\n");
    exit(1);
}

$craft = namesIn($craftFiles);
$status = 0;

printf("Checking %d theme file(s) against %d Craft stylesheet(s).\n\n", count($themeFiles), count($craftFiles));

foreach ($themeFiles as $file) {
    $missing = array_filter(
        array_diff_key(namesIn([$file]), $craft, IGNORE),
        static fn(string $name) => !str_starts_with($name, OWN_PREFIX),
        ARRAY_FILTER_USE_KEY,
    );

    if ($missing === []) {
        printf("  ok    %s\n", basename($file));
        continue;
    }

    $status = 1;

    printf("  STALE %s\n", basename($file));

    foreach (array_keys($missing) as $name) {
        printf("        %s — no longer in Craft's CSS\n", $name);
    }
}

if ($status !== 0) {
    echo "\nThese selectors match nothing Craft ships, so their rules never apply.\n"
        . "Either update them to Craft's current markup, or add them to IGNORE in "
        . "bin/check-selectors.php with a reason.\n";
} else {
    echo "\nEvery selector still resolves.\n";
}

exit($status);
