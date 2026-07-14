# Changelog

## 1.0.0

- Initial release.
- Excludes tracking requests matching the stealth headless-bot
  fingerprint: desktop Chrome on Linux + 1920x1080 + Google/empty
  referrer.
- Fingerprint configurable via an `[ExcludeBots]` section in
  `config.ini.php` (`ua_contains`, `resolutions`,
  `referrer_google_or_empty`).
- Standalone test suite runnable without a Matomo installation.
