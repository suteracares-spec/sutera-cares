# Sutera Cares — website

Landing page for **Sutera Cares**, a community-led medical fund for refugees in Malaysia
(maternal health, safe birth, newborn care and medical emergencies).

Static HTML/CSS — no build step, no framework. Host it anywhere (any shared host,
Netlify, GitHub Pages, or the existing elsystem server). Content is drawn from the
**Sutera Business Plan v1.0 (Aug 2026)** — see `Sutera Business Plan.md` in Downloads or
the Claude artifact of the same name.

## Files

- `index.html` — the whole landing page (one page, anchor navigation)
- `css/styles.css` — styles; brand tokens at the top (`:root`)
- `js/i18n.js` — the Malay and Chinese translations, and the language switcher
- `img/logo.svg` — standalone logo (thread-heart + wordmark) for print/social use
- `img/favicon.svg` — favicon

## Languages

The page ships in **English, Bahasa Malaysia and 中文 (Simplified)**, switched in
the browser — one HTML file, no build step, no page reload.

English is not duplicated anywhere: it lives in `index.html` and is read straight
off the page on load. `js/i18n.js` holds only the two translations.

- Every translatable element carries `data-i18n="some.key"` (plus
  `data-i18n-label` for `aria-label`s). Values are HTML, so inline
  `<strong>` / `<em>` / `<a>` / `<br>` are fine inside a translation.
- **To edit copy:** change the English in `index.html`, then update the same key
  under `ms` and `zh` in `js/i18n.js`.
- **To add a string:** give the element a new `data-i18n` key, then add that key
  to both dictionaries. A key missing from a translation falls back to English
  rather than disappearing.
- Language is chosen by `?lang=ms` / `?lang=zh` in the URL, else a saved choice
  (`localStorage`), else the browser's language, else English. The chosen
  language is written back to the URL so a link is shareable.
- Chinese renders in the system CJK font — no webfont is downloaded for it.
  Latin words inside Chinese text still use Nunito Sans / Fraunces.

The switcher is hidden when JavaScript is off; the page then reads as English.

## Placeholders to fill before launch

| Placeholder | Where | Replace with |
|---|---|---|
| `+60 1X-XXX XXXX` | header + contact | real WhatsApp hotline number (also make it a `https://wa.me/...` link) |
| `[Partner organisation account name]` | donate section | fiscal sponsor's bank details after the MOU is signed |
| DuitNow QR box | donate section | real QR image once the gateway/merchant code exists |
| `contact@suteracares.org` | contact | works once the mailbox is created in cPanel |
| Partner names | partners section | only after MOUs are signed — the page says so deliberately |
| Live ledger box | promises section | link/table once the first cases exist |

## Deliberate choices (from the plan)

- **No partner logos yet** — the plan's trust principle is "we only show what is real".
- **Tax-deductibility stated honestly** (not yet deductible; s.44(6) takes ~2 years).
- **No patient photos** — consent-first media policy; the hero is abstract thread art.
- **Campaigns 100% to patients / Circle funds operations** is repeated on the page —
  it is the fundraising engine's core message.
- Tagline: *"Every mother a safe birth. Every life a chance."* (+ BM version).

## Hosting — cPanel at www.suteracares.org

Static files in `public_html/`. No build step, no Node, no database.

```
python make-deploy-zip.py      # writes suteracares-deploy.zip
```

Upload that zip in **cPanel → File Manager → public_html**, then **Extract**.
The zip has no wrapper folder, so the files land directly in `public_html`.
Re-run the script and re-upload after any edit; extracting again overwrites.

Files that go up are listed at the top of `make-deploy-zip.py`. `README.md`,
`.git/`, `.vercel/` and the script itself are deliberately excluded.

### Push to deploy

Both sites and the portal deploy with one command:

```
git push cpanel main
```

The `cpanel` remote is a plain Git repository on the server
(`~/repos/sutera-cares.git`). Its `post-receive` hook exports the pushed
commit and runs [`deploy/deploy.sh`](deploy/deploy.sh), which copies:

| From the repo | To the server |
|---|---|
| site root files, `css/`, `js/`, `img/` | `~/public_html` (www.suteracares.org) |
| `provider/` | `~/providers.suteracares.org` |
| `portal/` (the application) | `~/portal_app`, outside the web root |
| `portal/public/` | `~/providers.suteracares.org/portal` |

It never touches the portal's `.env`, `vendor/`, `storage/` or `index.php`
(which holds absolute paths for this server). The portal goes into
maintenance mode for the few seconds of the copy, the database is backed up
to `~/deploy-backups/auto/` (last 14 kept) whenever there is a database
update, and then `migrate` runs. The deploy's output shows in your terminal
and is kept in `~/deploy/deploy.log` on the server.

The server has no Composer. If `portal/composer.lock` changes, the deploy
stops after the two static sites and says so, rather than shipping code whose
packages are not on the server.

`origin` (GitHub) and `cpanel` (the live server) are separate remotes: push
to both. Nothing deploys from GitHub on its own. Only `main` deploys; other
branches pushed to `cpanel` are stored and nothing else.

One-time setup, already done on 5 October 2026. To repeat it on a new
machine or server:

1. **SSH.** Shell access must be enabled for the cPanel account (ask the
   host). Import the public key in **cPanel → SSH Access → Manage SSH Keys**
   and **Authorize** it. Namecheap uses **port 21098**, not 22; this
   machine's `~/.ssh/config` has a `cpanel-sutera` host entry for it.
2. **Server repository:**
   ```
   ssh -l sutempck cpanel-sutera 'git init --bare -b main ~/repos/sutera-cares.git'
   scp deploy/post-receive sutempck@cpanel-sutera:repos/sutera-cares.git/hooks/
   ssh -l sutempck cpanel-sutera 'chmod +x ~/repos/sutera-cares.git/hooks/post-receive'
   ```
3. **Remote:** `git remote add cpanel sutempck@cpanel-sutera:repos/sutera-cares.git`
4. **Test gate:** `cp deploy/pre-push .git/hooks/pre-push`. Pushes to `cpanel`
   then run the portal's tests first, and a failing test stops the deploy.

If the hook itself changes, copy `deploy/post-receive` up again (step 2);
`deploy.sh` needs nothing, as it is read from each pushed commit.

The zip in `make-deploy-zip.py` still works for the charity site if SSH is
ever unavailable.

### Launch order

1. Point the domain's nameservers at the host, and wait for DNS to resolve.
2. Upload and extract the zip.
3. Wait for cPanel → **SSL/TLS Status** to show a valid certificate (AutoSSL
   needs DNS working first).
4. **Only then** uncomment the HTTPS block in `.htaccess` — and, optionally,
   the HSTS header below it. Forcing HTTPS before the certificate exists shows
   every visitor a security warning.

`.htaccess` also sets UTF-8 (needed for the Chinese text), the www canonical
redirect, gzip, cache headers, a custom 404 and basic security headers.

### Still outstanding

- `contact@suteracares.org` needs creating in **cPanel → Email Accounts**
  (a mailbox or a forwarder), or mail to the address on the page bounces.
- No `og:image`, so WhatsApp and Facebook shares show no picture — worth
  adding a 1200×630 PNG before promoting the site.
- The old Vercel deployment still exists; it simply stops receiving traffic
  once DNS points at cPanel.

## Still to build (later phases, per plan §14)

- Donation gateway (ToyyibPay or Billplz + DuitNow QR) and campaign pages
- Public ledger page (start as a published Google Sheet embed)
- Volunteer + contact forms (start with mailto/WhatsApp links)
