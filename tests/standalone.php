<?php

/**
 * Standalone logic test – runs without a Matomo installation.
 *
 *     php tests/standalone.php
 *
 * Stubs the few Piwik classes the plugin touches, then asserts that the
 * fingerprint matching excludes exactly what it should. Exits non-zero
 * on failure.
 */

namespace Piwik {
    class Plugin
    {
    }

    class Common
    {
        public static function printDebug($message)
        {
        }
    }

    class Config
    {
        public static $sections = [];

        public static function getInstance(): self
        {
            return new self();
        }

        public function __get($name)
        {
            return self::$sections[$name] ?? null;
        }
    }
}

namespace Piwik\Tracker {
    class Request
    {
        private $ua;
        private $params;

        public function __construct(string $ua, array $params = [])
        {
            $this->ua = $ua;
            $this->params = $params;
        }

        public function getUserAgent(): string
        {
            return $this->ua;
        }

        public function getParam($name)
        {
            return $this->params[$name] ?? '';
        }
    }
}

namespace {
    require __DIR__ . '/../ExcludeBots.php';

    use Piwik\Plugins\ExcludeBots\ExcludeBots;
    use Piwik\Tracker\Request;

    const BOT_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36';
    const WIN_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36';
    const LINUX_FIREFOX_UA = 'Mozilla/5.0 (X11; Linux x86_64; rv:152.0) '
        . 'Gecko/20100101 Firefox/152.0';
    const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 11) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36';

    $plugin = new ExcludeBots();
    $failures = 0;

    function check(string $name, bool $expectExcluded, string $ua, array $params): void
    {
        global $plugin, $failures;
        $excluded = false;
        $plugin->isExcludedVisit($excluded, new Request($ua, $params));
        $status = $excluded === $expectExcluded ? 'OK  ' : 'FAIL';
        if ($excluded !== $expectExcluded) {
            $failures++;
        }
        printf("%s %-55s excluded=%s\n", $status, $name, var_export($excluded, true));
    }

    // ── Should be excluded ──
    check('bot: Linux Chrome 1080p, Google referrer', true, BOT_UA,
        ['res' => '1920x1080', 'urlref' => 'https://www.google.com/']);
    check('bot: Linux Chrome 1080p, no referrer', true, BOT_UA,
        ['res' => '1920x1080']);
    check('bot: Linux Chrome 1080p, google.se referrer', true, BOT_UA,
        ['res' => '1920x1080', 'urlref' => 'https://www.google.se/url?q=x']);

    // ── Should NOT be excluded ──
    check('human: Windows Chrome 1080p from Google', false, WIN_UA,
        ['res' => '1920x1080', 'urlref' => 'https://www.google.com/']);
    check('human: Linux Firefox 1080p from Google', false, LINUX_FIREFOX_UA,
        ['res' => '1920x1080', 'urlref' => 'https://www.google.com/']);
    check('human: Linux Chrome other resolution', false, BOT_UA,
        ['res' => '2560x1440', 'urlref' => 'https://www.google.com/']);
    check('human: Linux Chrome 1080p from a newsletter', false, BOT_UA,
        ['res' => '1920x1080', 'urlref' => 'https://old.webperf.se/']);
    check('human: Android Chrome (Mobile)', false, ANDROID_UA,
        ['res' => '412x823', 'urlref' => 'https://www.google.com/']);

    // ── Respects already-excluded flag ──
    $already = true;
    $plugin->isExcludedVisit($already, new Request(WIN_UA, ['res' => '800x600']));
    printf("%s %-55s excluded=%s\n", $already ? 'OK  ' : 'FAIL',
        'already excluded stays excluded', var_export($already, true));
    if (!$already) {
        $failures++;
    }

    // ── config.ini.php overrides ──
    \Piwik\Config::$sections['ExcludeBots'] = [
        'resolutions' => '1920x1080, 2560x1440',
        'referrer_google_or_empty' => 0,
    ];
    check('override: extra resolution now excluded', true, BOT_UA,
        ['res' => '2560x1440', 'urlref' => 'https://www.google.com/']);
    check('override: referrer rule off, bing referrer excluded', true, BOT_UA,
        ['res' => '1920x1080', 'urlref' => 'https://www.bing.com/']);
    \Piwik\Config::$sections = [];

    echo $failures === 0 ? "\nAll tests passed.\n" : "\n$failures test(s) FAILED.\n";
    exit($failures === 0 ? 0 : 1);
}
