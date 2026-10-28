# Privatus Analytics for Bolt CMS

**Real visitor numbers for your Bolt CMS site. No cookies, no cookie pop-up, no bots.**

You want to know if people visit your Bolt CMS site. But Google Analytics uses
cookies, so in many countries you must show a cookie pop-up first (the box that
asks visitors to say yes to cookies). Many people click no, so they never show
up in your numbers. Bots add fake visits on top.

This extension adds the Privatus Analytics script to every front-end page of
your Bolt CMS site. You set your site ID once in a config file. Your own visits are not
counted while you are logged in, unless you turn that off. The script sets no
cookies and keeps no IP addresses. Bots are filtered out before they are
counted.

So you see how many real people came, which pages they looked at and where they
came from, on one simple dashboard. A report comes to your inbox each week, and
you can set alerts for when traffic jumps or drops. And you don't need a cookie
pop-up for your analytics.

**Free plan, no card:** 10,000 events a month (an event is a page view or an action you track) on up to 3 sites. [Start free at privatusanalytics.com](https://privatusanalytics.com).

- **No cookies.** No cookies and no hidden IDs on your visitors' devices, and no IP
  addresses are kept. Laws differ by country, so check your own legal advice.
- **Real people, not bots.** Known bots, data centers and spam visits are left
  out on every plan.
- **Small and fast.** The script is about 3 KB when compressed.

## Requirements

- Bolt CMS 6 (tested on 6.1.10). PHP 8.2 or later.
- A Privatus Analytics site ID. It starts with `pa_`. You find it in Privatus
  Analytics under Site settings, Tracking.

## Install

```sh
composer require privatus/bolt-privatus-analytics
php bin/console extensions:configure
```

The second command copies the default settings to
`config/extensions/privatus-boltanalytics.yaml`.

## Setup

Open `config/extensions/privatus-boltanalytics.yaml`, paste your site ID and
clear the cache:

```yaml
site_id: pa_YOURSITEID
modules: [engage, auto, vitals, clicks, ask]
skip_logged_in: true
```

```sh
php bin/console cache:clear
```

Until you set a valid site ID, the extension adds nothing to your pages. The
tag goes at the end of `<head>` on front-end pages. The Bolt back end never
gets it.

## Settings

| Setting | What it does |
| --- | --- |
| `site_id` | Your Privatus Analytics site ID, like `pa_7Q2K9XH3AB`. If it is empty, the `PRIVATUS_SITE_ID` environment variable is used. A value that does not look like a site ID prints nothing. |
| `modules` | The extra tracking to load. `engage` is time on page and how far people scroll. `auto` is clicks on outside links, file downloads, forms people send and missing pages (404). `vitals` is page speed (Web Vitals). `clicks` is click maps. `ask` is short surveys on your site. Use `[]` to load none. |
| `skip_logged_in` | On by default. People logged in to Bolt get no script, so your own visits are not counted. |

With the default settings, the extension adds this to each page:

```html
<script defer src="https://privatusstats.com/js/pa.js" data-site="pa_YOURSITEID" data-modules="engage,auto,vitals,clicks,ask"></script>
```

## External service


After you save a site ID, every page loads `https://privatusstats.com/js/pa.js`
in the visitor's browser. The script sends the page address (query parameters
are removed except campaign parameters), the referrer, the browser language, the
screen width, the time zone name and event names to
`https://privatusstats.com/api/event`. The visitor's IP address is read in
memory to work out a rough country and to count unique visits, and it is not
stored.

* Terms of service: https://privatusanalytics.com/legal/terms
* Privacy policy: https://privatusanalytics.com/legal/privacy

## Development

```sh
composer install && vendor/bin/phpunit   # tag builder tests
./scripts/verify-docker.sh               # real Bolt project in Docker: install, configure, check pages
```

## License

MIT. See `LICENSE`.
