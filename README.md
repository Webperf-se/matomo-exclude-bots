# Matomo ExcludeBots Plugin

## Description

Excludes fake visits from **stealth headless-browser bots** that Matomo's
built-in bot detection and the TrackingSpamPrevention plugin cannot catch.

Since mid-2025 many Matomo (and GA4) users see waves of fake traffic with
this signature:

- Sudden spike of visits, almost always **exactly one pageview per visit**
- Visit duration of **0 seconds**, bounce rate above 90 %
- Claims to come **from Google search** (or direct entry)
- Geographically spread across the whole globe via rotating
  residential and small-hosting proxies
- Fingerprint: genuine **Chrome on desktop Linux** (`X11; Linux x86_64`)
  reporting a **1920x1080** screen – the default viewport of headless
  Chrome automation
- Hits a handful of specific pages over and over (typical for
  SERP-rank-checker and CTR-manipulation bots)

These bots run real Chrome in stealth headless mode, so they execute the
JavaScript tracker and present a normal user agent. IP-based blocking
does not work either: the proxy pools include residential ISP addresses
and small hosting providers that are not on cloud-provider blocklists.

This plugin hooks into `Tracker.isExcludedVisit` and drops tracking
requests matching the full fingerprint (all conditions must match):

1. User agent contains `X11; Linux x86_64`
2. Desktop Chrome (not Mobile, and declared bots are untouched –
   Matomo already handles those)
3. Screen resolution is exactly `1920x1080`
4. Referrer is empty or contains `google.` (the faked "organic" entry)

The only real visitors this can exclude are humans on desktop Linux, in
Chrome, at exactly 1080p, arriving via Google or directly – for most
sites a negligible share, and a trade-off you control (see FAQ).

Exclusion happens at tracking time only: nothing is stored, historical
data is not modified, and reports stay untouched.

## Installation

Copy the plugin into your Matomo installation and activate it:

```
matomo/plugins/ExcludeBots/
├── ExcludeBots.php
└── plugin.json
```

Then activate under *Administration → Plugins*, or via the console:

```
./console plugin:activate ExcludeBots
```

## Configuration (optional)

Defaults match the bot wave described above. Override them in an
`[ExcludeBots]` section in `config/config.ini.php`:

```ini
[ExcludeBots]
; Substring that must appear in the user agent
ua_contains = "X11; Linux x86_64"
; Comma-separated list of screen resolutions that trigger exclusion
resolutions = "1920x1080"
; 1 = only exclude when referrer is empty or contains "google."
; 0 = exclude on user agent + resolution alone
referrer_google_or_empty = 1
```

## FAQ

**How do I know if I am affected?**

Check *Visitors → Overview* for a sudden jump in visits combined with a
collapse of pages-per-visit towards 1.0 and a bounce rate above 90 %.
Then open a day in the Visits Log and look for the fingerprint: Chrome
on GNU/Linux, 1920x1080, 0 s visits, "from Google", from countries that
do not match your audience.

**Will this exclude real visitors?**

Only desktop-Linux Chrome users at exactly 1920x1080 arriving via Google
or directly. Check your own pre-spike share of that segment first; for
most sites it is well under 1 % of traffic. You can view what is being
excluded by temporarily deactivating the plugin and using a segment
instead: `resolution==1920x1080;operatingSystemName==GNU/Linux;browserName==Chrome`.

**Why doesn't TrackingSpamPrevention stop these bots?**

Its cloud-IP blocklists cover the large cloud providers, while these
bots exit through residential ISPs and small hosting companies. Its
headless-browser detection relies on the browser revealing itself
(e.g. a `HeadlessChrome` user agent), which stealth headless setups
avoid. This plugin complements it – keep both enabled.

**Does it clean up historical data?**

No. Use Matomo's GDPR tools (*Administration → Privacy → GDPR Tools*) to
find and delete already-tracked bot visits, then invalidate and
re-archive the affected date ranges.

**How can I verify it works?**

Enable tracker debug output (`[Tracker] debug = 1` in config, or
`&debug=1` on a tracking request if debug on demand is enabled) and send
a request with the bot fingerprint – the response will contain
`ExcludeBots: request matched headless-bot fingerprint`. The plugin's
logic also has a standalone test suite: `php tests/standalone.php`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE](LICENSE).

## Support

Please report issues at
[github.com/Webperf-se/matomo-exclude-bots/issues](https://github.com/Webperf-se/matomo-exclude-bots/issues).
Built by [webperf.se](https://webperf.se) after being hit by this exact
bot wave.
